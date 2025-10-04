// ejemplo: define esto en la página del vendedor (o en un JS global)
window.addProductToSale = function(product, qty = 1) {
  // product: objeto devuelto por product/getById
  const cartBody = document.getElementById('saleCartBody');
  if (!cartBody) {
    alert('No hay carrito en esta página (id="saleCartBody")');
    return;
  }
  const pid = String(product.id);
  let row = cartBody.querySelector(`tr[data-product-id="${pid}"]`);
  if (row) {
    const qinput = row.querySelector('.cart-qty');
    qinput.value = (parseFloat(qinput.value || 0) + qty).toFixed(3);
    return;
  }
  const tr = document.createElement('tr');
  tr.setAttribute('data-product-id', pid);
  tr.innerHTML = `<td>${escapeHtml(product.name)}</td>
                  <td>${escapeHtml(product.sku)}</td>
                  <td><input class="form-control form-control-sm cart-qty" type="number" step="0.001" min="0.001" value="${qty}"></td>
                  <td>${escapeHtml(product.consumption_unit || '')}</td>
                  <td><button class="btn btn-sm btn-danger btn-remove">Quitar</button></td>`;
  cartBody.appendChild(tr);
  tr.querySelector('.btn-remove').addEventListener('click', () => tr.remove());
};
