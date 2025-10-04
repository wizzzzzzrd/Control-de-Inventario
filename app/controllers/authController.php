<?php
// app/controllers/authController.php
namespace App\Controllers;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../models/conexion.php';
require_once __DIR__ . '/../models/user.php';

class authController {
    public function login() {
        // view
        require_once __DIR__ . '/../views/auth/login.php';
    }

   public function dologin() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ?url=auth/login'); exit;
    }
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $um = new \User();
    $user = $um->findByEmail($email);
    if (!$user || !password_verify($password, $user['password'])) {
        set_flash('Email o contraseña inválida');
        header('Location: ?url=auth/login'); exit;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

    $_SESSION['user_id']   = intval($user['id']);
    $_SESSION['user_name'] = $user['name'] ?? '';
    $_SESSION['role_id']   = intval($user['role_id'] ?? 0);
    $_SESSION['branch_id'] = isset($user['branch_id']) ? intval($user['branch_id']) : null;

    $db = getConexion();
    $role_slug = '';
    if (!empty($_SESSION['role_id'])) {
        $stmt = $db->prepare("SELECT slug, name FROM roles WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $_SESSION['role_id']);
            $stmt->execute();
            $res = $stmt->get_result();
            $rr = $res->fetch_assoc();
            if ($rr) {
                $role_slug = $rr['slug'];
                $_SESSION['role_slug'] = $rr['slug'];
                $_SESSION['role_name'] = $rr['name'];
            }
            $stmt->close();
        }
    }

    if (empty($role_slug) && !empty($_SESSION['role_id'])) {
        $_SESSION['role_slug'] = '';
    }

    if (function_exists('login_user')) {
        login_user($user);
    }

    set_flash('Bienvenido: ' . htmlspecialchars($_SESSION['user_name'] ?? ''));

    // REDIRECCIÓN SEGÚN ROL (mejorada)
    $rs = strtolower((string)($_SESSION['role_slug'] ?? ''));
    $branchId = $_SESSION['branch_id'] ?? 0;

    if ($rs === 'vendor') {
        if ($branchId && intval($branchId) > 0) {
            header('Location: ?url=branch/sales&id=' . intval($branchId));
            exit;
        } else {
            set_flash('Tu usuario no tiene asignada una sucursal. Contacta al administrador.');
            header('Location: ?url=branch/index'); exit;
        }
    }

    if ($rs === 'admin_view') {
        // admin_visualizador -> llevar a traspasos (las opciones que puede ver)
        header('Location: ?url=transfer/index'); exit;
    }

    // owner/admin_full y demás -> dashboard productos
    header('Location: ?url=product/index'); exit;
}


    public function logout() {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        // limpiar sesión
        foreach (['user_id','user_name','role_id','role_slug','role_name','branch_id'] as $k) {
            if (isset($_SESSION[$k])) unset($_SESSION[$k]);
        }
        // si existe helper
        if (function_exists('logout_user')) logout_user();
        set_flash('Has cerrado sesión.');
        header('Location: ?url=auth/login'); exit;
    }
}
