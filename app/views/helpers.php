<?php
// app/views/helpers.php

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

/**
 * has_role
 */
function has_role(array $allowed): bool {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $role = strtolower(trim((string)($_SESSION['role_slug'] ?? '')));
    foreach ($allowed as $a) {
        if ($role === strtolower(trim((string)$a))) return true;
    }
    return false;
}

/**
 * require_role
 * - $allowed: array de slugs permitidos
 * - $redirect: 'auto' (por defecto) o una URL explícita
 * - $msg: mensaje flash
 *
 * Si $redirect === 'auto' redirige a un landing por rol (vendor->POS, admin_view->transfers, owner/admin_full->product, etc).
 */
function require_role(array $allowed, string $redirect = 'auto', string $msg = 'No tienes permiso para acceder a esta sección.') {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

    // Si no hay usuario logueado, forzamos login
    if (empty($_SESSION['user_id'])) {
        set_flash('Debes iniciar sesión.');
        header('Location: ?url=auth/login');
        exit;
    }

    if (has_role($allowed)) {
        return; // permiso concedido
    }

    // calcular redirect "inteligente" si se pidió 'auto'
    if ($redirect === 'auto') {
        $role = strtolower((string)($_SESSION['role_slug'] ?? ''));
        switch ($role) {
            case 'vendor':
                $branchId = $_SESSION['branch_id'] ?? null;
                if ($branchId && intval($branchId) > 0) {
                    $redirect = '?url=branch/sales&id=' . intval($branchId);
                } else {
                    $redirect = '?url=branch/index';
                }
                break;
            case 'admin_view':
                $redirect = '?url=transfer/index';
                break;
            case 'almacenista':
                $redirect = '?url=product/index';
                break;
            case 'owner':
            case 'admin_full':
                $redirect = '?url=product/index';
                break;
            default:
                $redirect = '?url=auth/login';
                break;
        }
    }

    set_flash($msg);
    header('Location: ' . $redirect);
    exit;
}

/**
 * render_view
 */
function render_view($path, $data = [], $force_layout = null) {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

    $viewFile = __DIR__ . '/' . $path . '.php';
    if (!is_array($data)) $data = [];
    extract($data, EXTR_SKIP);

    ob_start();
    if (file_exists($viewFile)) {
        require $viewFile;
    } else {
        echo "<div class=\"alert alert-danger\">View not found: " . htmlspecialchars($viewFile) . "</div>";
    }
    $content = ob_get_clean();

    $role = strtolower((string)($_SESSION['role_slug'] ?? ''));

    $map = [
        'owner'       => 'layouts/main',
        'admin_full'  => 'layouts/main',
        'admin_view'  => 'layouts/admin_main',
        'vendor'      => 'layouts/vendor_main',
        'almacenista' => 'layouts/alma_main'
    ];

    if (!empty($force_layout)) {
        $layoutPath = __DIR__ . '/' . $force_layout . '.php';
    } else {
        $layoutRel = $map[$role] ?? 'layouts/main';
        $layoutPath = __DIR__ . '/' . $layoutRel . '.php';
    }

    if ($layoutPath && file_exists($layoutPath)) {
        require $layoutPath;
    } else {
        echo $content;
    }
}
