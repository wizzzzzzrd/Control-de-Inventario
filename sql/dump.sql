-- Dump completo: Sistema inventario (MySQL) - listo para MVC PHP
-- Charset/InnoDB
SET FOREIGN_KEY_CHECKS=0;

-- 1) Roles
CREATE TABLE IF NOT EXISTS roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  description VARCHAR(255) NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) Branches (sucursales). is_bodega = 1 indica la bodega central
CREATE TABLE IF NOT EXISTS branches (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  code VARCHAR(80) NULL,
  address VARCHAR(255) NULL,
  phone VARCHAR(50) NULL,
  is_active TINYINT(1) DEFAULT 1,
  is_bodega TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) Users
CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role_id INT UNSIGNED NOT NULL,
  branch_id INT UNSIGNED NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT,
  FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4) Products
CREATE TABLE IF NOT EXISTS products (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(120) NOT NULL UNIQUE,
  barcode VARCHAR(120) NULL,
  name VARCHAR(255) NOT NULL,
  description TEXT NULL,
  category VARCHAR(120) NULL,
  type ENUM('primario','secundario','directo','indirecto') NOT NULL DEFAULT 'primario',
  purchase_unit VARCHAR(60) NOT NULL DEFAULT 'caja',
  consumption_unit VARCHAR(60) NOT NULL DEFAULT 'pieza',
  factor DECIMAL(10,3) NOT NULL DEFAULT 1.000,
  default_price_purchase DECIMAL(12,2) NULL,
  default_price_consumption DECIMAL(12,2) NULL,
  recommended_stock INT DEFAULT 10,
  critical_threshold INT DEFAULT 5,
  bodega_critical_boxes INT DEFAULT 4,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5) Stock por sucursal (product_branch_stock)
CREATE TABLE IF NOT EXISTS product_branch_stock (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  branch_id INT UNSIGNED NOT NULL,
  qty_consumption_unit DECIMAL(14,3) NOT NULL DEFAULT 0.000,
  boxes_count DECIMAL(12,3) NULL,
  last_updated_at TIMESTAMP NULL,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
  UNIQUE (product_id, branch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6) Stock movements (ledger)
CREATE TABLE IF NOT EXISTS stock_movements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  from_location_id INT UNSIGNED NULL, -- legacy, no usar si no aplica
  to_location_id INT UNSIGNED NULL,   -- legacy
  from_branch_id INT UNSIGNED NULL,
  to_branch_id INT UNSIGNED NULL,
  movement_type ENUM('receipt','transfer_out','transfer_in','sale','adjustment','inventory_recount') NOT NULL,
  qty_consumption DECIMAL(14,3) NOT NULL,
  qty_purchase DECIMAL(14,3) NULL,
  reference VARCHAR(255) NULL,
  user_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  note TEXT NULL,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (from_branch_id) REFERENCES branches(id) ON DELETE SET NULL,
  FOREIGN KEY (to_branch_id) REFERENCES branches(id) ON DELETE SET NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7) Transfers (convenience table)
CREATE TABLE IF NOT EXISTS transfers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  from_branch_id INT UNSIGNED NOT NULL,
  to_branch_id INT UNSIGNED NOT NULL,
  qty_consumption DECIMAL(14,3) NOT NULL,
  qty_purchase DECIMAL(14,3) NULL,
  created_by BIGINT UNSIGNED NULL,
  status ENUM('pending','completed','cancelled') DEFAULT 'completed',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (from_branch_id) REFERENCES branches(id) ON DELETE CASCADE,
  FOREIGN KEY (to_branch_id) REFERENCES branches(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8) Branch requests (tickets de sucursal)
CREATE TABLE IF NOT EXISTS branch_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  branch_id INT UNSIGNED NOT NULL,
  request_number VARCHAR(100) NOT NULL UNIQUE,
  status ENUM('open','sent_to_bodega','fulfilled','cancelled') DEFAULT 'open',
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  note TEXT NULL,
  FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS branch_request_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  qty_requested_consumption DECIMAL(14,3) NOT NULL,
  qty_requested_purchase DECIMAL(14,3) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (request_id) REFERENCES branch_requests(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9) Bodega notes (consolidación)
