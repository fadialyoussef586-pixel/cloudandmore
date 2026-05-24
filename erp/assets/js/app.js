(function initSidebar() {
  const sidebar = document.getElementById('sidebar');
  const toggle = document.getElementById('sidebarToggle');
  const backdrop = document.getElementById('sidebarBackdrop');
  if (!sidebar || !toggle) return;

  function setOpen(open) {
    sidebar.classList.toggle('open', open);
    backdrop?.classList.toggle('visible', open);
    document.body.classList.toggle('sidebar-open', open);
    if (backdrop) backdrop.setAttribute('aria-hidden', open ? 'false' : 'true');
  }

  toggle.addEventListener('click', () => setOpen(!sidebar.classList.contains('open')));
  backdrop?.addEventListener('click', () => setOpen(false));
  sidebar.querySelectorAll('.nav-item').forEach((link) => {
    link.addEventListener('click', () => {
      if (window.matchMedia('(max-width: 768px)').matches) setOpen(false);
    });
  });
})();

document.querySelectorAll('[data-confirm]').forEach((el) => {
  el.addEventListener('click', (e) => {
    const msg = el.dataset.confirm || 'Are you sure?';
    if (!confirm(msg)) {
      e.preventDefault();
    }
  });
});

function addInvoiceRow() {
  const tbody = document.querySelector('#invoiceItems tbody');
  if (!tbody) return;
  const idx = tbody.children.length;
  const row = document.createElement('tr');
  row.innerHTML = `
    <td>
      <select name="items[${idx}][product_id]" class="product-select" onchange="fillProductPrice(this)" required>
        <option value="">--</option>
        ${window.productsOptions || ''}
      </select>
    </td>
    <td><input type="number" name="items[${idx}][quantity]" value="1" min="1" class="qty-input" onchange="calcRow(this)"></td>
    <td><input type="number" name="items[${idx}][unit_price]" value="0" min="0.01" step="0.01" class="price-input" onchange="calcRow(this)" required></td>
    <td class="row-total">0.00</td>
    <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">×</button></td>
  `;
  tbody.appendChild(row);
}

function fillProductPrice(select) {
  const option = select.selectedOptions[0];
  if (!option || !option.dataset.price) return;
  const row = select.closest('tr');
  row.querySelector('.price-input').value = option.dataset.price;
  calcRow(select);
}

function calcRow(el) {
  const row = el.closest('tr');
  const qty = parseFloat(row.querySelector('.qty-input')?.value || 0);
  const price = parseFloat(row.querySelector('.price-input')?.value || 0);
  row.querySelector('.row-total').textContent = (qty * price).toFixed(2);
}

function addDeliveryRow() {
  const tbody = document.querySelector('#deliveryItems tbody');
  if (!tbody) return;
  const idx = tbody.children.length;
  const row = document.createElement('tr');
  row.innerHTML = `
    <td>
      <select name="items[${idx}][product_id]" onchange="fillDeliveryDesc(this)">
        <option value="">--</option>
        ${window.productsOptions || ''}
      </select>
    </td>
    <td><input type="text" name="items[${idx}][description]" required></td>
    <td><input type="number" name="items[${idx}][quantity]" value="1" min="1"></td>
    <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">×</button></td>
  `;
  tbody.appendChild(row);
}

function fillDeliveryDesc(select) {
  const option = select.selectedOptions[0];
  if (!option) return;
  select.closest('tr').querySelector('[name*="[description]"]').value = option.textContent.trim();
}
