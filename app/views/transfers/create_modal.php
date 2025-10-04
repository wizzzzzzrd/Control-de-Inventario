<?php
// transfers/create_modal.php
require_once __DIR__ . '/../../bootstrap.php';
require_login();
require_role(['owner', 'admin_full', 'admin_view', 'almacenista']);
?>

<!-- Transfer Modal -->
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
            // Cargar sucursales desde DB
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
