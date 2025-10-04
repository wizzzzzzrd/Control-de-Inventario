<?php
// app/models/user.php
require_once __DIR__ . '/conexion.php';

class User {
    protected $db;
    public function __construct() {
        $this->db = getConexion();
    }

    public function findByEmail(string $email) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();
        $r = $res->fetch_assoc();
        $stmt->close();
        return $r ?: null;
    }

    public function findById($id) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $r = $res->fetch_assoc();
        $stmt->close();
        return $r ?: null;
    }

    public function all(int $limit = 1000) {
        $q = "SELECT u.*, r.slug as role_slug, r.name as role_name, b.name as branch_name
              FROM users u
              LEFT JOIN roles r ON u.role_id = r.id
              LEFT JOIN branches b ON u.branch_id = b.id
              ORDER BY u.id DESC LIMIT ?";
        $stmt = $this->db->prepare($q);
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) $rows[] = $row;
        $stmt->close();
        return $rows;
    }

    public function create(array $data) {
        // $data keys: name,email,password,role_id,branch_id|null,is_active(optional)
        $name = $data['name'];
        $email = $data['email'];
        $hash = password_hash($data['password'], PASSWORD_BCRYPT);
        $role_id = (int)$data['role_id'];
        $is_active = isset($data['is_active']) ? (int)$data['is_active'] : 1;

        if (isset($data['branch_id']) && $data['branch_id'] !== '' && $data['branch_id'] !== null) {
            $branch_id = (int)$data['branch_id'];
            $stmt = $this->db->prepare("INSERT INTO users (name,email,password,role_id,branch_id,is_active,created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param('sssiii', $name, $email, $hash, $role_id, $branch_id, $is_active);
        } else {
            $stmt = $this->db->prepare("INSERT INTO users (name,email,password,role_id,is_active,created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param('sssii', $name, $email, $hash, $role_id, $is_active);
        }

        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new \Exception("Error creando usuario: " . $err);
        }
        $id = $this->db->insert_id;
        $stmt->close();
        return $id;
    }
}
