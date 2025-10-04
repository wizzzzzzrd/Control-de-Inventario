<?php
// app/controllers/userController.php
namespace App\Controllers;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../models/conexion.php';
require_once __DIR__ . '/../models/user.php';

class userController {
    public function index() {
        require_login();
        // Solo owner / admin_full (IDs 1 y 2 según tu seed)
        $rid = current_user_role();
        if (!in_array($rid, [1,2], true)) { http_response_code(403); echo 'Acceso denegado'; exit; }

        $um = new \User();
        $users = $um->all(1000);
        require_once __DIR__ . '/../views/users/index.php';
    }

    public function create() {
        require_login();
        $rid = current_user_role();
        if (!in_array($rid, [1,2], true)) { http_response_code(403); echo 'Acceso denegado'; exit; }

        $db = getConexion();
        $roles = []; $branches = [];
        $r = $db->query("SELECT id, slug, name FROM roles ORDER BY id");
        while ($row = $r->fetch_assoc()) $roles[] = $row;
        $r = $db->query("SELECT id, name FROM branches WHERE is_active=1 ORDER BY name");
        while ($row = $r->fetch_assoc()) $branches[] = $row;
        require_once __DIR__ . '/../views/users/create.php';
    }

    public function store() {
        require_login();
        $rid = current_user_role();
        if (!in_array($rid, [1,2], true)) { http_response_code(403); echo 'Acceso denegado'; exit; }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ?url=user/index'); exit; }

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role_id = intval($_POST['role_id'] ?? 0);
        $branch_id = $_POST['branch_id'] ?? null;
        $branch_id = ($branch_id === '' ? null : (int)$branch_id);

        if ($name === '' || $email === '' || $password === '' || $role_id <= 0) {
            set_flash('Faltan datos obligatorios');
            header('Location: ?url=user/create'); exit;
        }

        $um = new \User();
        try {
            $um->create([
                'name'=>$name,
                'email'=>$email,
                'password'=>$password,
                'role_id'=>$role_id,
                'branch_id'=>$branch_id
            ]);
        } catch (\Exception $e) {
            set_flash('Error: ' . $e->getMessage());
            header('Location: ?url=user/create'); exit;
        }

        set_flash('Usuario creado con éxito');
        header('Location: ?url=user/index'); exit;
    }
}
