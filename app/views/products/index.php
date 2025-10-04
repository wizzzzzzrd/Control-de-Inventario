<?php
// app/views/products/index.php
// Asegúrate de que $products esté definido por el controller antes de renderizar esta vista
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../models/conexion.php';
require_login(); // protege la vista
require_role(['owner', 'admin_full', 'almacenista']);

$flash = get_flash();
?>
<?php if ($flash): ?>
  <div class="alert alert-info"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h2 class="h4 mb-0">Productos</h2>
    <div class="text-muted small">Lista de productos y stock por sucursal</div>
  </div>
  <div>
    <!-- BOTÓN ABRE MODAL (no redirige) -->
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createProductModal">+ Nuevo producto</button>
  </div>
</div>

<!-- Leyenda colores -->
<div class="mb-3">
  <span class="badge bg-danger">Crítico</span>
  <small class="text-muted ms-2">Nivel por debajo o igual al umbral crítico (acción urgente)</small>
  &nbsp;&nbsp;
  <span class="badge bg-warning text-dark">Recomendado</span>
  <small class="text-muted ms-2">Nivel por debajo o igual al stock recomendado (reposición recomendada)</small>
</div>

<div class="card shadow-sm">
  <div class="card-body p-3">
    <?php if (empty($products)): ?>
      <div class="alert alert-info mb-0">No hay productos. Crea uno con el botón <strong>Nuevo producto</strong>.</div>
    <?php else: ?>
      <div class="table-responsive">
        <!-- Marcamos la tabla como lista de productos para que el JS la detecte -->
        <table class="table table-hover table-striped align-middle mb-0" data-products-list="1">
          <thead class="table-light">
            <tr>
              <th style="width:56px">ID</th>
              <th>SKU</th>
              <th>Nombre</th>
              <th style="width:90px">Factor</th>
              <th style="width:140px">Precio (consumo)</th>
              <th style="width:120px">Stock total</th>
              <th>Stock por sucursal</th>
              <th style="width:64px"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($products as $p):
              // asegurar índices ausentes
              $pid = intval($p['id'] ?? 0);
              $psku = $p['sku'] ?? '';
              $pbarcode = $p['barcode'] ?? '';
              $pname = $p['name'] ?? '';
              $pdescription = $p['description'] ?? '';
              $ptype = $p['type'] ?? '';
              $pfactor = $p['factor'] ?? 1;

              // thresholds
              $recommended_threshold = isset($p['recommended_stock']) ? intval($p['recommended_stock']) : 10;
              $critical_threshold = isset($p['critical_threshold']) ? intval($p['critical_threshold']) : 5;

              // Recuperar precio consumo desde el array $p (si viene)
              $pprice_raw = null;
              $possible_price_keys = ['default_price_consumption', 'sale_price', 'price_sale', 'price', 'default_price'];
              foreach ($possible_price_keys as $k) {
                if (array_key_exists($k, $p) && $p[$k] !== null && $p[$k] !== '') {
                  $pprice_raw = $p[$k];
                  break;
                }
              }

              // Si no vino o es 0, intentar recuperar desde la BD (tabla products)
              if ($pprice_raw === null || (float)$pprice_raw === 0.0) {
                $db = getConexion();
                if ($db) {
                  $stmt = $db->prepare("SELECT COALESCE(default_price_consumption, 0) AS default_price_consumption FROM products WHERE id = ? LIMIT 1");
                  if ($stmt) {
                    $stmt->bind_param('i', $pid);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($res && ($row = $res->fetch_assoc())) {
                      $pprice_raw = $row['default_price_consumption'];
                    }
                    $stmt->close();
                  }
                }
              }

              if ($pprice_raw === null) $pprice_raw = 0.00;
              $ppricec = number_format((float)$pprice_raw, 2);

              // photo path (si existe)
              $ppath = $p['photo_path'] ?? '';

              // stocks JSON (para mostrar en modal sin fetch)
              $stocks_json = htmlspecialchars(json_encode($p['stocks'] ?? []), ENT_QUOTES, 'UTF-8');

              // totales
              $total_pieces = isset($p['total_pieces']) ? (float)$p['total_pieces'] : 0.0;
              $estimated_boxes = isset($p['estimated_boxes']) ? intval($p['estimated_boxes']) : 0;

              // created/updated/created_by si están en el array producto
              $p_created_by = $p['created_by'] ?? '';
              $p_created_at = $p['created_at'] ?? '';
              $p_updated_at = $p['updated_at'] ?? '';
            ?>
              <tr
                data-product-id="<?= $pid ?>"
                data-sku="<?= htmlspecialchars($psku) ?>"
                data-barcode="<?= htmlspecialchars($pbarcode) ?>"
                data-name="<?= htmlspecialchars($pname) ?>"
                data-description="<?= htmlspecialchars($pdescription) ?>"
                data-type="<?= htmlspecialchars($ptype) ?>"
                data-category="<?= htmlspecialchars($p['category'] ?? '') ?>"
                data-default-price-purchase="<?= htmlspecialchars((string)($p['default_price_purchase'] ?? '0.00')) ?>"
                data-default-price-consumption="<?= htmlspecialchars((string)$pprice_raw) ?>"
                data-purchase-unit="<?= htmlspecialchars($p['purchase_unit'] ?? '') ?>"
                data-consumption-unit="<?= htmlspecialchars($p['consumption_unit'] ?? '') ?>"
                data-recommended-stock="<?= htmlspecialchars((string)($recommended_threshold ?? '')) ?>"
                data-critical-threshold="<?= htmlspecialchars((string)($critical_threshold ?? '')) ?>"
                data-bodega-critical-boxes="<?= htmlspecialchars((string)($p['bodega_critical_boxes'] ?? '')) ?>"
                data-raw-sku="<?= htmlspecialchars($psku) ?>"
                data-raw-name="<?= htmlspecialchars($pname) ?>"
                data-raw-factor="<?= htmlspecialchars((string)$pfactor) ?>"
                data-photo-path="<?= htmlspecialchars($ppath) ?>"
                data-stocks="<?= $stocks_json ?>"
                data-total-pieces="<?= htmlspecialchars((string)$total_pieces) ?>"
                data-estimated-boxes="<?= htmlspecialchars((string)$estimated_boxes) ?>"
                data-created-by="<?= htmlspecialchars($p_created_by) ?>"
                data-created-at="<?= htmlspecialchars($p_created_at) ?>"
                data-updated-at="<?= htmlspecialchars($p_updated_at) ?>">
                <td class="align-middle"><?= htmlspecialchars($pid) ?></td>
                <td class="align-middle cell-sku"><?= htmlspecialchars($psku) ?></td>
                <td class="align-middle cell-name"><?= htmlspecialchars($pname) ?></td>
                <td class="align-middle cell-factor"><?= htmlspecialchars($pfactor) ?></td>
                <td class="align-middle text-end">$<?= $ppricec ?></td>
                <td class="align-middle">
                  <?= number_format((float)$total_pieces, 3, '.', ',') ?> pcs
                  <br><small class="text-muted"><?= intval($estimated_boxes) ?> cajas (est.)</small>
                </td>
                <td class="align-middle">
                  <?php
                  $parts = [];
                  if (!empty($p['stocks']) && is_array($p['stocks'])) {
                    foreach ($p['stocks'] as $s) {
                      $branchName = htmlspecialchars($s['branch_name'] ?? ('Sucursal ' . ($s['branch_id'] ?? '')));
                      $piezas = (float)($s['piezas'] ?? 0);
                      $cajas = (float)($s['cajas'] ?? 0);
                      $totalBranchPieces = $piezas + ($cajas * $pfactor);

                      // decidir color: crítico si <= critical_threshold; recomendado si <= recommended_threshold
                      $cls = '';
                      $label = '';
                      if ($totalBranchPieces <= $critical_threshold) {
                        $cls = 'bg-danger text-white rounded px-2 py-1';
                        $label = '<span class="badge bg-danger">Crítico</span>';
                      } elseif ($totalBranchPieces <= $recommended_threshold) {
                        $cls = 'bg-warning text-dark rounded px-2 py-1';
                        $label = '<span class="badge bg-warning text-dark">Recomendado</span>';
                      }

                      $display = "<div class=\"mb-1 ".($cls)."\">";
                      $display .= "<strong>{$branchName}</strong>: " . number_format($totalBranchPieces, 3, '.', ',') . " pcs";
                      $display .= " <small class='text-muted'>(" . number_format($cajas, 3, '.', ',') . " cajas, " . number_format($piezas, 3, '.', ',') . " pcs)</small>";
                      if ($label) $display .= " &nbsp; {$label}";
                      // mostrar umbrales (ocultos en tooltip sencillo)
                      $display .= "<br><small class='text-muted'>Umbrales: crítico ≤ " . intval($critical_threshold) . ", recomendado ≤ " . intval($recommended_threshold) . "</small>";
                      $display .= "</div>";

                      $parts[] = $display;
                    }
                    echo implode('', $parts);
                  } else {
                    echo '<small class="text-muted">Sin stock registrado</small>';
                  }
                  ?>
                </td>
                <td class="align-middle text-center">
                  <!-- Uso dropend + data-bs-display="static" para que el menú flote fuera del scroll -->
                  <div class="dropdown dropend product-actions" data-bs-display="static">
                    <button class="btn btn-sm btn-light product-actions-btn" type="button" id="actionsMenu<?= $pid ?>"
                      data-bs-toggle="dropdown" aria-expanded="false" data-product-id="<?= htmlspecialchars($pid) ?>">
                      ⋮
                    </button>

                    <!-- Añado z-index para evitar recortes -->
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="actionsMenu<?= $pid ?>" style="z-index:2200;">
                      <li><a class="dropdown-item btn-view-product" href="#" data-product-id="<?= htmlspecialchars($pid) ?>">Ver</a></li>
                      <li><a class="dropdown-item btn-edit-product" href="#" data-product-id="<?= htmlspecialchars($pid) ?>">Editar</a></li>
                      <li><a class="dropdown-item btn-transfer-row" href="#" data-product-id="<?= htmlspecialchars($pid) ?>">Traspasar</a></li>
                      <li><a class="dropdown-item btn-product-movements" href="#" data-product-id="<?= htmlspecialchars($pid) ?>">Historial</a></li>
                      <li><a class="dropdown-item btn-receive" href="#" data-product-id="<?= htmlspecialchars($pid) ?>">Recepcionar</a></li>
                      <li>
                        <hr class="dropdown-divider">
                      </li>
                      <li><a class="dropdown-item text-danger" href="?url=product/delete&id=<?= $pid ?>" onclick="return confirm('Eliminar producto?')">Eliminar</a></li>
                    </ul>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Modal Crear Producto (completo con campos de BD relevantes) -->
