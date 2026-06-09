<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requirePermission(PERM_CURRENT_ACCOUNTS);

ensureCurrentAccountTables();
ensureTreasuryTables();

$pageTitle = __('current_accounts');

$accounts = db()->query(
    'SELECT a.* FROM current_accounts a ORDER BY a.name ASC'
)->fetchAll();

$accountStats = [];
foreach ($accounts as $account) {
    $accountStats[(int) $account['id']] = currentAccountTotals((int) $account['id']);
}

require __DIR__ . '/../includes/header.php';
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="label"><?= e(__('current_accounts')) ?></div>
        <div class="value"><?= count($accounts) ?></div>
    </div>
    <div class="stat-card warning">
        <div class="label"><?= e(__('ca_total_outstanding')) ?></div>
        <div class="value"><?= formatMoney(array_sum(array_column($accountStats, 'balance'))) ?></div>
    </div>
</div>

<div class="page-actions">
    <span></span>
    <a href="<?= url('accounts/create.php') ?>" class="btn btn-primary"><?= e(__('ca_new_account')) ?></a>
</div>

<div class="card">
    <div class="card-body table-wrap">
        <?php if ($accounts === []): ?>
            <p class="text-muted"><?= e(__('no_data')) ?></p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th><?= e(__('name')) ?></th>
                        <th><?= e(__('phone')) ?></th>
                        <th><?= e(__('ca_total_withdrawn')) ?></th>
                        <th><?= e(__('ca_total_repaid')) ?></th>
                        <th><?= e(__('ca_balance_owed')) ?></th>
                        <th><?= e(__('status')) ?></th>
                        <th><?= e(__('actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($accounts as $account): ?>
                        <?php $stats = $accountStats[(int) $account['id']] ?? ['withdrawn' => 0, 'repaid' => 0, 'balance' => 0]; ?>
                        <tr>
                            <td><a href="<?= url('accounts/view.php?id=' . $account['id']) ?>"><?= e($account['name']) ?></a></td>
                            <td><?= e($account['phone'] ?? '-') ?></td>
                            <td><?= formatMoney((float) $stats['withdrawn']) ?></td>
                            <td><?= formatMoney((float) $stats['repaid']) ?></td>
                            <td><strong><?= formatMoney((float) $stats['balance']) ?></strong></td>
                            <td><?= (int) ($account['is_active'] ?? 0) ? statusBadge('active') : statusBadge('inactive') ?></td>
                            <td>
                                <a href="<?= url('accounts/view.php?id=' . $account['id']) ?>" class="btn btn-secondary btn-sm"><?= e(__('view')) ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
