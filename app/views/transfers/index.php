<?php
// app/views/transfers/index.php
require_once __DIR__ . '/../../bootstrap.php';
require_login();
require_role(['owner', 'admin_full', 'admin_view', 'almacenista']);

// Variables desde el controlador: $transfers (array), $date (YYYY-MM-DD)
if (!isset($transfers)) $transfers = [];
if (!isset($date)) $date = date('Y-m-d');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="h4 mb-0">Traspasos</h2>
        <div class="text-muted small">Lista de traspasos seleccionables por día</div>
    </div>
    <form class="d-flex" method="get" action="">
        <input type="hidden" name="url" value="transfer/index">
        <div class="input-group">
            <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date) ?>">
            <button class="btn btn-primary" type="submit">Aplicar</button>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-body p-3">
        <?php if (empty($transfers)): ?>
            <div class="alert alert-info mb-0">No hay traspasos para la fecha indicada (<?= htmlspecialchars($date) ?>).</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Producto</th>
                            <th class="text-end">Qty (consumo)</th>
                            <th class="text-end">Qty (compra)</th>
                            <th>Desde</th>
                            <th>Hacia</th>
                            <th>Usuario</th>
                            <th>Estado</th>
                            <th style="width:80px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transfers as $t):
                            $id = htmlspecialchars($t['id'] ?? '');
                            $created_at = $t['created_at'] ?? '';
                            $created_fmt = $created_at ? (date('Y-m-d H:i', strtotime($created_at)) ?: $created_at) : '';
                            $product_name = htmlspecialchars($t['product_name'] ?? ($t['product_id'] ?? '-'));
                            $qty_consumption = isset($t['qty_consumption']) ? number_format((float)$t['qty_consumption'], 3, '.', ',') : '';
                            $qty_purchase = isset($t['qty_purchase']) ? number_format((float)$t['qty_purchase'], 3, '.', ',') : '';
                            $from_branch = htmlspecialchars($t['from_branch_name'] ?? '');
                            $to_branch = htmlspecialchars($t['to_branch_name'] ?? '');
                            $user_name = htmlspecialchars($t['created_by'] ?? $t['user_name'] ?? '');
                            $status = htmlspecialchars($t['status'] ?? '');
                        ?>
                            <tr>
                                <td><?= $id ?></td>
                                <td><?= $created_fmt ?></td>
                                <td><?= $product_name ?></td>
                                <td class="text-end"><?= $qty_consumption !== '' ? $qty_consumption . ' pcs' : '-' ?></td>
                                <td class="text-end"><?= $qty_purchase !== '' ? $qty_purchase : '-' ?></td>
                                <td><?= $from_branch ?: '-' ?></td>
                                <td><?= $to_branch ?: '-' ?></td>
                                <td><?= $user_name ?: '-' ?></td>
                                <td><?= $status ?: '-' ?></td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-secondary btn-view-transfer" data-id="<?= $id ?>">Ver</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal detalle traspaso -->
<div class="modal fade" id="transferDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle traspaso</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="transferDetailBody"></div>
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
            var btn = evt.target.closest && evt.target.closest('.btn-view-transfer');
            if (!btn) return;
            evt.preventDefault();
            var id = btn.getAttribute('data-id');
            if (!id) return;

            fetch('?url=transfer/get&id=' + encodeURIComponent(id), {
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
                    html += '<dt class="col-sm-4">Fecha</dt><dd class="col-sm-8">' + esc(js.created_at || '') + '</dd>';
                    html += '<dt class="col-sm-4">Producto</dt><dd class="col-sm-8">' + esc(js.product_name || js.product_id || '') + '</dd>';
                    html += '<dt class="col-sm-4">SKU</dt><dd class="col-sm-8">' + esc(js.product_sku || '') + '</dd>';
                    html += '<dt class="col-sm-4">Qty (consumo)</dt><dd class="col-sm-8">' + (js.qty_consumption ? Number(js.qty_consumption).toLocaleString() + ' pcs' : '-') + '</dd>';
                    html += '<dt class="col-sm-4">Qty (compra)</dt><dd class="col-sm-8">' + (js.qty_purchase ? Number(js.qty_purchase).toLocaleString() : '-') + '</dd>';
                    html += '<dt class="col-sm-4">Desde</dt><dd class="col-sm-8">' + esc(js.from_branch_name || js.from_branch_id || '') + '</dd>';
                    html += '<dt class="col-sm-4">Hacia</dt><dd class="col-sm-8">' + esc(js.to_branch_name || js.to_branch_id || '') + '</dd>';
                    html += '<dt class="col-sm-4">Usuario</dt><dd class="col-sm-8">' + esc(js.created_by || js.user_name || '') + '</dd>';
                    html += '<dt class="col-sm-4">Estado</dt><dd class="col-sm-8">' + esc(js.status || '') + '</dd>';
                    html += '</dl>';

                    var body = document.getElementById('transferDetailBody');
                    if (body) body.innerHTML = html;
                    var modalEl = document.getElementById('transferDetailModal');
                    var bs = bootstrap.Modal.getInstance(modalEl);
                    if (!bs) bs = new bootstrap.Modal(modalEl);
                    bs.show();
                })
                .catch(function(err) {
                    console.error(err);
                    alert('No se pudo obtener detalle del traspaso.');
                });
        }, true);
    })();
</script>