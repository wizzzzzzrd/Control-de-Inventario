<?php
// app/controllers/ProductController.php
namespace App\Controllers;

require_once __DIR__ . '/../models/product.php';
require_once __DIR__ . '/../models/conexion.php';
require_once __DIR__ . '/../views/helpers.php';

class ProductController {
    protected $model;

    public function __construct() {
        $this->model = new \Product();
    }

    /**
     * Index: lista productos + stocks y totales por producto
     */
    public function index() {
        $products = $this->model->all(200);

        // Para cada producto cargamos stocks y totales (aceptable para catálogos pequeños/medianos)
        foreach ($products as &$p) {
            $p['stocks'] = $this->model->stocksByBranches($p['id']);
            $tot = $this->model->totals($p['id']);
            $p['total_pieces'] = $tot['total_pieces'];
            $p['total_boxes_sum'] = $tot['boxes_sum'];
            $p['estimated_boxes'] = $tot['estimated_boxes'];
            // asegurar factor presente para la vista
            $p['factor'] = $p['factor'] ?? ($tot['factor'] ?? 1);
            // asegurar precios (si vienen NULL lo dejamos en 0)
            $p['default_price_purchase'] = isset($p['default_price_purchase']) ? floatval($p['default_price_purchase']) : 0.00;
            $p['default_price_consumption'] = isset($p['default_price_consumption']) ? floatval($p['default_price_consumption']) : 0.00;
        }
        unset($p);

        render_view('products/index', ['products' => $products]);
    }

    /**
     * Alerts: vista para administrador que muestra productos en recommended/critical por sucursal
     */
    public function alerts() {
        require_login();

        $db = getConexion();
        $branches = [];
        if ($db) {
            $stmt = $db->prepare("SELECT id, name, is_bodega, is_active FROM branches ORDER BY is_bodega DESC, name");
            if ($stmt) {
                $stmt->execute();
                $res = $stmt->get_result();
                while ($r = $res->fetch_assoc()) {
                    $branches[] = $r;
                }
                $stmt->close();
            }
        }

        // Recoger todos los productos (si la tabla es grande puede requerir paginado; ahora limitamos a 2000)
        $products = $this->model->all(2000);

        // Estructura de salida por sucursal:
        // $outBranches[branch_id] = ['info'=>branchRow, 'critical' => [...], 'recommended' => [...]]
        $outBranches = [];
        foreach ($branches as $b) {
            $outBranches[$b['id']] = ['info' => $b, 'critical' => [], 'recommended' => []];
        }

        // Mapear productos por sucursal evaluando thresholds
        foreach ($products as $p) {
            $pid = intval($p['id']);
            $pfactor = max(1.0, (float)($p['factor'] ?? 1));
            $recommended = intval($p['recommended_stock'] ?? 10);
            $critical = intval($p['critical_threshold'] ?? 5);

            // obtener stocks bruto por sucursal
            $stocks = $this->model->stocksByBranches($pid);
            if (!is_array($stocks)) $stocks = [];

            foreach ($stocks as $s) {
                $branchId = intval($s['branch_id'] ?? 0);
                if (!isset($outBranches[$branchId])) continue; // sucursal no listada

                $piezas = isset($s['piezas']) ? (float)$s['piezas'] : 0.0;
                $cajas = isset($s['cajas']) ? (float)$s['cajas'] : 0.0;
                $totalPieces = $piezas + ($cajas * $pfactor);

                $item = [
                    'id' => $pid,
                    'sku' => $p['sku'] ?? '',
                    'name' => $p['name'] ?? '',
                    'branch_id' => $branchId,
                    'branch_name' => $s['branch_name'] ?? '',
                    'piezas' => $piezas,
                    'cajas' => $cajas,
                    'total_pieces' => $totalPieces,
                    'factor' => $pfactor,
                    'recommended_stock' => $recommended,
                    'critical_threshold' => $critical
                ];

                if ($totalPieces <= $critical) {
                    $outBranches[$branchId]['critical'][] = $item;
                } elseif ($totalPieces <= $recommended) {
                    $outBranches[$branchId]['recommended'][] = $item;
                }
            }
        }

        // ordenamiento simple: crítico primero por nombre
        foreach ($outBranches as &$ob) {
            usort($ob['critical'], function($a,$b){ return strcasecmp($a['name'],$b['name']); });
            usort($ob['recommended'], function($a,$b){ return strcasecmp($a['name'],$b['name']); });
        }
        unset($ob);

        render_view('products/alerts', ['branches' => $outBranches]);
    }