<div class="modal fade" id="createProductModal" tabindex="-1" aria-labelledby="createProductModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" id="createProductForm" method="post" action="?url=product/store" enctype="multipart/form-data">
      <div class="modal-header">
        <h5 class="modal-title" id="createProductModalLabel">Crear producto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">SKU</label>
            <input name="sku" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Código de barras (barcode)</label>
            <input name="barcode" class="form-control">
          </div>
          <div class="col-12">
            <label class="form-label">Nombre</label>
            <input name="name" class="form-control" required>
          </div>

          <div class="col-md-4">
            <label class="form-label">Categoría</label>
            <input name="category" class="form-control" placeholder="Ej: bebidas, abarrotes...">
          </div>

          <div class="col-md-4">
            <label class="form-label">Tipo</label>
            <select name="type" class="form-select">
              <option value="primario" selected>Primario</option>
              <option value="secundario">Secundario</option>
              <option value="directo">Directo</option>
              <option value="indirecto">Indirecto</option>
            </select>
            <div class="form-text">Define la clasificación del producto (impacta recepciones/alertas).</div>
          </div>

          <div class="col-md-4">
            <label class="form-label">Unidad compra</label>
            <select name="purchase_unit" class="form-select">
              <option value="caja">caja</option>
              <option value="paquete">paquete</option>
              <option value="pieza">pieza</option>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label">Unidad consumo</label>
            <select name="consumption_unit" class="form-select">
              <option value="pieza">pieza</option>
              <option value="gr">gramo</option>
              <option value="ml">ml</option>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label">Factor (piezas por caja)</label>
            <input name="factor" class="form-control" value="1" type="number" step="0.001" min="0.001">
          </div>

          <div class="col-md-4">
            <label class="form-label">Precio compra (unidad compra)</label>
            <input name="default_price_purchase" class="form-control" type="number" step="0.01" min="0" value="0.00">
          </div>

          <div class="col-md-4">
            <label class="form-label">Precio venta (unidad consumo)</label>
            <input name="default_price_consumption" class="form-control" type="number" step="0.01" min="0" value="0.00">
          </div>

          <div class="col-md-4">
            <label class="form-label">Recommended stock</label>
            <input name="recommended_stock" class="form-control" type="number" value="10">
          </div>
          <div class="col-md-4">
            <label class="form-label">Critical threshold (sucursales)</label>
            <input name="critical_threshold" class="form-control" type="number" value="5">
          </div>
          <div class="col-md-4">
            <label class="form-label">Bodega critical (cajas)</label>
            <input name="bodega_critical_boxes" class="form-control" type="number" value="4">
          </div>

          <div class="col-12">
            <label class="form-label">Descripción</label>
            <textarea name="description" class="form-control" rows="2"></textarea>
          </div>

          <div class="col-md-6">
            <label class="form-label">Imagen (jpg/png/webp)</label>
            <input name="image" class="form-control" type="file" accept="image/*">
            <div class="form-text">Si subes una imagen podrá verse en la ficha del producto.</div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar producto</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Traspaso -->
