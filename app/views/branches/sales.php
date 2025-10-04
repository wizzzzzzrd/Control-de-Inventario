<?php
// app/views/branches/sales.php
require_once __DIR__ . '/../../bootstrap.php';
require_login();
require_role(['owner', 'admin_full', 'vendor']);

$branch = $branch ?? null;
$products = $products ?? []; // se usa sólo en caso de fallback
if (!$branch) {
    set_flash('Sucursal no encontrada');
    header('Location: ?url=branch/index'); exit;
}

// Mostrar "Volver a sucursales" solo para administradores/dueño
$role_slug = $_SESSION['role_slug'] ?? '';
$showBack = in_array($role_slug, ['owner', 'admin_full', 'admin_view'], true);
?>


<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h2 class="h4 mb-0">Ventas - <?= htmlspecialchars($branch['name']) ?></h2>
    <div class="text-muted small">Sucursal: <?= htmlspecialchars($branch['code'] ?? '-') ?> — Dirección: <?= htmlspecialchars($branch['address'] ?? '-') ?></div>
  </div>
  <div class="d-flex align-items-center">
    <?php if ($showBack): ?>
      <a href="?url=branch/index" class="btn btn-secondary me-2">Volver a sucursales</a>
    <?php endif; ?>
    <!-- Tarjeta llamativa al historial -->
    <a href="?url=branch/history&id=<?= intval($branch['id']) ?>" class="card text-decoration-none" style="min-width:170px; padding:10px; border-radius:6px; box-shadow:0 1px 6px rgba(0,0,0,.06);">
      <div class="d-flex align-items-center">
        <div style="width:48px;height:48px;background:#f8f9fa;border-radius:6px;display:flex;align-items:center;justify-content:center;margin-right:8px;">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" class="bi bi-receipt" viewBox="0 0 16 16"><path d="M4 .5a.5.5 0 0 0-.5.5V2H2.5A1.5 1.5 0 0 0 1 3.5v10A1.5 1.5 0 0 0 2.5 15H4v.5a.5.5 0 0 0 .8.4L6.5 14l1.7 1.9a.5.5 0 0 0 .8 0L10.5 14l1.7 1.9a.5.5 0 0 0 .8 0L14.5 14h1.5A1.5 1.5 0 0 0 17.5 12.5v-10A1.5 1.5 0 0 0 16 1H13.5V.5a.5.5 0 0 0-.5-.5h-8z"/></svg>
        </div>
        <div>
          <div style="font-weight:600;color:#212529;">Historial de ventas</div>
          <div class="text-muted small">Ver todas las ventas de esta sucursal</div>
        </div>
      </div>
    </a>
  </div>
</div>

