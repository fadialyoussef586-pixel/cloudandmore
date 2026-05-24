<?php
require_once __DIR__ . '/../includes/functions.php';
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if (isset($_GET['remove'])) {
    unset($_SESSION['cart'][(int) $_GET['remove']]);
    redirect(shopUrl('cart.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $pid = (int) ($_POST['product_id'] ?? 0);
    $qty = max(1, (int) ($_POST['qty'] ?? 1));
    if ($action === 'add' && $pid) {
        $_SESSION['cart'][$pid] = ($_SESSION['cart'][$pid] ?? 0) + $qty;
        flash('success', __('success_saved'));
        redirect(shopUrl('cart.php'));
    }
    if ($action === 'update') {
        foreach ($_POST['quantities'] ?? [] as $id => $q) {
            $id = (int) $id;
            $q = (int) $q;
            if ($q <= 0) {
                unset($_SESSION['cart'][$id]);
            } else {
                $_SESSION['cart'][$id] = $q;
            }
        }
        redirect(shopUrl('cart.php'));
    }
}

$pageTitle = __('cart');
$items = [];
$total = 0;
foreach ($_SESSION['cart'] as $pid => $qty) {
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ? AND is_published = 1');
    $stmt->execute([(int) $pid]);
    if ($p = $stmt->fetch()) {
        $line = (float) $p['sell_price'] * $qty;
        $items[] = ['product' => $p, 'qty' => $qty, 'line' => $line];
        $total += $line;
    }
}
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
<td><?= e(productName($p)) ?></td>
<td><?= formatMoney((float) $p['sell_price']) ?></td>
<td><input type="number" name="quantities[<?= $p['id'] ?>]" value="<?= $row['qty'] ?>" min="1" max="<?= (int) $p['quantity'] ?>" style="width:70px"></td>
<td><?= formatMoney($row['line']) ?></td>
<td><a href="<?= shopUrl('cart.php?remove=' . $p['id']) ?>" class="btn btn-danger btn-sm">×</a></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<p style="text-align:end;margin:1rem 0;font-size:1.2rem"><strong><?= e(__('total')) ?>: <?= formatMoney($total) ?></strong></p>
<button type="submit" class="btn btn-secondary"><?= e(__('save')) ?></button>
<a href="<?= shopUrl('checkout.php') ?>" class="btn btn-primary"><?= e(__('checkout')) ?></a>
</form>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
