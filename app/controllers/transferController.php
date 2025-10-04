<?php
// app/controllers/transferController.php
namespace App\Controllers;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../models/conexion.php';
require_once __DIR__ . '/../models/product.php';

class TransferController {

    // Detecta tabla existente (transfers / traspasos)
    protected function tableExists($db, $name) {
        $name = $db->real_escape_string($name);
        $res = $db->query("SHOW TABLES LIKE '{$name}'");
        return ($res && $res->num_rows > 0);
    }

    /**
     * Lista traspasos. Filtra por fecha (parámetro GET 'date' con formato YYYY-MM-DD).
     * Si no se pasa fecha, usa la fecha de hoy.
     */
    public function index() {
        require_once __DIR__ . '/../bootstrap.php';
        require_once __DIR__ . '/../models/conexion.php';
        require_login();
        // Permisos explícitos: owner, admin_full, almacenista, admin_view pueden ver traspasos
        require_role(['owner','admin_full','almacenista','admin_view']);

        $db = getConexion();
        $transfers = [];
        $table = null;

        if ($db) {
            $table = $this->tableExists($db, 'transfers') ? 'transfers' : ($this->tableExists($db, 'traspasos') ? 'traspasos' : null);
            if ($table) {
                // fecha por GET o hoy
                $date = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']) ? $_GET['date'] : date('Y-m-d');

                $sql = "SELECT t.*, p.name AS product_name, fb.name AS from_branch_name, tb.name AS to_branch_name
                        FROM {$table} t
                        LEFT JOIN products p ON p.id = t.product_id
                        LEFT JOIN branches fb ON fb.id = t.from_branch_id
                        LEFT JOIN branches tb ON tb.id = t.to_branch_id
                        WHERE DATE(t.created_at) = ?
                        ORDER BY t.created_at DESC
                        LIMIT 1000";
                if ($stmt = $db->prepare($sql)) {
                    $stmt->bind_param('s', $date);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    while ($r = $res->fetch_assoc()) $transfers[] = $r;
                    $stmt->close();
                }
            } else {
                // si no existe tabla, $transfers queda vacío; view lo manejará
                $date = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']) ? $_GET['date'] : date('Y-m-d');
            }
        } else {
            $date = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']) ? $_GET['date'] : date('Y-m-d');
        }

        // Render view usando render_view para que el layout sea elegido según role_slug
        render_view('transfers/index', ['transfers' => $transfers, 'date' => $date]);
    }

    /**
     * Devuelve detalle JSON de un traspaso (GET id=)
     */
    public function get() {
        require_once __DIR__ . '/../bootstrap.php';
        require_once __DIR__ . '/../models/conexion.php';
        require_login();
        // Permisos: solo roles autorizados
        require_role(['owner','admin_full','almacenista','admin_view']);

        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (!$id) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Falta id']);
            exit;
        }

