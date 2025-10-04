<?php
// app/controllers/OrderController.php
namespace App\Controllers;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../models/conexion.php';
require_once __DIR__ . '/../models/product.php';
require_once __DIR__ . '/../views/helpers.php';

class OrderController {
    /**
     * Persistir una orden (venta) desde POS
     * Endpoint: ?url=order/store
     * Recibe JSON:
     * {
     *   branch_id: int,
     *   payment_method: string,
     *   received_amount: number,
     *   items: [ { product_id: int, qty: int, price_unit: number } ... ]
     * }
     *
     * Responde JSON { success: true, order_id, order_number } o { success:false, error: '...' }
     */
    public function store() {
        header('Content-Type: application/json; charset=utf-8');
        // permitir sólo POST con JSON
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Método no permitido']);
            return;
        }

        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'JSON inválido']);
            return;
        }

        $branch_id = intval($data['branch_id'] ?? 0);
        $payment_method = trim($data['payment_method'] ?? 'unknown');
        $received_amount = floatval($data['received_amount'] ?? 0);
        $items = $data['items'] ?? [];

        if ($branch_id <= 0 || empty($items) || !is_array($items)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
            return;
        }

        // Chequeo sesión / permisos
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        if (!is_logged_in()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'No autenticado']);
            return;
        }

        // obtener user_id (fallback a session si current_user_id no existe)
        $user_id = function_exists('current_user_id') ? current_user_id() : ($_SESSION['user_id'] ?? null);
        $role_slug = $_SESSION['role_slug'] ?? null;
        $user_branch = intval($_SESSION['branch_id'] ?? 0);

        // Si es vendor, sólo permitir crear en su propia sucursal
        if ($role_slug === 'vendor' && $user_branch !== $branch_id) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'No autorizado para vender en esta sucursal']);
            return;
        }

        $db = getConexion();
        if (!$db) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error de base de datos']);
            return;
        }

        // Normalize & validate items
        $cleanItems = [];
        foreach ($items as $it) {
            $pid = intval($it['product_id'] ?? 0);
            $qty = intval($it['qty'] ?? 0);
            $price_unit = floatval($it['price_unit'] ?? 0);
            if ($pid <= 0 || $qty <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Items inválidos']);
                return;
            }
            $cleanItems[] = ['product_id' => $pid, 'qty' => $qty, 'price_unit' => $price_unit];
        }

        // Begin transaction
        $db->begin_transaction();
        try {
            // 1) Validar stock para cada item y reservar (calcular nuevo stock)
            foreach ($cleanItems as $it) {
                $product_id = $it['product_id'];
                $qty_needed = (float)$it['qty'];

                // obtener factor y stock en branch
                $stmt = $db->prepare("SELECT p.factor, COALESCE(pbs.qty_consumption_unit,0) AS qp, COALESCE(pbs.boxes_count,0) AS bc
                                      FROM products p
                                      LEFT JOIN product_branch_stock pbs ON pbs.product_id = p.id AND pbs.branch_id = ?
                                      WHERE p.id = ? LIMIT 1 FOR UPDATE");
                if ($stmt === false) throw new \Exception("prepare product stock: " . $db->error);
                $stmt->bind_param('ii', $branch_id, $product_id);
                if (!$stmt->execute()) throw new \Exception("execute product stock: " . $stmt->error);
                $res = $stmt->get_result();
                $row = $res->fetch_assoc();
                $stmt->close();

                if (!$row) {
                    throw new \Exception("Producto no encontrado id={$product_id}");
                }

                $factor = max((float)($row['factor'] ?? 1), 1.0);
                $qp = (float)$row['qp'];
                $bc = (float)($row['bc'] ?? 0);
                $totalPieces = $qp + ($bc * $factor);

                if ($totalPieces < $qty_needed) {
                    throw new \Exception("Stock insuficiente para producto {$product_id}");
                }

                // calcular remanente y valores a guardar
                $remaining = $totalPieces - $qty_needed;
                $new_boxes = floor($remaining / $factor);
                $new_pieces = $remaining - ($new_boxes * $factor);

                // upsert product_branch_stock (se usa ON DUPLICATE KEY: la tabla tiene UNIQUE(product_id,branch_id))
                $stmt = $db->prepare("INSERT INTO product_branch_stock (product_id, branch_id, qty_consumption_unit, boxes_count, last_updated_at)
                                      VALUES (?, ?, ?, ?, NOW())
                                      ON DUPLICATE KEY UPDATE qty_consumption_unit = VALUES(qty_consumption_unit), boxes_count = VALUES(boxes_count), last_updated_at = NOW()");
                if ($stmt === false) throw new \Exception("prepare upsert stock: " . $db->error);
                // types: product_id i, branch_id i, new_pieces d, new_boxes d (boxes_count is decimal)
                $stmt->bind_param('iidd', $product_id, $branch_id, $new_pieces, $new_boxes);
                if (!$stmt->execute()) throw new \Exception("execute upsert stock: " . $stmt->error);
                $stmt->close();
            }

            // 2) Crear orden (orders)
            $order_number = 'ORD' . date('YmdHis') . rand(100,999);
            $total_amount = 0.0;
            foreach ($cleanItems as $it) $total_amount += $it['qty'] * $it['price_unit'];

            $stmt = $db->prepare("INSERT INTO orders (order_number, branch_id, user_id, total_amount, status, created_at)
                                  VALUES (?, ?, ?, ?, 'paid', NOW())");
            if ($stmt === false) throw new \Exception("prepare insert order: " . $db->error);
            $stmt->bind_param('siid', $order_number, $branch_id, $user_id, $total_amount);
            if (!$stmt->execute()) throw new \Exception("execute insert order: " . $stmt->error);
            $order_id = $stmt->insert_id;
            $stmt->close();

            // 3) Insertar order_items y stock_movements por cada producto
            foreach ($cleanItems as $it) {
                $product_id = $it['product_id'];
                $qty = $it['qty'];
                $price_unit = $it['price_unit'];
                $subtotal = round($qty * $price_unit, 2);

                // order_items
                $stmt = $db->prepare("INSERT INTO order_items (order_id, product_id, qty, price_unit, subtotal)
                                      VALUES (?, ?, ?, ?, ?)");
                if ($stmt === false) throw new \Exception("prepare insert order_item: " . $db->error);
                $stmt->bind_param('iiidd', $order_id, $product_id, $qty, $price_unit, $subtotal);
                if (!$stmt->execute()) throw new \Exception("execute insert order_item: " . $stmt->error);
                $stmt->close();

                // stock_movements (sale) - registra decremento
                $note = "Venta orden {$order_number}";
                $stmt = $db->prepare("INSERT INTO stock_movements (product_id, from_branch_id, movement_type, qty_consumption, qty_purchase, user_id, created_at, note, reference)
                                      VALUES (?, ?, 'sale', ?, NULL, ?, NOW(), ?, ?)");
                if ($stmt === false) throw new \Exception("prepare insert movement: " . $db->error);
                // types: product_id i, branch_id i, qty i, user_id i, note s, reference s
                $stmt->bind_param('iiiiss', $product_id, $branch_id, $qty, $user_id, $note, $order_number);
                if (!$stmt->execute()) throw new \Exception("execute insert movement: " . $stmt->error);
                $stmt->close();
            }

            $db->commit();

            echo json_encode(['success' => true, 'order_id' => $order_id, 'order_number' => $order_number]);
            return;
        } catch (\Exception $e) {
            if (isset($db)) { @$db->rollback(); }
            error_log("OrderController::store error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            return;
        }
    }

    /**
     * Devuelve detalle de orden como JSON para usar en modal
     * Endpoint: ?url=order/view_json&id=NN
     * Responde { success:true, order: {...}, items: [...] } o { success:false, error: '...' }
     */
    public function view_json() {
        header('Content-Type: application/json; charset=utf-8');
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        if (!is_logged_in()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'No autenticado']);
            return;
        }

        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ID inválido']);
            return;
        }

        $db = getConexion();
        // cargar orden
        $stmt = $db->prepare("SELECT o.*, b.name as branch_name, u.name as user_name
                              FROM orders o
                              LEFT JOIN branches b ON b.id = o.branch_id
                              LEFT JOIN users u ON u.id = o.user_id
                              WHERE o.id = ? LIMIT 1");
        if ($stmt === false) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error interno']);
            return;
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $order = $res->fetch_assoc();
        $stmt->close();
        if (!$order) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Orden no encontrada']);
            return;
        }

        // cargar items
        $sql = "SELECT oi.*, p.sku, p.name as product_name
                FROM order_items oi
                LEFT JOIN products p ON p.id = oi.product_id
                WHERE oi.order_id = ?
                ORDER BY oi.id";
        $items = [];
        $stmt = $db->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) $items[] = $r;
            $stmt->close();
        }

        echo json_encode(['success' => true, 'order' => $order, 'items' => $items]);
        return;
    }

    /**
     * (Opcional) mantén compatible la vista HTML tradicional si la tuvieras
     * public function view() { ... render_view('orders/view', [...]); }
     */
}
