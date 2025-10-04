<?php
// app/views/branches/history.php
require_once __DIR__ . '/../../bootstrap.php';
require_login();
require_role(['owner', 'admin_full', 'vendor']);

$branch = $branch ?? null;
$orders = $orders ?? [];
$branch_id = $branch_id ?? ($branch['id'] ?? 0);

// Si no vino $branch intentar cargar nombre rápido si tenemos branch_id
if (!$branch && $branch_id) {
    $db = getConexion();
    $stmt = $db->prepare("SELECT id, name, code, address FROM branches WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $branch_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $branch = $res->fetch_assoc();
        $stmt->close();
    }
}

if (!$branch) {
    set_flash('Sucursal no encontrada');
    header('Location: ?url=branch/index'); exit;
}

// determinar rol desde sesión para mostrar/ocultar botones
$role_slug = $_SESSION['role_slug'] ?? '';
$showBackToBranches = in_array($role_slug, ['owner', 'admin_full', 'admin_view'], true);
$showOpenPOS = in_array($role_slug, ['vendor', 'owner', 'admin_full', 'admin_view'], true);
?>


<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h2 class="h4 mb-0">Historial de ventas - <?= htmlspecialchars($branch['name'] ?? 'Sucursal') ?></h2>
    <div class="text-muted small">
      Sucursal: <?= htmlspecialchars($branch['code'] ?? '-') ?> — Dirección: <?= htmlspecialchars($branch['address'] ?? '-') ?>
    </div>
  </div>

  <div class="d-flex align-items-center">
    <?php if ($showBackToBranches): ?>
      <a href="?url=branch/index" class="btn btn-outline-secondary me-2">Volver a sucursales</a>
    <?php endif; ?>

    <?php if ($showOpenPOS): ?>
      <a href="?url=branch/sales&id=<?= intval($branch['id']) ?>" class="btn btn-primary me-2">Abrir POS</a>
    <?php endif; ?>

    <a href="?url=branch/history_export&id=<?= intval($branch['id']) ?>" class="btn btn-outline-secondary">Exportar servidor (CSV)</a>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form id="filterForm" class="row g-2 align-items-end mb-3" method="get" action="">
      <input type="hidden" name="url" value="branch/history">
      <input type="hidden" name="id" value="<?= intval($branch['id']) ?>">
      <div class="col-auto">
        <label class="form-label small mb-0">Desde</label>
        <input type="date" name="from" class="form-control form-control-sm" value="<?= htmlspecialchars($_GET['from'] ?? '') ?>">
      </div>
      <div class="col-auto">
        <label class="form-label small mb-0">Hasta</label>
        <input type="date" name="to" class="form-control form-control-sm" value="<?= htmlspecialchars($_GET['to'] ?? '') ?>">
      </div>
      <div class="col-auto">
        <label class="form-label small mb-0">Vendedor</label>
        <input type="text" name="user" class="form-control form-control-sm" placeholder="Nombre vendedor..." value="<?= htmlspecialchars($_GET['user'] ?? '') ?>">
      </div>
      <div class="col-auto">
        <button id="filterBtn" class="btn btn-sm btn-outline-primary">Filtrar</button>
      </div>
      <div class="col-auto ms-auto">
        <button id="exportBtn" type="button" class="btn btn-sm btn-outline-secondary">Exportar CSV (cliente)</button>
      </div>
    </form>

    <?php if (empty($orders)): ?>
      <div class="alert alert-info mb-0">No hay ventas registradas para esta sucursal en el periodo seleccionado.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle" id="ordersTable">
          <thead class="table-light">
            <tr>
              <th style="width:64px">ID</th>
              <th>Orden</th>
              <th>Fecha</th>
              <th>Vendedor</th>
              <th class="text-end">Total</th>
              <th>Estado</th>
              <th style="width:120px">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($orders as $o): ?>
              <tr data-order-id="<?= intval($o['id']) ?>">
                <td><?= intval($o['id']) ?></td>
                <td><?= htmlspecialchars($o['order_number'] ?? '-') ?></td>
                <td><?= htmlspecialchars($o['created_at'] ?? '-') ?></td>
                <td><?= htmlspecialchars($o['user_name'] ?? '-') ?></td>
                <td class="text-end">$<?= number_format((float)($o['total_amount'] ?? 0), 2) ?></td>
                <td><?= htmlspecialchars($o['status'] ?? '-') ?></td>
                <td>
                  <a href="?url=order/view&id=<?= intval($o['id']) ?>" class="btn btn-sm btn-outline-primary">Ver</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<!-- Modal global para ver detalle de orden (pegar en sales.php / history.php) -->
