<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requireRole(['admin', 'manager']);

ensurePurchaseSchema();
ensureTreasuryTables();

$pageTitle = __('new_purchase');

$suppliers = db()->query('SELECT * FROM suppliers ORDER BY name')->fetchAll();
$products = db()->query('SELECT id, sku, name_ar, name_en, cost_price FROM products ORDER BY name_en')->fetchAll();
$productsById = [];
foreach ($products as $p) {
    $productsById[(int) $p['id']] = $p;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $supplierId = (int) ($_POST['supplier_id'] ?? 0);
        $productId = (int) ($_POST['product_id'] ?? 0);
        $qty = max(1, (int) ($_POST['quantity'] ?? 1));
        $unitCost = (float) ($_POST['unit_cost'] ?? 0);
        $paymentMethod = ($_POST['payment_method'] ?? 'cash') === 'credit' ? 'credit' : 'cash';
        $notes = trim($_POST['notes'] ?? '');

        if ($supplierId < 1) {
            throw new RuntimeException('supplier');
        }
        if ($productId < 1 || !isset($productsById[$productId])) {
            throw new RuntimeException('product');
        }
        if ($unitCost <= 0) {
            throw new RuntimeException('price');
        }

        $description = productName($productsById[$productId]);
        $total = round($qty * $unitCost, 2);
        $amountPaid = $paymentMethod === 'cash' ? $total : 0.0;
        $debtBalance = $paymentMethod === 'credit' ? $total : 0.0;

        $supplierName = db()->prepare('SELECT name FROM suppliers WHERE id = ?');
        $supplierName->execute([$supplierId]);
        $supplierName = $supplierName->fetchColumn() ?: __('suppliers');

        if ($paymentMethod === 'cash') {
            treasuryWithdrawForPurchase(
                $total,
                __('treasury_purchase_cash') . ' — ' . $description . ' / ' . $supplierName,
                $_SESSION['user_id'] ?? null
            );
        }

        $purchaseNum = generateNumber('PUR');
        $pdo->prepare(
            'INSERT INTO purchases (purchase_number, supplier_id, product_id, description, quantity, unit_cost, total, payment_method, amount_paid, debt_balance, notes, user_id)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $purchaseNum,
            $supplierId,
            $productId,
            $description,
            $qty,
            $unitCost,
            $total,
            $paymentMethod,
            $amountPaid,
            $debtBalance,
            $notes !== '' ? $notes : null,
            $_SESSION['user_id'] ?? null,
        ]);

        applyPurchaseToInventory($productId, $qty, $unitCost);

        $pdo->commit();
        flash('success', __('success_saved'));
        redirect(url('purchases/index.php'));
    } catch (Throwable $e) {
        $pdo->rollBack();
        $errors = [
            'supplier' => __('supplier_required'),
            'product' => __('invoice_product_required'),
            'price' => __('purchase_price_required'),
            'insufficient_treasury' => __('insufficient_treasury'),
        ];
        flash('error', $errors[$e->getMessage()] ?? __('error'));
    }
}

$selectedProductId = (int) ($_POST['product_id'] ?? ($products[0]['id'] ?? 0));
$previewQty = max(1, (int) ($_POST['quantity'] ?? 1));
$previewCost = isset($productsById[$selectedProductId])
    ? (float) ($_POST['unit_cost'] ?? $productsById[$selectedProductId]['cost_price'])
    : 0;

require __DIR__ . '/../includes/header.php';
?>
<div class="page-actions">
    <a href="<?= url('purchases/index.php') ?>" class="btn btn-secondary"><?= e(__('purchases')) ?></a>
    <a href="<?= url('purchases/suppliers.php') ?>" class="btn btn-secondary"><?= e(__('suppliers')) ?></a>
</div>

<div class="card sale-form-card">
    <div class="card-header"><h2><?= e(__('new_purchase')) ?></h2></div>
    <div class="card-body">
        <?php if (empty($suppliers)): ?>
            <div class="alert alert-error"><?= e(__('no_suppliers_hint')) ?></div>
            <a href="<?= url('purchases/suppliers.php?action=add') ?>" class="btn btn-primary"><?= e(__('add_supplier')) ?></a>
        <?php else: ?>
        <form method="post" class="sale-form" id="purchaseForm">
            <div class="form-group">
                <label><?= e(__('supplier_name')) ?> *</label>
                <select name="supplier_id" required>
                    <option value="">--</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= (int) ($_POST['supplier_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>>
                            <?= e($s['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label><?= e(__('product')) ?> *</label>
                <select name="product_id" id="purchaseProduct" required>
                    <option value="">--</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>" data-cost="<?= e((string) $p['cost_price']) ?>"
                            <?= (int) $p['id'] === $selectedProductId ? 'selected' : '' ?>>
                            <?= e(productName($p)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label><?= e(__('quantity')) ?> *</label>
                    <input type="number" name="quantity" id="purchaseQty" value="<?= $previewQty ?>" min="1" required>
                </div>
                <div class="form-group">
                    <label><?= e(__('unit_cost')) ?> *</label>
                    <input type="number" name="unit_cost" id="purchaseCost" value="<?= $previewCost ?>" step="0.01" min="0.01" required>
                </div>
            </div>

            <div class="form-group">
                <label><?= e(__('payment_method')) ?> *</label>
                <div class="payment-options">
                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="cash"
                            <?= ($_POST['payment_method'] ?? 'cash') !== 'credit' ? 'checked' : '' ?>>
                        <span><?= e(__('payment_cash')) ?></span>
                        <small class="text-muted"><?= e(__('purchase_cash_hint')) ?></small>
                    </label>
                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="credit"
                            <?= ($_POST['payment_method'] ?? '') === 'credit' ? 'checked' : '' ?>>
                        <span><?= e(__('payment_credit')) ?></span>
                        <small class="text-muted"><?= e(__('purchase_credit_hint')) ?></small>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label><?= e(__('notes')) ?></label>
                <input type="text" name="notes" value="<?= e($_POST['notes'] ?? '') ?>">
            </div>

            <div class="sale-total-preview" id="purchaseTotalPreview">
                <span><?= e(__('total')) ?></span>
                <strong><?= formatMoney($previewCost * $previewQty) ?></strong>
            </div>

            <button type="submit" class="btn btn-primary btn-lg"><?= e(__('save_purchase')) ?></button>
        </form>
        <?php endif; ?>
    </div>
</div>
<script>
(function () {
  const product = document.getElementById('purchaseProduct');
  const qty = document.getElementById('purchaseQty');
  const cost = document.getElementById('purchaseCost');
  const preview = document.getElementById('purchaseTotalPreview');
  if (!product || !qty || !cost || !preview) return;

  function updatePreview() {
    const opt = product.selectedOptions[0];
    if (opt?.dataset.cost && !cost.dataset.touched) {
      cost.value = opt.dataset.cost;
    }
    const total = parseFloat(cost.value || 0) * parseInt(qty.value || '1', 10);
    preview.querySelector('strong').textContent =
      total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' USD';
  }

  product.addEventListener('change', () => {
    delete cost.dataset.touched;
    updatePreview();
  });
  cost.addEventListener('input', () => { cost.dataset.touched = '1'; updatePreview(); });
  qty.addEventListener('input', updatePreview);
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
