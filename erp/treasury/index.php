<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requirePermission(PERM_TREASURY);

ensureTreasuryTables();

$pageTitle = __('treasury');
$balance = 0.0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] === 'withdrawal' ? 'withdrawal' : 'deposit';
    $amount = (float) ($_POST['amount'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    if ($amount > 0) {
        $category = $type === 'deposit' ? 'external_funding' : 'other';
        recordTreasuryMovement(
            $type,
            $amount,
            $category,
            $description !== '' ? $description : __('external_funding_default'),
            $_SESSION['user_id'] ?? null
        );
        flash('success', __('success_saved'));
        redirect(url('treasury/index.php'));
    }
    flash('error', __('invalid_amount'));
    redirect(url('treasury/index.php'));
}

$transactions = db()->query(
    'SELECT t.*, u.name AS user_name FROM treasury_transactions t
     LEFT JOIN users u ON u.id = t.user_id
     ORDER BY t.created_at DESC LIMIT 100'
)->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<div class="stats-grid">
    <div class="stat-card success">
        <div class="label"><?= e(__('treasury_balance')) ?></div>
        <div class="value money-usd-lg"><?= formatMoneyHtml($balance) ?></div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h2><?= e(__('add_treasury_deposit')) ?></h2></div>
        <div class="card-body">
            <p class="text-muted" style="margin-bottom:1rem"><?= e(__('treasury_deposit_hint')) ?></p>
            <form method="post">
                <input type="hidden" name="type" value="deposit">
                <div class="form-group">
                    <label><?= e(__('amount')) ?> (<?= e(CURRENCY_CODE) ?>)</label>
                    <input type="number" name="amount" step="0.01" min="0.01" required>
                </div>
                <div class="form-group">
                    <label><?= e(__('description')) ?></label>
                    <textarea name="description" placeholder="<?= e(__('external_funding_placeholder')) ?>"></textarea>
                </div>
                <button type="submit" class="btn btn-primary"><?= e(__('record_deposit')) ?></button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2><?= e(__('treasury_withdrawal')) ?></h2></div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="type" value="withdrawal">
                <div class="form-group">
                    <label><?= e(__('amount')) ?> (<?= e(CURRENCY_CODE) ?>)</label>
                    <input type="number" name="amount" step="0.01" min="0.01" required>
                </div>
                <div class="form-group">
                    <label><?= e(__('description')) ?></label>
                    <textarea name="description"></textarea>
                </div>
                <button type="submit" class="btn btn-secondary"><?= e(__('record_withdrawal')) ?></button>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2><?= e(__('treasury_ledger')) ?></h2></div>
    <div class="card-body table-wrap">
        <table>
            <thead>
                <tr>
                    <th><?= e(__('reference')) ?></th>
                    <th><?= e(__('type')) ?></th>
                    <th><?= e(__('amount')) ?></th>
                    <th><?= e(__('description')) ?></th>
                    <th><?= e(__('user')) ?></th>
                    <th><?= e(__('date')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $t): ?>
                <tr>
                    <td><?= e($t['reference_number']) ?></td>
                    <td><?= e(__($t['type'])) ?></td>
                    <td><?= formatMoneyHtml((float) $t['amount_sar']) ?></td>
                    <td><?= e($t['description'] ?? '-') ?></td>
                    <td><?= e($t['user_name'] ?? '-') ?></td>
                    <td><?= formatDate($t['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($transactions)): ?>
                <tr><td colspan="6" class="text-muted"><?= e(__('no_records')) ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
