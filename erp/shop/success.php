<?php
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = __('order_success');
$orderNum = $_SESSION['last_order'] ?? '';
require __DIR__ . '/includes/header.php';
?>
<div class="order-success">
  <h2><?= e(__('order_success')) ?></h2>
  <p><?= e(__('order_success_msg')) ?></p>
  <?php if ($orderNum): ?><p><strong><?= e(__('order_number')) ?>:</strong> <?= e($orderNum) ?></p><?php endif; ?>
  <a href="<?= shopUrl() ?>" class="btn btn-primary" style="margin-top:1.5rem"><?= e(__('shop')) ?></a>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
