<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();

$pageTitle = __('inventory');
$search = trim($_GET['q'] ?? '');

$sql = 'SELECT * FROM products WHERE 1=1';
$params = [];
if ($search !== '') {
    $sql .= ' AND (sku LIKE ? OR name_ar LIKE ? OR name_en LIKE ? OR category LIKE ?)';
    $like = '%' . $search . '%';
    $params = [$like, $like, $like, $like];
}
$sql .= ' ORDER BY name_en ASC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<div class="page-actions">
    <form class="search-bar" method="get">
        <input type="text" name="q" placeholder="<?= e(__('search')) ?>..." value="<?= e($search) ?>">
        <button type="submit" class="btn btn-secondary"><?= e(__('search')) ?></button>
    </form>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
        <a href="<?= url('inventory/movement.php') ?>" class="btn btn-secondary"><?= e(__('movements')) ?></a>
        <a href="<?= url('inventory/add.php') ?>" class="btn btn-primary"><?= e(__('add_product')) ?></a>
    </div>
</div>

<div class="card">
    <div class="card-body table-wrap">
        <?php if (empty($products)): ?>
            <p class="text-muted"><?= e(__('no_data')) ?></p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th><?= e(__('sku')) ?></th>
                        <th><?= e(__('name')) ?></th>
                        <th><?= e(__('category')) ?></th>
                        <th><?= e(__('quantity')) ?></th>
                        <th><?= e(__('sell_price')) ?></th>
                        <th><?= e(__('actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?= e($product['sku']) ?></td>
                            <td><?= e(productName($product)) ?></td>
                            <td><?= e($product['category']) ?></td>
                            <td class="<?= $product['quantity'] <= $product['min_stock'] ? 'low-stock' : '' ?>">
                                <?= (int) $product['quantity'] ?> <?= e($product['unit']) ?>
                            </td>
                            <td><?= formatMoney((float) $product['sell_price']) ?></td>
                            <td>
                                <a href="<?= url('inventory/edit.php?id=' . $product['id']) ?>" class="btn btn-secondary btn-sm"><?= e(__('edit')) ?></a>
                                <a href="<?= url('inventory/movement.php?product_id=' . $product['id']) ?>" class="btn btn-secondary btn-sm"><?= e(__('movements')) ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