    /**
     * alertsOnly: lista / vista que muestra únicamente productos en estado crítico o recomendado.
     * Uso:
     *   ?url=product/alerts_only&type=critical
     *   ?url=product/alerts_only&type=recommended
     *   ?url=product/alerts_only&type=both   (por defecto)
     */
    public function alertsOnly() {
        require_login();

        $type = strtolower(trim($_GET['type'] ?? 'both'));
        if (!in_array($type, ['critical','recommended','both'], true)) $type = 'both';

        $db = getConexion();
        $branches = [];
        if ($db) {
            $stmt = $db->prepare("SELECT id, name, is_bodega, is_active FROM branches ORDER BY is_bodega DESC, name");
            if ($stmt) {
                $stmt->execute();
                $res = $stmt->get_result();
                while ($r = $res->fetch_assoc()) {
                    $branches[] = $r;
                }
                $stmt->close();
            }
        }

        $products = $this->model->all(2000);

        // Estructura simple por sucursal: branch_id => ['info'=>..., 'items' => [...]]
        $outBranches = [];
        foreach ($branches as $b) {
            $outBranches[$b['id']] = ['info' => $b, 'items' => []];
        }

        foreach ($products as $p) {
            $pid = intval($p['id']);
            $pfactor = max(1.0, (float)($p['factor'] ?? 1));
            $recommended = intval($p['recommended_stock'] ?? 10);
            $critical = intval($p['critical_threshold'] ?? 5);

            $stocks = $this->model->stocksByBranches($pid);
            if (!is_array($stocks)) $stocks = [];

            foreach ($stocks as $s) {
                $branchId = intval($s['branch_id'] ?? 0);
                if (!isset($outBranches[$branchId])) continue;

                $piezas = isset($s['piezas']) ? (float)$s['piezas'] : 0.0;
                $cajas = isset($s['cajas']) ? (float)$s['cajas'] : 0.0;
                $totalPieces = $piezas + ($cajas * $pfactor);

                $isCritical = $totalPieces <= $critical;
                $isRecommended = (!$isCritical) && ($totalPieces <= $recommended);

                $include = false;
                if ($type === 'both' && ($isCritical || $isRecommended)) $include = true;
                if ($type === 'critical' && $isCritical) $include = true;
                if ($type === 'recommended' && $isRecommended) $include = true;

                if ($include) {
                    $outBranches[$branchId]['items'][] = [
                        'id' => $pid,
                        'sku' => $p['sku'] ?? '',
                        'name' => $p['name'] ?? '',
                        'branch_id' => $branchId,
                        'branch_name' => $s['branch_name'] ?? '',
                        'piezas' => $piezas,
                        'cajas' => $cajas,
                        'total_pieces' => $totalPieces,
                        'factor' => $pfactor,
                        'isCritical' => $isCritical,
                        'isRecommended' => $isRecommended,
                        'recommended_stock' => $recommended,
                        'critical_threshold' => $critical
                    ];
                }
            }
        }

        // ordenar por nombre producto
        foreach ($outBranches as &$ob) {
            usort($ob['items'], function($a,$b){ return strcasecmp($a['name'],$b['name']); });
        }
        unset($ob);

        render_view('products/alerts_only', ['branches' => $outBranches, 'filter_type' => $type]);
    }

    /**
     * Wrapper en snake_case para compatibilidad con rutas tipo "alerts_only"
     * Evita 404 si el router busca la acción con guión bajo.
     */
    public function alerts_only() {
        $this->alertsOnly();
    }

