<?php
require_once __DIR__ . '/../includes/functions.php';
ensureShopSchema();

$pageTitle = __('order_success');
$orderNum = $_SESSION['last_order'] ?? '';
$orderPhone = $_SESSION['last_order_phone'] ?? '';
$order = null;
$items = [];

if ($orderNum !== '' && $orderPhone !== '') {
    $order = lookupOrderByNumberAndPhone($orderNum, $orderPhone);
    if ($order) {
        $items = fetchOrderItems((int) $order['id']);
    }
}

require __DIR__ . '/includes/header.php';
?>
<div class="order-success">
  <h2><?= e(__('order_success')) ?></h2>
  <p><?= e(__('order_success_msg')) ?></p>
  <?php if ($orderNum): ?>
    <p><strong><?= e(__('order_number')) ?>:</strong> <?= e($orderNum) ?></p>
  <?php endif; ?>

  <div class="order-success-actions">
    <?php if ($order): ?>
      <?= orderWhatsAppButton($order, $items, 'btn btn-whatsapp') ?>
    <?php endif; ?>
    <?php if ($orderNum && $orderPhone): ?>
      <a href="<?= shopUrl('track.php?order=' . urlencode($orderNum) . '&phone=' . urlencode($orderPhone)) ?>" class="btn btn-secondary"><?= e(__('track_order')) ?></a>
    <?php endif; ?>
    <a href="<?= shopUrl() ?>" class="btn btn-primary"><?= e(__('shop')) ?></a>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
