<?php
require_once __DIR__ . '/../includes/functions.php';
$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM products WHERE id = ? AND is_published = 1 AND quantity > 0');
$stmt->execute([$id]);
$product = $stmt->fetch();
if (!$product) { redirect(shopUrl()); }
$pageTitle = productName($product);
require __DIR__ . '/includes/header.php';
?>
<div class="product-detail">
  <img src="<?= productImageUrl($product) ?>" alt="<?= e(productName($product)) ?>">
  <div>
    <h1><?= e(productName($product)) ?></h1>
    <p class="text-muted"><?= e($product['sku']) ?> · <?= e($product['category']) ?></p>
    <p class="product-price" style="font-size:1.5rem;margin:1rem 0"><?= formatMoney((float)$product['sell_price']) ?></p>
    <p><?= e(isRtl() ? ($product['description_ar'] ?: '') : ($product['description_en'] ?: '')) ?></p>
    <p style="margin:1rem 0"><?= e(__('in_stock')) ?>: <?= (int)$product['quantity'] ?></p>
    <form method="post" action="<?= shopUrl('cart.php') ?>" style="display:flex;gap:0.5rem;align-items:center;margin-top:1rem">
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
      <input type="number" name="qty" value="1" min="1" max="<?= (int)$product['quantity'] ?>" style="width:80px;padding:0.5rem">
      <button type="submit" class="btn btn-primary"><?= e(__('add_to_cart')) ?></button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
