<?php
// app/views/users/index.php
// Vista: listado de usuarios
require_once __DIR__ . '/../../bootstrap.php';
require_login();
require_role(['owner', 'admin_full']);

$flash = get_flash();

// Generamos el cuerpo de la vista en un buffer para poder inyectarlo
// en el layout si éste no fue provisto por el controller.
ob_start();
?>


<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h2 class="h4 mb-0">Usuarios</h2>
    <div class="text-muted small">Gestiona cuentas y roles</div>
  </div>
  <div>
    <!-- Abrir modal o página de creación (tu controller user/create debe existir) -->
    <a href="<?= BASE_URL ?>/?url=user/create" class="btn btn-primary">+ Nuevo usuario</a>
  </div>
</div>

<?php if ($flash): ?>
  <div class="alert alert-info"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div class="card card-compact">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Email</th>
            <th>Rol</th>
            <th>Sucursal</th>
            <th>Activo</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($users)): ?>
            <tr>
              <td colspan="6" class="text-center text-muted py-4">No hay usuarios registrados.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($users as $u): ?>
              <tr>
                <td><?= intval($u['id']) ?></td>
                <td><?= htmlspecialchars($u['name']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><?= htmlspecialchars($u['role_name'] ?? '') ?></td>
                <td><?= htmlspecialchars($u['branch_name'] ?? '-') ?></td>
                <td><?= $u['is_active'] ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-secondary">No</span>' ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
// Fin del body de la vista
$viewHtml = ob_get_clean();

// Si el controller ya declaró $content (p. ej. al usar un sistema que inyecta la vista en el layout),
// simplemente imprimimos el fragmento para no duplicar el layout.
// Si no existe $content, inyectamos la vista en el layout principal.
if (isset($content)) {
    // Estamos dentro de un layout -> imprimir solo el fragmento
    echo $viewHtml;
} else {
    // No hay layout activo: inyectamos en layouts/main.php (usa la variable $content)
    $content = $viewHtml;
    // <-- ruta CORRECTA al layout desde app/views/users/
    require_once __DIR__ . '/../layouts/main.php';
}
