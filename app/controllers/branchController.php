<?php
// app/controllers/BranchController.php
namespace App\Controllers;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../models/conexion.php';
require_once __DIR__ . '/../models/product.php';
require_once __DIR__ . '/../views/helpers.php';

class BranchController {
    /**
     * Listar sucursales (usa la vista app/views/branches/index.php)
     */
    public function index() {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        if (!is_logged_in()) {
            header('Location: ?url=auth/login'); exit;
        }

        $db = getConexion();
        $branches = [];

        $stmt = $db->prepare("SELECT id, name, code, address, phone, is_bodega, is_active FROM branches ORDER BY is_bodega DESC, name");
        if ($stmt) {
            $stmt->execute();
            $res = $stmt->get_result();
            $branches = $res->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }

        // intentar obtener información mínima del usuario (branch_id / is_admin) si existe
        $current_user_branch_id = null;
        $is_admin = false;
        $uid = function_exists('current_user_id') ? current_user_id() : ($_SESSION['user_id'] ?? null);
        if ($uid) {
            try {
                $uStmt = $db->prepare("SELECT branch_id, is_admin FROM users WHERE id = ? LIMIT 1");
                if ($uStmt) {
                    $uStmt->bind_param('i', $uid);
                    $uStmt->execute();
                    $uRes = $uStmt->get_result();
                    $uRow = $uRes->fetch_assoc();
                    if ($uRow) {
                        $current_user_branch_id = isset($uRow['branch_id']) ? ($uRow['branch_id'] !== '' ? intval($uRow['branch_id']) : null) : null;
                        $is_admin = !empty($uRow['is_admin']);
                    }
                    $uStmt->close();
                }
            } catch (\Throwable $ex) {
                // columna branch_id / is_admin no existe o error: lo ignoramos y dejamos defaults
                error_log("BranchController::index user fetch ignored: " . $ex->getMessage());
            }
        }

        render_view('branches/index', [
            'branches' => $branches,
            'current_user_branch_id' => $current_user_branch_id,
            'is_admin' => $is_admin
        ]);
    }

