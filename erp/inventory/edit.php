<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requirePermission(PERM_INVENTORY);

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
$stmt->execute([$id]);
$product = $stmt->fetch();
if (!$product) {
    redirect(url('inventory/index.php'));
}

if (isset($_GET['delete_image'])) {
    deleteProductImage((int) $_GET['delete_image'], $id);
    flash('success', __('success_deleted'));
    redirect(url('inventory/edit.php?id=' . $id));
}

if (isset($_GET['primary_image'])) {
    setPrimaryProductImage((int) $_GET['primary_image'], $id);
    flash('success', __('success_saved'));
    redirect(url('inventory/edit.php?id=' . $id));
}

$pageTitle = __('edit');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sku = trim($_POST['sku']);
    $nameAr = trim($_POST['name_ar'] ?? '');
    $nameEn = trim($_POST['name_en'] ?? '');
    $descAr = trim($_POST['description_ar'] ?? '');
    $descEn = trim($_POST['description_en'] ?? '');
    if ($nameAr === '' && $nameEn === '') {
        flash('error', __('product_name_required'));
        redirect(url('inventory/edit.php?id=' . $id));
    }
    $category = resolveProductCategoryFromPost($_POST);
    if ($category === '') {
        flash('error', __('category_required'));
        redirect(url('inventory/edit.php?id=' . $id));
    }

    $stmt = db()->prepare('UPDATE products SET sku=?, name_ar=?, name_en=?, description_ar=?, description_en=?, category=?, unit=?, min_stock=?, cost_price=?, sell_price=?, is_published=? WHERE id=?');
    $stmt->execute([
        $sku, $nameAr, $nameEn, $descAr, $descEn,
        $category, trim($_POST['unit'] ?? 'piece'),
        (int) ($_POST['min_stock'] ?? 5), (float) ($_POST['cost_price'] ?? 0),
        (float) ($_POST['sell_price'] ?? 0),
        isset($_POST['is_published']) ? 1 : 0, $id,
    ]);

    if (isset($_FILES['images'])) {
        addProductImagesFromUploads($_FILES['images'], $sku, $id);
    }

    syncProductCoverImage($id);
    flash('success', __('success_saved'));
    redirect(url('inventory/edit.php?id=' . $id));
}

$productImages = productImages($id);

require __DIR__ . '/../includes/header.php';
$existingCategories = productCategories($product['category'] ?? null);
$currentCategory = trim((string) ($product['category'] ?? ''));
$categoryInList = $currentCategory !== '' && isset($existingCategories[$currentCategory]);
?>
<div class="card"><div class="card-body">
<form method="post" enctype="multipart/form-data">
<div class="form-grid">
    <?php if ($productImages !== []): ?>
    <div class="form-group" style="grid-column:1/-1">
        <label><?= e(__('product_images')) ?></label>
        <div class="product-admin-gallery">
            <?php foreach ($productImages as $image): ?>
            <div class="product-admin-gallery-item">
                <img src="<?= productImageFilenameUrl((string) $image['filename']) ?>" alt="">
                <?php if (!empty($image['is_primary'])): ?>
                    <span class="badge badge-green"><?= e(__('primary_image')) ?></span>
                <?php else: ?>
                    <a href="<?= url('inventory/edit.php?id=' . $id . '&primary_image=' . (int) $image['id']) ?>" class="btn btn-secondary btn-sm"><?= e(__('set_as_primary')) ?></a>
                <?php endif; ?>
                <a href="<?= url('inventory/edit.php?id=' . $id . '&delete_image=' . (int) $image['id']) ?>" class="btn btn-danger btn-sm" data-confirm="<?= e(__('confirm_delete')) ?>"><?= e(__('delete')) ?></a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="form-group"><label><?= e(__('sku')) ?></label><input name="sku" value="<?= e($product['sku']) ?>" required></div>
    <div class="form-group"><label><?= e(__('name_ar')) ?></label><input name="name_ar" value="<?= e($product['name_ar']) ?>"></div>
    <div class="form-group"><label><?= e(__('name_en')) ?></label><input name="name_en" value="<?= e($product['name_en']) ?>"></div>
    <div class="form-group" style="grid-column:1/-1"><label><?= e(__('description_ar')) ?></label><textarea name="description_ar" rows="3"><?= e($product['description_ar'] ?? '') ?></textarea></div>
    <div class="form-group" style="grid-column:1/-1"><label><?= e(__('description_en')) ?></label><textarea name="description_en" rows="3"><?= e($product['description_en'] ?? '') ?></textarea></div>
    <div class="form-group" style="grid-column:1/-1">
        <label><?= e(__('category')) ?> *</label>
        <?php if ($existingCategories !== []): ?>
            <select name="category" style="margin-bottom:0.5rem">
                <option value="">--</option>
                <?php foreach ($existingCategories as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $categoryInList && $currentCategory === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="text-muted" style="margin:0 0 0.5rem;font-size:0.85rem"><?= e(__('custom_category_or_pick')) ?></p>
        <?php endif; ?>
        <input name="custom_category" value="<?= e($categoryInList ? '' : $currentCategory) ?>" placeholder="<?= e(__('custom_category_placeholder')) ?>" <?= $existingCategories === [] ? 'required' : '' ?>>
    </div>
    <div class="form-group" style="grid-column:1/-1">
        <label><?= e(__('add_product_images')) ?></label>
        <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
        <p class="text-muted" style="margin-top:0.35rem;font-size:0.85rem"><?= e(__('product_images_hint')) ?></p>
    </div>
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
    <?php if (!empty($product['is_published']) && (int) $product['quantity'] > 0): ?>
    <a href="<?= shopUrl('product.php?id=' . $id) ?>" class="btn btn-secondary" target="_blank" rel="noopener"><?= e(__('view_on_shop')) ?></a>
    <?php endif; ?>
</div>
</form>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
