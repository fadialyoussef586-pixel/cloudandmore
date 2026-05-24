<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requirePermission(PERM_ORDERS);
$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM orders WHERE id = ?');
$stmt->execute([$id]);
$order = $stmt->fetch();
if (!$order) redirect(url('orders/index.php'));
$items = db()->prepare('SELECT oi.*, p.image, p.name_ar, p.name_en FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE order_id=?');
$items->execute([$id]);
$items = $items->fetchAll();
$pageTitle = $order['order_number'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-actions">
<a href="<?= url('orders/index.php') ?>" class="btn btn-secondary"><?= e(__('cancel')) ?></a>
<?php if ($order['status']==='new' && canAccessOrders()): ?>
<a href="<?= url('orders/sales.php?confirm='.$id) ?>" class="btn btn-success"><?= e(__('confirm_order')) ?></a>
<?php endif; ?>
<?php if ($order['status']==='confirmed' && canAccessOrders()): ?>
<a href="<?= url('orders/sales.php?send_delivery='.$id) ?>" class="btn btn-primary"><?= e(__('send_to_delivery')) ?></a>
<?php endif; ?>
</div>
<div class="card"><div class="card-body">
<h2><?= e($order['order_number']) ?> <?= orderStatusBadge($order['status']) ?></h2>
<p><strong><?= e(__('customer')) ?>:</strong> <?= e($order['customer_name']) ?> | <?= e($order['customer_phone']) ?></p>
<p><strong><?= e(__('email')) ?>:</strong> <?= e($order['customer_email'] ?: '-') ?></p>
<p><strong><?= e(__('address')) ?>:</strong> <?= e($order['delivery_address']) ?></p>
<table style="margin-top:1.5rem"><thead><tr><th></th><th><?= e(__('product')) ?></th><th><?= e(__('quantity')) ?></th><th><?= e(__('total')) ?></th></tr></thead><tbody>
<?php foreach ($items as $item): ?>
<tr>
<td><?php if ($item['product_id']): ?><img src="<?= productImageUrl($item) ?>" style="width:48px;height:48px;object-fit:cover;border-radius:6px"><?php endif; ?></td>
<td><?= e($item['description']) ?></td>
<td><?= (int)$item['quantity'] ?></td>
<td><?= formatMoney((float)$item['total']) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<p style="text-align:end;margin-top:1rem"><strong><?= e(__('total')) ?>: <?= formatMoney((float)$order['total']) ?></strong></p>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
