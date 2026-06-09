<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requirePermission(PERM_INVOICES);

ensureInvoiceSchema();
ensureCustomerSchema();

$pageTitle = __('create_invoice');
$products = db()->query('SELECT id, sku, name_ar, name_en, sell_price FROM products ORDER BY name_en')->fetchAll();
$productsById = [];
$productSearchData = [];
foreach ($products as $p) {
    $productId = (int) $p['id'];
    $productsById[$productId] = $p;
    $productSearchData[] = [
        'id' => $productId,
        'price' => (float) $p['sell_price'],
        'label' => trim(($p['sku'] ?? '') . ' - ' . productName($p)),
        'search' => strtolower(trim(
            ($p['sku'] ?? '') . ' ' .
            ($p['name_ar'] ?? '') . ' ' .
            ($p['name_en'] ?? '')
        )),
    ];
}

$defaultProductId = (int) ($products[0]['id'] ?? 0);
$defaultProductPrice = isset($productsById[$defaultProductId])
    ? (float) $productsById[$defaultProductId]['sell_price']
    : 0.0;
$selectedInvoiceType = normalizeInvoiceType($_POST['invoice_type'] ?? 'sale');
$formItems = $_POST['items'] ?? [[
    'product_id' => $defaultProductId,
    'quantity' => 1,
    'serial_number' => '',
    'unit_price' => $defaultProductPrice,
]];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = db();
    $transactionStarted = false;

    try {
        $invoiceType = normalizeInvoiceType($_POST['invoice_type'] ?? 'sale');
        $paymentMethod = normalizePaymentMethod($_POST['payment_method'] ?? 'cash');
        $customerName = trim($_POST['customer_name'] ?? '');
        $customerPhone = trim($_POST['customer_phone'] ?? '');
        $customerEmail = trim($_POST['customer_email'] ?? '');
        $customerAddress = trim($_POST['customer_address'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $rawItems = is_array($_POST['items'] ?? null) ? $_POST['items'] : [];
        $lineItems = [];
        $seenSerials = [];
        $listSubtotal = 0.0;
        $finalTotal = 0.0;

        if ($customerName === '') {
            throw new RuntimeException('customer_name');
        }
        if ($customerPhone === '') {
            throw new RuntimeException('customer_phone');
        }

        $existingCustomer = findCustomerByPhone($customerPhone);
        if ($existingCustomer && (int) ($existingCustomer['is_blocked'] ?? 0) === 1) {
            throw new RuntimeException('customer_blocked');
        }

        foreach ($rawItems as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            $qty = max(1, (int) ($row['quantity'] ?? 1));
            $serial = trim($row['serial_number'] ?? '');
            $unitPrice = round((float) ($row['unit_price'] ?? 0), 2);
            $isBlankRow = $productId < 1 && $serial === '' && $unitPrice <= 0;

            if ($isBlankRow) {
                continue;
            }
            if ($productId < 1 || !isset($productsById[$productId])) {
                throw new RuntimeException('product');
            }
            if ($serial === '') {
                throw new RuntimeException('serial');
            }

            $serialKey = strtolower($serial);
            if (isset($seenSerials[$serialKey]) || invoiceSerialExists($serial)) {
                throw new RuntimeException('serial_duplicate');
            }
            $seenSerials[$serialKey] = true;

            $product = $productsById[$productId];
            $defaultUnitPrice = (float) $product['sell_price'];
            if ($defaultUnitPrice <= 0) {
                throw new RuntimeException('price');
            }
            if ($unitPrice <= 0) {
                throw new RuntimeException('unit_price');
            }

            $storedUnitPrice = $invoiceType === 'gift' ? 0.0 : $unitPrice;
            $lineTotal = $invoiceType === 'gift' ? 0.0 : round($qty * $storedUnitPrice, 2);
            $listLineSubtotal = $invoiceType === 'gift' ? 0.0 : round($qty * $defaultUnitPrice, 2);
            $listSubtotal += $listLineSubtotal;
            $finalTotal += $lineTotal;
            $lineItems[] = [
                'product_id' => $productId,
                'description' => productName($product),
                'serial_number' => $serial,
                'quantity' => $qty,
                'unit_price' => $storedUnitPrice,
                'total' => $lineTotal,
            ];
        }

        if ($lineItems === []) {
            throw new RuntimeException('items');
        }

        if ($invoiceType === 'gift') {
            $discount = 0.0;
            $totals = invoiceTotalsFromLines(0.0, 0.0);
            $paymentMethod = 'cash';
            $invoiceStatus = 'paid';
        } else {
            $discount = max(0.0, round($listSubtotal - $finalTotal, 2));
            $totals = invoiceTotalsFromLines(max($listSubtotal, $finalTotal), $discount);
            $invoiceStatus = invoiceStatusForNewSale($paymentMethod);
        }

        // Prepare treasury tables before the write transaction so schema migrations
        // never trigger an implicit commit in the middle of invoice creation.
        ensureTreasuryTables();

        $pdo->beginTransaction();
        $transactionStarted = true;

        $invNum = generateNumber('INV');
        $customerId = findOrCreateCustomer($customerName, $customerPhone, $customerEmail, $customerAddress);

        $pdo->prepare(
            'INSERT INTO invoices (invoice_number, customer_id, subtotal, tax_rate, tax_amount, discount, total, status, payment_method, invoice_type, notes, user_id)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $invNum,
            $customerId,
            $totals['subtotal'],
            $totals['tax_rate'],
            $totals['tax_amount'],
            $totals['discount'],
            $totals['total'],
            $invoiceStatus,
            $paymentMethod,
            $invoiceType,
            $notes !== '' ? $notes : null,
            $_SESSION['user_id'] ?? null,
        ]);
        $invoiceId = dbLastInsertId($pdo);

        $insertItem = $pdo->prepare(
            'INSERT INTO invoice_items (invoice_id, product_id, description, serial_number, quantity, unit_price, total)
             VALUES (?,?,?,?,?,?,?)'
        );
        $updateStock = $pdo->prepare('UPDATE products SET quantity = GREATEST(0, quantity - ?) WHERE id = ?');
        foreach ($lineItems as $item) {
            $insertItem->execute([
                $invoiceId,
                $item['product_id'],
                $item['description'],
                $item['serial_number'],
                $item['quantity'],
                $item['unit_price'],
                $item['total'],
            ]);
            $updateStock->execute([$item['quantity'], $item['product_id']]);
        }

        if (invoiceShouldCreateImmediateTreasuryEntry($invoiceType, $paymentMethod)) {
            recordInvoiceTreasuryDeposit(
                (float) $totals['total'],
                $invNum,
                $customerName,
                $_SESSION['user_id'] ?? null,
                $pdo
            );
        }

        // Touch the persistent cash account inside the same transaction so
        // sales are reflected immediately in the main cash box balance.
        $cashBalanceNow = cashAccountBalance($pdo);

        $pdo->commit();
        flash('success', __('success_saved'));
        redirect(url('invoices/preview.php?id=' . $invoiceId));
    } catch (Throwable $e) {
        if ($transactionStarted && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $code = $e->getMessage();
        $errors = [
            'items' => __('invoice_items_required'),
            'product' => __('invoice_product_required'),
            'serial' => __('invoice_serial_required'),
            'serial_duplicate' => __('invoice_serial_duplicate'),
            'price' => __('invoice_price_missing'),
            'unit_price' => __('invoice_custom_price_required'),
            'customer_name' => __('customer_name_required'),
            'customer_phone' => __('customer_phone_required'),
            'customer_blocked' => __('customer_blocked'),
        ];
        unset($cashBalanceNow);
        flash('error', $errors[$code] ?? __('error'));
    }
}

$previewListSubtotal = 0.0;
$previewFinalTotal = 0.0;
foreach ($formItems as $row) {
    $productId = (int) ($row['product_id'] ?? 0);
    $qty = max(1, (int) ($row['quantity'] ?? 1));
    $unitPrice = round((float) ($row['unit_price'] ?? 0), 2);
    $defaultUnitPrice = isset($productsById[$productId]) ? (float) $productsById[$productId]['sell_price'] : 0.0;
    if ($selectedInvoiceType === 'sale') {
        $previewListSubtotal += round($qty * $defaultUnitPrice, 2);
        $previewFinalTotal += round($qty * $unitPrice, 2);
    }
}
$previewDiscount = max(0.0, round($previewListSubtotal - $previewFinalTotal, 2));
$previewTotals = invoiceTotalsFromLines(max($previewListSubtotal, $previewFinalTotal), $previewDiscount);

require __DIR__ . '/../includes/header.php';
?>
<div class="card sale-form-card">
    <div class="card-header"><h2><?= e(__('sale_quick_title')) ?></h2></div>
    <div class="card-body">
        <form method="post" class="sale-form" id="saleForm">
            <div class="form-group">
                <label><?= e(__('invoice_type')) ?> *</label>
                <div class="payment-options">
                    <label class="payment-option">
                        <input type="radio" name="invoice_type" value="sale"
                            <?= $selectedInvoiceType !== 'gift' ? 'checked' : '' ?>>
                        <span><?= e(__('invoice_type_sale')) ?></span>
                    </label>
                    <label class="payment-option">
                        <input type="radio" name="invoice_type" value="gift"
                            <?= $selectedInvoiceType === 'gift' ? 'checked' : '' ?>>
                        <span><?= e(__('invoice_type_gift')) ?></span>
                    </label>
                </div>
                <p class="text-muted" id="giftInvoiceHint" style="<?= $selectedInvoiceType === 'gift' ? '' : 'display:none' ?>;margin-top:0.5rem">
                    <?= e(__('gift_invoice_hint')) ?>
                </p>
            </div>

            <div class="form-group customer-search-wrap">
                <label><?= e(__('cust_search_customer')) ?></label>
                <input type="search" id="customerSearchInput" autocomplete="off"
                    placeholder="<?= e(__('cust_search_placeholder')) ?>">
                <p class="text-muted" style="margin-top:0.35rem;font-size:0.85rem"><?= e(__('cust_search_hint')) ?></p>
                <div id="customerSearchResults" class="customer-search-results" hidden></div>
            </div>

            <div id="customerPreview" class="customer-preview card" hidden>
                <div class="card-body">
                    <div class="customer-preview-head">
                        <strong id="customerPreviewTitle"><?= e(__('cust_profile_preview')) ?></strong>
                        <a id="customerPreviewLink" href="#" class="btn btn-secondary btn-sm"><?= e(__('cust_view_profile')) ?></a>
                    </div>
                    <div id="customerPreviewStats" class="customer-preview-stats"></div>
                </div>
            </div>

            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label><?= e(__('customer')) ?> *</label>
                    <input type="text" name="customer_name" id="customerNameInput" value="<?= e($_POST['customer_name'] ?? '') ?>"
                        placeholder="<?= e(__('customer_name_placeholder')) ?>" required>
                </div>
                <div class="form-group">
                    <label><?= e(__('phone')) ?> *</label>
                    <input type="text" name="customer_phone" id="customerPhoneInput" value="<?= e($_POST['customer_phone'] ?? '') ?>"
                        placeholder="05xxxxxxxx" required>
                </div>
            </div>

            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label><?= e(__('email')) ?></label>
                    <input type="email" name="customer_email" id="customerEmailInput" value="<?= e($_POST['customer_email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label><?= e(__('address')) ?></label>
                    <input type="text" name="customer_address" id="customerAddressInput" value="<?= e($_POST['customer_address'] ?? '') ?>">
                </div>
            </div>

            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label><?= e(__('payment_method')) ?> *</label>
                    <div class="payment-options">
                        <?php $selectedPayment = $_POST['payment_method'] ?? 'cash'; ?>
                        <label class="payment-option">
                            <input type="radio" name="payment_method" value="cash"
                                <?= $selectedPayment === 'cash' ? 'checked' : '' ?>>
                            <span><?= e(__('payment_cash')) ?></span>
                        </label>
                        <label class="payment-option">
                            <input type="radio" name="payment_method" value="transfer"
                                <?= $selectedPayment === 'transfer' ? 'checked' : '' ?>>
                            <span><?= e(__('payment_transfer')) ?></span>
                        </label>
                        <label class="payment-option">
                            <input type="radio" name="payment_method" value="pending"
                                <?= $selectedPayment === 'pending' ? 'checked' : '' ?>>
                            <span><?= e(__('payment_pending')) ?></span>
                        </label>
                        <label class="payment-option">
                            <input type="radio" name="payment_method" value="deferred"
                                <?= $selectedPayment === 'deferred' ? 'checked' : '' ?>>
                            <span><?= e(__('payment_deferred')) ?></span>
                        </label>
                    </div>
                    <p class="text-muted" style="margin-top:0.5rem;font-size:0.85rem"><?= e(__('payment_pending_hint')) ?></p>
                </div>
                <div class="form-group">
                    <label><?= e(__('notes')) ?></label>
                    <input type="text" name="notes" value="<?= e($_POST['notes'] ?? '') ?>">
                </div>
            </div>

            <div class="card" style="margin-top:1rem">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;gap:1rem">
                    <h2><?= e(__('items')) ?></h2>
                    <button type="button" class="btn btn-secondary btn-sm" id="addInvoiceItem"><?= e(__('add_item')) ?></button>
                </div>
                <div class="card-body table-wrap">
                    <table id="invoiceItems">
                        <thead>
                            <tr>
                                <th><?= e(__('product_search')) ?></th>
                                <th><?= e(__('product')) ?></th>
                                <th><?= e(__('serial_number')) ?></th>
                                <th><?= e(__('quantity')) ?></th>
                                <th><?= e(__('list_price')) ?></th>
                                <th><?= e(__('price_after_discount')) ?></th>
                                <th><?= e(__('total')) ?></th>
                                <th><?= e(__('actions')) ?></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <div class="sale-total-preview" id="saleTotalPreview">
                <div><?= e(__('subtotal')) ?>: <strong id="saleSubtotalPreview"><?= formatMoney((float) $previewTotals['subtotal']) ?></strong></div>
                <div><?= e(__('discount')) ?>: <strong id="saleDiscountPreview"><?= formatMoney((float) $previewTotals['discount']) ?></strong></div>
                <div style="margin-top:0.35rem"><?= e(__('total')) ?>: <strong id="saleGrandTotalPreview"><?= formatMoney((float) $previewTotals['total']) ?></strong></div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg"><?= e(__('save_sale')) ?></button>
        </form>
    </div>
</div>

<template id="invoiceItemRowTemplate">
    <tr class="invoice-item-row">
        <td><input type="search" class="invoice-item-search" placeholder="<?= e(__('product_search_placeholder')) ?>" autocomplete="off"></td>
        <td><select class="invoice-item-product" required></select></td>
        <td><input type="text" class="invoice-item-serial" placeholder="<?= e(__('serial_number_placeholder')) ?>" autocomplete="off" required></td>
        <td><input type="number" class="invoice-item-qty" min="1" value="1" required></td>
        <td><input type="text" class="invoice-item-list-price" value="0.00" readonly></td>
        <td><input type="number" class="invoice-item-unit-price" min="0.01" step="0.01" value="0.00" required></td>
        <td class="invoice-item-line-total">0.00 <?= e(CURRENCY_CODE) ?></td>
        <td><button type="button" class="btn btn-danger btn-sm invoice-item-remove">×</button></td>
    </tr>
</template>

<script>
(function () {
  const products = <?= json_encode($productSearchData, JSON_UNESCAPED_UNICODE) ?>;
  const formItems = <?= json_encode(array_values($formItems), JSON_UNESCAPED_UNICODE) ?>;
  const currencyCode = <?= json_encode(CURRENCY_CODE, JSON_UNESCAPED_UNICODE) ?>;
  const invoiceTypeInputs = document.querySelectorAll('input[name="invoice_type"]');
  const giftInvoiceHint = document.getElementById('giftInvoiceHint');
  const tbody = document.querySelector('#invoiceItems tbody');
  const template = document.getElementById('invoiceItemRowTemplate');
  const addButton = document.getElementById('addInvoiceItem');
  const subtotalPreview = document.getElementById('saleSubtotalPreview');
  const discountPreview = document.getElementById('saleDiscountPreview');
  const totalPreview = document.getElementById('saleGrandTotalPreview');
  const productMap = Object.fromEntries(products.map(function (item) {
    return [String(item.id), item];
  }));
  let nextIndex = 0;

  if (!tbody || !template || !addButton) return;

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function formatMoneyValue(amount) {
    return Number(amount || 0).toLocaleString(undefined, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }) + ' ' + currencyCode;
  }

  function currentInvoiceType() {
    const checked = document.querySelector('input[name="invoice_type"]:checked');
    return checked ? checked.value : 'sale';
  }

  function buildOptions(selectedId, query) {
    const term = (query || '').trim().toLowerCase();
    const filtered = term
      ? products.filter(function (item) { return item.search.indexOf(term) !== -1; })
      : products.slice();
    const source = filtered.length > 0 ? filtered : products;

    return '<option value="">--</option>' + source.map(function (item) {
      const selected = String(item.id) === String(selectedId) ? ' selected' : '';
      return '<option value="' + item.id + '"' + selected + '>' + escapeHtml(item.label) + '</option>';
    }).join('');
  }

  function createRow(data) {
    const row = template.content.firstElementChild.cloneNode(true);
    const index = nextIndex++;
    const searchInput = row.querySelector('.invoice-item-search');
    const select = row.querySelector('.invoice-item-product');
    const serialInput = row.querySelector('.invoice-item-serial');
    const qtyInput = row.querySelector('.invoice-item-qty');
    const listPriceInput = row.querySelector('.invoice-item-list-price');
    const unitPriceInput = row.querySelector('.invoice-item-unit-price');

    searchInput.value = '';
    select.name = 'items[' + index + '][product_id]';
    serialInput.name = 'items[' + index + '][serial_number]';
    qtyInput.name = 'items[' + index + '][quantity]';
    unitPriceInput.name = 'items[' + index + '][unit_price]';

    const selectedId = String(data && data.product_id ? data.product_id : '');
    select.innerHTML = buildOptions(selectedId, '');
    select.value = selectedId;
    serialInput.value = data && data.serial_number ? data.serial_number : '';
    qtyInput.value = data && data.quantity ? data.quantity : 1;

    const selectedProduct = productMap[selectedId] || null;
    const defaultPrice = selectedProduct ? Number(selectedProduct.price || 0) : 0;
    listPriceInput.value = defaultPrice.toFixed(2);
    unitPriceInput.value = data && data.unit_price ? Number(data.unit_price).toFixed(2) : defaultPrice.toFixed(2);

    bindRow(row);
    tbody.appendChild(row);
    updateSummary();
  }

  function bindRow(row) {
    const searchInput = row.querySelector('.invoice-item-search');
    const select = row.querySelector('.invoice-item-product');
    const qtyInput = row.querySelector('.invoice-item-qty');
    const serialInput = row.querySelector('.invoice-item-serial');
    const listPriceInput = row.querySelector('.invoice-item-list-price');
    const unitPriceInput = row.querySelector('.invoice-item-unit-price');
    const removeButton = row.querySelector('.invoice-item-remove');

    function syncProduct(resetUnitPrice) {
      const selectedProduct = productMap[select.value] || null;
      const defaultPrice = selectedProduct ? Number(selectedProduct.price || 0) : 0;
      listPriceInput.value = defaultPrice.toFixed(2);
      if (resetUnitPrice || Number(unitPriceInput.value || 0) <= 0) {
        unitPriceInput.value = defaultPrice.toFixed(2);
      }
      updateRowTotal(row);
      updateSummary();
    }

    searchInput.addEventListener('input', function () {
      const currentId = select.value;
      select.innerHTML = buildOptions(currentId, searchInput.value);
      if (currentId) {
        select.value = currentId;
      }
      syncProduct(false);
    });

    select.addEventListener('change', function () {
      syncProduct(true);
    });

    qtyInput.addEventListener('input', function () {
      updateRowTotal(row);
      updateSummary();
    });

    unitPriceInput.addEventListener('input', function () {
      updateRowTotal(row);
      updateSummary();
    });

    serialInput.addEventListener('input', function () {
      updateSummary();
    });

    removeButton.addEventListener('click', function () {
      if (tbody.children.length === 1) {
        return;
      }
      row.remove();
      updateSummary();
    });

    syncProduct(false);
  }

  function updateRowTotal(row) {
    const qty = Number(row.querySelector('.invoice-item-qty').value || 0);
    const unitPrice = Number(row.querySelector('.invoice-item-unit-price').value || 0);
    const totalCell = row.querySelector('.invoice-item-line-total');
    const total = currentInvoiceType() === 'gift' ? 0 : (qty * unitPrice);
    totalCell.textContent = formatMoneyValue(total);
  }

  function updateSummary() {
    const invoiceType = currentInvoiceType();
    let subtotal = 0;
    let finalTotal = 0;

    tbody.querySelectorAll('.invoice-item-row').forEach(function (row) {
      const select = row.querySelector('.invoice-item-product');
      const qty = Number(row.querySelector('.invoice-item-qty').value || 0);
      const unitPrice = Number(row.querySelector('.invoice-item-unit-price').value || 0);
      const selectedProduct = productMap[select.value] || null;
      const defaultPrice = selectedProduct ? Number(selectedProduct.price || 0) : 0;

      if (invoiceType === 'sale') {
        subtotal += defaultPrice * qty;
        finalTotal += unitPrice * qty;
      }
    });

    const normalizedSubtotal = Math.max(subtotal, finalTotal);
    const discount = Math.max(0, subtotal - finalTotal);
    subtotalPreview.textContent = formatMoneyValue(normalizedSubtotal);
    discountPreview.textContent = formatMoneyValue(discount);
    totalPreview.textContent = formatMoneyValue(finalTotal);
  }

  addButton.addEventListener('click', function () {
    createRow({
      product_id: '',
      quantity: 1,
      serial_number: '',
      unit_price: 0
    });
  });

  (formItems.length ? formItems : [{
    product_id: <?= json_encode($defaultProductId) ?>,
    quantity: 1,
    serial_number: '',
    unit_price: <?= json_encode($defaultProductPrice) ?>
  }]).forEach(createRow);

    invoiceTypeInputs.forEach(function (input) {
    input.addEventListener('change', function () {
      if (giftInvoiceHint) {
        giftInvoiceHint.style.display = currentInvoiceType() === 'gift' ? '' : 'none';
      }
      tbody.querySelectorAll('.invoice-item-row').forEach(updateRowTotal);
      updateSummary();
    });
  });

  const customerSearchInput = document.getElementById('customerSearchInput');
  const customerSearchResults = document.getElementById('customerSearchResults');
  const customerPreview = document.getElementById('customerPreview');
  const customerPreviewStats = document.getElementById('customerPreviewStats');
  const customerPreviewLink = document.getElementById('customerPreviewLink');
  const customerNameInput = document.getElementById('customerNameInput');
  const customerPhoneInput = document.getElementById('customerPhoneInput');
  const customerEmailInput = document.getElementById('customerEmailInput');
  const customerAddressInput = document.getElementById('customerAddressInput');
  const customerSearchUrl = <?= json_encode(url('customers/search.php')) ?>;
  let customerSearchTimer = null;

  function renderCustomerPreview(customer) {
    if (!customerPreview || !customerPreviewStats) {
      return;
    }
    if (!customer || !customer.id) {
      customerPreview.hidden = true;
      return;
    }

    customerPreview.hidden = false;
    if (customerPreviewLink) {
      customerPreviewLink.href = customer.profile_url || '#';
    }

    const blocked = customer.is_blocked ? '<span class="badge badge-red"><?= e(__('cust_blocked')) ?></span>' : '';
    const outstanding = Number(customer.outstanding || 0) > 0
      ? '<strong class="text-warning">' + formatMoneyValue(customer.outstanding) + '</strong>'
      : formatMoneyValue(0);

    customerPreviewStats.innerHTML =
      '<div class="customer-preview-stat"><span><?= e(__('cust_existing_customer')) ?></span><strong>' + (customer.name || '') + '</strong></div>' +
      '<div class="customer-preview-stat"><span><?= e(__('cust_total_spent')) ?></span><strong>' + formatMoneyValue(customer.total_spent) + '</strong></div>' +
      '<div class="customer-preview-stat"><span><?= e(__('cust_outstanding')) ?></span>' + outstanding + '</div>' +
      '<div class="customer-preview-stat"><span><?= e(__('invoices')) ?></span><strong>' + (customer.invoice_count || 0) + '</strong></div>' +
      '<div class="customer-preview-stat"><span><?= e(__('cust_rating')) ?></span><strong>' + (customer.rating_count > 0 ? customer.rating_avg + '/5' : '—') + '</strong></div>' +
      blocked;
  }

  function applyCustomer(customer) {
    if (!customer) {
      return;
    }
    if (customerNameInput) customerNameInput.value = customer.name || '';
    if (customerPhoneInput) customerPhoneInput.value = customer.phone || '';
    if (customerEmailInput && customer.email) customerEmailInput.value = customer.email;
    if (customerAddressInput && customer.address) customerAddressInput.value = customer.address;
    renderCustomerPreview(customer);
    if (customerSearchResults) {
      customerSearchResults.hidden = true;
      customerSearchResults.innerHTML = '';
    }
    if (customerSearchInput) {
      customerSearchInput.value = '';
    }
  }

  function searchCustomers(query) {
    if (!customerSearchResults || query.length < 2) {
      if (customerSearchResults) {
        customerSearchResults.hidden = true;
        customerSearchResults.innerHTML = '';
      }
      return;
    }

    fetch(customerSearchUrl + '?q=' + encodeURIComponent(query), {
      headers: { 'Accept': 'application/json' }
    })
      .then(function (response) { return response.json(); })
      .then(function (payload) {
        const results = payload.results || [];
        if (!results.length) {
          customerSearchResults.hidden = true;
          customerSearchResults.innerHTML = '';
          return;
        }

        customerSearchResults.innerHTML = results.map(function (item) {
          return '<button type="button" class="customer-search-item" data-customer="' +
            encodeURIComponent(JSON.stringify(item)) + '">' +
            '<strong>' + item.name + '</strong>' +
            '<span>' + (item.phone || '') + '</span>' +
            '<small>' + formatMoneyValue(item.total_spent) + ' · ' + (item.invoice_count || 0) + ' <?= e(__('invoices')) ?></small>' +
            '</button>';
        }).join('');
        customerSearchResults.hidden = false;
      })
      .catch(function () {
        customerSearchResults.hidden = true;
      });
  }

  if (customerSearchInput) {
    customerSearchInput.addEventListener('input', function () {
      clearTimeout(customerSearchTimer);
      customerSearchTimer = setTimeout(function () {
        searchCustomers(customerSearchInput.value.trim());
      }, 250);
    });
  }

  if (customerSearchResults) {
    customerSearchResults.addEventListener('click', function (event) {
      const button = event.target.closest('.customer-search-item');
      if (!button) {
        return;
      }
      try {
        applyCustomer(JSON.parse(decodeURIComponent(button.getAttribute('data-customer') || '')));
      } catch (error) {
        /* ignore malformed payload */
      }
    });
  }

  if (customerPhoneInput) {
    customerPhoneInput.addEventListener('blur', function () {
      const phone = customerPhoneInput.value.trim();
      if (phone.length < 6) {
        return;
      }
      fetch(customerSearchUrl + '?q=' + encodeURIComponent(phone), {
        headers: { 'Accept': 'application/json' }
      })
        .then(function (response) { return response.json(); })
        .then(function (payload) {
          const match = (payload.results || []).find(function (item) {
            return (item.phone || '') === phone;
          }) || (payload.results || [])[0];
          if (match) {
            renderCustomerPreview(match);
          } else {
            renderCustomerPreview(null);
          }
        })
        .catch(function () {});
    });
  }
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
