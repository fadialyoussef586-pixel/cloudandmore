<?php
require_once __DIR__ . '/../includes/functions.php';
ensureShopSchema();

$pageTitle = __('track_order');
$order = null;
$items = [];
$lookupError = '';
$orderNumber = trim($_GET['order'] ?? $_POST['order_number'] ?? '');
$phone = trim($_GET['phone'] ?? $_POST['customer_phone'] ?? '');

if ($orderNumber !== '' && $phone !== '') {
    $order = lookupOrderByNumberAndPhone($orderNumber, $phone);
    if ($order) {
        $items = fetchOrderItems((int) $order['id']);
    } else {
        $lookupError = __('shop_track_not_found');
    }
}

require __DIR__ . '/includes/header.php';
?>
<div class="shop-form-card">
  <h1><?= e(__('track_order')) ?></h1>
  <p class="text-muted"><?= e(__('shop_track_hint')) ?></p>

  <form method="get" class="form-grid" style="margin-top:1rem">
    <div class="form-group">
      <label><?= e(__('order_number')) ?></label>
      <input name="order" value="<?= e($orderNumber) ?>" required>
    </div>
    <div class="form-group">
      <label><?= e(__('phone')) ?></label>
      <input name="phone" value="<?= e($phone) ?>" required>
    </div>
    <div class="form-group" style="grid-column:1/-1">
      <button type="submit" class="btn btn-primary"><?= e(__('track_order')) ?></button>
    </div>
  </form>

  <?php if ($lookupError !== ''): ?>
    <div class="alert alert-error" style="margin-top:1rem"><?= e($lookupError) ?></div>
  <?php endif; ?>

  <?php if ($order): ?>
    <div class="track-result" style="margin-top:1.5rem">
      <p><strong><?= e(__('order_number')) ?>:</strong> <?= e($order['order_number']) ?></p>
      <p><strong><?= e(__('status')) ?>:</strong> <?= orderStatusBadge((string) $order['status']) ?></p>
      <p><strong><?= e(__('payment_method')) ?>:</strong> <?= e(shopPaymentMethodLabel((string) ($order['payment_method'] ?? 'cod'))) ?></p>
      <p><strong><?= e(__('date')) ?>:</strong> <?= formatDate($order['created_at']) ?></p>

      <table class="cart-table" style="margin-top:1rem">
        <thead><tr><th><?= e(__('product')) ?></th><th><?= e(__('quantity')) ?></th><th><?= e(__('total')) ?></th></tr></thead>
        <tbody>
          <?php foreach ($items as $item): ?>
          <tr>
            <td><?= e($item['description']) ?></td>
            <td><?= (int) $item['quantity'] ?></td>
            <td><?= formatMoney((float) $item['total']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <?php if ((float) ($order['delivery_fee'] ?? 0) > 0): ?>
        <p style="margin-top:0.75rem"><?= e(__('delivery_fee')) ?>: <?= formatMoney((float) $order['delivery_fee']) ?></p>
      <?php endif; ?>
      <p style="margin-top:0.75rem;font-weight:700"><?= e(__('total')) ?>: <?= formatMoney((float) $order['total']) ?></p>

      <div style="margin-top:1rem;display:flex;gap:0.5rem;flex-wrap:wrap">
        <?= orderWhatsAppButton($order, $items, 'btn btn-whatsapp') ?>
        <?php if (shopContactPhone() !== ''): ?>
          <a href="tel:<?= e(preg_replace('/\s+/', '', shopContactPhone())) ?>" class="btn btn-secondary"><?= e(__('call_store')) ?></a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