<div class="modal fade" id="transferModal" tabindex="-1" aria-labelledby="transferModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" id="transferForm" method="post" action="?url=transfer/store">
      <div class="modal-header">
        <h5 class="modal-title" id="transferModalLabel">Traspaso desde Bodega</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="product_id" id="transferProductId" value="">
        <div class="mb-2">
          <label class="form-label">Sucursal destino</label>
          <select name="to_branch_id" class="form-select" required>
            <?php
            $db = getConexion();
            $stmt = $db->prepare("SELECT id, name FROM branches WHERE is_active = 1 AND is_bodega = 0 ORDER BY name");
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
              echo "<option value=\"{$r['id']}\">" . htmlspecialchars($r['name']) . "</option>";
            }
            $stmt->close();
            ?>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label">Unidad</label>
          <select name="unit" class="form-select" id="transferUnit">
            <option value="pieces">Piezas</option>
            <option value="boxes">Cajas</option>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label">Cantidad</label>
          <input type="number" step="0.001" min="0.001" name="qty" class="form-control" required>
        </div>
        <div class="form-text">Si indicas <strong>cajas</strong> se convertirá automáticamente a piezas al realizar el traspaso (según factor del producto).</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Enviar traspaso</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Recepción en Bodega -->
<div class="modal fade" id="receiveModal" tabindex="-1" aria-labelledby="receiveModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" id="receiveForm" method="post" action="?url=reception/store">
      <div class="modal-header">
        <h5 class="modal-title" id="receiveModalLabel">Recepcionar producto en Bodega</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="product_id" id="receiveProductId" value="">
        <div class="mb-2">
          <label class="form-label">Unidad</label>
          <select name="unit" class="form-select" id="receiveUnit">
            <option value="pieces">Piezas</option>
            <option value="boxes">Cajas</option>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label">Cantidad</label>
          <input type="number" step="0.001" min="0.001" name="qty" id="receiveQty" class="form-control" required>
        </div>
        <div class="form-text">Si seleccionas <strong>Cajas</strong>, se convertirán a piezas usando el factor del producto.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Registrar recepción</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Detalle producto (robusto) -->