    /**
     * Guardar producto nuevo (POST)
     */
    public function store() {
        // iniciar sesión solo si no está iniciada
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        // Campos obligatorios
        $sku = trim($_POST['sku'] ?? '');
        $name = trim($_POST['name'] ?? '');
        if ($sku === '' || $name === '') {
            $_SESSION['flash'] = 'SKU y nombre son obligatorios';
            header('Location: ?url=product/index'); exit;
        }

        // Normalizar SKU para evitar pequeñas variaciones
        $sku = mb_strtoupper($sku, 'UTF-8');

        // Tipo: validar contra enum permitido (ahora usamos primario/secundario por defecto)
        $allowedTypes = ['primario','secundario','directo','indirecto'];
        $type = strtolower(trim($_POST['type'] ?? 'primario'));
        if (!in_array($type, $allowedTypes, true)) {
            $type = 'primario';
        }

        $barcode = $_POST['barcode'] ?? null;
        $description = $_POST['description'] ?? null;
        $category = $_POST['category'] ?? null;
        $purchase_unit = $_POST['purchase_unit'] ?? 'caja';
        $consumption_unit = $_POST['consumption_unit'] ?? 'pieza';
        $factor = floatval($_POST['factor'] ?? 1);
        $default_price_purchase = isset($_POST['default_price_purchase']) ? floatval($_POST['default_price_purchase']) : 0.00;
        $default_price_consumption = isset($_POST['default_price_consumption']) ? floatval($_POST['default_price_consumption']) : 0.00;
        $recommended_stock = intval($_POST['recommended_stock'] ?? 10);
        $critical_threshold = intval($_POST['critical_threshold'] ?? 5);
        $bodega_critical_boxes = intval($_POST['bodega_critical_boxes'] ?? 4);

        // created_by : current user id if available
        $created_by = null;
        if (function_exists('current_user_id')) {
            $created_by = current_user_id();
        } else {
            if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
            $created_by = intval($_SESSION['user_id'] ?? 0);
        }

        $db = getConexion(); // mysqli
        if (!$db) {
            $_SESSION['flash'] = 'Error interno: no hay conexión a BD';
            header('Location: ?url=product/index'); exit;
        }

        // Verificar SKU existente (pre-check para evitar duplicate key)
        $chk = $db->prepare("SELECT id FROM products WHERE sku = ? LIMIT 1");
        if ($chk === false) {
            error_log("ProductController::store prepare check failed: " . $db->error);
            $_SESSION['flash'] = 'Error interno al crear producto (check)';
            header('Location: ?url=product/index'); exit;
        }
        $chk->bind_param('s', $sku);

        if (!$chk->execute()) {
            error_log("ProductController::store execute check failed: " . $chk->error);
            $chk->close();
            $_SESSION['flash'] = 'Error interno al crear producto (check exec)';
            header('Location: ?url=product/index'); exit;
        }

        // Compatibilidad: si get_result no está disponible, usamos store_result
        $skuExists = false;
        if (method_exists($chk, 'get_result')) {
            $resChk = $chk->get_result();
            if ($resChk && $resChk->fetch_assoc()) $skuExists = true;
        } else {
            $chk->store_result();
            if ($chk->num_rows > 0) $skuExists = true;
        }

        if ($skuExists) {
            $chk->close();
            $_SESSION['flash'] = 'El SKU ya existe en el sistema. Usa otro SKU único.';
            header('Location: ?url=product/index'); exit;
        }
        $chk->close();

        // Manejo de imagen (opcional)
        $imagePath = null;
        if (!empty($_FILES['image']) && isset($_FILES['image']['tmp_name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
            $f = $_FILES['image'];
            $allowed = ['image/jpeg','image/png','image/jpg','image/webp'];
            $mime = @mime_content_type($f['tmp_name']);
            if (!in_array($mime, $allowed)) {
                $_SESSION['flash'] = 'Tipo de imagen no permitido';
                header('Location: ?url=product/index'); exit;
            }
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            $newName = uniqid('prod_', true) . '.' . $ext;
            $destDir = __DIR__ . '/../../uploads/products';
            if (!is_dir($destDir)) {
                if (!mkdir($destDir, 0755, true)) {
                    $_SESSION['flash'] = 'Error al crear carpeta de uploads';
                    header('Location: ?url=product/index'); exit;
                }
            }
            $dest = $destDir . '/' . $newName;
            if (move_uploaded_file($f['tmp_name'], $dest)) {
                // ruta pública relativa
                $imagePath = '/uploads/products/' . $newName;
            } else {
                // no fatal: dejamos continuar sin imagen
                error_log("ProductController::store move_uploaded_file failed for " . ($f['name'] ?? ''));
            }
        }

        // Insertar con nuevos campos (category, default_price_purchase, default_price_consumption, created_by)
        $sql = "INSERT INTO products
          (sku, barcode, name, type, description, category, purchase_unit, consumption_unit,
           factor, default_price_purchase, default_price_consumption, recommended_stock, critical_threshold, bodega_critical_boxes, photo_path, created_by, created_at)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $db->prepare($sql);
        if ($stmt === false) {
            error_log("ProductController::store prepare failed: " . $db->error);
            $_SESSION['flash'] = 'Error interno al crear producto';
            header('Location: ?url=product/index'); exit;
        }

        // types: 8 strings, 3 doubles, 3 ints, string, int
        // sku, barcode, name, type, description, category, purchase_unit, consumption_unit,
        // factor(d), default_price_purchase(d), default_price_consumption(d),
        // recommended_stock(i), critical_threshold(i), bodega_critical_boxes(i),
        // photo_path(s), created_by(i)
        $types = 'ssssssssdddiiisi';

        $bindOk = $stmt->bind_param($types,
            $sku,
            $barcode,
            $name,
            $type,
            $description,
            $category,
            $purchase_unit,
            $consumption_unit,
            $factor,
            $default_price_purchase,
            $default_price_consumption,
            $recommended_stock,
            $critical_threshold,
            $bodega_critical_boxes,
            $imagePath,
            $created_by
        );

        if ($bindOk === false) {
            error_log("ProductController::store bind_param failed: " . $stmt->error);
            $stmt->close();
            $_SESSION['flash'] = 'Error interno al crear producto (bind)';
            header('Location: ?url=product/index'); exit;
        }

        // Ejecutar con manejo de errores
        try {
            $ok = $stmt->execute();
            if (!$ok) {
                // comprobar error concreto (ej: duplicate) y reportar
                $errno = $stmt->errno;
                $err = $stmt->error;
                error_log("ProductController::store execute failed (errno={$errno}): " . $err);

                if ($errno === 1062) { // duplicate entry
                    $_SESSION['flash'] = 'Ya existe un producto con ese SKU o valor único duplicado.';
                } else {
                    $_SESSION['flash'] = 'Error al crear producto (interno).';
                }

                $stmt->close();
                header('Location: ?url=product/index'); exit;
            }
        } catch (\mysqli_sql_exception $ex) {
            // captura por si mysqli está en excepción mode
            error_log("ProductController::store exception: " . $ex->getMessage());
            if (strpos($ex->getMessage(), 'Duplicate entry') !== false) {
                $_SESSION['flash'] = 'Ya existe un producto con ese SKU.';
            } else {
                $_SESSION['flash'] = 'Error interno al crear producto.';
            }
            if (isset($stmt) && $stmt) $stmt->close();
            header('Location: ?url=product/index'); exit;
        }

        $stmt->close();

        $_SESSION['flash'] = 'Producto creado correctamente';
        header('Location: ?url=product/index');
        exit;
    }

    /**
     * Buscar por q (sku, barcode, name, description o id) -> devuelve JSON con lista de coincidencias
     * /?url=product/search&q=...(&branch_id=NN)
     *
     * Implementación solicitada (versión proporcionada).
     */
    public function search() {
        $q = trim($_GET['q'] ?? ($_GET['term'] ?? ''));
        $branchId = intval($_GET['branch_id'] ?? 0);
        header('Content-Type: application/json');

        if ($q === '') {
            echo json_encode(['results' => []]);
            return;
        }

        $db = getConexion();
        if (!$db) {
            echo json_encode(['error' => 'No DB connection', 'results' => []]);
            return;
        }

        // Si es entero puro buscar id exacto
        if (preg_match('/^\d+$/', $q)) {
            $stmt = $db->prepare("SELECT p.id, p.sku, p.barcode, p.name, p.description, p.factor, p.default_price_consumption,
                                         COALESCE(pbs.qty_consumption_unit,0) AS qty_pieces,
                                         COALESCE(pbs.boxes_count,0) AS boxes
                                  FROM products p
                                  LEFT JOIN product_branch_stock pbs ON pbs.product_id = p.id AND pbs.branch_id = ?
                                  WHERE p.id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('ii', $branchId, $q);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res->fetch_assoc();
                $stmt->close();
                if ($row) {
                    $factor = max((float)($row['factor'] ?? 1), 1.0);
                    $total_pieces = (float)$row['qty_pieces'] + ((float)$row['boxes'] * $factor);
                    $row['total_pieces'] = $total_pieces;
                    echo json_encode(['results' => [$row]]);
                    return;
                }
            }
        }

        // Busqueda parcial: sku prefix, barcode prefix, name LIKE, description LIKE
        $like = '%' . $q . '%';
        $sql = "SELECT p.id, p.sku, p.barcode, p.name, p.description, p.factor, p.default_price_consumption,
                       COALESCE(pbs.qty_consumption_unit,0) AS qty_pieces,
                       COALESCE(pbs.boxes_count,0) AS boxes
                FROM products p
                LEFT JOIN product_branch_stock pbs ON pbs.product_id = p.id AND pbs.branch_id = ?
                WHERE p.sku LIKE ? OR p.barcode LIKE ? OR p.name LIKE ? OR p.description LIKE ?
                ORDER BY (p.sku = ?) DESC, p.name ASC
                LIMIT 200";
        $stmt = $db->prepare($sql);
        if ($stmt === false) {
            echo json_encode(['error' => 'prepare failed', 'results' => []]);
            return;
        }
        $paramSku = $q . '%';
        $paramBarcode = $q . '%';
        $paramLike = $like;
        $stmt->bind_param('isssss', $branchId, $paramSku, $paramBarcode, $paramLike, $paramLike, $q);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) {
            $factor = max((float)($r['factor'] ?? 1), 1.0);
            $total_pieces = (float)$r['qty_pieces'] + ((float)$r['boxes'] * $factor);
            $r['total_pieces'] = $total_pieces;
            $rows[] = $r;
        }
        $stmt->close();

        echo json_encode(['results' => $rows]);
    }

    /**
     * getById() - devuelve JSON con product + stocks + totals
     * /?url=product/getById&id=NN
     */
    public function getById() {
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'id inválido']);
            return;
        }
        $prod = $this->model->findById($id);
        if (!$prod) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'No encontrado']);
            return;
        }
        $stocks = $this->model->stocksByBranches($id);
        $tot = $this->model->totals($id);
        header('Content-Type: application/json');
        // incluir photo_path directo (si es NULL, JS puede manejarlo)
        echo json_encode([
            'product' => $prod,
            'stock' => [
                'total_pieces' => (float)$tot['total_pieces'],
                'branches' => $stocks,
                'boxes_sum' => (float)$tot['boxes_sum'],
                'estimated_boxes' => (int)$tot['estimated_boxes'],
                'factor' => $tot['factor']
            ]
        ]);
    }

    /**
     * Movements (ledger) endpoint -> devuelve JSON con movimientos del producto
     * /?url=product/movements&id=NN
     */
    public function movements() {
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'id inválido']);
            return;
        }
        $rows = $this->model->getMovements($id, 500);
        header('Content-Type: application/json');
        echo json_encode(['movements' => $rows]);
    }

    /**
     * Actualizar producto (POST)
     */
    public function update() {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['flash'] = 'Método no permitido';
            header('Location: ?url=product/index'); exit;
        }

        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['flash'] = 'ID de producto inválido';
            header('Location: ?url=product/index'); exit;
        }

        $data = [
            'sku' => isset($_POST['sku']) ? trim($_POST['sku']) : null,
            'barcode' => isset($_POST['barcode']) ? trim($_POST['barcode']) : null,
            'name' => isset($_POST['name']) ? trim($_POST['name']) : null,
            'description' => isset($_POST['description']) ? trim($_POST['description']) : null,
            'category' => isset($_POST['category']) ? trim($_POST['category']) : null,
            'type' => isset($_POST['type']) ? trim($_POST['type']) : null,
            'purchase_unit' => isset($_POST['purchase_unit']) ? trim($_POST['purchase_unit']) : null,
            'consumption_unit' => isset($_POST['consumption_unit']) ? trim($_POST['consumption_unit']) : null,
            'factor' => isset($_POST['factor']) ? $_POST['factor'] : null,
            'default_price_purchase' => isset($_POST['default_price_purchase']) ? $_POST['default_price_purchase'] : null,
            'default_price_consumption' => isset($_POST['default_price_consumption']) ? $_POST['default_price_consumption'] : null,
            'recommended_stock' => isset($_POST['recommended_stock']) ? $_POST['recommended_stock'] : null,
            'critical_threshold' => isset($_POST['critical_threshold']) ? $_POST['critical_threshold'] : null,
            'bodega_critical_boxes' => isset($_POST['bodega_critical_boxes']) ? $_POST['bodega_critical_boxes'] : null
        ];

        $file = $_FILES['image'] ?? null;

        $productModel = new \Product();
        $ok = $productModel->update($id, $data, $file);

        $_SESSION['flash'] = $ok ? 'Producto actualizado correctamente' : 'Error al actualizar producto';
        header('Location: ?url=product/index');
        exit;
    }

    /**
     * Eliminar producto (GET) - acción por link de la vista
     */
    public function delete() {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['flash'] = 'ID inválido';
            header('Location: ?url=product/index'); exit;
        }
        $productModel = new \Product();
        $ok = $productModel->delete($id);
        $_SESSION['flash'] = $ok ? 'Producto eliminado' : 'Error al eliminar producto';
        header('Location: ?url=product/index');
        exit;
    }

}
