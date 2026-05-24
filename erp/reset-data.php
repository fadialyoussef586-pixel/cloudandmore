<?php

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

requireOwner();

$pageTitle = __('reset_data');
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === 'RESET') {
    $pdo = db();
    $owner = ownerEmail();
    $tables = [
        'supplier_debt_payments',
        'purchases',
        'suppliers',
        'treasury_transactions',
        'exchange_rates',
        'delivery_items',
        'deliveries',
        'order_items',
        'orders',
        'invoice_items',
        'invoices',
        'stock_movements',
        'products',
        'customers',
        'payroll',
        'employees',
    ];

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tables as $table) {
        $pdo->exec('TRUNCATE TABLE `' . $table . '`');
    }
    $pdo->prepare('DELETE FROM users WHERE LOWER(email) <> ?')->execute([$owner]);
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    $done = true;
    flash('success', __('reset_data_done'));
    redirect(url('index.php'));
}

require __DIR__ . '/includes/header.php';
?>
<div class="page-actions">
    <a href="<?= url('index.php') ?>" class="btn btn-secondary"><?= e(__('cancel')) ?></a>
</div>

<div class="card">
    <div class="card-header"><h2><?= e(__('reset_data')) ?></h2></div>
    <div class="card-body">
        <p class="text-muted"><?= e(__('reset_data_hint')) ?></p>
        <ul class="text-muted" style="margin:1rem 0;padding-inline-start:1.25rem">
            <li><?= e(__('inventory')) ?></li>
            <li><?= e(__('invoices')) ?></li>
            <li><?= e(__('employees')) ?></li>
            <li><?= e(__('treasury')) ?></li>
            <li><?= e(__('orders')) ?></li>
        </ul>
        <p><strong><?= e(__('reset_data_keeps')) ?>:</strong> <?= e(ownerEmail()) ?></p>

        <form method="post" style="margin-top:1.5rem" onsubmit="return confirm('<?= e(__('reset_data_confirm_js')) ?>')">
            <div class="form-group">
                <label><?= e(__('reset_data_type_reset')) ?></label>
                <input type="text" name="confirm" placeholder="RESET" required autocomplete="off"
                    pattern="RESET" title="RESET">
            </div>
            <button type="submit" class="btn btn-danger"><?= e(__('reset_data')) ?></button>
        </form>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
