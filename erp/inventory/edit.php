<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
$stmt->execute([$id]);
$product = $stmt->fetch();
if (!$product) {
    redirect(url('inventory/index.php'));
}

$pageTitle = __('edit');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sku = trim($_POST['sku']);
    $image = saveProductImage($_FILES['image'] ?? [], $sku) ?: $product['image'];
    $stmt = db()->prepare('UPDATE products SET sku=?, name_ar=?, name_en=?, category=?, unit=?, min_stock=?, cost_price=?, sell_price=?, image=?, is_published=? WHERE id=?');
    $stmt->execute([
        $sku, trim($_POST['name_ar']), trim($_POST['name_en']),
        trim($_POST['category'] ?? ''), trim($_POST['unit'] ?? 'piece'),
        (int) ($_POST['min_stock'] ?? 5), (float) ($_POST['cost_price'] ?? 0),
        (float) ($_POST['sell_price'] ?? 0), $image,
        isset($_POST['is_published']) ? 1 : 0, $id,
    ]);
    flash('success', __('success_saved'));
    redirect(url('inventory/index.php'));
}

require __DIR__ . '/../includes/header.php';
?>
<div class="card"><div class="card-body">
<form method="post" enctype="multipart/form-data">
<div class="form-grid">
    <p class="form-group" style="grid-column:1/-1">
        <img src="<?= productImageUrl($product) ?>" alt="" style="width:120px;height:120px;object-fit:cover;border-radius:8px;border:1px solid var(--border)">
    </p>
    <div class="form-group"><label><?= e(__('sku')) ?></label><input name="sku" value="<?= e($product['sku']) ?>" required></div>
    <div class="form-group"><label><?= e(__('name')) ?> (AR)</label><input name="name_ar" value="<?= e($product['name_ar']) ?>" required></div>
    <div class="form-group"><label><?= e(__('name')) ?> (EN)</label><input name="name_en" value="<?= e($product['name_en']) ?>" required></div>
    <div class="form-group"><label><?= e(__('category')) ?></label>
    <select name="category" required>
        <?php foreach (productCategories() as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $product['category'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select></div>
    <div class="form-group"><label><?= e(__('product_image')) ?></label><input type="file" name="image" accept="image/*"></div>
    <div class="form-group"><label><?= e(__('unit')) ?></label><input name="unit" value="<?= e($product['unit']) ?>"></div>
    <div class="form-group"><label><?= e(__('quantity')) ?></label><input type="number" value="<?= (int) $product['quantity'] ?>" disabled></div>
    <div class="form-group"><label><?= e(__('min_stock')) ?></label><input type="number" name="min_stock" value="<?= (int) $product['min_stock'] ?>"></div>
    <div class="form-group"><label><?= e(__('cost_price')) ?></label><input type="number" step="0.01" name="cost_price" value="<?= e($product['cost_price']) ?>"></div>
    <div class="form-group"><label><?= e(__('sell_price')) ?></label><input type="number" step="0.01" name="sell_price" value="<?= e($product['sell_price']) ?>"></div>
    <div class="form-group"><label><input type="checkbox" name="is_published" value="1" <?= !empty($product['is_published']) ? 'checked' : '' ?>> <?= e(__('published')) ?></label></div>
</div>
<div class="form-actions">
    <button type="submit" class="btn btn-primary"><?= e(__('save')) ?></button>
    <a href="<?= url('inventory/index.php') ?>" class="btn btn-secondary"><?= e(__('cancel')) ?></a>
</div>
</form>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
