<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requirePermission(PERM_INVENTORY);
$pageTitle = __('add_product');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sku = trim($_POST['sku']);
    $name = trim($_POST['name'] ?? '');
    $category = resolveProductCategoryFromPost($_POST);
    if ($category === '') {
        flash('error', __('category_required'));
        redirect(url('inventory/add.php'));
    }
    $image = saveProductImage($_FILES['image'] ?? [], $sku);
    $stmt = db()->prepare('INSERT INTO products (sku, name_ar, name_en, description_ar, description_en, category, unit, quantity, min_stock, cost_price, sell_price, image, is_published) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([
        $sku, $name, $name,
        '', '',
        $category, trim($_POST['unit'] ?? 'piece'),
        (int) ($_POST['quantity'] ?? 0), (int) ($_POST['min_stock'] ?? 5),
        (float) ($_POST['cost_price'] ?? 0), (float) ($_POST['sell_price'] ?? 0),
        $image, isset($_POST['is_published']) ? 1 : 0,
    ]);
    flash('success', __('success_saved'));
    redirect(url('inventory/index.php'));
}

require __DIR__ . '/../includes/header.php';
$existingCategories = productCategories();
?>
<div class="card"><div class="card-body">
<form method="post" enctype="multipart/form-data">
<div class="form-grid">
    <div class="form-group"><label><?= e(__('sku')) ?></label><input name="sku" value="<?= e($_POST['sku'] ?? '') ?>" required></div>
    <div class="form-group"><label><?= e(__('product_name')) ?></label><input name="name" value="<?= e($_POST['name'] ?? '') ?>" required></div>
    <div class="form-group" style="grid-column:1/-1">
        <label><?= e(__('category')) ?> *</label>
        <?php if ($existingCategories !== []): ?>
            <select name="category" style="margin-bottom:0.5rem">
                <option value="">--</option>
                <?php foreach ($existingCategories as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($value === ($_POST['category'] ?? '')) ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="text-muted" style="margin:0 0 0.5rem;font-size:0.85rem"><?= e(__('custom_category_or_pick')) ?></p>
        <?php endif; ?>
        <input name="custom_category" value="<?= e($_POST['custom_category'] ?? '') ?>" placeholder="<?= e(__('custom_category_placeholder')) ?>" <?= $existingCategories === [] ? 'required' : '' ?>>
    </div>
    <div class="form-group"><label><?= e(__('product_image')) ?></label><input type="file" name="image" accept="image/*"></div>
    <div class="form-group"><label><?= e(__('unit')) ?></label><input name="unit" value="<?= e($_POST['unit'] ?? 'piece') ?>"></div>
    <div class="form-group"><label><?= e(__('quantity')) ?></label><input type="number" name="quantity" value="<?= e($_POST['quantity'] ?? '0') ?>" min="0"></div>
    <div class="form-group"><label><?= e(__('min_stock')) ?></label><input type="number" name="min_stock" value="<?= e($_POST['min_stock'] ?? '5') ?>" min="0"></div>
    <div class="form-group"><label><?= e(__('cost_price')) ?></label><input type="number" step="0.01" name="cost_price" value="<?= e($_POST['cost_price'] ?? '0') ?>"></div>
    <div class="form-group"><label><?= e(__('sell_price')) ?></label><input type="number" step="0.01" name="sell_price" value="<?= e($_POST['sell_price'] ?? '0') ?>"></div>
    <div class="form-group"><label><input type="checkbox" name="is_published" value="1" <?= isset($_POST['is_published']) || $_SERVER['REQUEST_METHOD'] !== 'POST' ? 'checked' : '' ?>> <?= e(__('published')) ?></label></div>
</div>
<div class="form-actions">
    <button type="submit" class="btn btn-primary"><?= e(__('save')) ?></button>
    <a href="<?= url('inventory/index.php') ?>" class="btn btn-secondary"><?= e(__('cancel')) ?></a>
</div>
</form>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
