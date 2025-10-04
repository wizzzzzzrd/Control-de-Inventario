<?php
// app/views/products/alerts.php
require_once __DIR__ . '/../../bootstrap.php';
require_login();
require_role(['owner', 'admin_full', 'almacenista', 'admin_view' ]);

$flash = get_flash();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h2 class="h4 mb-0">Alertas de Stock (Administrador)</h2>
    <div class="text-muted small">Productos por sucursal que están en stock recomendado o crítico</div>
  </div>
</div>

<?php if ($flash): ?>
  <div class="alert alert-info"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div class="mb-3 d-flex align-items-center gap-2">
  <span class="badge bg-danger">Crítico</span>
  <small class="text-muted">Nivel por debajo o igual al umbral crítico (acción urgente)</small>
  <span class="ms-3 badge bg-warning text-dark">Recomendado</span>
  <small class="text-muted">Nivel por debajo o igual al stock recomendado (reposición recomendada)</small>
</div>

<?php if (empty($branches)): ?>
  <div class="alert alert-info">No hay sucursales registradas.</div>
<?php else: ?>

  <?php foreach ($branches as $branchId => $b):
    $info = $b['info'];
    $critical = $b['critical'];
    $recommended = $b['recommended'];
  ?>
    <div class="card mb-4">
      <div class="card-header">
        <strong><?= htmlspecialchars($info['name'] ?? 'Sucursal') ?></strong>
        <?php if (!empty($info['is_bodega'])): ?>
          <span class="badge bg-primary ms-2">Bodega</span>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6">
            <h6 class="mb-2">Críticos <small class="text-muted">(cantidad: <?= count($critical) ?>)</small></h6>
            <?php if (empty($critical)): ?>
              <div class="alert alert-light">No hay productos en estado crítico en esta sucursal.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm table-bordered mb-3">
                  <thead class="table-light">
                    <tr>
                      <th>ID</th>
                      <th>SKU</th>
                      <th>Producto</th>
                      <th>Cantidad (pcs)</th>
                      <th>Umbral crítico</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($critical as $it): ?>
                      <tr class="table-danger">
                        <td><?= intval($it['id']) ?></td>
                        <td><?= htmlspecialchars($it['sku']) ?></td>
                        <td><?= htmlspecialchars($it['name']) ?></td>
                        <td><?= number_format($it['total_pieces'], 3, '.', ',') ?></td>
                        <td><?= intval($it['critical_threshold']) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>

          <div class="col-md-6">
            <h6 class="mb-2">Recomendados <small class="text-muted">(cantidad: <?= count($recommended) ?>)</small></h6>
            <?php if (empty($recommended)): ?>
              <div class="alert alert-light">No hay productos en estado recomendado en esta sucursal.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm table-bordered mb-3">
                  <thead class="table-light">
                    <tr>
                      <th>ID</th>
                      <th>SKU</th>
                      <th>Producto</th>
                      <th>Cantidad (pcs)</th>
                      <th>Recommended</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($recommended as $it): ?>
                      <tr class="table-warning">
                        <td><?= intval($it['id']) ?></td>
                        <td><?= htmlspecialchars($it['sku']) ?></td>
                        <td><?= htmlspecialchars($it['name']) ?></td>
                        <td><?= number_format($it['total_pieces'], 3, '.', ',') ?></td>
                        <td><?= intval($it['recommended_stock']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

<?php endif; ?>
