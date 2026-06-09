<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requirePermission(PERM_CURRENT_ACCOUNTS);

ensureCurrentAccountTables();
ensureTreasuryTables();

$id = (int) ($_GET['id'] ?? 0);
$account = getCurrentAccount($id);
if (!$account) {
    flash('error', __('error'));
    redirect(url('accounts/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $amount = (float) ($_POST['amount'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    try {
        if ($action === 'withdraw') {
            recordCurrentAccountWithdrawal($id, $amount, $description !== '' ? $description : __('ca_withdrawal'), $_SESSION['user_id'] ?? null);
            flash('success', __('success_saved'));
        } elseif ($action === 'repay') {
            recordCurrentAccountRepayment($id, $amount, $description !== '' ? $description : __('ca_repayment'), $_SESSION['user_id'] ?? null);
            flash('success', __('success_saved'));
        }
    } catch (Throwable $e) {
        $errors = [
            'insufficient_treasury' => __('insufficient_treasury'),
            'repayment_exceeds_balance' => __('ca_repayment_exceeds'),
            'invalid_amount' => __('invalid_amount'),
        ];
        flash('error', $errors[$e->getMessage()] ?? __('error'));
    }

    redirect(url('accounts/view.php?id=' . $id));
}

$stats = currentAccountTotals($id);
$movements = db()->prepare(
    'SELECT m.*, u.name AS user_name
     FROM current_account_movements m
     LEFT JOIN users u ON u.id = m.user_id
     WHERE m.account_id = ?
     ORDER BY m.created_at DESC'
);
$movements->execute([$id]);
$movements = $movements->fetchAll();

$pageTitle = $account['name'];

require __DIR__ . '/../includes/header.php';
?>

<div class="page-actions">
    <a href="<?= url('accounts/index.php') ?>" class="btn btn-secondary"><?= e(__('current_accounts')) ?></a>
</div>

<div class="stats-grid">
    <div class="stat-card warning">
        <div class="label"><?= e(__('ca_total_withdrawn')) ?></div>
        <div class="value"><?= formatMoney((float) $stats['withdrawn']) ?></div>
    </div>
    <div class="stat-card success">
        <div class="label"><?= e(__('ca_total_repaid')) ?></div>
        <div class="value"><?= formatMoney((float) $stats['repaid']) ?></div>
    </div>
    <div class="stat-card primary">
        <div class="label"><?= e(__('ca_balance_owed')) ?></div>
        <div class="value"><?= formatMoney((float) $stats['balance']) ?></div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h2><?= e(__('ca_record_withdrawal')) ?></h2></div>
        <div class="card-body">
            <p class="text-muted" style="margin-bottom:1rem"><?= e(__('ca_withdrawal_hint')) ?></p>
            <form method="post">
                <input type="hidden" name="action" value="withdraw">
                <div class="form-group">
                    <label><?= e(__('amount')) ?></label>
                    <input type="number" name="amount" step="0.01" min="0.01" required>
                </div>
                <div class="form-group">
                    <label><?= e(__('description')) ?></label>
                    <input name="description" placeholder="<?= e(__('ca_withdrawal_placeholder')) ?>">
                </div>
                <button type="submit" class="btn btn-secondary"><?= e(__('ca_record_withdrawal')) ?></button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2><?= e(__('ca_record_repayment')) ?></h2></div>
        <div class="card-body">
            <p class="text-muted" style="margin-bottom:1rem"><?= e(__('ca_repayment_hint')) ?></p>
            <form method="post">
                <input type="hidden" name="action" value="repay">
                <div class="form-group">
                    <label><?= e(__('amount')) ?></label>
                    <input type="number" name="amount" step="0.01" min="0.01" max="<?= e((string) max(0.01, $stats['balance'])) ?>" required>
                </div>
                <div class="form-group">
                    <label><?= e(__('description')) ?></label>
                    <input name="description" placeholder="<?= e(__('ca_repayment_placeholder')) ?>">
                </div>
                <button type="submit" class="btn btn-primary"><?= e(__('ca_record_repayment')) ?></button>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2><?= e(__('ca_movements')) ?></h2></div>
    <div class="card-body table-wrap">
        <?php if ($movements === []): ?>
            <p class="text-muted"><?= e(__('no_data')) ?></p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th><?= e(__('date')) ?></th>
                        <th><?= e(__('type')) ?></th>
                        <th><?= e(__('amount')) ?></th>
                        <th><?= e(__('description')) ?></th>
                        <th><?= e(__('reference')) ?></th>
                        <th><?= e(__('user')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movements as $m): ?>
                        <tr>
                            <td><?= formatDate($m['created_at']) ?></td>
                            <td><?= e(currentAccountMovementLabel($m['type'])) ?></td>
                            <td><?= formatMoney((float) $m['amount']) ?></td>
                            <td><?= e($m['description'] ?? '-') ?></td>
                            <td><?= e($m['treasury_reference'] ?? '-') ?></td>
                            <td><?= e($m['user_name'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
