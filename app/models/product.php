<?php
// app/models/product.php
require_once __DIR__ . '/baseModel.php';

class Product extends BaseModel {

    /**
     * Lista de productos (limit)
     */
    public function all($limit = 50) {
        $limit = (int)$limit;
        $sql = "SELECT id, sku, name, factor, recommended_stock, critical_threshold, photo_path
                FROM products
                ORDER BY name
                LIMIT ?";
        $stmt = $this->db->prepare($sql);
        if ($stmt === false) {
            error_log("Product::all prepare failed: " . $this->db->error);
            return [];
        }
        $stmt->bind_param('i', $limit);
        if (!$stmt->execute()) {
            error_log("Product::all execute failed: " . $stmt->error);
            $stmt->close();
            return [];
        }
        $res = $stmt->get_result();
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows ?: [];
    }

    /**
     * Buscar producto por id
     */
    public function findById($id) {
        $sql = "SELECT * FROM products WHERE id = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if ($stmt === false) {
            error_log("Product::findById prepare failed: " . $this->db->error);
            return null;
        }
        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) {
            error_log("Product::findById execute failed: " . $stmt->error);
            $stmt->close();
            return null;
        }
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Buscar por q: intenta igualar sku o barcode, o name LIKE q
     */
    public function search($q) {
        $like = '%' . $q . '%';
        $sql = "SELECT * FROM products WHERE sku = ? OR barcode = ? OR name LIKE ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if ($stmt === false) {
            error_log("Product::search prepare failed: " . $this->db->error);
            return null;
        }
        $stmt->bind_param('sss', $q, $q, $like);
        if (!$stmt->execute()) {
            error_log("Product::search execute failed: " . $stmt->error);
            $stmt->close();
            return null;
        }
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Stocks por sucursal para un producto.
     * Retorna filas: branch_id, branch_name, is_bodega, piezas (piezas totales), cajas (boxes_count)
     *
     * piezas = COALESCE(qty_consumption_unit,0) + COALESCE(boxes_count,0) * COALESCE(product.factor,1)
     */
    public function stocksByBranches($product_id) {
        $sql = "SELECT b.id AS branch_id,
                       b.name AS branch_name,
                       b.is_bodega AS is_bodega,
                       COALESCE(pbs.qty_consumption_unit,0) AS piezas_raw,
                       COALESCE(pbs.boxes_count,0) AS cajas_raw,
                       COALESCE(pr.factor,1) AS factor
                FROM branches b
                LEFT JOIN product_branch_stock pbs
                  ON pbs.product_id = ? AND pbs.branch_id = b.id
                LEFT JOIN products pr
                  ON pr.id = ?
                WHERE b.is_active = 1
                ORDER BY b.is_bodega DESC, b.name ASC";
        $stmt = $this->db->prepare($sql);
        if ($stmt === false) {
            error_log("Product::stocksByBranches prepare failed: " . $this->db->error);
            return [];
        }
        // bind product_id two times (for pbs.product_id and pr.id)
        $stmt->bind_param('ii', $product_id, $product_id);
        if (!$stmt->execute()) {
            error_log("Product::stocksByBranches execute failed: " . $stmt->error);
            $stmt->close();
            return [];
        }
        $res = $stmt->get_result();
        $out = [];
        while ($r = $res->fetch_assoc()) {
            $piezas_raw = isset($r['piezas_raw']) ? (float)$r['piezas_raw'] : 0.0;
            $cajas_raw  = isset($r['cajas_raw']) ? (float)$r['cajas_raw'] : 0.0;
            $factor     = isset($r['factor']) ? (float)$r['factor'] : 1.0;
            if ($factor <= 0) $factor = 1.0;

            $piezas_total = $piezas_raw + ($cajas_raw * $factor);

            $out[] = [
                'branch_id' => (int)$r['branch_id'],
                'branch_name' => $r['branch_name'] ?? '',
                'is_bodega' => (int)$r['is_bodega'],
                'piezas' => (float)$piezas_total,
                'cajas' => (float)$cajas_raw,
                'last_updated_at' => isset($r['last_updated_at']) ? $r['last_updated_at'] : null
            ];
        }
        $stmt->close();
        return $out;
    }

    /**
     * Devuelve el total de piezas para un producto (piezas + cajas * factor) sumando todas las sucursales.
     */
    public function totalPieces($product_id) {
        // Primero obtener factor (si existe) — por seguridad
        $prod = $this->findById($product_id);
        $factor = 1.0;
        if ($prod && isset($prod['factor'])) {
            $factor = (float)$prod['factor'];
            if ($factor <= 0) $factor = 1.0;
        }

        // Sumamos piezas y cajas y aplicamos factor en PHP (más robusto que intentar multiplicar en SQL con joins)
        $sql = "SELECT COALESCE(SUM(COALESCE(qty_consumption_unit,0)),0) AS pieces_sum,
                       COALESCE(SUM(COALESCE(boxes_count,0)),0) AS boxes_sum
                FROM product_branch_stock
                WHERE product_id = ?";
        $stmt = $this->db->prepare($sql);
        if ($stmt === false) {
            error_log("Product::totalPieces prepare failed: " . $this->db->error);
            return 0.0;
        }
        $stmt->bind_param('i', $product_id);
        if (!$stmt->execute()) {
            error_log("Product::totalPieces execute failed: " . $stmt->error);
            $stmt->close();
            return 0.0;
        }
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        $pieces_sum = isset($row['pieces_sum']) ? (float)$row['pieces_sum'] : 0.0;
        $boxes_sum  = isset($row['boxes_sum']) ? (float)$row['boxes_sum'] : 0.0;

        $total = $pieces_sum + ($boxes_sum * $factor);
        return (float)$total;
    }

    /**
     * Retorna totales útiles: piezas totales, cajas sumadas, cajas estimadas por factor y factor
     */
    public function totals($product_id) {
        $prod = $this->findById($product_id);
        $factor = 1.0;
        if ($prod && isset($prod['factor'])) {
            $factor = (float)$prod['factor'];
            if ($factor <= 0) $factor = 1.0;
        }

        $totalPieces = $this->totalPieces($product_id);

        // Suma de boxes_count
        $sql = "SELECT COALESCE(SUM(COALESCE(boxes_count,0)),0) AS boxes_sum FROM product_branch_stock WHERE product_id = ?";
        $stmt = $this->db->prepare($sql);
        $boxesSum = 0.0;
        if ($stmt !== false) {
            $stmt->bind_param('i', $product_id);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                $r = $res->fetch_assoc();
                $boxesSum = isset($r['boxes_sum']) ? (float)$r['boxes_sum'] : 0.0;
            } else {
                error_log("Product::totals boxes_sum execute failed: " . $stmt->error);
            }
            $stmt->close();
        } else {
            error_log("Product::totals boxes_sum prepare failed: " . $this->db->error);
        }

        $estimatedBoxes = ($factor > 0) ? (int)floor($totalPieces / $factor) : 0;

        return [
            'total_pieces' => (float)$totalPieces,
            'boxes_sum' => (float)$boxesSum,
            'estimated_boxes' => (int)$estimatedBoxes,
            'factor' => (float)$factor
        ];
    }

    /**
     * Movimientos (ledger) del producto
     */
    public function getMovements($product_id, $limit = 200) {
        $limit = (int)$limit;
        $sql = "SELECT sm.*,
                       fb.name AS from_branch_name,
                       tb.name AS to_branch_name,
                       u.name AS user_name
                FROM stock_movements sm
                LEFT JOIN branches fb ON fb.id = sm.from_branch_id
                LEFT JOIN branches tb ON tb.id = sm.to_branch_id
                LEFT JOIN users u ON u.id = sm.user_id
                WHERE sm.product_id = ?
                ORDER BY sm.created_at DESC
                LIMIT ?";
        $stmt = $this->db->prepare($sql);
        if ($stmt === false) {
            error_log("Product::getMovements prepare failed: " . $this->db->error);
            return [];
        }
        $stmt->bind_param('ii', $product_id, $limit);
        if (!$stmt->execute()) {
            error_log("Product::getMovements execute failed: " . $stmt->error);
            $stmt->close();
            return [];
        }
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) {
            // cast columnas numéricas
            $r['qty_consumption'] = isset($r['qty_consumption']) ? (float)$r['qty_consumption'] : 0.0;
            $r['qty_purchase'] = isset($r['qty_purchase']) ? (float)$r['qty_purchase'] : null;
            $rows[] = $r;
        }
        $stmt->close();
        return $rows;
    }

    /**
     * Actualiza producto (con manejo simple de imagen opcional)
     */
    public function update(int $id, array $data, ?array $file = null) {
        // Preparar campos y tipos dinámicamente
        $fields = [];
        $params = [];
        $types = '';

        $map = [
            'sku' => 's', 'barcode' => 's', 'name' => 's', 'description' => 's',
            'category' => 's', 'type' => 's', 'purchase_unit' => 's', 'consumption_unit' => 's',
            'factor' => 'd', 'default_price_purchase' => 'd', 'default_price_consumption' => 'd',
            'recommended_stock' => 'i', 'critical_threshold' => 'i', 'bodega_critical_boxes' => 'i'
        ];

        foreach ($map as $field => $typechar) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
                $types .= $typechar;
            }
        }

        // Imagen
        $imagePath = null;
        if ($file && isset($file['tmp_name']) && isset($file['error']) && $file['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg','image/png','image/jpg'];
            $mime = mime_content_type($file['tmp_name']);
            if (in_array($mime, $allowed)) {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $newName = uniqid('prod_', true) . '.' . $ext;
                $destDir = __DIR__ . '/../../uploads/products';
                if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
                $dest = $destDir . '/' . $newName;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $imagePath = '/uploads/products/' . $newName;
                    $fields[] = "photo_path = ?";
                    $params[] = $imagePath;
                    $types .= 's';
                }
            } else {
                error_log("Product::update image mime not allowed: " . (isset($mime) ? $mime : 'unknown'));
            }
        }

        if (empty($fields)) {
            // nada que actualizar
            return true;
        }

        $fields[] = "updated_at = NOW()";
        $sql = "UPDATE products SET " . implode(', ', $fields) . " WHERE id = ?";
        $params[] = $id;
        $types .= 'i';

        $stmt = $this->db->prepare($sql);
        if ($stmt === false) {
            error_log("Product::update prepare failed: " . $this->db->error);
            return false;
        }

        // bind dinámico (por referencia)
        $bindParams = [];
        $bindParams[] = $types;
        for ($i = 0; $i < count($params); $i++) {
            $bindParams[] = &$params[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bindParams);

        $ok = $stmt->execute();
        if (!$ok) {
            error_log("Product::update execute failed: " . $stmt->error);
        }
        $stmt->close();
        return (bool)$ok;
    }

    /**
     * Eliminar producto
     */
    public function delete(int $id) {
        $sql = "DELETE FROM products WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        if ($stmt === false) {
            error_log("Product::delete prepare failed: " . $this->db->error);
            return false;
        }
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        if (!$ok) {
            error_log("Product::delete execute failed: " . $stmt->error);
        }
        $stmt->close();
        return (bool)$ok;
    }
}
