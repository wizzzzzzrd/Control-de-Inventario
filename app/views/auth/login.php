<?php
require_once __DIR__ . '/../../bootstrap.php';
$flash = get_flash();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <title>Iniciar sesión</title>
  <style> body { background: linear-gradient(120deg,#0d6efd 0%, #6610f2 100%); min-height:100vh; color:#fff; } .card{border-radius:1rem;} </style>
</head>
<body>
  <div class="container d-flex align-items-center justify-content-center" style="min-height:100vh">
    <div class="col-12 col-md-6 col-lg-4">
      <div class="card shadow-lg">
        <div class="card-body p-4">
          <h3 class="card-title text-center mb-3">Acceso</h3>
          <?php if ($flash): ?><div class="alert alert-info"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
          <form method="post" action="?url=auth/dologin">
            <div class="mb-3"><label class="form-label">Correo</label><input type="email" name="email" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Contraseña</label><input type="password" name="password" class="form-control" required></div>
            <div class="d-grid gap-2"><button class="btn btn-primary" type="submit">Entrar</button></div>
          </form>
        </div>
      </div>
      <p class="text-center text-white mt-3 small">Sistema de Inventario</p>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
