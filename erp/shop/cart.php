<?php
require_once __DIR__ . '/../includes/functions.php';
ensureShopSchema();
shopCartInit();

if (isset($_GET['remove'])) {
    unset($_SESSION['cart'][(int) $_GET['remove']]);
    redirect(shopUrl('cart.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $pid = (int) ($_POST['product_id'] ?? 0);
    $qty = max(1, (int) ($_POST['qty'] ?? 1));

    if ($action === 'add' && $pid) {
        shopAddToCart($pid, $qty);
        redirect(shopUrl('cart.php'));
    }

    if ($action === 'update') {
        shopUpdateCartQuantities($_POST['quantities'] ?? []);
        flash('success', __('success_saved'));
        redirect(shopUrl('cart.php'));
    }
}

$pageTitle = __('cart');
$cart = shopCartLines(true);
$items = $cart['items'];
$subtotal = $cart['subtotal'];
$deliveryFee = $cart['delivery_fee'];
$total = $cart['total'];

require __DIR__ . '/includes/header.php';
$flash = getFlash();
?>
<?php if ($flash): ?><div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
<h1><?= e(__('cart')) ?></h1>
<?php if (empty($items)): ?>
<p class="text-muted" style="margin:2rem 0"><?= e(__('empty_cart')) ?></p>
<a href="<?= shopUrl() ?>" class="btn btn-primary"><?= e(__('shop')) ?></a>
<?php else: ?>
<form method="post">
<input type="hidden" name="action" value="update">
<table class="cart-table">
<thead><tr><th></th><th><?= e(__('product')) ?></th><th><?= e(__('price')) ?></th><th><?= e(__('quantity')) ?></th><th><?= e(__('total')) ?></th><th></th></tr></thead>
<tbody>
<?php foreach ($items as $row): $p = $row['product']; ?>
<tr>
<td><img src="<?= productImageUrl($p) ?>" alt=""></td>
<td><a href="<?= shopUrl('product.php?id=' . (int) $p['id']) ?>"><?= e(productName($p)) ?></a></td>
<td><?= formatMoney((float) $p['sell_price']) ?></td>
<td><input type="number" name="quantities[<?= $p['id'] ?>]" value="<?= $row['qty'] ?>" min="1" max="<?= (int) $p['quantity'] ?>" style="width:70px"></td>
<td><?= formatMoney($row['line']) ?></td>
<td><a href="<?= shopUrl('cart.php?remove=' . $p['id']) ?>" class="btn btn-danger btn-sm">×</a></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<div class="cart-summary">
  <p><span><?= e(__('subtotal')) ?></span><strong><?= formatMoney($subtotal) ?></strong></p>
  <p><span><?= e(__('delivery_fee')) ?></span><strong><?= $deliveryFee > 0 ? formatMoney($deliveryFee) : e(__('shop_free_delivery')) ?></strong></p>
  <p class="cart-summary-total"><span><?= e(__('total')) ?></span><strong><?= formatMoney($total) ?></strong></p>
  <?php if (defined('SHOP_FREE_DELIVERY_MIN') && SHOP_FREE_DELIVERY_MIN > 0): ?>
  <p class="text-muted shop-delivery-note"><?= e(__('shop_free_delivery_hint')) ?> <?= formatMoney((float) SHOP_FREE_DELIVERY_MIN) ?></p>
  <?php endif; ?>
</div>
<button type="submit" class="btn btn-secondary"><?= e(__('save')) ?></button>
<a href="<?= shopUrl('checkout.php') ?>" class="btn btn-primary"><?= e(__('checkout')) ?></a>
</form>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
