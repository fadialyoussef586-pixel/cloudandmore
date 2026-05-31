(function initNavigation() {
  const body = document.body;
  const sidebar = document.getElementById('sidebar');
  const toggle = document.getElementById('sidebarToggle');
  const backdrop = document.getElementById('sidebarBackdrop');
  const quickMenu = document.getElementById('quickActionMenu');
  const quickToggle = document.getElementById('quickActionToggle');
  const quickPanel = document.getElementById('quickActionPanel');
  const desktopQuery = window.matchMedia('(max-width: 820px)');
  const collapsedStorageKey = 'erp-sidebar-collapsed';

  if (!sidebar || !toggle) return;

  function setMobileOpen(open) {
    sidebar.classList.toggle('open', open);
    backdrop?.classList.toggle('visible', open);
    body.classList.toggle('sidebar-open', open);
    if (backdrop) {
      backdrop.setAttribute('aria-hidden', open ? 'false' : 'true');
    }
  }

  function setDesktopCollapsed(collapsed) {
    body.classList.toggle('sidebar-collapsed', collapsed);
    toggle.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
  }

  function isMobileViewport() {
    return desktopQuery.matches;
  }

  function closeQuickMenu() {
    if (!quickMenu || !quickToggle || !quickPanel) return;
    quickMenu.classList.remove('open');
    quickToggle.setAttribute('aria-expanded', 'false');
    quickPanel.setAttribute('hidden', '');
  }

  function openQuickMenu() {
    if (!quickMenu || !quickToggle || !quickPanel) return;
    quickMenu.classList.add('open');
    quickToggle.setAttribute('aria-expanded', 'true');
    quickPanel.removeAttribute('hidden');
    quickPanel.scrollTop = 0;
  }

  function setSubmenuState(group, open) {
    if (!group) return;
    const button = group.querySelector('[data-submenu-toggle]');
    const panel = group.querySelector('.nav-group-links');
    if (!button || !panel) return;

    group.classList.toggle('open', open);
    button.setAttribute('aria-expanded', open ? 'true' : 'false');
    panel.style.maxHeight = open ? panel.scrollHeight + 'px' : '0px';
  }

  function collapseOtherGroups(currentGroup) {
    sidebar.querySelectorAll('.nav-group').forEach((group) => {
      if (group !== currentGroup && !group.classList.contains('active')) {
        setSubmenuState(group, false);
      }
    });
  }

  if (!isMobileViewport()) {
    setDesktopCollapsed(localStorage.getItem(collapsedStorageKey) === '1');
  }

  sidebar.querySelectorAll('.nav-group').forEach((group) => {
    setSubmenuState(group, group.classList.contains('open') || group.classList.contains('active'));
  });

  toggle.addEventListener('click', () => {
    if (isMobileViewport()) {
      setMobileOpen(!sidebar.classList.contains('open'));
      return;
    }

    const nextCollapsed = !body.classList.contains('sidebar-collapsed');
    setDesktopCollapsed(nextCollapsed);
    localStorage.setItem(collapsedStorageKey, nextCollapsed ? '1' : '0');
  });

  backdrop?.addEventListener('click', () => setMobileOpen(false));

  sidebar.querySelectorAll('[data-submenu-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
      const group = button.closest('.nav-group');
      if (!group) return;

      if (!isMobileViewport() && body.classList.contains('sidebar-collapsed')) {
        setDesktopCollapsed(false);
        localStorage.setItem(collapsedStorageKey, '0');
      }

      const shouldOpen = !group.classList.contains('open');
      collapseOtherGroups(group);
      setSubmenuState(group, shouldOpen);
    });
  });

  sidebar.querySelectorAll('.nav-link, .nav-subitem').forEach((link) => {
    link.addEventListener('click', () => {
      if (isMobileViewport()) {
        setMobileOpen(false);
      }
      closeQuickMenu();
    });
  });

  quickToggle?.addEventListener('click', (event) => {
    event.stopPropagation();
    const willOpen = !quickMenu.classList.contains('open');
    if (willOpen) {
      openQuickMenu();
    } else {
      closeQuickMenu();
    }
  });

  quickPanel?.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => closeQuickMenu());
  });

  document.addEventListener('click', (event) => {
    if (quickMenu && !quickMenu.contains(event.target)) {
      closeQuickMenu();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      setMobileOpen(false);
      closeQuickMenu();
    }
  });

  const handleViewportChange = (event) => {
    if (event.matches) {
      setMobileOpen(false);
      setDesktopCollapsed(false);
      return;
    }

    setMobileOpen(false);
    setDesktopCollapsed(localStorage.getItem(collapsedStorageKey) === '1');
    sidebar.querySelectorAll('.nav-group').forEach((group) => {
      setSubmenuState(group, group.classList.contains('active'));
    });
  };

  if (typeof desktopQuery.addEventListener === 'function') {
    desktopQuery.addEventListener('change', handleViewportChange);
  } else if (typeof desktopQuery.addListener === 'function') {
    desktopQuery.addListener(handleViewportChange);
  }
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
