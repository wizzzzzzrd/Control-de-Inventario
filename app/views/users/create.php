<?php
// app/views/users/create.php
require_once __DIR__ . '/../../bootstrap.php';
require_login();
require_role(['owner', 'admin_full']);

$flash = get_flash();

// Asegurar variables por si el controller no las definió (evita warnings)
if (!isset($roles) || !is_array($roles)) $roles = [];
if (!isset($branches) || !is_array($branches)) $branches = [];

// Bufferizamos la vista
ob_start();
?>


<div class="card card-compact">
  <div class="card-body">
    <h4>Crear usuario</h4>
    <?php if ($flash): ?><div class="alert alert-info"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
    <form method="post" action="?url=user/store">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Nombre</label>
          <input name="name" class="form-control" required>
        </div>

        <div class="col-md-6">
          <label class="form-label">Email</label>
          <input name="email" type="email" class="form-control" required>
        </div>

        <div class="col-md-6">
          <label class="form-label">Contraseña</label>
          <input name="password" type="password" class="form-control" required>
        </div>

        <div class="col-md-6">
          <label class="form-label">Rol</label>
          <select name="role_id" class="form-select" required>
            <?php if (empty($roles)): ?>
              <option value="">No hay roles</option>
            <?php else: ?>
              <?php foreach ($roles as $r): ?>
                <option value="<?= intval($r['id']) ?>"><?= htmlspecialchars($r['name']) ?></option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label">Sucursal (opcional)</label>
          <select name="branch_id" class="form-select">
            <option value="">-- Sin asignar --</option>
            <?php foreach ($branches as $b): ?>
              <option value="<?= intval($b['id']) ?>"><?= htmlspecialchars($b['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="mt-3">
        <button class="btn btn-primary">Crear usuario</button>
        <a href="<?= BASE_URL ?>/?url=user/index" class="btn btn-outline-secondary ms-2">Cancelar</a>
      </div>
    </form>
  </div>
</div>

<?php
$viewHtml = ob_get_clean();

// Si ya existe $content (controller maneja layout) solo imprimimos el fragmento.
// Si no existe, inyectamos en el layout principal (ruta correcta).
if (isset($content)) {
    echo $viewHtml;
} else {
    $content = $viewHtml;
    require_once __DIR__ . '/../layouts/main.php';
}
