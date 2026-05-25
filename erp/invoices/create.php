<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requirePermission(PERM_INVOICES);

ensureInvoiceSchema();

$pageTitle = __('create_invoice');
$products = db()->query('SELECT id, sku, name_ar, name_en, sell_price FROM products ORDER BY name_en')->fetchAll();
$productsById = [];
$productSearchData = [];
foreach ($products as $p) {
    $productsById[(int) $p['id']] = $p;
    $productSearchData[] = [
        'id' => (int) $p['id'],
        'price' => (float) $p['sell_price'],
        'label' => trim(($p['sku'] ?? '') . ' - ' . productName($p)),
        'search' => strtolower(trim(
            ($p['sku'] ?? '') . ' ' .
            ($p['name_ar'] ?? '') . ' ' .
            ($p['name_en'] ?? '')
        )),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $qty = max(1, (int) ($_POST['quantity'] ?? 1));
        $serial = trim($_POST['serial_number'] ?? '');
        $paymentMethod = normalizePaymentMethod($_POST['payment_method'] ?? 'cash');
        $customerName = trim($_POST['customer_name'] ?? '');
        $customerPhone = trim($_POST['customer_phone'] ?? '');
        $unitPrice = round((float) ($_POST['unit_price'] ?? 0), 2);

        if ($productId < 1 || !isset($productsById[$productId])) {
            throw new RuntimeException('product');
        }
        if ($serial === '') {
            throw new RuntimeException('serial');
        }
        if ($customerName === '') {
            throw new RuntimeException('customer_name');
        }
        if ($customerPhone === '') {
            throw new RuntimeException('customer_phone');
        }
        if (invoiceSerialExists($serial)) {
            throw new RuntimeException('serial_duplicate');
        }

        $product = $productsById[$productId];
        $defaultUnitPrice = (float) $product['sell_price'];
        if ($defaultUnitPrice <= 0) {
            throw new RuntimeException('price');
        }
        if ($unitPrice <= 0) {
            throw new RuntimeException('unit_price');
        }

        $customerId = findOrCreateQuickCustomer(
            $customerName,
            $customerPhone
        );

        $lineTotal = $qty * $unitPrice;
        $listSubtotal = $qty * $defaultUnitPrice;
        $discount = max(0.0, $listSubtotal - $lineTotal);
        $totals = invoiceTotalsFromLines(max($listSubtotal, $lineTotal), $discount);
        $invNum = generateNumber('INV');

        $pdo->prepare(
            'INSERT INTO invoices (invoice_number, customer_id, subtotal, tax_rate, tax_amount, discount, total, status, payment_method, notes, user_id)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $invNum,
            $customerId,
            $totals['subtotal'],
            $totals['tax_rate'],
            $totals['tax_amount'],
            $totals['discount'],
            $totals['total'],
            'paid',
            $paymentMethod,
            trim($_POST['notes'] ?? ''),
            $_SESSION['user_id'],
        ]);
        $invoiceId = dbLastInsertId($pdo);

        $pdo->prepare(
            'INSERT INTO invoice_items (invoice_id, product_id, description, serial_number, quantity, unit_price, total)
             VALUES (?,?,?,?,?,?,?)'
        )->execute([
            $invoiceId,
            $productId,
            productName($product),
            $serial,
            $qty,
            $unitPrice,
            $lineTotal,
        ]);

        $pdo->prepare('UPDATE products SET quantity = GREATEST(0, quantity - ?) WHERE id = ?')
            ->execute([$qty, $productId]);

        $pdo->commit();
        flash('success', __('success_saved'));
        redirect(url('invoices/view.php?id=' . $invoiceId));
    } catch (Throwable $e) {
        $pdo->rollBack();
        $code = $e->getMessage();
        $errors = [
            'product' => __('invoice_product_required'),
            'serial' => __('invoice_serial_required'),
            'serial_duplicate' => __('invoice_serial_duplicate'),
            'price' => __('invoice_price_missing'),
            'unit_price' => __('invoice_custom_price_required'),
            'customer_name' => __('customer_name_required'),
            'customer_phone' => __('customer_phone_required'),
        ];
        flash('error', $errors[$code] ?? __('error'));
    }
}