CREATE TABLE IF NOT EXISTS bodega_notes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  note_number VARCHAR(100) NOT NULL UNIQUE,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  status ENUM('created','dispatched','completed') DEFAULT 'created',
  note TEXT NULL,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bodega_note_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bodega_note_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  qty_dispatch_consumption DECIMAL(14,3) NOT NULL,
  qty_dispatch_purchase DECIMAL(14,3) NULL,
  FOREIGN KEY (bodega_note_id) REFERENCES bodega_notes(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10) Orders and order_items (POS)
CREATE TABLE IF NOT EXISTS orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_number VARCHAR(120) NOT NULL UNIQUE,
  branch_id INT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NULL,
  total_amount DECIMAL(12,2) DEFAULT 0.00,
  status ENUM('created','paid','cancelled') DEFAULT 'created',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  qty DECIMAL(14,3) NOT NULL,
  price_unit DECIMAL(12,2) NOT NULL,
  subtotal DECIMAL(12,2) NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11) Inventory sessions + counts
CREATE TABLE IF NOT EXISTS inventory_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NULL,
  session_type ENUM('diario','domingo','manual') DEFAULT 'diario',
  allowed_types JSON NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  status ENUM('open','closed','processing') DEFAULT 'open',
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inventory_counts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  branch_id INT UNSIGNED NOT NULL,
  counted_qty DECIMAL(14,3) NOT NULL,
  counted_boxes DECIMAL(12,3) NULL,
  counted_by BIGINT UNSIGNED NULL,
  counted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  note TEXT NULL,
  FOREIGN KEY (session_id) REFERENCES inventory_sessions(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
  FOREIGN KEY (counted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12) Product files (jpg/png etc.)
CREATE TABLE IF NOT EXISTS product_files (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  filename VARCHAR(300) NOT NULL,
  mime_type VARCHAR(100) NULL,
  filesize INT NULL,
  path VARCHAR(400) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13) Audits
CREATE TABLE IF NOT EXISTS audits (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  table_name VARCHAR(150) NOT NULL,
  record_id BIGINT UNSIGNED NULL,
  action VARCHAR(50) NOT NULL,
  old_value JSON NULL,
  new_value JSON NULL,
  user_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14) Índices recomendados
CREATE INDEX IF NOT EXISTS idx_products_type ON products(type);
CREATE INDEX IF NOT EXISTS idx_product_branch_stock ON product_branch_stock(product_id, branch_id);
CREATE INDEX IF NOT EXISTS idx_stockmov_product ON stock_movements(product_id);
CREATE INDEX IF NOT EXISTS idx_branches_isbodega ON branches(is_bodega);
CREATE INDEX IF NOT EXISTS idx_products_search ON products(sku(60), barcode(60), name(100));

SET FOREIGN_KEY_CHECKS=1;

-- 15) Seeds básicos: roles y ejemplo de branches
-- Insert roles (owner, admin_full, admin_view, vendor, almacenista)
INSERT INTO roles (slug, name, description, created_at) VALUES
  ('owner','Dueño','Acceso total al sistema', NOW()),
  ('admin_full','Administrador (full)','Admin con permisos para todo', NOW()),
  ('admin_view','Administrador (solo visualización)','Puede ver reportes y movimientos', NOW()),
  ('vendor','Vendedor','Puede crear ventas en su sucursal', NOW()),
  ('almacenista','Almacenista','Encargado de recepciones en bodega y transferencias', NOW())
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Crear Bodega Central y una sucursal demo (si no existen)
INSERT INTO branches (name, code, address, phone, is_active, is_bodega, created_at)
VALUES
  ('Bodega Central','BODEGA','Dirección Bodega',NULL,1,1,NOW()),
  ('Sucursal Centro','SUC-CTR','Dirección Sucursal Centro',NULL,1,0,NOW())
ON DUPLICATE KEY UPDATE is_bodega = VALUES(is_bodega), is_active = VALUES(is_active);

-- NOTA: no creamos usuarios con contraseña por seguridad.
-- Para crear un admin, puedes ejecutar manualmente (ejemplo):
-- INSERT INTO users (name,email,password,role_id,branch_id,created_at) VALUES
-- ('Admin Demo','admin@example.com','[HASH_PHP_PASSWORD]', (SELECT id FROM roles WHERE slug='admin_full'), (SELECT id FROM branches WHERE code='SUC-CTR'), NOW());
-- Reemplaza [HASH_PHP_PASSWORD] por password_hash('tuPass', PASSWORD_BCRYPT) desde PHP o desde tu sistema.

-- FIN DEL DUMP
