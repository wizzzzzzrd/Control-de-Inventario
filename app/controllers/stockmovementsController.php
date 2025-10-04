<?php
// app/controllers/stockmovementsController.php
namespace App\Controllers;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../models/conexion.php';

class StockmovementsController {

    protected function tableExists($db, $name) {
        $name = $db->real_escape_string($name);
        $res = $db->query("SHOW TABLES LIKE '{$name}'");
        return ($res && $res->num_rows > 0);
    }

    /**
     * Devuelve array de columnas existentes en la tabla indicada
     */
    protected function getExistingColumns($db, $table) {
        $cols = [];
        $t = $db->real_escape_string($table);
        $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$t}'";
        if ($res = $db->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $cols[] = $row['COLUMN_NAME'];
            }
            $res->free();
        }
        return $cols;
    }

    /**
     * Lista movimientos. Filtra por fecha (GET 'date' YYYY-MM-DD). Si no, por hoy.
     */
    public function index() {
        require_once __DIR__ . '/../bootstrap.php';
        require_once __DIR__ . '/../models/conexion.php';
        require_login();
        // permisos explícitos: owner, admin_full, almacenista, admin_view pueden ver movimientos
        require_role(['owner','admin_full','almacenista','admin_view']);

        $db = getConexion();
        $movements = [];
        $table = null;

        // fecha por GET o hoy (siempre la definimos para la vista)
        $date = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']) ? $_GET['date'] : date('Y-m-d');

        if ($db) {
            // detectar tabla posible
            $table = $this->tableExists($db, 'stock_movements') ? 'stock_movements' : ($this->tableExists($db, 'movements') ? 'movements' : null);

            if ($table) {
                // columnas de la tabla de movimientos
                $movCols = $this->getExistingColumns($db, $table);

                // ---- Usuarios: detectar posibles columnas en users (username, user_name, name, email)
                $userCols = $this->getExistingColumns($db, 'users'); // si no existe, devuelve []
                $possibleUserCols = ['username', 'user_name', 'name', 'email'];
                $useUserCols = [];
                foreach ($possibleUserCols as $c) {
                    if (in_array($c, $userCols, true)) $useUserCols[] = $c;
                }
                if (!empty($useUserCols)) {
                    $parts = array_map(function($c){ return "u.`" . str_replace("`","", $c) . "`"; }, $useUserCols);
                    $user_coalesce = "COALESCE(" . implode(", ", $parts) . ") AS user_name";
                } else {
                    $user_coalesce = "'' AS user_name";
                }

                // ---- Branches: detectar columnas de branch en la tabla de movimientos
                // posibles nombres que tu BD podría tener
                $possibleBranchCols = [
                    'branch_id', 'to_branch_id', 'from_branch_id',
                    'branch_from_id', 'branch_to_id',
                    'branchid', 'frombranchid', 'tobranchid',
                    'branch', 'branch_from', 'branch_to'
                ];
                $foundBranchCols = [];
                foreach ($possibleBranchCols as $c) {
                    if (in_array($c, $movCols, true)) $foundBranchCols[] = $c;
                }

                // Construir joins dinámicos para branches y expresión COALESCE(...) para nombre de sucursal
                $branch_join_sql = '';
                $branch_name_expr = "'' AS branch_name";
                if (!empty($foundBranchCols)) {
                    $branchAliases = [];
                    $i = 0;
                    foreach ($foundBranchCols as $col) {
                        $alias = "b_{$i}";
                        // join: LEFT JOIN branches b_i ON b_i.id = m.<col>
                        $branch_join_sql .= " LEFT JOIN branches {$alias} ON {$alias}.id = m.`" . str_replace("`","", $col) . "`\n";
                        $branchAliases[] = "{$alias}.name";
                        $i++;
                    }
                    $branch_name_expr = "COALESCE(" . implode(", ", $branchAliases) . ") AS branch_name";
                } else {
                    // fallback: intentar join con columns estándar si existen en branches tabla (pero si no hay cols, devolvemos vacio)
                    $branch_join_sql = '';
                    $branch_name_expr = "'' AS branch_name";
                }

                // ---- Product join (si existe column product_id en movements)
                $product_join_sql = "";
                $select_product = "NULL AS product_name";
                if (in_array('product_id', $movCols, true)) {
                    $product_join_sql = " LEFT JOIN products p ON p.id = m.product_id ";
                    $select_product = "p.name AS product_name";
                }

                // Construir SQL final dinámico
                $sql = "SELECT m.*,
                               {$select_product},
                               {$branch_name_expr},
                               {$user_coalesce}
                        FROM `{$table}` m
                        {$product_join_sql}
                        {$branch_join_sql}
                        LEFT JOIN users u ON u.id = m.user_id
                        WHERE DATE(m.created_at) = ?
                        ORDER BY m.created_at DESC
                        LIMIT 2000";

                if ($stmt = $db->prepare($sql)) {
                    $stmt->bind_param('s', $date);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    while ($r = $res->fetch_assoc()) $movements[] = $r;
                    $stmt->close();
                } else {
                    // si falla la preparación, registrar y seguir (no romper)
                    error_log("stockmovementsController prepare failed: " . $db->error . " SQL: " . $sql);
                }
            } else {
                // no hay tabla; movements se queda vacío
            }
        }

        // render view usando render_view para que el layout sea elegido según role_slug
        render_view('stockmovements/index', ['movements' => $movements, 'date' => $date]);
    }

    /**
     * Devuelve detalle JSON de un movimiento
     */
    public function get() {
        require_once __DIR__ . '/../bootstrap.php';
        require_once __DIR__ . '/../models/conexion.php';
        require_login();
        // permisos explícitos
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

        $table = $this->tableExists($db, 'stock_movements') ? 'stock_movements' : ($this->tableExists($db, 'movements') ? 'movements' : null);
        if (!$table) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['error' => "No existe tabla de movimientos ('stock_movements' o 'movements')"]);
            exit;
        }

        // columnas en tabla movimientos
        $movCols = $this->getExistingColumns($db, $table);

        // usuarios
        $userCols = $this->getExistingColumns($db, 'users');
        $possibleUserCols = ['username', 'user_name', 'name', 'email'];
        $useUserCols = [];
        foreach ($possibleUserCols as $c) {
            if (in_array($c, $userCols, true)) $useUserCols[] = $c;
        }
        if (!empty($useUserCols)) {
            $parts = array_map(function($c){ return "u.`" . str_replace("`","", $c) . "`"; }, $useUserCols);
            $user_coalesce = "COALESCE(" . implode(", ", $parts) . ") AS user_name";
        } else {
            $user_coalesce = "'' AS user_name";
        }

        // branches - mismos candidatos que en index
        $possibleBranchCols = [
            'branch_id', 'to_branch_id', 'from_branch_id',
            'branch_from_id', 'branch_to_id',
            'branchid', 'frombranchid', 'tobranchid',
            'branch', 'branch_from', 'branch_to'
        ];
        $foundBranchCols = [];
        foreach ($possibleBranchCols as $c) {
            if (in_array($c, $movCols, true)) $foundBranchCols[] = $c;
        }
        $branch_join_sql = '';
        $branch_name_expr = "'' AS branch_name";
        if (!empty($foundBranchCols)) {
            $branchAliases = [];
            $i = 0;
            foreach ($foundBranchCols as $col) {
                $alias = "b_{$i}";
                $branch_join_sql .= " LEFT JOIN branches {$alias} ON {$alias}.id = m.`" . str_replace("`","", $col) . "`\n";
                $branchAliases[] = "{$alias}.name";
                $i++;
            }
            $branch_name_expr = "COALESCE(" . implode(", ", $branchAliases) . ") AS branch_name";
        }

        // product join si existe product_id
        $product_join_sql = "";
        $select_product = "NULL AS product_name";
        if (in_array('product_id', $movCols, true)) {
            $product_join_sql = " LEFT JOIN products p ON p.id = m.product_id ";
            $select_product = "p.name AS product_name";
        }

        $sql = "SELECT m.*,
                       {$select_product},
                       {$branch_name_expr},
                       {$user_coalesce}
                FROM `{$table}` m
                {$product_join_sql}
                {$branch_join_sql}
                LEFT JOIN users u ON u.id = m.user_id
                WHERE m.id = ? LIMIT 1";

        $out = null;
        if ($stmt = $db->prepare($sql)) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && ($row = $res->fetch_assoc())) $out = $row;
            $stmt->close();
        } else {
            error_log("stockmovementsController::get prepare failed: " . $db->error . " SQL: " . $sql);
        }

        if ($out === null) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Movimiento no encontrado']);
            exit;
        }

        header('Content-Type: application/json');
        echo json_encode($out);
        exit;
    }
}