$selectedProductId = (int) ($_POST['product_id'] ?? ($products[0]['id'] ?? 0));
$defaultPreviewPrice = isset($productsById[$selectedProductId])
    ? (float) $productsById[$selectedProductId]['sell_price']
    : 0.0;
$previewPrice = (float) ($_POST['unit_price'] ?? $defaultPreviewPrice);
$previewQty = max(1, (int) ($_POST['quantity'] ?? 1));
$previewLineTotal = $previewPrice * $previewQty;
$previewListSubtotal = max($defaultPreviewPrice * $previewQty, $previewLineTotal);
$previewDiscount = max(0.0, $previewListSubtotal - $previewLineTotal);
$previewTotals = invoiceTotalsFromLines($previewListSubtotal, $previewDiscount);

require __DIR__ . '/../includes/header.php';
?>
<div class="card sale-form-card">
    <div class="card-header"><h2><?= e(__('sale_quick_title')) ?></h2></div>
    <div class="card-body">
        <form method="post" class="sale-form" id="saleForm">
            <div class="form-group">
                <label for="saleProductSearch"><?= e(__('product_search')) ?></label>
                <input type="search" id="saleProductSearch" placeholder="<?= e(__('product_search_placeholder')) ?>" autocomplete="off">
            </div>

            <div class="form-group">
                <label for="saleProduct"><?= e(__('product')) ?> *</label>
                <select name="product_id" id="saleProduct" required>
                    <option value="">--</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>"
                            data-price="<?= e((string) $p['sell_price']) ?>"
                            data-search="<?= e(strtolower(trim(($p['sku'] ?? '') . ' ' . ($p['name_ar'] ?? '') . ' ' . ($p['name_en'] ?? '')))) ?>"
                            <?= (int) $p['id'] === $selectedProductId ? 'selected' : '' ?>>
                            <?= e(trim(($p['sku'] ?? '') . ' - ' . productName($p))) ?> — <?= formatMoney((float) $p['sell_price']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label><?= e(__('quantity')) ?> *</label>
                    <input type="number" name="quantity" id="saleQty" value="<?= $previewQty ?>" min="1" required>
                </div>
                <div class="form-group">
                    <label><?= e(__('serial_number')) ?> *</label>
                    <input type="text" name="serial_number" value="<?= e($_POST['serial_number'] ?? '') ?>"
                        placeholder="<?= e(__('serial_number_placeholder')) ?>" required autocomplete="off">
                </div>
            </div>

            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label><?= e(__('list_price')) ?></label>
                    <input type="text" id="saleDefaultPrice" value="<?= e(number_format($defaultPreviewPrice, 2, '.', '')) ?>" readonly>
                </div>
                <div class="form-group">
                    <label><?= e(__('price_after_discount')) ?> *</label>
                    <input type="number" name="unit_price" id="saleUnitPrice"
                        value="<?= e(number_format($previewPrice, 2, '.', '')) ?>"
                        min="0.01" step="0.01" required>
                </div>
            </div>

            <div class="form-group">
                <label><?= e(__('payment_method')) ?> *</label>
                <div class="payment-options">
                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="cash"
                            <?= ($_POST['payment_method'] ?? 'cash') !== 'transfer' ? 'checked' : '' ?>>
                        <span><?= e(__('payment_cash')) ?></span>
                    </label>
                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="transfer"
                            <?= ($_POST['payment_method'] ?? '') === 'transfer' ? 'checked' : '' ?>>
                        <span><?= e(__('payment_transfer')) ?></span>
                    </label>
                </div>
            </div>

            <div class="sale-form-extra">
                <div class="form-grid form-grid-2" style="margin-top:0.75rem">
                    <div class="form-group">
                        <label><?= e(__('customer')) ?> *</label>
                        <input type="text" name="customer_name" value="<?= e($_POST['customer_name'] ?? '') ?>"
                            placeholder="<?= e(__('customer_name_placeholder')) ?>" required>
                    </div>
                    <div class="form-group">
                        <label><?= e(__('phone')) ?> *</label>
                        <input type="text" name="customer_phone" value="<?= e($_POST['customer_phone'] ?? '') ?>"
                            placeholder="05xxxxxxxx" required>
                    </div>
                </div>
                <div class="form-group">
                    <label><?= e(__('notes')) ?></label>
                    <input type="text" name="notes" value="<?= e($_POST['notes'] ?? '') ?>">
                </div>
            </div>

            <div class="sale-total-preview" id="saleTotalPreview">
                <span><?= e(__('total')) ?></span>
                <strong><?= formatMoney($previewTotals['total']) ?></strong>
                <small style="display:block;margin-top:0.35rem;color:var(--text-muted)">
                    <?= e(__('discount')) ?>: <span id="saleDiscountPreview"><?= e(number_format($previewTotals['discount'], 2)) ?></span> <?= e(CURRENCY_CODE) ?>
                </small>
            </div>

            <button type="submit" class="btn btn-primary btn-lg"><?= e(__('save_sale')) ?></button>
        </form>
    </div>
</div>
<script>
(function () {
  const products = <?= json_encode($productSearchData, JSON_UNESCAPED_UNICODE) ?>;
  const currencyCode = <?= json_encode(CURRENCY_CODE, JSON_UNESCAPED_UNICODE) ?>;
  const search = document.getElementById('saleProductSearch');
  const product = document.getElementById('saleProduct');
  const qty = document.getElementById('saleQty');
  const defaultPrice = document.getElementById('saleDefaultPrice');
  const unitPrice = document.getElementById('saleUnitPrice');
  const preview = document.getElementById('saleTotalPreview');
  const discountPreview = document.getElementById('saleDiscountPreview');
  if (!product || !qty || !preview || !unitPrice || !defaultPrice || !search) return;

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function renderProducts(query) {
    const selected = product.value;
    const term = (query || '').trim().toLowerCase();
    const matches = term
      ? products.filter(function (item) { return item.search.indexOf(term) !== -1; })
      : products.slice();

    product.innerHTML = '<option value="">--</option>' + matches.map(function (item) {
      const isSelected = String(item.id) === String(selected) ? ' selected' : '';
      const priceText = Number(item.price || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
      return '<option value="' + item.id + '" data-price="' + item.price + '"' + isSelected + '>'
        + escapeHtml(item.label) + ' - ' + priceText + ' ' + currencyCode + '</option>';
    }).join('');

    if (selected && !product.value && matches.length === 1) {
      product.value = String(matches[0].id);
    }
  }

  function updatePreview() {
    const opt = product.selectedOptions[0];
    const listPrice = parseFloat(opt?.dataset.price || 0);
    const price = parseFloat(unitPrice.value || 0);
    const q = parseInt(qty.value || '1', 10);
    const total = price * q;
    const discount = Math.max(0, (listPrice * q) - total);
    defaultPrice.value = listPrice.toFixed(2);
    preview.querySelector('strong').textContent =
      total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + currencyCode;
    if (discountPreview) {
      discountPreview.textContent = discount.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
    }
  }

  search.addEventListener('input', function () {
    renderProducts(search.value);
    updatePreview();
  });
  product.addEventListener('change', function () {
    const opt = product.selectedOptions[0];
    if (opt?.dataset.price) {
      unitPrice.value = parseFloat(opt.dataset.price).toFixed(2);
    }
    updatePreview();
  });
  qty.addEventListener('input', updatePreview);
  unitPrice.addEventListener('input', updatePreview);

  renderProducts('');
  updatePreview();
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