<div class="row">
  <div class="col-lg-8">
    <div class="card mb-3">
      <div class="card-body p-2">
        <!-- Barra de búsqueda / scanner -->
        <div class="mb-3">
          <div class="input-group">
            <input id="posSearch" class="form-control" type="search" placeholder="Buscar por SKU, barcode o nombre (ó escanear)" aria-label="Buscar">
            <button id="posSearchBtn" class="btn btn-light" type="button">Buscar</button>
            <button id="clearSearchBtn" class="btn btn-outline-secondary" type="button" title="Limpiar búsqueda">✕</button>
          </div>
        </div>

        <!-- Resultados de búsqueda (vacío al inicio) -->
        <div id="searchResults" class="mb-2">
          <div class="text-muted">Busca un producto para añadirlo a la Venta (o usa el escáner).</div>
        </div>

        <!-- tabla compacta de resultados -->
        <div id="resultsTableWrapper" class="table-responsive d-none">
          <table class="table table-hover table-sm align-middle mb-0" id="resultsTable">
            <thead class="table-light">
              <tr>
                <th style="width:56px">ID</th>
                <th>SKU</th>
                <th>Nombre</th>
                <th style="width:120px">Disponibles</th>
                <th style="width:140px">Acción</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>

      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card shadow-sm">
      <div class="card-header d-flex justify-content-between align-items-center">
        <strong>VENTA</strong>
        <small id="cartCount" class="text-muted">0 Productos</small>
      </div>
      <div class="card-body" id="cartBody">
        <p class="text-muted">No hay Productos</p>
      </div>
      <div class="card-footer d-flex justify-content-between">
        <button id="clearCartBtn" class="btn btn-sm btn-secondary">Limpiar</button>
        <button id="checkoutBtn" class="btn btn-sm btn-success">Finalizar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal de Pago -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <form id="paymentForm">
        <div class="modal-header">
          <h5 class="modal-title" id="paymentModalLabel">Finalizar venta</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div id="paymentSummary" class="mb-2"></div>

          <div class="mb-2">
            <label class="form-label">Método de pago</label>
            <select id="paymentMethod" class="form-select" required>
              <option value="cash">Efectivo</option>
              <option value="card">Tarjeta</option>
              <option value="other">Otro</option>
            </select>
          </div>

          <div class="mb-2" id="cashInputs">
            <label class="form-label">Recibido (opcional)</label>
            <input type="number" class="form-control" id="receivedAmount" step="0.01" min="0" placeholder="0.00">
          </div>

          <div class="form-text text-muted">Al confirmar se registrará la venta y se descontará el stock.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" id="confirmPaymentBtn" class="btn btn-primary">Confirmar y guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function(){
  const branchId = <?= intval($branch['id']) ?>;
  const searchInput = document.getElementById('posSearch');
  const searchBtn = document.getElementById('posSearchBtn');
  const clearSearchBtn = document.getElementById('clearSearchBtn');
  const resultsWrapper = document.getElementById('resultsTableWrapper');
  const resultsTbody = document.querySelector('#resultsTable tbody');
  const searchResultsDiv = document.getElementById('searchResults');

  // carrito en memoria
  // qty siempre será entero (Number)
  const cart = []; // { id, sku, name, qty, factor, available, price_unit }

  function findInCart(id) { return cart.findIndex(it => it.id === id); }

  function renderCart() {
    const body = document.getElementById('cartBody');
    const count = document.getElementById('cartCount');
    if (!body) return;
    if (cart.length === 0) {
      body.innerHTML = '<p class="text-muted">No hay Productos</p>';
      if (count) count.textContent = '0 Productos';
      return;
    }
    if (count) count.textContent = cart.length + (cart.length === 1 ? ' producto' : ' productos');
    let html = '<ul class="list-group mb-2">';
    cart.forEach((it, idx) => {
      // mostrar precio unitario y subtotal si price_unit está presente
      const priceUnit = (typeof it.price_unit !== 'undefined' && it.price_unit !== null) ? Number(it.price_unit).toFixed(2) : '0.00';
      const subtotal = (Number(it.qty) * Number(it.price_unit || 0)).toFixed(2);
      html += `<li class="list-group-item d-flex justify-content-between align-items-center small">
        <div>
          <strong>${escapeHtml(it.name)}</strong><br>
          <span class="text-muted">${escapeHtml(it.sku)} — factor ${it.factor}</span><br>
          <small class="text-muted">PU: $${priceUnit} — Subtotal: $${subtotal}</small>
        </div>
        <div class="text-end">
          <div>${Number(it.qty).toLocaleString()} pcs</div>
          <div class="mt-1">
            <button data-idx="${idx}" class="btn btn-sm btn-outline-secondary btn-decr">−</button>
            <button data-idx="${idx}" class="btn btn-sm btn-outline-secondary btn-incr">+</button>
            <button data-idx="${idx}" class="btn btn-sm btn-outline-danger btn-remove">x</button>
          </div>
        </div>
      </li>`;
    });
    html += '</ul>';
    // mostrar total
    const total = cart.reduce((s,it) => s + (Number(it.qty) * Number(it.price_unit || 0)), 0);
    html += `<div class="d-flex justify-content-between align-items-center">
      <div class="fw-bold">Total</div>
      <div class="fw-bold">$${total.toFixed(2)}</div>
    </div>`;
    body.innerHTML = html;
  }

  function escapeHtml(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/[&<>"'`=\/]/g, function(ch) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;','=':'&#x3D;','`':'&#x60'}[ch];
    });
  }

  // render resultados
  function renderResults(items, q) {
    if (!items || items.length === 0) {
      resultsWrapper.classList.add('d-none');
      searchResultsDiv.innerHTML = `<div class="p-2 text-muted">No se encontraron productos para <strong>${escapeHtml(q)}</strong></div>`;
      return;
    }
    searchResultsDiv.innerHTML = '';
    resultsWrapper.classList.remove('d-none');
    resultsTbody.innerHTML = '';
    items.forEach(p => {
      const available = Math.floor(Number(p.total_pieces || 0)); // entero
      const factor = Number(p.factor || 1);
      const boxes_full = Math.floor(available / (factor || 1));
      const loose = available - (boxes_full * (factor || 1));
      const tr = document.createElement('tr');
      tr.setAttribute('data-product-id', p.id);
      tr.innerHTML = `<td>${p.id}</td>
                      <td>${escapeHtml(p.sku || '')}</td>
                      <td><strong>${escapeHtml(p.name || '')}</strong><br><small class="text-muted">${escapeHtml((p.description||'').substring(0,80))}</small></td>
                      <td>${available.toLocaleString()} pcs<br><small class="text-muted">${boxes_full} cajas + ${loose} pcs</small></td>
                      <td>
                        <div class="input-group input-group-sm">
                          <input type="number" class="form-control form-control-sm add-qty" min="1" step="1" value="1" style="max-width:110px">
                          <button class="btn btn-primary btn-add-result" type="button">Agregar</button>
                        </div>
                      </td>`;
      // attach product data (incluye precio si viene)
      tr._prod = {
        id: parseInt(p.id,10),
        sku: p.sku || '',
        name: p.name || '',
        factor: factor,
        total_pieces: available,
        price_unit: (p.default_price_consumption !== null && p.default_price_consumption !== undefined) ? parseFloat(p.default_price_consumption) : 0.00
      };
      resultsTbody.appendChild(tr);
    });
  }

  // fetch search
  function doSearch(q) {
    if (!q || !q.trim()) return;
    searchResultsDiv.innerHTML = `<div class="p-2 text-muted">Buscando...</div>`;
    fetch('?url=product/search&q=' + encodeURIComponent(q.trim()) + '&branch_id=' + encodeURIComponent(branchId), { credentials: 'same-origin' })
      .then(r => {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(json => {
        const results = json.results || [];
        renderResults(results, q);
      })
      .catch(err => {
        console.error('Error search:', err);
        searchResultsDiv.innerHTML = `<div class="p-2 text-danger">Error al buscar. Revisa la consola.</div>`;
      });
  }

  // handlers
  searchBtn.addEventListener('click', () => { const q = (searchInput.value||'').trim(); if (!q) return; doSearch(q); });
  searchInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); const q = (searchInput.value||'').trim(); if (!q) return; doSearch(q); }
  });
  clearSearchBtn.addEventListener('click', () => {
    searchInput.value = '';
    resultsTbody.innerHTML = '';
    resultsWrapper.classList.add('d-none');
    searchResultsDiv.innerHTML = `<div class="text-muted">Busca un producto para añadirlo a la Venta (o usa el escáner).</div>`;
  });

  // Delegación clicks
  document.addEventListener('click', function(e){
    const addBtn = e.target.closest('.btn-add-result');
    if (addBtn) {
      const row = addBtn.closest('tr[data-product-id]');
      if (!row) return;
      const p = row._prod;
      const qtyInput = row.querySelector('.add-qty');
      let qty = parseInt(qtyInput?.value || '0', 10) || 0;
      if (!Number.isInteger(qty) || qty <= 0) { alert('Cantidad inválida (debe ser número entero mayor o igual a 1)'); return; }
      const available = Number(p.total_pieces || 0);
      if (qty > available) { alert('Cantidad mayor al stock disponible'); return; }
      const id = parseInt(p.id,10);
      const idx = findInCart(id);
      if (idx === -1) {
        cart.push({ id: id, sku: p.sku||'', name: p.name||'', qty: qty, factor: p.factor || 1, available: available, price_unit: p.price_unit || 0.00 });
      } else {
        const newQty = cart[idx].qty + qty;
        if (newQty > cart[idx].available) { alert('No hay suficiente stock para sumar esa cantidad'); return; }
        cart[idx].qty = newQty;
      }
      renderCart();
      return;
    }

    const decr = e.target.closest('.btn-decr');
    if (decr) {
      const idx = parseInt(decr.getAttribute('data-idx'),10);
      if (!isNaN(idx) && cart[idx]) {
        // decrement by 1 integer
        cart[idx].qty = Math.max(0, parseInt(cart[idx].qty, 10) - 1);
        if (cart[idx].qty < 1) cart.splice(idx,1);
        renderCart();
      }
      return;
    }
    const incr = e.target.closest('.btn-incr');
    if (incr) {
      const idx = parseInt(incr.getAttribute('data-idx'),10);
      if (!isNaN(idx) && cart[idx]) {
        const next = parseInt(cart[idx].qty, 10) + 1;
        if (next > cart[idx].available) { alert('No hay suficiente stock'); return; }
        cart[idx].qty = next;
        renderCart();
      }
      return;
    }
    const rem = e.target.closest('.btn-remove');
    if (rem) {
      const idx = parseInt(rem.getAttribute('data-idx'),10);
      if (!isNaN(idx)) {
        cart.splice(idx,1);
        renderCart();
      }
      return;
    }

    const clearBtn = e.target.closest('#clearCartBtn');
    if (clearBtn) {
      cart.length = 0;
      renderCart();
      return;
    }

    const checkoutBtn = e.target.closest('#checkoutBtn');
    if (checkoutBtn) {
      if (cart.length === 0) { alert('Carrito vacío'); return; }
      // preparar resumen y abrir modal de pago
      const total = cart.reduce((s,it) => s + (Number(it.qty) * Number(it.price_unit || 0)), 0);
      document.getElementById('paymentSummary').innerHTML = `<div>Total: <strong>$${total.toFixed(2)}</strong></div>`;
      // reset received
      document.getElementById('receivedAmount').value = '';
      new bootstrap.Modal(document.getElementById('paymentModal')).show();
      return;
    }
  });

  // pago: manejar envío al backend
  const paymentForm = document.getElementById('paymentForm');
  paymentForm.addEventListener('submit', function(ev){
    ev.preventDefault();
    const method = document.getElementById('paymentMethod').value;
    const received = parseFloat(document.getElementById('receivedAmount').value || '0') || 0;
    const payload = {
      branch_id: branchId,
      payment_method: method,
      received_amount: received,
      items: cart.map(it => ({ product_id: it.id, qty: parseInt(it.qty, 10), price_unit: Number(it.price_unit || 0) }))
    };

    // simple UI disable
    const btn = document.getElementById('confirmPaymentBtn');
    btn.disabled = true;
    btn.textContent = 'Procesando...';

    fetch('?url=order/store', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).then(r => r.json())
      .then(json => {
        if (json && json.success) {
          // cerrar modal
          const bs = bootstrap.Modal.getInstance(document.getElementById('paymentModal'));
          if (bs) bs.hide();
          // limpiar carrito
          cart.length = 0;
          renderCart();
          alert('Venta registrada correctamente. Orden: ' + (json.order_number || json.order_id));
          // opción: redirigir al historial
          window.location.href = '?url=branch/history&id=' + encodeURIComponent(branchId);
        } else {
          alert('Error al registrar venta: ' + (json.error || 'Error desconocido'));
        }
      }).catch(err => {
        console.error('Error checkout:', err);
        alert('Error al procesar la venta. Revisa la consola.');
      }).finally(() => {
        btn.disabled = false;
        btn.textContent = 'Confirmar y guardar';
      });
  });

  // inicial render
  renderCart();

  // Expose some hooks for vendor_main.js scanner or layout to trigger search results directly
  window.addEventListener('vendor.search.results', function(e){
    const results = e.detail.results || [];
    // si viene 1 resultado queremos auto mostrarlo (o agregar 1 a cart)
    if (results.length === 1) {
      // mostrar en resultados y agregar la fila lista
      renderResults(results, e.detail.q || '');
      // auto agregar 1 unidad (si hay stock)
      setTimeout(() => {
        const tr = resultsTbody.querySelector('tr[data-product-id]');
        if (tr) {
          const addBtn = tr.querySelector('.btn-add-result');
          const qtyInput = tr.querySelector('.add-qty');
          if (qtyInput) qtyInput.value = '1';
          if (addBtn) addBtn.click();
        }
      }, 80);
    } else {
      // mostrar lista para que usuario seleccione
      renderResults(results, e.detail.q || '');
    }
  });

  window.addEventListener('vendor.search.clear', function(){
    searchInput.value = '';
    resultsTbody.innerHTML = '';
    resultsWrapper.classList.add('d-none');
    searchResultsDiv.innerHTML = `<div class="text-muted">Busca un producto para añadirlo al carrito (o usa el escáner).</div>`;
  });

})();
</script>
