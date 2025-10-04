// assets/js/scanner-hid.js
// Scanner HID minimal: crea un input oculto que solo toma foco cuando se activa el modo "scanner"
// Para activar: pulsar el botón con id="scanBtn" en la página

(function(){
  // crear input oculto para recibir datos del scanner (emula teclado)
  const input = document.createElement('input');
  input.type = 'text';
  input.id = 'hidScannerInput';
  input.style.position = 'absolute';
  input.style.left = '-9999px';
  input.style.top = '0';
  input.autocomplete = 'off';
  document.body.appendChild(input);

  let scannerActive = false;
  let deactivateTimeout = null;

  function activateScanner(timeoutMs = 5000) {
    scannerActive = true;
    input.value = '';
    input.focus();
    // Si no se recibe nada en X ms, desactivar automáticamente
    if (deactivateTimeout) clearTimeout(deactivateTimeout);
    deactivateTimeout = setTimeout(() => {
      deactivateScanner();
    }, timeoutMs);
  }

  function deactivateScanner() {
    scannerActive = false;
    try { input.blur(); } catch(e) {}
    input.value = '';
    if (deactivateTimeout) { clearTimeout(deactivateTimeout); deactivateTimeout = null; }
  }

  // Cuando el scanner envía Enter, disparar búsqueda
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      const code = input.value.trim();
      input.value = '';
      if (!code) return;
      // Llamar endpoint de búsqueda (igual que app.js)
      fetch('?url=product/search&q=' + encodeURIComponent(code))
        .then(r => r.json())
        .then(data => {
          // Abrir modal global con resultado (reutilizar modal ya existente)
          const modalBody = document.getElementById('globalModalBody');
          const modalTitle = document.getElementById('globalModalLabel');
          const globalModalEl = document.getElementById('globalModal');
          if (!modalBody || !modalTitle || !globalModalEl) {
            alert('Escaneo: ' + code);
            return;
          }
          if (data.error) {
            modalTitle.innerText = 'No encontrado';
            modalBody.innerHTML = `<div class="alert alert-warning">${data.error}</div>`;
            new bootstrap.Modal(globalModalEl).show();
            return;
          }
          const p = data.product;
          modalTitle.innerText = `Producto: ${p.name} — SKU: ${p.sku}`;
          let html = `<div><strong>Descripción:</strong> ${p.description || '-'}</div>`;
          html += `<hr><h6>Stock por sucursal</h6><ul>`;
          data.stock.branches.forEach(b => {
            html += `<li>${b.branch_name}: ${b.piezas} piezas (${b.cajas} cajas)</li>`;
          });
          html += `</ul>`;
          html += `<div class="mt-3 text-end"><button class="btn btn-sm btn-success" id="modalTransferBtn" data-product-id="${p.id}">Traspasar desde bodega</button></div>`;
          modalBody.innerHTML = html;
          new bootstrap.Modal(globalModalEl).show();
        })
        .catch(err => {
          console.error('Scanner fetch error', err);
        })
        .finally(() => {
          // desactivar automaticamente tras un escaneo
          deactivateScanner();
        });
    }
  });

  // Asociar botón "Escanear" (si existe) para activar por 8s
  document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('scanBtn');
    if (btn) {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        // Activar scanner por 8 segundos (ajustable)
        activateScanner(8000);
        // feedback visual temporal (cambiar texto del boton)
        const prev = btn.innerText;
        btn.innerText = 'Escaneando...';
        setTimeout(() => { btn.innerText = prev; }, 8000);
      });
    }
  });

  // No fuerce foco global en cada click (antes causaba problemas en forms/modals)
})();
