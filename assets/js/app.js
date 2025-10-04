// assets/js/app.js (contenido completo)
// Delegación y manejo dinámico para: ver, editar, traspasar, ver historial, recepcionar.
// Incluye búsqueda global que resalta coincidencias en amarillo y filtra la tabla localmente.
document.addEventListener('DOMContentLoaded', () => {
  console.log('app.js cargado (productos)');

  // Modal elements
  const productDetailModalEl = document.getElementById('productDetailModal');
  const productDetailModal = productDetailModalEl ? new bootstrap.Modal(productDetailModalEl) : null;
  const productDetailBody = document.getElementById('productDetailBody');
  const detailTransferBtn = document.getElementById('detailTransferBtn');

  const editProductModalEl = document.getElementById('editProductModal');
  const editProductModal = editProductModalEl ? new bootstrap.Modal(editProductModalEl) : null;

  const productMovementsModalEl = document.getElementById('productMovementsModal');
  const productMovementsModal = productMovementsModalEl ? new bootstrap.Modal(productMovementsModalEl) : null;
  const productMovementsBody = document.getElementById('productMovementsBody');

  // Modal recepción
  const receiveModalEl = document.getElementById('receiveModal');
  const receiveModal = receiveModalEl ? new bootstrap.Modal(receiveModalEl) : null;
  const receiveProductIdInput = document.getElementById('receiveProductId');
  const receiveUnitSelect = document.getElementById('receiveUnit');
  const receiveQtyInput = document.getElementById('receiveQty');

  // Global search modal (si existe en layout)
  const globalModalEl = document.getElementById('globalModal');
  const globalModalBody = document.getElementById('globalModalBody');

  // Barra de búsqueda global (probablemente en header)
  const searchInput = document.getElementById('globalSearch');
  const searchBtn = document.getElementById('searchBtn');

  // Table rows (cached)
  const productsTable = document.querySelector('table[data-products-list]');
  const productRows = productsTable ? Array.from(productsTable.querySelectorAll('tbody tr[data-product-id]')) : [];

  function fetchJson(url) {
    return fetch(url, { credentials: 'same-origin' })
      .then(r => {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      });
  }

  // Helpers
  function escapeHtml(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/[&<>"'`=\/]/g, function(ch) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;','=':'&#x3D;','`':'&#x60;'}[ch];
    });
  }
  function escapeRegex(s) {
    return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }
  function highlight(text, term) {
    if (!term) return escapeHtml(text);
    const t = String(text || '');
    const parts = term.trim().split(/\s+/).map(escapeRegex).filter(Boolean);
    if (parts.length === 0) return escapeHtml(text);
    const re = new RegExp('(' + parts.join('|') + ')', 'ig');
    return escapeHtml(t).replace(re, (m) => `<mark class="bg-warning text-dark">${escapeHtml(m)}</mark>`);
  }

  // Resolver rutas de imagen de forma robusta
  function resolvePhotoPath(p) {
    if (!p) return null;
    if (p.startsWith('http://') || p.startsWith('https://')) return p;
    if (p.startsWith('/')) return p;
    if (window && window.BASE_URL) return window.BASE_URL.replace(/\/$/, '') + '/' + p.replace(/^\/+/, '');
    return p;
  }

  // Renderers: detalle completo (todos los campos de registro)
  function renderProductDetail(data) {
    const p = data.product || {};
    const stock = data.stock || {};
    let html = `<div class="row gy-3"><div class="col-md-4 text-center">`;

    if (p.photo_path) {
      const src = resolvePhotoPath(p.photo_path);
      html += `<img src="${escapeHtml(src)}" alt="${escapeHtml(p.name)}" class="img-fluid rounded mb-2" style="max-height:360px;">`;
    } else {
      html += `<div class="border rounded p-4 text-muted">Sin imagen</div>`;
    }

    html += `<div class="mt-2"><strong>SKU:</strong> ${escapeHtml(p.sku || '-')}</div>`;
    html += `<div><strong>Barcode:</strong> ${escapeHtml(p.barcode || '-')}</div>`;
    html += `</div><div class="col-md-8">`;

    html += `<h4>${escapeHtml(p.name || '-')}</h4>`;
    html += `<p class="text-muted">${escapeHtml(p.description || '')}</p>`;

    // Mostrar todos los campos del formulario
    html += `<div class="row"><div class="col-sm-6"><strong>Categoría:</strong> ${escapeHtml(p.category || '-')}</div>`;
    html += `<div class="col-sm-6"><strong>Tipo:</strong> ${escapeHtml(p.type || '-')}</div>`;
    html += `<div class="col-sm-6 mt-2"><strong>Unidad compra:</strong> ${escapeHtml(p.purchase_unit || '-')}</div>`;
    html += `<div class="col-sm-6 mt-2"><strong>Unidad consumo:</strong> ${escapeHtml(p.consumption_unit || '-')}</div>`;
    html += `<div class="col-sm-6 mt-2"><strong>Factor (piezas/caja):</strong> ${escapeHtml(p.factor || '1')}</div>`;
    html += `<div class="col-sm-6 mt-2"><strong>Recommended stock:</strong> ${escapeHtml(p.recommended_stock || '0')}</div>`;
    html += `<div class="col-sm-6 mt-2"><strong>Critical threshold:</strong> ${escapeHtml(p.critical_threshold || '-')}</div>`;
    html += `<div class="col-sm-6 mt-2"><strong>Bodega critical (cajas):</strong> ${escapeHtml(p.bodega_critical_boxes || '-')}</div>`;
    html += `<div class="col-sm-6 mt-2"><strong>Precio compra:</strong> $${(Number(p.default_price_purchase || 0)).toFixed(2)}</div>`;
    html += `<div class="col-sm-6 mt-2"><strong>Precio consumo:</strong> $${(Number(p.default_price_consumption || 0)).toFixed(2)}</div>`;
    html += `</div>`; // row

    html += `<hr class="my-3">`;
    html += `<h6>Totales</h6>`;
    html += `<p><strong>Piezas totales:</strong> ${ (stock.total_pieces !== undefined) ? Number(stock.total_pieces).toLocaleString() : '0' } pcs</p>`;
    html += `<p><strong>Cajas (estimadas):</strong> ${ (stock.estimated_boxes !== undefined) ? Number(stock.estimated_boxes).toLocaleString() : Math.floor((stock.total_pieces||0) / (p.factor || 1)) }</p>`;

    html += `<hr><h6>Stock por sucursal</h6>`;
    html += `<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Sucursal</th><th>Bodega?</th><th>Piezas</th><th>Cajas</th></tr></thead><tbody>`;
    (stock.branches||[]).forEach(b => {
      html += `<tr><td>${escapeHtml(b.branch_name || ('Sucursal ' + b.branch_id))}</td><td>${b.is_bodega ? 'Sí' : 'No'}</td><td>${Number(b.piezas||0).toLocaleString()}</td><td>${Number(b.cajas||0).toLocaleString()}</td></tr>`;
    });
    html += `</tbody></table></div>`;

    html += `</div></div>`; // close cols/row

    if (productDetailBody) productDetailBody.innerHTML = html;
    if (detailTransferBtn) detailTransferBtn.dataset.productId = p.id || '';
    if (productDetailModal) productDetailModal.show();
  }

  function renderProductMovements(data) {
    const rows = data.movements || [];
    let html = `<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Fecha</th><th>Tipo</th><th>De</th><th>A</th><th>Piezas</th><th>Cajas</th><th>User</th><th>Nota</th></tr></thead><tbody>`;
    rows.forEach(r => {
      html += `<tr>
        <td>${escapeHtml(r.created_at)}</td>
        <td>${escapeHtml(r.movement_type)}</td>
        <td>${escapeHtml(r.from_branch_name || '-')}</td>
        <td>${escapeHtml(r.to_branch_name || '-')}</td>
        <td>${Number(r.qty_consumption || r.qty || 0).toLocaleString()}</td>
        <td>${r.qty_purchase !== null ? Number(r.qty_purchase).toLocaleString() : '-'}</td>
        <td>${escapeHtml(r.user_name || '-')}</td>
        <td>${escapeHtml((r.note||'').substring(0,80))}</td>
      </tr>`;
    });
    html += `</tbody></table></div>`;
    if (productMovementsBody) productMovementsBody.innerHTML = html;
    if (productMovementsModal) productMovementsModal.show();
  }

  //////////////////////////////////////////////////////////////
  // BUSCADOR: filtrado local cuando estamos en la vista tabla //
  //////////////////////////////////////////////////////////////

  function clearProductFilter() {
    if (!productsTable) return;
    productRows.forEach(tr => {
      tr.style.display = '';
      // restaurar contenido original si lo tenemos en data-raw-*
      const skuCell = tr.querySelector('.cell-sku');
      const nameCell = tr.querySelector('.cell-name');
      const factorCell = tr.querySelector('.cell-factor');
      if (skuCell && tr.dataset.rawSku) skuCell.innerHTML = escapeHtml(tr.dataset.rawSku);
      if (nameCell && tr.dataset.rawName) nameCell.innerHTML = escapeHtml(tr.dataset.rawName);
      if (factorCell && tr.dataset.rawFactor) factorCell.innerHTML = escapeHtml(tr.dataset.rawFactor);
    });
    const firstCard = document.querySelector('.card .card-body');
    if (firstCard) {
      const msg = firstCard.querySelector('.search-no-results');
      if (msg) msg.remove();
    }
    if (searchInput) searchInput.value = '';
  }

  function filterTable(q) {
    if (!productsTable) return;
    const qparts = q.trim().toLowerCase().split(/\s+/).filter(Boolean);
    let shown = 0;
    productRows.forEach(tr => {
      const dataStr = (
        (tr.dataset.sku||'') + ' ' +
        (tr.dataset.barcode||'') + ' ' +
        (tr.dataset.name||'') + ' ' +
        (tr.dataset.description||'') + ' ' +
        (tr.dataset.type||'')
      ).toLowerCase();

      const match = qparts.every(p => dataStr.indexOf(p) !== -1);
      if (match) {
        tr.style.display = '';
        // resaltar en las celdas visibles usando data-raw-*
        const skuCell = tr.querySelector('.cell-sku');
        const nameCell = tr.querySelector('.cell-name');
        const factorCell = tr.querySelector('.cell-factor');
        if (skuCell && tr.dataset.rawSku) skuCell.innerHTML = highlight(tr.dataset.rawSku, q);
        if (nameCell && tr.dataset.rawName) nameCell.innerHTML = highlight(tr.dataset.rawName, q);
        if (factorCell && tr.dataset.rawFactor) factorCell.innerHTML = highlight(tr.dataset.rawFactor, q);
        shown++;
      } else {
        tr.style.display = 'none';
      }
    });

    const firstCard = document.querySelector('.card .card-body');
    if (firstCard) {
      let msg = firstCard.querySelector('.search-no-results');
      if (!msg) {
        msg = document.createElement('div');
        msg.className = 'alert alert-info search-no-results mt-2';
        firstCard.prepend(msg);
      }
      if (shown === 0) {
        msg.innerHTML = `No se encontraron productos para <strong>${escapeHtml(q)}</strong>. <a href="#" id="clearFilterLink">Ver todos</a>`;
        const clear = document.getElementById('clearFilterLink');
        if (clear) clear.addEventListener('click', (ev) => { ev.preventDefault(); clearProductFilter(); });
      } else {
        // si hay resultados los mantenemos; remover mensaje si existe
        const existing = firstCard.querySelector('.search-no-results');
        if (existing) existing.remove();
      }
    }
  }

  // doSearch decide entre filtrado local (si estamos en la vista) o buscar en servidor (página no-list)
  function doSearch(q) {
    if (!q || !q.trim()) {
      clearProductFilter();
      return;
    }
    if (productsTable) {
      // filtrado local
      filterTable(q);
      return;
    }
    // fallback: pedir al servidor y mostrar modal global si está presente
    fetchJson('?url=product/search&q=' + encodeURIComponent(q.trim()))
      .then(json => {
        const results = json.results || [];
        // showSearchResults fallback uses modal
        if (!globalModalEl || !globalModalBody) {
          if (results.length > 0) openDetailById(results[0].id);
          else alert('No se encontraron resultados para: ' + q);
        } else {
          if (results.length === 0) {
            globalModalBody.innerHTML = `<div class="p-3">No se encontraron productos para <strong>${escapeHtml(q)}</strong>.</div>`;
            new bootstrap.Modal(globalModalEl).show();
          } else {
            let html = `<div class="list-group">`;
            results.forEach(r => {
              const nameH = highlight(r.name || '', q);
              const skuH = highlight(r.sku || '', q);
              const barcodeH = highlight(r.barcode || '', q);
              const descH = highlight((r.description || '').substring(0, 200), q);
              html += `<a href="#" class="list-group-item list-group-item-action result-item" data-id="${r.id}">
                <div class="d-flex w-100 justify-content-between">
                  <h6 class="mb-1">${nameH}</h6>
                  <small class="text-muted">${escapeHtml(r.type || '')}</small>
                </div>
                <p class="mb-1 small">${skuH} • ${barcodeH}</p>
                <p class="mb-1 small text-muted">${descH}</p>
              </a>`;
            });
            html += `</div>`;
            globalModalBody.innerHTML = html;
            new bootstrap.Modal(globalModalEl).show();
          }
        }
      }).catch(err => {
        console.error('Error search:', err);
        alert('Error al buscar. Revisa la consola.');
      });
  }

  //////////////////////////////////////////////////////
  // ABRIR detalle / editar / movimientos / recept.   //
  //////////////////////////////////////////////////////

  function openDetailById(productId) {
    fetchJson('?url=product/getById&id=' + encodeURIComponent(productId))
      .then(data => {
        if (data.error) { alert(data.error); return; }
        // asegurar rutas de foto coherentes
        if (data.product && data.product.photo_path) data.product.photo_path = resolvePhotoPath(data.product.photo_path);
        renderProductDetail(data);
      }).catch(err => { console.error('Error loading product detail', err); alert('Error al cargar detalle.'); });
  }

  function openEditById(productId) {
    fetchJson('?url=product/getById&id=' + encodeURIComponent(productId))
      .then(data => {
        if (data.error) { alert(data.error); return; }
        const p = data.product || {};
        if (document.getElementById('editProductId')) document.getElementById('editProductId').value = p.id || '';
        if (document.getElementById('editSku')) document.getElementById('editSku').value = p.sku || '';
        if (document.getElementById('editBarcode')) document.getElementById('editBarcode').value = p.barcode || '';
        if (document.getElementById('editName')) document.getElementById('editName').value = p.name || '';
        if (document.getElementById('editDescription')) document.getElementById('editDescription').value = p.description || '';
        if (document.getElementById('editPurchaseUnit')) document.getElementById('editPurchaseUnit').value = p.purchase_unit || 'caja';
        if (document.getElementById('editConsumptionUnit')) document.getElementById('editConsumptionUnit').value = p.consumption_unit || 'pieza';
        if (document.getElementById('editFactor')) document.getElementById('editFactor').value = p.factor || 1;
        if (document.getElementById('editType')) document.getElementById('editType').value = p.type || 'primario';
        if (document.getElementById('editCategory')) document.getElementById('editCategory').value = p.category || '';
        if (document.getElementById('editPricePurchase')) document.getElementById('editPricePurchase').value = (p.default_price_purchase !== null ? Number(p.default_price_purchase).toFixed(2) : '0.00');
        if (document.getElementById('editPriceConsumption')) document.getElementById('editPriceConsumption').value = (p.default_price_consumption !== null ? Number(p.default_price_consumption).toFixed(2) : '0.00');

        const preview = document.getElementById('currentImagePreview');
        if (preview) {
          preview.innerHTML = '';
          if (p.photo_path) {
            const img = document.createElement('img');
            img.src = resolvePhotoPath(p.photo_path);
            img.style.maxWidth = '180px';
            img.style.maxHeight = '140px';
            img.className = 'img-thumbnail';
            preview.appendChild(img);
          } else {
            preview.innerHTML = '<div class="border rounded p-3 text-muted">Sin imagen</div>';
          }
        }
        if (editProductModal) editProductModal.show();
      }).catch(err => { console.error(err); alert('Error cargando producto para editar'); });
  }

  //////////////////////////////////////////////////////
  // DELEGACIÓN de clicks (único listener)            //
  //////////////////////////////////////////////////////

  document.addEventListener('click', (e) => {
    // detectar si click está dentro del contenedor de acciones (dropdown)
    const insideProductActions = !!e.target.closest('.product-actions');

    // Prioridad: botones de dropdown / acciones
    const viewBtn = e.target.closest('.btn-view-product');
    if (viewBtn) {
      e.preventDefault();
      const id = viewBtn.dataset.productId || viewBtn.getAttribute('data-product-id');
      if (id) openDetailById(id);
      return;
    }

    const editBtn = e.target.closest('.btn-edit-product');
    if (editBtn) {
      e.preventDefault();
      const id = editBtn.dataset.productId || editBtn.getAttribute('data-product-id');
      if (id) openEditById(id);
      return;
    }

    const movBtn = e.target.closest('.btn-product-movements');
    if (movBtn) {
      e.preventDefault();
      const id = movBtn.dataset.productId || movBtn.getAttribute('data-product-id');
      if (!id) return;
      fetchJson('?url=product/movements&id=' + encodeURIComponent(id))
        .then(data => {
          if (data.error) { alert(data.error); return; }
          renderProductMovements(data);
        }).catch(err => { console.error(err); alert('Error cargando movimientos'); });
      return;
    }

    const trBtn = e.target.closest('.btn-transfer-row');
    if (trBtn) {
      e.preventDefault();
      const id = trBtn.dataset.productId || trBtn.getAttribute('data-product-id');
      if (!id) return;
      const input = document.getElementById('transferProductId');
      if (input) input.value = id;
      const transferModalEl = document.getElementById('transferModal');
      if (transferModalEl) new bootstrap.Modal(transferModalEl).show();
      return;
    }

    const receiveBtn = e.target.closest('.btn-receive');
    if (receiveBtn) {
      e.preventDefault();
      const id = receiveBtn.dataset.productId || receiveBtn.getAttribute('data-product-id');
      if (!id) {
        alert('ID de producto no encontrado');
        return;
      }
      fetchJson('?url=product/getById&id=' + encodeURIComponent(id))
        .then(data => {
          if (data.error) { alert(data.error || 'Producto no encontrado'); return; }
          const p = data.product || {};
          const type = (p.type || 'primario').toString().toLowerCase();
          const todayIsSunday = (new Date()).getDay() === 0; // 0 = domingo
          if (type === 'secundario' && !todayIsSunday) {
            alert('Este producto es de tipo "secundario" y solo puede recepcionarse los domingos.');
            return;
          }
          if (receiveProductIdInput) receiveProductIdInput.value = id;
          if (receiveUnitSelect) receiveUnitSelect.value = 'pieces';
          if (receiveQtyInput) receiveQtyInput.value = '';
          if (receiveModal) receiveModal.show();
        }).catch(err => { console.error('Error validar recepcion', err); alert('Error al validar producto'); });
      return;
    }

    // Click en resultado del global modal (si existe)
    const resultItem = e.target.closest('.result-item');
    if (resultItem) {
      e.preventDefault();
      const id = resultItem.getAttribute('data-id');
      if (!id) return;
      const bs = globalModalEl ? bootstrap.Modal.getInstance(globalModalEl) : null;
      if (bs) bs.hide();
      setTimeout(() => { openDetailById(id); }, 200);
      return;
    }

    // Click directo sobre fila del listado -> abrir detalle o quick-add
    const productRow = e.target.closest('tr[data-product-id]');
    if (productRow) {
      // Si el click vino desde el dropdown/acciones, no abrimos detalle (evitar doble apertura)
      if (insideProductActions || e.target.closest('.product-actions-btn') || e.target.closest('.dropdown') || e.target.closest('.dropdown-menu')) {
        return;
      }

      // Si clicamos sobre un enlace/input/button dentro de la fila (no acciones), ignoramos para no interferir
      if (e.target.closest('a') || e.target.closest('button') || e.target.closest('input') || e.target.closest('select')) {
        // permitir comportamiento específico (por ejemplo, si tienes un enlace dentro de celda)
        return;
      }

      // Comportamiento por defecto: abrir detalle, salvo que exista addProductToSale
      const id = productRow.getAttribute('data-product-id');
      if (!id) return;
      if (typeof window.addProductToSale === 'function') {
        fetchJson('?url=product/getById&id=' + encodeURIComponent(id))
          .then(data => {
            if (data.error) { alert(data.error || 'Producto no encontrado'); return; }
            window.addProductToSale(data.product, 1);
          }).catch(err => { console.error(err); alert('Error al añadir producto'); });
        return;
      } else {
        openDetailById(id);
        return;
      }
    }

  }); // end document click listener

  // Detail transfer button (desde modal detalle)
  if (detailTransferBtn) {
    detailTransferBtn.addEventListener('click', (e) => {
      const id = e.target.dataset.productId;
      const input = document.getElementById('transferProductId');
      if (input) input.value = id;
      if (productDetailModal) productDetailModal.hide();
      const transferModalEl = document.getElementById('transferModal');
      if (transferModalEl) {
        setTimeout(() => { new bootstrap.Modal(transferModalEl).show(); }, 200);
      }
    });
  }

  //////////////////////////////////////////////////
  // Búsqueda: handlers (botón y tecla Enter)     //
  //////////////////////////////////////////////////

  if (searchBtn && searchInput) {
    searchBtn.addEventListener('click', () => {
      const q = (searchInput.value || '').trim();
      if (!q) return;
      doSearch(q);
    });
    searchInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        const q = (searchInput.value || '').trim();
        if (!q) return;
        doSearch(q);
      }
    });

    // Evitar que el "tache" (evento search en inputs type=search) recargue la página:
    searchInput.addEventListener('search', (e) => {
      // si el contenido quedó vacío -> limpiar filtro local
      const q = (searchInput.value || '').trim();
      if (!q) {
        clearProductFilter();
      } else {
        // si hay texto, invocar búsqueda
        doSearch(q);
      }
      // No dejar que el navegador haga acciones por defecto que recarguen
      e.preventDefault();
    });
  }

  // Si la página se cargó con query en el input, aplicar filtro automático
  if (searchInput && (searchInput.value || '').trim() && productsTable) {
    filterTable(searchInput.value.trim());
  }

}); // DOMContentLoaded end
