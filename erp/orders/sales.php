<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requirePermission(PERM_ORDERS);
if (!canAccessOrders()) { redirect(homeUrlForRole($_SESSION['user_role'] ?? '')); }
$pageTitle = __('sales_orders');

if (isset($_GET['confirm'])) {
    $id = (int)$_GET['confirm'];
    db()->prepare("UPDATE orders SET status='confirmed', sales_user_id=?, confirmed_at=NOW() WHERE id=? AND status='new'")
        ->execute([$_SESSION['user_id'], $id]);
    deductOrderStock($id);
    flash('success', __('success_saved'));
    redirect(url('orders/sales.php'));
}
if (isset($_GET['send_delivery'])) {
    $id = (int)$_GET['send_delivery'];
    db()->prepare("UPDATE orders SET status='ready_for_delivery', handed_at=NOW() WHERE id=? AND status='confirmed'")
        ->execute([$id]);
    createDeliveryFromOrder($id, (int)$_SESSION['user_id']);
    flash('success', __('success_saved'));
    redirect(url('orders/sales.php'));
}

$newOrders = db()->query("SELECT * FROM orders WHERE status='new' ORDER BY created_at ASC")->fetchAll();
$confirmed = db()->query("SELECT * FROM orders WHERE status='confirmed' ORDER BY created_at ASC")->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<h2 style="margin-bottom:1rem"><?= e(__('pending_orders')) ?></h2>
<?php if (empty($newOrders)): ?><p class="text-muted"><?= e(__('no_data')) ?></p><?php else: ?>
<div class="card"><div class="card-body table-wrap">
<table><thead><tr><th><?= e(__('order_number')) ?></th><th><?= e(__('customer')) ?></th><th><?= e(__('phone')) ?></th><th><?= e(__('total')) ?></th><th><?= e(__('actions')) ?></th></tr></thead><tbody>
<?php foreach ($newOrders as $o): ?>
<tr>
<td><a href="<?= url('orders/view.php?id='.$o['id']) ?>"><?= e($o['order_number']) ?></a></td>
<td><?= e($o['customer_name']) ?></td><td><?= e($o['customer_phone']) ?></td>
<td><?= formatMoney((float)$o['total']) ?></td>
<td>
<a href="<?= url('orders/sales.php?confirm='.$o['id']) ?>" class="btn btn-success btn-sm"><?= e(__('confirm_order')) ?></a>
<a href="<?= url('orders/view.php?id='.$o['id']) ?>" class="btn btn-secondary btn-sm"><?= e(__('view')) ?></a>
</td></tr>
<?php endforeach; ?>
</tbody></table></div></div>
<?php endif; ?>

<h2 style="margin:2rem 0 1rem"><?= e(__('confirmed')) ?> — <?= e(__('send_to_delivery')) ?></h2>
<?php if (empty($confirmed)): ?><p class="text-muted"><?= e(__('no_data')) ?></p><?php else: ?>
<div class="card"><div class="card-body table-wrap">
<table><thead><tr><th><?= e(__('order_number')) ?></th><th><?= e(__('customer')) ?></th><th><?= e(__('total')) ?></th><th><?= e(__('actions')) ?></th></tr></thead><tbody>
<?php foreach ($confirmed as $o): ?>
<tr>
<td><?= e($o['order_number']) ?></td><td><?= e($o['customer_name']) ?></td>
<td><?= formatMoney((float)$o['total']) ?></td>
<td><a href="<?= url('orders/sales.php?send_delivery='.$o['id']) ?>" class="btn btn-primary btn-sm"><?= e(__('send_to_delivery')) ?></a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
