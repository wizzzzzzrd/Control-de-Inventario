<?php
// app/bootstrap.php
// Autoloader mejorado + helpers de sesión integrados

spl_autoload_register(function($class) {
    // 1) Si es App\... -> mapear directo a app/
    $prefix = 'App\\';
    if (strpos($class, $prefix) === 0) {
        $relative = substr($class, strlen($prefix));
        $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }

    // 2) Fallbacks para controllers y models (varias variantes de mayúsculas/minúsculas)
    $tries = [];

    // nombre tal cual
    $tries[] = __DIR__ . '/controllers/' . $class . '.php';
    $tries[] = __DIR__ . '/models/' . $class . '.php';

    // lcfirst / ucfirst
    $tries[] = __DIR__ . '/controllers/' . lcfirst($class) . '.php';
    $tries[] = __DIR__ . '/controllers/' . ucfirst($class) . '.php';
    $tries[] = __DIR__ . '/models/' . lcfirst($class) . '.php';
    $tries[] = __DIR__ . '/models/' . ucfirst($class) . '.php';

    // con sufijo Controller (por si tus ficheros se llaman authController.php pero la clase es AuthController)
    $tries[] = __DIR__ . '/controllers/' . $class . 'Controller.php';
    $tries[] = __DIR__ . '/controllers/' . lcfirst($class) . 'Controller.php';
    $tries[] = __DIR__ . '/controllers/' . ucfirst($class) . 'Controller.php';

    // también probar sin sufijo "Controller" en caso de inconsistencia
    $tries[] = __DIR__ . '/controllers/' . str_replace('Controller', '', $class) . '.php';
    $tries[] = __DIR__ . '/controllers/' . lcfirst(str_replace('Controller', '', $class)) . '.php';

    foreach ($tries as $f) {
        if (file_exists($f)) {
            require_once $f;
            return;
        }
    }

    // último recurso: buscar en la raíz app/ (por si usas subcarpetas diferentes)
    $alt = __DIR__ . '/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($alt)) require_once $alt;
});

// include helpers de vistas (si existe)
$vh = __DIR__ . '/views/helpers.php';
if (file_exists($vh)) require_once $vh;

/**
 * Helpers de sesión centralizados (no necesita crear carpeta helpers/)
 * - start_session_if_none()
 * - login_user($user)
 * - logout_user()
 * - current_user_id(), current_user_role()
 * - is_logged_in(), require_login()
 * - set_flash(), get_flash()
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    // si tu sitio usa HTTPS en producción, activa:
    // ini_set('session.cookie_secure', 1);
    session_start();
}

function start_session_if_none() {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
}

function login_user(array $user) {
    start_session_if_none();
    // por seguridad regenerar id
    session_regenerate_id(true);
    $_SESSION['user_id']   = isset($user['id']) ? (int)$user['id'] : null;
    $_SESSION['user_name'] = $user['name'] ?? null;
    $_SESSION['role_id']   = isset($user['role_id']) ? (int)$user['role_id'] : null;
    $_SESSION['branch_id'] = isset($user['branch_id']) ? (int)$user['branch_id'] : null;
}

function logout_user() {
    start_session_if_none();
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function current_user_id() {
    start_session_if_none();
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function current_user_role() {
    start_session_if_none();
    return isset($_SESSION['role_id']) ? (int)$_SESSION['role_id'] : null;
}

function is_logged_in() {
    return current_user_id() !== null;
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: ?url=auth/login');
        exit;
    }
}

function set_flash($msg) {
    start_session_if_none();
    $_SESSION['flash'] = $msg;
}

function get_flash() {
    start_session_if_none();
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}
