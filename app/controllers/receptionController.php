<?php
// app/controllers/receptionController.php
namespace App\Controllers;

require_once __DIR__ . '/../models/conexion.php';
require_once __DIR__ . '/../models/product.php';

class ReceptionController {

    public function store() {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

        // Debug/log
        $dbgDir = __DIR__ . '/../../tmp';
        if (!is_dir($dbgDir)) @mkdir($dbgDir, 0755, true);
        $dbgFile = $dbgDir . '/reception_debug.log';

        $debugMode = isset($_GET['debug']) && $_GET['debug'] == '1';

        @file_put_contents($dbgFile, date('Y-m-d H:i:s') . " -> ENTRY reception store\n", FILE_APPEND);
        @file_put_contents($dbgFile, "REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? '') . "\n", FILE_APPEND);
        @file_put_contents($dbgFile, "GET: " . json_encode($_GET) . "\n", FILE_APPEND);
        @file_put_contents($dbgFile, "POST: " . json_encode($_POST) . "\n\n", FILE_APPEND);

        $result = ['ok' => false, 'messages' => []];

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $result['messages'][] = 'Método no permitido';
            @file_put_contents($dbgFile, "Método no permitido\n", FILE_APPEND);
            return $this->respond($result, $debugMode);
        }

        $product_id = intval($_POST['product_id'] ?? 0);
        $unit = $_POST['unit'] ?? 'pieces';
        $qty = floatval($_POST['qty'] ?? 0.0);
        $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

        @file_put_contents($dbgFile, "Parsed: product_id={$product_id}, unit={$unit}, qty={$qty}, user_id={$user_id}\n", FILE_APPEND);

        if ($product_id <= 0 || $qty <= 0) {
            $result['messages'][] = 'Datos de recepción inválidos (product_id/qty)';
            @file_put_contents($dbgFile, "VALIDATION FAILED\n", FILE_APPEND);
            return $this->respond($result, $debugMode);
        }

        try {
            $db = getConexion();
            if (!$db) {
                $result['messages'][] = 'No se obtuvo conexión a BD';
                @file_put_contents($dbgFile, "No DB connection\n", FILE_APPEND);
                return $this->respond($result, $debugMode);
            }

            // buscar bodega
            $stmt = $db->prepare("SELECT id FROM branches WHERE is_bodega = 1 LIMIT 1");
            if ($stmt === false) {
                @file_put_contents($dbgFile, "Prepare bodega fail: " . $db->error . "\n", FILE_APPEND);
                $result['messages'][] = "Error interno (prepare bodega)";
                return $this->respond($result, $debugMode);
            }
            $stmt->execute();
            $res = $stmt->get_result();
            $b = $res->fetch_assoc();
            $stmt->close();
            if (!$b) {
                @file_put_contents($dbgFile, "No existe bodega (branches.is_bodega)\n", FILE_APPEND);
                $result['messages'][] = 'No existe bodega configurada';
                return $this->respond($result, $debugMode);
            }
            $bodega_id = intval($b['id']);
            @file_put_contents($dbgFile, "Bodega ID: $bodega_id\n", FILE_APPEND);

            // producto + factor
            $productModel = new \Product();
            $prod = $productModel->findById($product_id);
            if (!$prod) {
                @file_put_contents($dbgFile, "Producto no encontrado id={$product_id}\n", FILE_APPEND);
                $result['messages'][] = 'Producto no encontrado';
                return $this->respond($result, $debugMode);
            }
            $factor = max((float)($prod['factor'] ?? 1), 1.0);
            @file_put_contents($dbgFile, "Producto factor: $factor\n", FILE_APPEND);

            // --- NUEVA LÓGICA: bloquear recepciones de 'secundario' si no es domingo
            $type = strtolower(trim($prod['type'] ?? 'primario'));
            $todayIsSunday = (date('w') === '0'); // domingo = 0
            if ($type === 'secundario' && !$todayIsSunday) {
                @file_put_contents($dbgFile, "Bloqueado: producto secundario fuera de domingo. type={$type}\n", FILE_APPEND);
                $result['messages'][] = 'Este producto es de tipo "secundario" y sólo puede recepcionarse los domingos.';
                return $this->respond($result, $debugMode);
            }

            // calcular cantidades
            if ($unit === 'boxes' || $unit === 'cajas') {
                $qty_purchase = $qty; // cajas
                $qty_consumption = $qty * $factor; // piezas
            } else {
                $qty_purchase = null;
                $qty_consumption = $qty;
            }
            @file_put_contents($dbgFile, "Converted: qty_consumption={$qty_consumption}, qty_purchase=" . ($qty_purchase===null?'NULL':$qty_purchase) . "\n", FILE_APPEND);

            // transacción
            if (!$db->begin_transaction()) {
                @file_put_contents($dbgFile, "begin_transaction failed: " . $db->error . "\n", FILE_APPEND);
            } else {
                @file_put_contents($dbgFile, "Transaction started\n", FILE_APPEND);
            }

            // obtener fila actual en bodega
            $stmt = $db->prepare("SELECT COALESCE(qty_consumption_unit,0) AS qp, COALESCE(boxes_count,0) AS bc FROM product_branch_stock WHERE product_id = ? AND branch_id = ? LIMIT 1");
            if ($stmt === false) throw new \Exception("prepare select stock bodega: " . $db->error);
            $stmt->bind_param('ii', $product_id, $bodega_id);
            if (!$stmt->execute()) throw new \Exception("execute select stock bodega: " . $stmt->error);
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $stmt->close();

            $current_qp = $row ? (float)$row['qp'] : 0.0;
            $current_bc = $row ? (float)$row['bc'] : 0.0;
            @file_put_contents($dbgFile, "Current bodega qp={$current_qp}, bc={$current_bc}\n", FILE_APPEND);

            // nuevos valores y upsert
            if ($unit === 'boxes' || $unit === 'cajas') {
                $new_bc = $current_bc + $qty_purchase;
                $new_qp = $current_qp;
            } else {
                $new_qp = $current_qp + $qty_consumption;
                $new_bc = $current_bc;
            }
            @file_put_contents($dbgFile, "New bodega values qp={$new_qp}, bc={$new_bc}\n", FILE_APPEND);

            $stmt = $db->prepare("INSERT INTO product_branch_stock (product_id, branch_id, qty_consumption_unit, boxes_count, last_updated_at)
                                  VALUES (?, ?, ?, ?, NOW())
                                  ON DUPLICATE KEY UPDATE qty_consumption_unit = VALUES(qty_consumption_unit), boxes_count = VALUES(boxes_count), last_updated_at = NOW()");
            if ($stmt === false) throw new \Exception("prepare upsert stock: " . $db->error);
            $stmt->bind_param('iidd', $product_id, $bodega_id, $new_qp, $new_bc);
            if (!$stmt->execute()) throw new \Exception("execute upsert stock: " . $stmt->error);
            $stmt->close();
            @file_put_contents($dbgFile, "Upsert product_branch_stock OK\n", FILE_APPEND);

            // registrar movimiento tipo 'receipt'
            $note = "Recepción en bodega (manual).";

            // --- usa SQL distinto según exista usuario o no (evita insertar 0)
            if ($user_id > 0) {
                // INSERT con user_id
                if ($qty_purchase === null) {
                    $sql = "INSERT INTO stock_movements (product_id, from_branch_id, to_branch_id, movement_type, qty_consumption, qty_purchase, user_id, created_at, note)
                            VALUES (?, NULL, ?, 'receipt', ?, NULL, ?, NOW(), ?)";
                    $stmt = $db->prepare($sql);
                    if ($stmt === false) throw new \Exception("prepare insert movement with user: " . $db->error);
                    $stmt->bind_param('idiss', $product_id, $bodega_id, $qty_consumption, $user_id, $note);
                } else {
                    $sql = "INSERT INTO stock_movements (product_id, from_branch_id, to_branch_id, movement_type, qty_consumption, qty_purchase, user_id, created_at, note)
                            VALUES (?, NULL, ?, 'receipt', ?, ?, ?, NOW(), ?)";
                    $stmt = $db->prepare($sql);
                    if ($stmt === false) throw new \Exception("prepare insert movement with user+purchase: " . $db->error);
                    $stmt->bind_param('iddiss', $product_id, $bodega_id, $qty_consumption, $qty_purchase, $user_id, $note);
                }
            } else {
                // INSERT omitiendo user_id -> quedará NULL
                if ($qty_purchase === null) {
                    $sql = "INSERT INTO stock_movements (product_id, from_branch_id, to_branch_id, movement_type, qty_consumption, qty_purchase, created_at, note)
                            VALUES (?, NULL, ?, 'receipt', ?, NULL, NOW(), ?)";
                    $stmt = $db->prepare($sql);
                    if ($stmt === false) throw new \Exception("prepare insert movement no user: " . $db->error);
                    $stmt->bind_param('iids', $product_id, $bodega_id, $qty_consumption, $note);
                } else {
                    $sql = "INSERT INTO stock_movements (product_id, from_branch_id, to_branch_id, movement_type, qty_consumption, qty_purchase, created_at, note)
                            VALUES (?, NULL, ?, 'receipt', ?, ?, NOW(), ?)";
                    $stmt = $db->prepare($sql);
                    if ($stmt === false) throw new \Exception("prepare insert movement no user + purchase: " . $db->error);
                    $stmt->bind_param('iidds', $product_id, $bodega_id, $qty_consumption, $qty_purchase, $note);
                }
            }

            if (!$stmt->execute()) throw new \Exception("execute insert movement: " . $stmt->error);
            $stmt->close();
            @file_put_contents($dbgFile, "Inserted stock_movements receipt OK\n", FILE_APPEND);

            $db->commit();
            @file_put_contents($dbgFile, "Transaction committed\n", FILE_APPEND);

            $result['ok'] = true;
            $result['messages'][] = 'Recepción registrada correctamente';
            @file_put_contents($dbgFile, "DONE OK\n\n", FILE_APPEND);
            return $this->respond($result, $debugMode);

        } catch (\Exception $e) {
            if (isset($db)) { @ $db->rollback(); }
            $msg = $e->getMessage();
            @file_put_contents($dbgFile, "EXCEPTION: $msg\n", FILE_APPEND);
            error_log("ReceptionController::store error: " . $msg);
            $result['messages'][] = 'Error interno al procesar recepción: ' . $msg;
            return $this->respond($result, $debugMode);
        }
    }

    private function respond(array $result, bool $debugMode) {
        if ($debugMode) {
            header('Content-Type: application/json');
            echo json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
            exit;
        } else {
            if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
            $_SESSION['flash'] = implode(' | ', $result['messages']);
            header('Location: ?url=product/index');
            exit;
        }
    }
}
