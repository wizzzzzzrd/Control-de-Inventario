<?php
// config/config.php - AJUSTA ESTO antes de usar
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'inventario');   // crea esta BD e importa sql/dump.sql
define('DB_USER', 'root');
define('DB_PASS', '');                   // contraseña de tu MySQL local
define('DB_CHARSET', 'utf8mb4');

// Rutas de upload (para URL y para filesystem)
define('UPLOADS_DIR', '/uploads/products'); // URL relativa a la raíz web local
define('UPLOADS_PATH', __DIR__ . '/../uploads/products'); // path absoluto en FS
define('BASE_URL', '/Inventario'); // ruta relativa desde la raíz web; sin barra final si tu carpeta es "Inventario"
