<?php
// app/models/conexion.php
// Conexión centralizada usando mysqli (db: 'inventario')

/**
 * Devuelve la instancia de conexión mysqli (singleton).
 * Uso: $conexion = getConexion();
 */
function getConexion() {
    static $conexion = null;

    if ($conexion instanceof mysqli) {
        return $conexion;
    }

    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $db   = 'inventario'; // <<-- nombre de la BD indicado

    // Habilitar informes de error opcionalmente durante desarrollo
    // mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $conexion = new mysqli($host, $user, $pass, $db);

    if ($conexion->connect_errno) {
        // En desarrollo: mostrar error; en producción registra y muestra mensaje genérico
        error_log("Error de conexión MySQL ({$conexion->connect_errno}): {$conexion->connect_error}");
        die("Error al conectar la base de datos. Revisa app/models/conexion.php");
    }

    // Forzar charset utf8mb4
    if (!$conexion->set_charset("utf8mb4")) {
        error_log("No se pudo establecer charset utf8mb4: " . $conexion->error);
    }

    return $conexion;
}

// Opcional: crear variable global $conexion para compatibilidad con código actual
$conexion = getConexion();
