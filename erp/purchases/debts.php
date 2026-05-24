<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requirePermission(PERM_PURCHASES);

ensurePurchaseSchema();
ensureTreasuryTables();

$pageTitle = __('supplier_debts');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $purchaseId = (int) ($_POST['purchase_id'] ?? 0);
    $amount = (float) ($_POST['amount'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    try {
        payPurchaseDebt($purchaseId, $amount, $_SESSION['user_id'] ?? null, $notes);
        flash('success', __('debt_paid_success'));
    } catch (Throwable $e) {
        $errors = [
            'not_found' => __('error'),
            'no_debt' => __('no_debt_remaining'),
            'invalid_amount' => __('invalid_amount'),
            'insufficient_treasury' => __('insufficient_treasury'),
        ];
        flash('error', $errors[$e->getMessage()] ?? __('error'));
    }
    redirect(url('purchases/debts.php'));
}

$debts = db()->query("
    SELECT p.*, s.name AS supplier_name, s.phone AS supplier_phone
    FROM purchases p
    JOIN suppliers s ON s.id = p.supplier_id
    WHERE p.debt_balance > 0
    ORDER BY p.created_at ASC
")->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<div class="page-actions">
    <a href="<?= url('purchases/index.php') ?>" class="btn btn-secondary"><?= e(__('purchases')) ?></a>
    <a href="<?= url('purchases/create.php') ?>" class="btn btn-primary"><?= e(__('new_purchase')) ?></a>
</div>

<div class="card">
    <div class="card-header"><h2><?= e(__('supplier_debts')) ?></h2></div>
    <div class="card-body">
        <?php if (empty($debts)): ?>
            <p class="text-muted"><?= e(__('no_open_debts')) ?></p>
        <?php else: ?>
            <?php foreach ($debts as $d): ?>
            <div class="card" style="margin-bottom:1rem">
                <div class="card-body">
                    <p><strong><?= e($d['supplier_name']) ?></strong> · <?= e($d['purchase_number']) ?></p>
                    <p class="text-muted"><?= e($d['description']) ?> × <?= (int) $d['quantity'] ?></p>
                    <p><?= e(__('debt_balance')) ?>: <strong class="text-danger"><?= formatMoney((float) $d['debt_balance']) ?></strong></p>
                    <form method="post" class="sale-form" style="margin-top:0.75rem">
                        <input type="hidden" name="purchase_id" value="<?= (int) $d['id'] ?>">
                        <div class="form-grid form-grid-2">
                            <div class="form-group">
                                <label><?= e(__('pay_amount')) ?></label>
                                <input type="number" name="amount" step="0.01" min="0.01"
                                    max="<?= e((string) $d['debt_balance']) ?>"
                                    value="<?= e((string) $d['debt_balance']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label><?= e(__('notes')) ?></label>
                                <input type="text" name="notes" placeholder="<?= e(__('payment_from_treasury')) ?>">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success"><?= e(__('pay_debt')) ?></button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