<div class="modal fade" id="productDetailModal" tabindex="-1" aria-labelledby="productDetailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="productDetailModalLabel">Producto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body" id="productDetailBody"><!-- dinámico --></div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        <button class="btn btn-success" id="detailTransferBtn" data-product-id="">Traspasar desde bodega</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Movimientos -->
<div class="modal fade" id="productMovementsModal" tabindex="-1" aria-labelledby="productMovementsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="productMovementsModalLabel">Movimientos</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body" id="productMovementsBody"></div>
      <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div>
    </div>
  </div>
</div>

<!-- Modal Editar Producto -->
<div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form class="modal-content" id="editProductForm" method="post" action="?url=product/update" enctype="multipart/form-data">
      <div class="modal-header">
        <h5 class="modal-title" id="editProductModalLabel">Editar producto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="editProductId" value="">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">SKU</label><input name="sku" id="editSku" class="form-control" required></div>
          <div class="col-md-6"><label class="form-label">Barcode</label><input name="barcode" id="editBarcode" class="form-control"></div>
          <div class="col-12"><label class="form-label">Nombre</label><input name="name" id="editName" class="form-control" required></div>

          <div class="col-md-4">
            <label class="form-label">Categoría</label>
            <input name="category" id="editCategory" class="form-control" placeholder="Ej: bebidas">
          </div>

          <!-- NUEVO: Tipo -->
          <div class="col-md-4">
            <label class="form-label">Tipo</label>
            <select name="type" id="editType" class="form-select">
              <option value="primario">Primario</option>
              <option value="secundario">Secundario</option>
              <option value="directo">Directo</option>
              <option value="indirecto">Indirecto</option>
            </select>
          </div>

          <div class="col-md-4"><label class="form-label">Unidad compra</label>
            <select name="purchase_unit" id="editPurchaseUnit" class="form-select">
              <option value="caja">caja</option>
              <option value="paquete">paquete</option>
              <option value="pieza">pieza</option>
            </select>
          </div>
          <div class="col-md-4"><label class="form-label">Unidad consumo</label>
            <select name="consumption_unit" id="editConsumptionUnit" class="form-select">
              <option value="pieza">pieza</option>
              <option value="gr">gramo</option>
              <option value="ml">ml</option>
            </select>
          </div>
          <div class="col-md-4"><label class="form-label">Factor</label><input name="factor" id="editFactor" class="form-control" type="number" step="0.001" min="0.001"></div>

          <div class="col-md-4">
            <label class="form-label">Precio compra (unidad compra)</label>
            <input name="default_price_purchase" id="editPricePurchase" class="form-control" type="number" step="0.01" min="0" value="0.00">
          </div>

          <div class="col-md-4">
            <label class="form-label">Precio venta (unidad consumo)</label>
            <input name="default_price_consumption" id="editPriceConsumption" class="form-control" type="number" step="0.01" min="0" value="0.00">
          </div>

          <div class="col-12"><label class="form-label">Descripción</label><textarea name="description" id="editDescription" class="form-control" rows="2"></textarea></div>
          <div class="col-md-6"><label class="form-label">Imagen (subir para reemplazar)</label><input name="image" id="editImage" class="form-control" type="file" accept="image/*">
            <div id="currentImagePreview" class="mt-2"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar cambios</button>
      </div>
    </form>
  </div>