<div class="modal fade" id="orderDetailModal" tabindex="-1" aria-labelledby="orderDetailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="orderDetailModalLabel">Detalle de venta</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body" id="orderDetailBody">
        <div class="text-center text-muted">Cargando...</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  // Delegación: intercepta clicks en enlaces/botones con data-order-id o class .js-order-view
  document.addEventListener('click', function(e){
    const el = e.target.closest('[data-order-id], .js-order-view');
    if (!el) return;
    e.preventDefault();

    // Obtener id: data-order-id o parsear href ?url=order/view&id=NN
    let orderId = el.getAttribute('data-order-id') || null;
    if (!orderId) {
      const href = el.getAttribute('href') || '';
      const m = href.match(/[?&]id=(\d+)/);
      if (m) orderId = m[1];
    }
    if (!orderId) return;

    const modalEl = document.getElementById('orderDetailModal');
    const modalBody = document.getElementById('orderDetailBody');
    if (!modalEl || !modalBody) return;

    modalBody.innerHTML = '<div class="text-center text-muted">Cargando...</div>';
    const bsModal = new bootstrap.Modal(modalEl);
    bsModal.show();

    fetch('?url=order/view_json&id=' + encodeURIComponent(orderId), { credentials: 'same-origin' })
      .then(resp => resp.json())
      .then(json => {
        if (!json || !json.success) {
          modalBody.innerHTML = '<div class="alert alert-danger">Error al cargar la orden: ' + (json && json.error ? json.error : 'Error desconocido') + '</div>';
          return;
        }
        const order = json.order || {};
        const items = json.items || [];

        let html = '';
        html += '<div class="mb-3">';
        html += '<div><strong>Orden:</strong> ' + (order.order_number || '-') + '</div>';
        html += '<div class="text-muted small">Sucursal: ' + (order.branch_name || '-') + ' — Vendedor: ' + (order.user_name || '-') + ' — Fecha: ' + (order.created_at || '-') + '</div>';
        html += '</div>';

        if (items.length === 0) {
          html += '<div class="alert alert-info">No hay items en esta orden.</div>';
        } else {
          html += '<div class="table-responsive"><table class="table table-sm">';
          html += '<thead class="table-light"><tr><th>#</th><th>SKU</th><th>Producto</th><th class="text-end">Cantidad</th><th class="text-end">P.U.</th><th class="text-end">Subtotal</th></tr></thead><tbody>';
          items.forEach((it, idx) => {
            html += '<tr>';
            html += '<td>' + (idx+1) + '</td>';
            html += '<td>' + (it.sku || '-') + '</td>';
            html += '<td>' + (it.product_name || '-') + '</td>';
            html += '<td class="text-end">' + Number(it.qty || 0).toLocaleString() + '</td>';
            html += '<td class="text-end">$' + (Number(it.price_unit || 0).toFixed(2)) + '</td>';
            html += '<td class="text-end">$' + (Number(it.subtotal || 0).toFixed(2)) + '</td>';
            html += '</tr>';
          });
          html += '</tbody></table></div>';
          html += '<div class="d-flex justify-content-end"><div style="min-width:220px;"><div class="d-flex justify-content-between"><div class="fw-bold">Total</div><div class="fw-bold">$' + (Number(order.total_amount || 0).toFixed(2)) + '</div></div></div></div>';
        }

        modalBody.innerHTML = html;
      })
      .catch(err => {
        console.error('order view fetch err', err);
        modalBody.innerHTML = '<div class="alert alert-danger">Error al cargar la orden. Revisa la consola (dev).</div>';
      });
  });
})();
</script>

<script>
(function(){
  // Exportar tabla a CSV (cliente)
  function tableToCSV(selector) {
    const rows = Array.from(document.querySelectorAll(selector + ' tbody tr'));
    if (!rows.length) return null;
    const csv = [];
    csv.push(['ID','Orden','Fecha','Vendedor','Total','Estado']);
    rows.forEach(tr => {
      const cols = tr.querySelectorAll('td');
      if (cols.length < 6) return;
      csv.push([
        cols[0].innerText.trim(),
        cols[1].innerText.trim(),
        cols[2].innerText.trim(),
        cols[3].innerText.trim(),
        cols[4].innerText.trim().replace('$',''),
        cols[5].innerText.trim()
      ]);
    });
    return csv.map(r => r.map(v => `"${v.replace(/"/g,'""')}"`).join(',')).join('\n');
  }

  document.getElementById('exportBtn').addEventListener('click', function(){
    const csv = tableToCSV('#ordersTable');
    if (!csv) { alert('No hay registros que exportar'); return; }
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'ventas_sucursal_<?= intval($branch['id']) ?>.csv';
    document.body.appendChild(link);
    link.click();
    link.remove();
  });
})();
</script>
