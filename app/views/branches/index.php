<?php 
// app/views/branches/index.php
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../models/conexion.php';
require_login();
require_role(['owner', 'admin_full']);

$flash = get_flash();
$branches = $branches ?? [];
// desde el controller pasamos current_user_branch_id e is_admin si estaban disponibles
$current_user_branch_id = $current_user_branch_id ?? null;
$is_admin = $is_admin ?? false;
?>
<?php if ($flash): ?>
  <div class="alert alert-info"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h2 class="h4 mb-0">Sucursales</h2>
    <div class="text-muted small">Lista de sucursales y bodega central</div>
  </div>

  <!-- Botón abre modal (no redirige) -->
  <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createBranchModal">+ Nueva sucursal</button>
</div>

<div class="card card-compact">
  <div class="card-body">
    <?php if (empty($branches)): ?>
      <div class="alert alert-info">No hay sucursales registradas.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead>
            <tr>
              <th>ID</th>
              <th>Nombre</th>
              <th>Código</th>
              <th>Dirección</th>
              <th>Teléfono</th>
              <th>Bodega?</th>
              <th style="width:140px">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($branches as $b): ?>
            <tr>
              <td><?=htmlspecialchars($b['id'])?></td>
              <td>
                <!-- Nombre como link para abrir directamente la vista de ventas -->
                <a href="?url=branch/sales&id=<?= intval($b['id']) ?>" class="text-decoration-none">
                  <?=htmlspecialchars($b['name'])?>
                </a>
              </td>
              <td><?=htmlspecialchars($b['code'])?></td>
              <td><?=htmlspecialchars($b['address'])?></td>
              <td><?=htmlspecialchars($b['phone'] ?? '-')?></td>
              <td>
                <?= $b['is_bodega'] ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-secondary">No</span>' ?>
              </td>
              <td class="text-end">
                <!-- Botón para abrir el dashboard/venta de la sucursal -->
                <a href="?url=branch/sales&id=<?= intval($b['id']) ?>" class="btn btn-sm btn-primary me-2">Abrir ventas</a>

                <!-- En un futuro: mostrar botón para asignar vendedor / administrar si is_admin -->
                <?php if ($is_admin): ?>
                  <a href="?url=branch/manage&id=<?= intval($b['id']) ?>" class="btn btn-sm btn-outline-secondary">Administrar</a>
                <?php else: ?>
                  <!-- si el usuario está asignado a esta sucursal podemos mostrar etiqueta -->
                  <?php if ($current_user_branch_id !== null && intval($current_user_branch_id) === intval($b['id'])): ?>
                    <span class="badge bg-info text-dark">Tu sucursal</span>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Modal Crear Sucursal (dejado aquí para compatibilidad; ya estaba en tu archivo original) -->
<div class="modal fade" id="createBranchModal" tabindex="-1" aria-labelledby="createBranchModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" id="createBranchForm" method="post" action="?url=branch/store">
      <div class="modal-header">
        <h5 class="modal-title" id="createBranchModalLabel">Crear nueva sucursal</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label">Nombre</label>
            <input name="name" class="form-control" required>
          </div>

          <div class="col-md-6">
            <label class="form-label">Código</label>
            <input name="code" class="form-control">
          </div>

          <div class="col-md-6">
            <label class="form-label">Teléfono</label>
            <input name="phone" class="form-control">
          </div>

          <div class="col-12">
            <label class="form-label">Dirección</label>
            <input name="address" class="form-control">
          </div>

          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" value="1" id="isBodega" name="is_bodega">
              <label class="form-check-label" for="isBodega">Marcar como <strong>Bodega central</strong></label>
            </div>
            <div class="form-text">Solo marque esto si esta sucursal será la bodega principal.</div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Crear sucursal</button>
      </div>
    </form>
  </div>
</div>