</div>

<!-- Unificar: abrir el modal "robusto" con datos completos (usa el endpoint getById para traer imagen y resto) -->
<script>
  (function() {
    function esc(s) {
      return String(s === null || s === undefined ? '' : s);
    }

    function normalizeImagePath(path) {
      if (!path) return '';
      path = String(path).trim();
      if (path.indexOf('http://') === 0 || path.indexOf('https://') === 0) return path;
      if (path.charAt(0) === '/') {
        if (window.BASE_URL) return window.BASE_URL.replace(/\/$/, '') + path;
        return path;
      }
      if (window.BASE_URL) return window.BASE_URL.replace(/\/$/, '') + '/' + path;
      return path;
    }

    function formatDateTime(s) {
      if (!s) return '';
      // intentar formateo simple: si viene ISO, mostrar local; si no, devolver tal cual
      try {
        var d = new Date(s);
        if (!isNaN(d.getTime())) {
          return d.toLocaleString();
        }
      } catch (e) {}
      return s;
    }

    function renderProductModalFromObject(p) {
      // Mostrar la mayoría de campos de la tabla products
      var imgPath = normalizeImagePath(p.photo_path || '');

      var html = '<div class="row g-3">';
      // Columna imagen
      html += '<div class="col-md-4 text-center">';
      if (imgPath) {
        html += '<img src="' + esc(imgPath) + '" alt="' + esc(p.name || '') + '" class="img-fluid rounded mb-2" style="max-width:100%;">';
      } else {
        html += '<div class="border rounded p-3 text-center text-muted">Sin imagen</div>';
      }
      html += '</div>';

      // Columna detalles principales
      html += '<div class="col-md-8">';
      html += '<h4 class="mb-1">' + esc(p.name || '-') + '</h4>';
      html += '<div class="text-muted mb-2">SKU: ' + esc(p.sku || '-') + ' &nbsp; | &nbsp; Barcode: ' + esc(p.barcode || '-') + '</div>';
      html += '<p>' + esc(p.description || '-') + '</p>';

      // Grid con campos
      html += '<div class="row">';

      function rowDt(label, value) {
        return '<div class="col-sm-6 mb-2"><small class="text-muted">' + label + '</small><div>' + esc(value || '-') + '</div></div>';
      }

      html += rowDt('Categoría', p.category);
      html += rowDt('Tipo', p.type);
      html += rowDt('Unidad compra', p.purchase_unit);
      html += rowDt('Unidad consumo', p.consumption_unit);
      html += rowDt('Factor (piezas/caja)', p.factor);
      html += rowDt('Recommended stock', p.recommended_stock);
      html += rowDt('Critical threshold (sucursales)', p.critical_threshold);
      html += rowDt('Bodega critical (cajas)', p.bodega_critical_boxes);
      html += rowDt('Precio compra (unidad compra)', (Number(p.default_price_purchase || 0).toFixed(2) ? '$' + Number(p.default_price_purchase || 0).toFixed(2) : '$0.00'));
      html += rowDt('Precio venta (unidad consumo)', (Number(p.default_price_consumption || 0).toFixed(2) ? '$' + Number(p.default_price_consumption || 0).toFixed(2) : '$0.00'));
      html += rowDt('Total piezas (estimadas)', (Number(p.total_pieces || 0).toLocaleString() + ' pcs'));
      html += rowDt('Cajas (estimadas)', (Number(p.estimated_boxes || 0).toLocaleString() + ' cajas'));
      html += rowDt('Creado por', p.created_by);
      html += rowDt('Creado', formatDateTime(p.created_at));
      html += rowDt('Última actualización', formatDateTime(p.updated_at));
      html += '</div>'; // .row

      // Stock por sucursal (si viene)
      if (Array.isArray(p.stocks) && p.stocks.length) {
        html += '<hr><h6 class="mt-3">Stock por sucursal</h6><ul class="list-unstyled">';
        p.stocks.forEach(function(b) {
          html += '<li class="mb-1"><strong>' + esc(b.branch_name || ('Sucursal ' + (b.branch_id || ''))) + ':</strong> ' + (Number(b.piezas || 0)).toLocaleString() + ' pcs <small class="text-muted">(' + (Number(b.cajas || 0)).toLocaleString() + ' cajas)</small></li>';
        });
        html += '</ul>';
      } else {
        html += '<hr><p class="text-muted">Sin stock registrado por sucursal</p>';
      }

      html += '</div>'; // col-md-8
      html += '</div>'; // row

      var body = document.getElementById('productDetailBody');
      if (body) body.innerHTML = html;

      var dtBtn = document.getElementById('detailTransferBtn');
      if (dtBtn) dtBtn.setAttribute('data-product-id', p.id || '');

      var modalEl = document.getElementById('productDetailModal');
      if (modalEl) {
        var bs = bootstrap.Modal.getInstance(modalEl);
        if (!bs) bs = new bootstrap.Modal(modalEl);
        bs.show();
      }
    }

    function productFromTrData(tr) {
      var stocks = [];
      try {
        stocks = JSON.parse(tr.getAttribute('data-stocks') || '[]');
      } catch (e) {
        stocks = [];
      }
      return {
        id: tr.getAttribute('data-product-id') || '',
        sku: tr.getAttribute('data-sku') || '',
        barcode: tr.getAttribute('data-barcode') || '',
        name: tr.getAttribute('data-name') || '',
        description: tr.getAttribute('data-description') || '',
        type: tr.getAttribute('data-type') || '',
        category: tr.getAttribute('data-category') || '',
        default_price_purchase: tr.getAttribute('data-default-price-purchase') || '0.00',
        default_price_consumption: tr.getAttribute('data-default-price-consumption') || '0.00',
        purchase_unit: tr.getAttribute('data-purchase-unit') || '',
        consumption_unit: tr.getAttribute('data-consumption-unit') || '',
        recommended_stock: tr.getAttribute('data-recommended-stock') || '',
        critical_threshold: tr.getAttribute('data-critical-threshold') || '',
        bodega_critical_boxes: tr.getAttribute('data-bodega-critical-boxes') || '',
        factor: tr.getAttribute('data-raw-factor') || 1,
        photo_path: tr.getAttribute('data-photo-path') || '',
        stocks: stocks,
        total_pieces: Number(tr.getAttribute('data-total-pieces') || 0),
        estimated_boxes: Number(tr.getAttribute('data-estimated-boxes') || 0),
        created_by: tr.getAttribute('data-created-by') || '',
        created_at: tr.getAttribute('data-created-at') || '',
        updated_at: tr.getAttribute('data-updated-at') || ''
      };
    }

    // Inserta HTML recibido (texto) en el modal y normaliza imgs relativas añadiendo BASE_URL si hace falta.
    function injectHtmlIntoModalAndShow(htmlText, productIdFallback) {
      var body = document.getElementById('productDetailBody');
      if (!body) return;
      body.innerHTML = htmlText;

      // Normalizar imágenes relativas dentro del modal
      var imgs = body.querySelectorAll('img');
      imgs.forEach(function(img) {
        var src = (img.getAttribute('src') || '').trim();
        if (!src) return;
        if (src.indexOf('http://') === 0 || src.indexOf('https://') === 0) return;
        // si es relativo, añadir BASE_URL si está definido
        if (window.BASE_URL) {
          var prefix = window.BASE_URL.replace(/\/$/, '');
          // evitar duplicar slashes
          if (src.charAt(0) !== '/') src = '/' + src;
          img.src = prefix + src;
        } else {
          // si no hay BASE_URL y src es relativo pero comienza sin slash, lo dejamos
          if (src.charAt(0) !== '/') img.src = src;
        }
      });

      // configurar botón traspaso
      var dtBtn = document.getElementById('detailTransferBtn');
      if (dtBtn) dtBtn.setAttribute('data-product-id', productIdFallback || '');

      var modalEl = document.getElementById('productDetailModal');
      if (modalEl) {
        var bs = bootstrap.Modal.getInstance(modalEl);
        if (!bs) bs = new bootstrap.Modal(modalEl);
        bs.show();
      }
    }

    // Intenta obtener detalle desde endpoint; si devuelve JSON con product, usa renderProductModalFromObject
    // si devuelve HTML (texto) lo inyecta; si falla usa data-* de la fila.
    function openProductDetailById(id, trFallback) {
      if (!id) return;
      var url = '?url=product/getById&id=' + encodeURIComponent(id);
      fetch(url, {
          credentials: 'same-origin'
        })
        .then(function(res) {
          var ct = (res.headers.get('content-type') || '');
          if (ct.indexOf('application/json') !== -1) {
            return res.json().then(function(js) {
              // si estructura { product: {...} } usamos el objeto
              if (js && js.product) {
                renderProductModalFromObject(js.product);
              } else if (js && typeof js === 'object') {
                // si el endpoint devuelve directamente un objeto de producto
                renderProductModalFromObject(js);
              } else {
                // si no trae product, intentar inyectar texto fallback
                injectHtmlIntoModalAndShow(JSON.stringify(js), id);
              }
            });
          } else {
            // tratar como texto (HTML) e inyectar
            return res.text().then(function(txt) {
              // Si el endpoint devuelve una página completa, intenta extraer sólo el fragmento útil:
              var frag = txt;
              try {
                var parser = new DOMParser();
                var doc = parser.parseFromString(txt, 'text/html');
                var el = doc.getElementById('productDetailBody');
                if (el) {
                  frag = el.innerHTML;
                } else {
                  // si el endpoint devuelve un fragmento JSON en texto, intentar parsear
                  // si no, se inyecta todo
                  frag = txt;
                }
              } catch (e) {
                frag = txt;
              }
              injectHtmlIntoModalAndShow(frag, id);
            });
          }
        })
        .catch(function(err) {
          // fallback: usar data-* del tr
          if (trFallback) {
            var obj = productFromTrData(trFallback);
            renderProductModalFromObject(obj);
          } else {
            console.error('Error cargando producto:', err);
            alert('No se pudo cargar detalle del producto (consulta al servidor falló).');
          }
        });
    }

    // Delegación de eventos:
    document.addEventListener('click', function(evt) {
      // Si se hizo click en "Ver" del menú
      var viewBtn = evt.target.closest && evt.target.closest('.btn-view-product');
      if (viewBtn) {
        evt.preventDefault();
        evt.stopPropagation();
        var pid = viewBtn.getAttribute('data-product-id') || viewBtn.dataset.productId;
        var tr = viewBtn.closest('tr[data-product-id]');
        openProductDetailById(pid, tr);
        return;
      }

      // Click en la fila para abrir modal (ignoramos clicks en acciones/links/buttons)
      var tr = evt.target.closest && evt.target.closest('tr[data-product-id]');
      if (!tr) return;
      if (evt.target.closest('.product-actions') || evt.target.closest('a') || evt.target.closest('button')) {
        return; // dejar que otros handlers actúen
      }
      evt.preventDefault();
      evt.stopPropagation();
      var pidRow = tr.getAttribute('data-product-id');
      openProductDetailById(pidRow, tr);
    }, true);

  })();
</script>

<!-- Exponer base URL para assets/js/app.js (si usas BASE_URL en bootstrap) -->
<script>
  // Si tienes constante PHP BASE_URL la exponemos; si no existe se deja vacío y app.js resolverá rutas relativas.
  window.BASE_URL = '<?= defined("BASE_URL") ? rtrim(BASE_URL, "/") : "" ?>';
</script>