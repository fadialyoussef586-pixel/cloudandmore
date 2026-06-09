<?php
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = __('shop');
$category = trim($_GET['category'] ?? '');
$search = trim($_GET['q'] ?? '');

$sql = publishedProductsQuery();
$params = [];

if ($category !== '') {
    $sql = str_replace('ORDER BY', 'AND category = ? ORDER BY', $sql);
    $params[] = $category;
}

if ($search !== '') {
    $sql = str_replace('ORDER BY', 'AND (name_ar LIKE ? OR name_en LIKE ? OR sku LIKE ?) ORDER BY', $sql);
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<section class="shop-hero">
  <?= companyLogoWithTagline('company-logo company-logo--hero', false, 'shop-brand-tagline') ?>
  <h1><?= e(__('shop')) ?></h1>
  <p><?= e(__('shop_tagline')) ?></p>
</section>

<form method="get" class="shop-search" action="<?= shopUrl() ?>">
  <?php if ($category !== ''): ?>
    <input type="hidden" name="category" value="<?= e($category) ?>">
  <?php endif; ?>
  <input type="search" name="q" value="<?= e($search) ?>" placeholder="<?= e(__('search_products')) ?>">
  <button type="submit" class="btn btn-primary btn-sm"><?= e(__('search')) ?></button>
</form>

<div class="shop-filters">
  <a href="<?= shopUrl($search !== '' ? '?q=' . urlencode($search) : '') ?>" class="filter-chip <?= $category === '' ? 'active' : '' ?>"><?= e(__('all')) ?></a>
  <?php foreach (productCategories() as $val => $label): ?>
  <a href="<?= shopUrl('?category=' . urlencode($val) . ($search !== '' ? '&q=' . urlencode($search) : '')) ?>" class="filter-chip <?= $category === $val ? 'active' : '' ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<div class="product-grid">
<?php if (empty($products)): ?>
  <p class="text-muted shop-empty"><?= e(__('no_data')) ?></p>
<?php else: foreach ($products as $p): ?>
  <article class="product-card">
    <a href="<?= shopUrl('product.php?id=' . $p['id']) ?>" class="product-card-link">
      <img src="<?= productImageUrl($p) ?>" alt="<?= e(productName($p)) ?>" loading="lazy">
      <h3><?= e(productName($p)) ?></h3>
      <p class="product-price"><?= formatMoney((float) $p['sell_price']) ?></p>
      <span class="stock-badge"><?= e(__('in_stock')) ?>: <?= (int) $p['quantity'] ?></span>
    </a>
    <form method="post" action="<?= shopUrl('cart.php') ?>">
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
      <input type="hidden" name="qty" value="1">
      <button type="submit" class="btn btn-primary btn-sm"><?= e(__('add_to_cart')) ?></button>
    </form>
  </article>
<?php endforeach; endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
