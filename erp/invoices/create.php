<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();

ensureInvoiceSchema();

$pageTitle = __('create_invoice');
$products = db()->query('SELECT id, sku, name_ar, name_en, sell_price FROM products ORDER BY name_en')->fetchAll();
$productsById = [];
foreach ($products as $p) {
    $productsById[(int) $p['id']] = $p;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $qty = max(1, (int) ($_POST['quantity'] ?? 1));
        $serial = trim($_POST['serial_number'] ?? '');
        $paymentMethod = normalizePaymentMethod($_POST['payment_method'] ?? 'cash');

        if ($productId < 1 || !isset($productsById[$productId])) {
            throw new RuntimeException('product');
        }
        if ($serial === '') {
            throw new RuntimeException('serial');
        }
        if (invoiceSerialExists($serial)) {
            throw new RuntimeException('serial_duplicate');
        }

        $product = $productsById[$productId];
        $unitPrice = (float) $product['sell_price'];
        if ($unitPrice <= 0) {
            throw new RuntimeException('price');
        }

        $customerId = findOrCreateQuickCustomer(
            trim($_POST['customer_name'] ?? ''),
            trim($_POST['customer_phone'] ?? '')
        );

        $lineTotal = $qty * $unitPrice;
        $totals = invoiceTotalsFromLines($lineTotal);
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
        ];
        flash('error', $errors[$code] ?? __('error'));
    }
}

$selectedProductId = (int) ($_POST['product_id'] ?? ($products[0]['id'] ?? 0));
$previewPrice = isset($productsById[$selectedProductId])
    ? (float) $productsById[$selectedProductId]['sell_price']
    : 0;
$previewQty = max(1, (int) ($_POST['quantity'] ?? 1));
$previewTotals = invoiceTotalsFromLines($previewPrice * $previewQty);

require __DIR__ . '/../includes/header.php';
?>
<div class="card sale-form-card">
    <div class="card-header"><h2><?= e(__('sale_quick_title')) ?></h2></div>
    <div class="card-body">
        <form method="post" class="sale-form" id="saleForm">
            <div class="form-group">
                <label><?= e(__('product')) ?> *</label>
                <select name="product_id" id="saleProduct" required>
                    <option value="">--</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>" data-price="<?= e((string) $p['sell_price']) ?>"
                            <?= (int) $p['id'] === $selectedProductId ? 'selected' : '' ?>>
                            <?= e(productName($p)) ?> — <?= formatMoney((float) $p['sell_price']) ?>
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

            <details class="sale-form-extra">
                <summary><?= e(__('optional_customer_details')) ?></summary>
                <div class="form-grid form-grid-2" style="margin-top:0.75rem">
                    <div class="form-group">
                        <label><?= e(__('customer')) ?></label>
                        <input type="text" name="customer_name" value="<?= e($_POST['customer_name'] ?? '') ?>"
                            placeholder="<?= e(__('customer_name_placeholder')) ?>">
                    </div>
                    <div class="form-group">
                        <label><?= e(__('phone')) ?></label>
                        <input type="text" name="customer_phone" value="<?= e($_POST['customer_phone'] ?? '') ?>"
                            placeholder="05xxxxxxxx">
                    </div>
                </div>
                <div class="form-group">
                    <label><?= e(__('notes')) ?></label>
                    <input type="text" name="notes" value="<?= e($_POST['notes'] ?? '') ?>">
                </div>
            </details>

            <div class="sale-total-preview" id="saleTotalPreview">
                <span><?= e(__('total')) ?></span>
                <strong><?= formatMoney($previewTotals['total']) ?></strong>
                <small class="text-muted"><?= e(__('invoice_vat_hint')) ?></small>
            </div>

            <button type="submit" class="btn btn-primary btn-lg"><?= e(__('save_sale')) ?></button>
        </form>
    </div>
</div>
<script>
(function () {
  const product = document.getElementById('saleProduct');
  const qty = document.getElementById('saleQty');
  const preview = document.getElementById('saleTotalPreview');
  if (!product || !qty || !preview) return;

  function updatePreview() {
    const opt = product.selectedOptions[0];
    const price = parseFloat(opt?.dataset.price || 0);
    const q = parseInt(qty.value || '1', 10);
    const sub = price * q;
    const vat = sub * (<?= (float) INVOICE_VAT_PERCENT ?> / 100);
    const total = sub + vat;
    preview.querySelector('strong').textContent =
      total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' SAR';
  }

  product.addEventListener('change', updatePreview);
  qty.addEventListener('input', updatePreview);
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
