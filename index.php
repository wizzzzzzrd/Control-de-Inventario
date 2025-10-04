<?php
// index.php - Front controller (raíz del proyecto)
declare(strict_types=1);

// Definir baseDir (este archivo está en la raíz del proyecto)
$baseDir = __DIR__;

// Cargar config
require_once $baseDir . '/config/config.php';

// Cargar bootstrap (autoload mínimo / includes)
require_once $baseDir . '/app/bootstrap.php';

// Simple router: ?url=controller/action/param1/param2
$url = $_GET['url'] ?? '';
$url = trim($url, '/');
$parts = $url === '' ? [] : explode('/', $url);

$controllerName = !empty($parts[0]) ? ucfirst($parts[0]) . 'Controller' : 'productController';
$action = $parts[1] ?? 'index';
$params = array_slice($parts, 2);

// Construir nombre de clase y archivo
$controllerFile = $baseDir . '/app/controllers/' . $controllerName . '.php';

if (!file_exists($controllerFile)) {
    header("HTTP/1.0 404 Not Found");
    echo "404 - Controlador no encontrado: $controllerName";
    exit;
}

require_once $controllerFile;

$fullClass = "\\App\\Controllers\\" . $controllerName;
if (!class_exists($fullClass)) {
    // fallback: cargar clase sin namespace
    $fullClass = $controllerName;
}

$ctrl = new $fullClass();
if (!method_exists($ctrl, $action)) {
    header("HTTP/1.0 404 Not Found");
    echo "404 - Acción no encontrada: $action";
    exit;
}

// Llamar acción con parámetros
call_user_func_array([$ctrl, $action], $params);
