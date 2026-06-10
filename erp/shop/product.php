<?php
require_once __DIR__ . '/../includes/functions.php';
$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM products WHERE id = ? AND is_published = 1 AND quantity > 0');
$stmt->execute([$id]);
$product = $stmt->fetch();
if (!$product) { redirect(shopUrl()); }
$pageTitle = productName($product);
$gallery = productImageUrlsForProduct($id, $product);
require __DIR__ . '/includes/header.php';
?>
<div class="product-detail">
  <div class="product-gallery">
    <img
      id="productMainImage"
      src="<?= e($gallery[0]['url'] ?? productImageUrl($product)) ?>"
      alt="<?= e(productName($product)) ?>"
      class="product-gallery-main"
    >
    <?php if (count($gallery) > 1): ?>
    <div class="product-gallery-thumbs" role="tablist" aria-label="<?= e(__('product_images')) ?>">
      <?php foreach ($gallery as $index => $image): ?>
      <button
        type="button"
        class="product-gallery-thumb<?= $index === 0 ? ' active' : '' ?>"
        data-image="<?= e($image['url']) ?>"
        aria-label="<?= e(__('product_image')) ?> <?= $index + 1 ?>"
      >
        <img src="<?= e($image['url']) ?>" alt="">
      </button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <div>
    <h1><?= e(productName($product)) ?></h1>
    <p class="text-muted"><?= e($product['sku']) ?> · <?= e($product['category']) ?></p>
    <p class="product-price" style="font-size:1.5rem;margin:1rem 0"><?= formatMoney((float)$product['sell_price']) ?></p>
    <?php $desc = productDescription($product); ?>
    <p><?= $desc !== '' ? nl2br(e($desc)) : '<span class="text-muted">' . e(__('no_description')) . '</span>' ?></p>
    <p style="margin:1rem 0"><?= e(__('in_stock')) ?>: <?= (int)$product['quantity'] ?></p>
    <form method="post" action="<?= shopUrl('cart.php') ?>" style="display:flex;gap:0.5rem;align-items:center;margin-top:1rem">
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
      <input type="number" name="qty" value="1" min="1" max="<?= (int)$product['quantity'] ?>" style="width:80px;padding:0.5rem">
      <button type="submit" class="btn btn-primary"><?= e(__('add_to_cart')) ?></button>
    </form>
  </div>
</div>
<?php if (count($gallery) > 1): ?>
<script>
(function () {
  var main = document.getElementById('productMainImage');
  document.querySelectorAll('.product-gallery-thumb').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var src = btn.getAttribute('data-image');
      if (!main || !src) return;
      main.src = src;
      document.querySelectorAll('.product-gallery-thumb').forEach(function (el) {
        el.classList.remove('active');
      });
      btn.classList.add('active');
    });
  });
})();
</script>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
