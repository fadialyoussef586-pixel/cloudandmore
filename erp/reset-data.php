<?php

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data_reset.php';

requireOwner();

$pageTitle = __('reset_data');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === 'RESET') {
    $pdo = db();
    $ok = resetBusinessDataVerified($pdo);
    ensureOwnerAccount($pdo);
    if ($ok) {
        flash('success', __('reset_data_done'));
    } else {
        flash('error', __('reset_data_failed'));
    }
    redirect(url('index.php?fresh=' . time()));
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
            <li><?= e(__('invoices')) ?> + <?= e(__('revenue_month')) ?></li>
            <li><?= e(__('treasury_balance')) ?> (treasury_transactions)</li>
            <li><?= e(__('employees')) ?></li>
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