        $db = getConexion();
        if (!$db) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'No se pudo conectar a BD']);
            exit;
        }

        $table = $this->tableExists($db, 'transfers') ? 'transfers' : ($this->tableExists($db, 'traspasos') ? 'traspasos' : null);
        if (!$table) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => "No existe tabla de traspasos ('transfers' o 'traspasos')"]);
            exit;
        }

        $sql = "SELECT t.*, p.name AS product_name, p.sku AS product_sku, fb.name AS from_branch_name, tb.name AS to_branch_name
                FROM {$table} t
                LEFT JOIN products p ON p.id = t.product_id
                LEFT JOIN branches fb ON fb.id = t.from_branch_id
                LEFT JOIN branches tb ON tb.id = t.to_branch_id
                WHERE t.id = ? LIMIT 1";
        $out = null;
        if ($stmt = $db->prepare($sql)) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && ($row = $res->fetch_assoc())) $out = $row;
            $stmt->close();
        }

        if ($out === null) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Traspaso no encontrado']);
            exit;
        }

        header('Content-Type: application/json');
        echo json_encode($out);
        exit;
    }

    /**
     * Mantengo tu método store() tal como me lo pasaste (sin alterar la lógica).
     * Sólo lo pego aquí para que el controlador sea completo y no rompa rutas existentes.
     */
    public function store() {
        require_once __DIR__ . '/../bootstrap.php';
        require_login();
        // permisos: quien puede crear traspasos
        require_role(['owner','admin_full','almacenista']);

        // helpers y sesión desde bootstrap.php
        $dbgFile = __DIR__ . '/../../tmp/transfer_debug.log';
        if (!is_dir(dirname($dbgFile))) @mkdir(dirname($dbgFile), 0755, true);
        @file_put_contents($dbgFile, date('Y-m-d H:i:s') . " -> ENTRY transfer store\n", FILE_APPEND);
        @file_put_contents($dbgFile, "POST: " . json_encode($_POST) . "\n", FILE_APPEND);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            set_flash('Método no permitido');
            header('Location: ?url=product/index'); exit;
        }

        $product_id = intval($_POST['product_id'] ?? 0);
        $to_branch_id = intval($_POST['to_branch_id'] ?? 0);
        $unit = $_POST['unit'] ?? 'pieces';
        $qty = floatval($_POST['qty'] ?? 0.0);
        $user_id = current_user_id(); // int|null

        if ($product_id <= 0 || $to_branch_id <= 0 || $qty <= 0) {
            @file_put_contents($dbgFile, "VALIDATION FAIL: product_id={$product_id}, to_branch_id={$to_branch_id}, qty={$qty}\n", FILE_APPEND);
            set_flash('Datos de traspaso inválidos');
            header('Location: ?url=product/index'); exit;
        }

        $db = getConexion();

        // obtener bodega id
        $stmt = $db->prepare("SELECT id FROM branches WHERE is_bodega = 1 LIMIT 1");
        if ($stmt === false) {
            @file_put_contents($dbgFile, "Prepare get bodega fail: " . $db->error . "\n", FILE_APPEND);
            set_flash('Error interno');
            header('Location: ?url=product/index'); exit;
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $b = $res->fetch_assoc();
        $stmt->close();
        if (!$b) {
            @file_put_contents($dbgFile, "NO BODEGA\n", FILE_APPEND);
            set_flash('No existe bodega configurada');
            header('Location: ?url=product/index'); exit;
        }
        $from_branch_id = intval($b['id']);

        // factor
        $prodModel = new \Product();
        $prod = $prodModel->findById($product_id);
        if (!$prod) {
            @file_put_contents($dbgFile, "PRODUCT NOT FOUND id={$product_id}\n", FILE_APPEND);
            set_flash('Producto no encontrado');
            header('Location: ?url=product/index'); exit;
        }
        // Asegurar factor >= 1 para evitar divisiones extrañas
        $factor = max((float)($prod['factor'] ?? 1), 1.0);

        // normalizar unidad
        $u = strtolower(trim($unit));

        if ($u === 'boxes' || $u === 'cajas') {
            $qty_consumption = $qty * $factor; // piezas equivalentes
            $qty_purchase = $qty; // cajas
        } else {
            $qty_consumption = $qty; // piezas directas
            $qty_purchase = null;
        }

        $db->begin_transaction();
        try {
            // leer existencia en bodega
            $stmt = $db->prepare("SELECT COALESCE(qty_consumption_unit,0) as qp, COALESCE(boxes_count,0) as bc FROM product_branch_stock WHERE product_id = ? AND branch_id = ? LIMIT 1");
            if ($stmt === false) throw new \Exception("prepare select bodega: " . $db->error);
            $stmt->bind_param('ii', $product_id, $from_branch_id);
            if (!$stmt->execute()) throw new \Exception("execute select bodega: " . $stmt->error);
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $stmt->close();
            $b_qp = $row ? (float)$row['qp'] : 0.0;
            $b_bc = $row ? (float)$row['bc'] : 0.0;
            $b_total_pieces = $b_qp + ($b_bc * $factor);

            @file_put_contents($dbgFile, "BODEGA STOCK: qp={$b_qp}, bc={$b_bc}, factor={$factor}, totalPieces={$b_total_pieces}\n", FILE_APPEND);
            @file_put_contents($dbgFile, "TRANSFER REQ: qty_consumption={$qty_consumption}, qty_purchase=" . ($qty_purchase===null?'NULL':$qty_purchase) . "\n", FILE_APPEND);

            if ($b_total_pieces < $qty_consumption) {
                $db->rollback();
                @file_put_contents($dbgFile, "INSUFFICIENT STOCK\n", FILE_APPEND);
                set_flash('Stock insuficiente en bodega');
                header('Location: ?url=product/index'); exit;
            }

            // calcular restante en bodega (en piezas), luego convertir a cajas/piezas para almacenar
            $remaining = $b_total_pieces - $qty_consumption;
            $new_boxes = floor($remaining / $factor);
            $new_pieces = $remaining - ($new_boxes * $factor);

            // upsert bodega (se almacenan qty_consumption_unit = piezas sueltas, boxes_count = cajas completas)
            $stmt = $db->prepare("INSERT INTO product_branch_stock (product_id, branch_id, qty_consumption_unit, boxes_count, last_updated_at)
                                  VALUES (?, ?, ?, ?, NOW())
                                  ON DUPLICATE KEY UPDATE qty_consumption_unit = VALUES(qty_consumption_unit), boxes_count = VALUES(boxes_count), last_updated_at = NOW()");
            if ($stmt === false) throw new \Exception("prepare upsert bodega: " . $db->error);
            $stmt->bind_param('iidd', $product_id, $from_branch_id, $new_pieces, $new_boxes);
            if (!$stmt->execute()) throw new \Exception("execute upsert bodega: " . $stmt->error);
            $stmt->close();

            // leer destino (actual)
            $stmt = $db->prepare("SELECT COALESCE(qty_consumption_unit,0) as qp, COALESCE(boxes_count,0) as bc FROM product_branch_stock WHERE product_id = ? AND branch_id = ? LIMIT 1");
            if ($stmt === false) throw new \Exception("prepare select destino: " . $db->error);
            $stmt->bind_param('ii', $product_id, $to_branch_id);
            if (!$stmt->execute()) throw new \Exception("execute select destino: " . $stmt->error);
            $res = $stmt->get_result();
            $dest = $res->fetch_assoc();
            $stmt->close();

            $dest_qp = $dest ? (float)$dest['qp'] : 0.0;
            $dest_bc = $dest ? (float)$dest['bc'] : 0.0;

            // --- NUEVO: calcular total de piezas en destino sumando lo actual + lo que llega (ya sea cajas o piezas)
            $dest_total_pieces = $dest_qp + ($dest_bc * $factor);
            if ($qty_purchase !== null) {
                // llegó en cajas -> cajas * factor -> piezas
                $dest_total_pieces += ($qty_purchase * $factor);
            } else {
                // llegó en piezas
                $dest_total_pieces += $qty_consumption;
            }
            // convertir a cajas + piezas para almacenar
            $dest_bc_new = floor($dest_total_pieces / $factor);
            $dest_qp_new = $dest_total_pieces - ($dest_bc_new * $factor);

            @file_put_contents($dbgFile, "DEST BEFORE: qp={$dest_qp}, bc={$dest_bc} -> totalPiecesBefore=" . ($dest_qp + $dest_bc*$factor) . "\n", FILE_APPEND);
            @file_put_contents($dbgFile, "DEST AFTER: qp_new={$dest_qp_new}, bc_new={$dest_bc_new}, totalPiecesAfter={$dest_total_pieces}\n", FILE_APPEND);

            // upsert destino con los nuevos valores normalizados
            $stmt = $db->prepare("INSERT INTO product_branch_stock (product_id, branch_id, qty_consumption_unit, boxes_count, last_updated_at)
                                  VALUES (?, ?, ?, ?, NOW())
                                  ON DUPLICATE KEY UPDATE qty_consumption_unit = VALUES(qty_consumption_unit), boxes_count = VALUES(boxes_count), last_updated_at = NOW()");
            if ($stmt === false) throw new \Exception("prepare upsert destino: " . $db->error);
            $stmt->bind_param('iidd', $product_id, $to_branch_id, $dest_qp_new, $dest_bc_new);
            if (!$stmt->execute()) throw new \Exception("execute upsert destino: " . $stmt->error);
            $stmt->close();

            // inserta transfer (created_by opcional)
            if ($user_id !== null && $user_id > 0) {
                if ($qty_purchase === null) {
                    $stmt = $db->prepare("INSERT INTO transfers (product_id, from_branch_id, to_branch_id, qty_consumption, qty_purchase, created_by, status, created_at)
                                          VALUES (?, ?, ?, ?, NULL, ?, 'completed', NOW())");
                    if ($stmt === false) throw new \Exception("prepare insert transfer with user no purchase: " . $db->error);
                    // params: i,i,i,d,i
                    $stmt->bind_param('iiidi', $product_id, $from_branch_id, $to_branch_id, $qty_consumption, $user_id);
                } else {
                    $stmt = $db->prepare("INSERT INTO transfers (product_id, from_branch_id, to_branch_id, qty_consumption, qty_purchase, created_by, status, created_at)
                                          VALUES (?, ?, ?, ?, ?, ?, 'completed', NOW())");
                    if ($stmt === false) throw new \Exception("prepare insert transfer with user+purchase: " . $db->error);
                    // params: i,i,i,d,d,i
                    $stmt->bind_param('iiiddi', $product_id, $from_branch_id, $to_branch_id, $qty_consumption, $qty_purchase, $user_id);
                }
            } else {
                if ($qty_purchase === null) {
                    $stmt = $db->prepare("INSERT INTO transfers (product_id, from_branch_id, to_branch_id, qty_consumption, qty_purchase, created_by, status, created_at)
                                          VALUES (?, ?, ?, ?, NULL, NULL, 'completed', NOW())");
                    if ($stmt === false) throw new \Exception("prepare insert transfer null user no purchase: " . $db->error);
                    // params: i,i,i,d
                    $stmt->bind_param('iiid', $product_id, $from_branch_id, $to_branch_id, $qty_consumption);
                } else {
                    $stmt = $db->prepare("INSERT INTO transfers (product_id, from_branch_id, to_branch_id, qty_consumption, qty_purchase, created_by, status, created_at)
                                          VALUES (?, ?, ?, ?, ?, NULL, 'completed', NOW())");
                    if ($stmt === false) throw new \Exception("prepare insert transfer null user + purchase: " . $db->error);
                    // params: i,i,i,d,d
                    $stmt->bind_param('iiidd', $product_id, $from_branch_id, $to_branch_id, $qty_consumption, $qty_purchase);
                }
            }
            if (!$stmt->execute()) throw new \Exception("execute insert transfer: " . $stmt->error);
            $stmt->close();

            // stock movement out (omitimos user_id si no hay)
            $note = "Traspaso a sucursal {$to_branch_id}";
            if ($user_id !== null && $user_id > 0) {
                if ($qty_purchase === null) {
                    $stmt = $db->prepare("INSERT INTO stock_movements (product_id, from_branch_id, to_branch_id, movement_type, qty_consumption, qty_purchase, user_id, created_at, note)
                                          VALUES (?, ?, ?, 'transfer_out', ?, NULL, ?, NOW(), ?)");
                    if ($stmt === false) throw new \Exception("prepare movement out with user no purchase: " . $db->error);
                    // params: i,i,i,d,i,s
                    $stmt->bind_param('iiidis', $product_id, $from_branch_id, $to_branch_id, $qty_consumption, $user_id, $note);
                } else {
                    $stmt = $db->prepare("INSERT INTO stock_movements (product_id, from_branch_id, to_branch_id, movement_type, qty_consumption, qty_purchase, user_id, created_at, note)
                                          VALUES (?, ?, ?, 'transfer_out', ?, ?, ?, NOW(), ?)");
                    if ($stmt === false) throw new \Exception("prepare movement out with user+purchase: " . $db->error);
                    // params: i,i,i,d,d,i,s
                    $stmt->bind_param('iiiddis', $product_id, $from_branch_id, $to_branch_id, $qty_consumption, $qty_purchase, $user_id, $note);
                }
            } else {
                if ($qty_purchase === null) {
                    $stmt = $db->prepare("INSERT INTO stock_movements (product_id, from_branch_id, to_branch_id, movement_type, qty_consumption, qty_purchase, created_at, note)
                                          VALUES (?, ?, ?, 'transfer_out', ?, NULL, NOW(), ?)");
                    if ($stmt === false) throw new \Exception("prepare movement out null user no purchase: " . $db->error);
                    // params: i,i,i,d,s
                    $stmt->bind_param('iiids', $product_id, $from_branch_id, $to_branch_id, $qty_consumption, $note);
                } else {
                    $stmt = $db->prepare("INSERT INTO stock_movements (product_id, from_branch_id, to_branch_id, movement_type, qty_consumption, qty_purchase, created_at, note)
                                          VALUES (?, ?, ?, 'transfer_out', ?, ?, NOW(), ?)");
                    if ($stmt === false) throw new \Exception("prepare movement out null user + purchase: " . $db->error);
                    // params: i,i,i,d,d,s
                    $stmt->bind_param('iiidds', $product_id, $from_branch_id, $to_branch_id, $qty_consumption, $qty_purchase, $note);
                }
            }
            if (!$stmt->execute()) throw new \Exception("execute movement out: " . $stmt->error);
            $stmt->close();

            // stock movement in
            $note2 = "Traspaso recibido en sucursal {$to_branch_id}";
            if ($user_id !== null && $user_id > 0) {
                if ($qty_purchase === null) {
                    $stmt = $db->prepare("INSERT INTO stock_movements (product_id, from_branch_id, to_branch_id, movement_type, qty_consumption, qty_purchase, user_id, created_at, note)
                                          VALUES (?, ?, ?, 'transfer_in', ?, NULL, ?, NOW(), ?)");
                    if ($stmt === false) throw new \Exception("prepare movement in with user no purchase: " . $db->error);
                    // params: i,i,i,d,i,s
                    $stmt->bind_param('iiidis', $product_id, $from_branch_id, $to_branch_id, $qty_consumption, $user_id, $note2);
                } else {
                    $stmt = $db->prepare("INSERT INTO stock_movements (product_id, from_branch_id, to_branch_id, movement_type, qty_consumption, qty_purchase, user_id, created_at, note)
                                          VALUES (?, ?, ?, 'transfer_in', ?, ?, ?, NOW(), ?)");
                    if ($stmt === false) throw new \Exception("prepare movement in with user+purchase: " . $db->error);
                    // params: i,i,i,d,d,i,s
                    $stmt->bind_param('iiiddis', $product_id, $from_branch_id, $to_branch_id, $qty_consumption, $qty_purchase, $user_id, $note2);
                }
            } else {
                if ($qty_purchase === null) {
                    $stmt = $db->prepare("INSERT INTO stock_movements (product_id, from_branch_id, to_branch_id, movement_type, qty_consumption, qty_purchase, created_at, note)
                                          VALUES (?, ?, ?, 'transfer_in', ?, NULL, NOW(), ?)");
                    if ($stmt === false) throw new \Exception("prepare movement in null user no purchase: " . $db->error);
                    // params: i,i,i,d,s
                    $stmt->bind_param('iiids', $product_id, $from_branch_id, $to_branch_id, $qty_consumption, $note2);
                } else {
                    $stmt = $db->prepare("INSERT INTO stock_movements (product_id, from_branch_id, to_branch_id, movement_type, qty_consumption, qty_purchase, created_at, note)
                                          VALUES (?, ?, ?, 'transfer_in', ?, ?, NOW(), ?)");
                    if ($stmt === false) throw new \Exception("prepare movement in null user + purchase: " . $db->error);
                    // params: i,i,i,d,d,s
                    $stmt->bind_param('iiidds', $product_id, $from_branch_id, $to_branch_id, $qty_consumption, $qty_purchase, $note2);
                }
            }
            if (!$stmt->execute()) throw new \Exception("execute movement in: " . $stmt->error);
            $stmt->close();

            $db->commit();
            @file_put_contents($dbgFile, "TRANSFER OK\n", FILE_APPEND);
            set_flash('Traspaso realizado correctamente');
            header('Location: ?url=product/index'); exit;
        } catch (\Exception $e) {
            if (isset($db)) { @ $db->rollback(); }
            @file_put_contents($dbgFile, "EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
            error_log("TransferController::store error: " . $e->getMessage());
            set_flash('Error interno al procesar traspaso');
            header('Location: ?url=product/index'); exit;
        }
    }
}
