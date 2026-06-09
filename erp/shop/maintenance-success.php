<?php

require_once __DIR__ . '/../includes/functions.php';

$pageTitle = __('maint_request_success');
$ticketNum = $_SESSION['last_maintenance_ticket'] ?? '';
unset($_SESSION['last_maintenance_ticket']);

require __DIR__ . '/includes/header.php';
?>

<div class="order-success">
    <h2><?= e(__('maint_request_success')) ?></h2>
    <p><?= e(__('maint_request_success_msg')) ?></p>
    <?php if ($ticketNum !== ''): ?>
        <p><strong><?= e(__('maint_ticket_number')) ?>:</strong> <?= e($ticketNum) ?></p>
    <?php endif; ?>
    <a href="<?= shopUrl() ?>" class="btn btn-primary" style="margin-top:1.5rem"><?= e(__('shop')) ?></a>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
