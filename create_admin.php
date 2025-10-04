<?php
// create_admin.php (ejecutar una vez desde navegador o CLI)
require_once __DIR__ . '/app/models/conexion.php';
$name = 'Admin Demo';
$email = 'admin@example.com';
$pass = password_hash('Admin123!', PASSWORD_BCRYPT);
$role_id = 2; // admin_full
$branch_id = 2; // sucursal demo

$db = getConexion();
$stmt = $db->prepare("INSERT INTO users (name,email,password,role_id,branch_id,is_active,created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
$stmt->bind_param('sssii', $name, $email, $pass, $role_id, $branch_id);
if ($stmt->execute()) {
    echo "Usuario admin creado OK. Email: $email  Pass: Admin123!";
} else {
    echo "Error: ".$stmt->error;
}