    /**
     * Guardar nueva sucursal (POST) -> ?url=branch/store
     */
    public function store() {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            set_flash('Método no permitido');
            header('Location: ?url=branch/index'); exit;
        }
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            set_flash('El nombre es obligatorio');
            header('Location: ?url=branch/index'); exit;
        }
        $code = trim($_POST['code'] ?? null);
        $phone = trim($_POST['phone'] ?? null);
        $address = trim($_POST['address'] ?? null);
        $is_bodega = isset($_POST['is_bodega']) && ($_POST['is_bodega'] == '1' || $_POST['is_bodega'] == 1) ? 1 : 0;

        $db = getConexion();
        $stmt = $db->prepare("INSERT INTO branches (name, code, address, phone, is_bodega, is_active, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
        if ($stmt === false) {
            error_log("BranchController::store prepare failed: " . $db->error);
            set_flash('Error interno al crear sucursal');
            header('Location: ?url=branch/index'); exit;
        }
        $stmt->bind_param('ssssi', $name, $code, $address, $phone, $is_bodega);
        if (!$stmt->execute()) {
            error_log("BranchController::store execute failed: " . $stmt->error);
            $stmt->close();
            set_flash('Error al crear sucursal');
            header('Location: ?url=branch/index'); exit;
        }
        $stmt->close();
        set_flash('Sucursal creada correctamente');
        header('Location: ?url=branch/index');
        exit;
    }

    /**
     * Vista de ventas de una sucursal -> ?url=branch/sales&id=NN
     * Muestra productos y stock real en esa sucursal.
     * Si el usuario es vendor se le presenta un layout reducido (vendor_main).
     */
    public function sales() {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        if (!is_logged_in()) {
            header('Location: ?url=auth/login'); exit;
        }

        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) {
            set_flash('Sucursal inválida');
            header('Location: ?url=branch/index'); exit;
        }

        // Si el usuario es vendor, asegurarnos que sólo acceda a su sucursal
        $roleSlug = $_SESSION['role_slug'] ?? null;
        $userBranch = $_SESSION['branch_id'] ?? null;
        if ($roleSlug === 'vendor') {
            if (empty($userBranch) || intval($userBranch) !== $id) {
                // redirigir al vendor a su sucursal (si existe) o mostrar mensaje
                if (!empty($userBranch)) {
                    header('Location: ?url=branch/sales&id=' . intval($userBranch)); exit;
                } else {
                    set_flash('No tienes sucursal asignada');
                    header('Location: ?url=branch/index'); exit;
                }
            }
        }

        $db = getConexion();

        // cargar datos de la sucursal
        $stmt = $db->prepare("SELECT id, name, code, address, phone, is_bodega FROM branches WHERE id = ? LIMIT 1");
        if ($stmt === false) {
            error_log("BranchController::sales prepare branch failed: " . $db->error);
            set_flash('Error interno');
            header('Location: ?url=branch/index'); exit;
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $branch = $res->fetch_assoc();
        $stmt->close();
        if (!$branch) {
            set_flash('Sucursal no encontrada');
            header('Location: ?url=branch/index'); exit;
        }

        // obtener productos con stock relativo a esta sucursal
        $sql = "SELECT p.id, p.sku, p.barcode, p.name, p.description, p.factor, p.photo_path, p.recommended_stock,
                       COALESCE(pbs.qty_consumption_unit,0) AS qty_pieces,
                       COALESCE(pbs.boxes_count,0) AS boxes,
                       p.default_price_consumption
                FROM products p
                LEFT JOIN product_branch_stock pbs ON pbs.product_id = p.id AND pbs.branch_id = ?
                ORDER BY p.name";
        $stmt = $db->prepare($sql);
        $products = [];
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $factor = max((float)($r['factor'] ?? 1), 1.0);
                $total_pieces = (float)$r['qty_pieces'] + ((float)$r['boxes'] * $factor);
                $boxes_display = (int)floor($total_pieces / $factor);
                $pieces_display = (float)($total_pieces - ($boxes_display * $factor));
                $r['total_pieces'] = $total_pieces;
                $r['boxes_display'] = $boxes_display;
                $r['pieces_display'] = $pieces_display;
                $products[] = $r;
            }
            $stmt->close();
        }

        // Si es vendor, forzamos layout vendor_main (render_view elegirá layout según rol si no forzamos)
        if ($roleSlug === 'vendor') {
            render_view('branches/sales', ['branch' => $branch, 'products' => $products], 'layouts/vendor_main');
            return;
        }

        // para admins/dueños usamos la render_view habitual
        render_view('branches/sales', ['branch' => $branch, 'products' => $products]);
    }

    /**
     * Historial de ventas/órdenes por sucursal -> ?url=branch/history&id=NN
     */
    public function history() {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        if (!is_logged_in()) {
            header('Location: ?url=auth/login'); exit;
        }

        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) {
            set_flash('Sucursal inválida');
            header('Location: ?url=branch/index'); exit;
        }

        // permisos: vendor solo puede ver su propia sucursal
        $roleSlug = $_SESSION['role_slug'] ?? null;
        $userBranch = $_SESSION['branch_id'] ?? null;
        if ($roleSlug === 'vendor' && intval($userBranch) !== $id) {
            set_flash('No tienes permisos para ver el historial de esa sucursal');
            header('Location: ?url=branch/sales&id=' . intval($userBranch)); exit;
        }

        $db = getConexion();
        // listar ventas (orders) con usuario
        $sql = "SELECT o.id, o.order_number, o.branch_id, o.user_id, o.total_amount, o.status, o.created_at, u.name as user_name
                FROM orders o
                LEFT JOIN users u ON u.id = o.user_id
                WHERE o.branch_id = ?
                ORDER BY o.created_at DESC
                LIMIT 500";
        $orders = [];
        $stmt = $db->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) $orders[] = $r;
            $stmt->close();
        }

        render_view('branches/history', ['branch_id' => $id, 'branch' => (isset($branch)?$branch:null), 'orders' => $orders]);
    }

}
