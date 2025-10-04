<?php
// app/views/stockmovements/index.php
require_once __DIR__ . '/../../bootstrap.php';
require_login();
require_role(['owner', 'admin_full', 'admin_view', 'almacenista']);

// Variables esperadas: $movements (array)
// Si el controlador no pasó $date, usamos GET o hoy.
if (!isset($movements)) $movements = [];
$date = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']) ? $_GET['date'] : date('Y-m-d');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h2 class="h4 mb-0">Movimiewntos de Stock</h2>
    <div class="text-muted small">Lista de movimientos seleccionables por día</div>
  </div>
  <form class="d-flex" method="get" action="">
    <input type="hidden" name="url" value="stockmovements/index">
    <div class="input-group">
      <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date) ?>">
      <button class="btn btn-primary" type="submit">Aplicar</button>
    </div>
  </form>
</div>

<div class="card">
  <div class="card-body p-3">
    <?php if (empty($movements)): ?>
      <div class="alert alert-info mb-0">No hay movimientos para la fecha indicada (<?= htmlspecialchars($date) ?>).</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover table-striped align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Fecha / Hora</th>
              <th>Producto</th>
              <th>Tipo</th>
              <th class="text-end">Qty (consumo)</th>
              <th class="text-end">Qty (compra)</th>
              <th>Desde</th>
              <th>Hacia</th>
              <th>Usuario</th>
              <th>Nota</th>
              <th style="width:80px"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($movements as $m):
              $id = htmlspecialchars($m['id'] ?? '');
              $created_at = $m['created_at'] ?? ($m['date'] ?? '');
              $created_fmt = $created_at ? (date('Y-m-d H:i', strtotime($created_at)) ?: $created_at) : '';
              $product_name = htmlspecialchars($m['product_name'] ?? ($m['product_id'] ?? '-'));
              $movement_type = htmlspecialchars($m['movement_type'] ?? ($m['type'] ?? ''));
              $qty_consumption = isset($m['qty_consumption']) ? number_format((float)$m['qty_consumption'], 3, '.', ',') : '';
              $qty_purchase = isset($m['qty_purchase']) ? number_format((float)$m['qty_purchase'], 3, '.', ',') : '';
              $from_branch = htmlspecialchars($m['from_branch_name'] ?? ($m['from_branch'] ?? ($m['from_branch_id'] ?? '')));
              $to_branch = htmlspecialchars($m['to_branch_name'] ?? ($m['to_branch'] ?? ($m['to_branch_id'] ?? '')));
              // preferimos campos con nombre amigable si el controlador los otorgó
              $branch_name = htmlspecialchars($m['branch_name'] ?? '');
              $user_name = htmlspecialchars($m['user_name'] ?? $m['user_name'] ?? $m['user'] ?? '');
              $note = htmlspecialchars($m['note'] ?? '');
            ?>
              <tr>
                <td><?= $id ?></td>
                <td><?= $created_fmt ?></td>
                <td><?= $product_name ?></td>
                <td><?= $movement_type ?></td>
                <td class="text-end"><?= $qty_consumption !== '' ? $qty_consumption . ' pcs' : '-' ?></td>
                <td class="text-end"><?= $qty_purchase !== '' ? $qty_purchase : '-' ?></td>
                <td><?= $from_branch ?: $branch_name ?: '-' ?></td>
                <td><?= $to_branch ?: '-' ?></td>
                <td><?= $user_name ?: '-' ?></td>
                <td><?= $note ?: '-' ?></td>
                <td class="text-center">
                  <button class="btn btn-sm btn-outline-secondary btn-view-movement" data-id="<?= $id ?>">Ver</button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Modal detalle movimiento -->
<div class="modal fade" id="movementDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detalle movimiento</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body" id="movementDetailBody"></div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script>
  (function() {
    function esc(s) {
      return String(s === null || s === undefined ? '' : s);
    }

    document.addEventListener('click', function(evt) {
      var btn = evt.target.closest && evt.target.closest('.btn-view-movement');
      if (!btn) return;
      evt.preventDefault();
      var id = btn.getAttribute('data-id');
      if (!id) return;

      fetch('?url=stockmovements/get&id=' + encodeURIComponent(id), {
          credentials: 'same-origin'
        })
        .then(function(res) {
          return res.json();
        })
        .then(function(js) {
          if (js && js.error) {
            alert('Error: ' + js.error);
            return;
          }
          var html = '<dl class="row">';
          html += '<dt class="col-sm-4">ID</dt><dd class="col-sm-8">' + esc(js.id || '') + '</dd>';
          html += '<dt class="col-sm-4">Fecha</dt><dd class="col-sm-8">' + esc(js.created_at || js.date || '') + '</dd>';
          html += '<dt class="col-sm-4">Producto</dt><dd class="col-sm-8">' + esc(js.product_name || js.product_id || '') + '</dd>';
          html += '<dt class="col-sm-4">Tipo</dt><dd class="col-sm-8">' + esc(js.movement_type || js.type || '') + '</dd>';
          html += '<dt class="col-sm-4">Qty (consumo)</dt><dd class="col-sm-8">' + (js.qty_consumption ? Number(js.qty_consumption).toLocaleString() + ' pcs' : '-') + '</dd>';
          html += '<dt class="col-sm-4">Qty (compra)</dt><dd class="col-sm-8">' + (js.qty_purchase ? Number(js.qty_purchase).toLocaleString() : '-') + '</dd>';
          html += '<dt class="col-sm-4">Desde</dt><dd class="col-sm-8">' + esc(js.from_branch_name || js.branch_name || js.from_branch_id || '') + '</dd>';
          html += '<dt class="col-sm-4">Hacia</dt><dd class="col-sm-8">' + esc(js.to_branch_name || js.to_branch_id || '') + '</dd>';
          html += '<dt class="col-sm-4">Usuario</dt><dd class="col-sm-8">' + esc(js.user_name || js.user_id || '') + '</dd>';
          html += '<dt class="col-sm-4">Nota</dt><dd class="col-sm-8">' + esc(js.note || '') + '</dd>';
          html += '</dl>';

          var body = document.getElementById('movementDetailBody');
          if (body) body.innerHTML = html;

          var modalEl = document.getElementById('movementDetailModal');
          var bs = bootstrap.Modal.getInstance(modalEl);
          if (!bs) bs = new bootstrap.Modal(modalEl);
          bs.show();
        })
        .catch(function(err) {
          console.error(err);
          alert('No se pudo obtener detalle del movimiento.');
        });
    }, true);
  })();
</script>