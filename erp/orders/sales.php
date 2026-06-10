<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requirePermission(PERM_ORDERS);
if (!canAccessOrders()) { redirect(homeUrlForRole($_SESSION['user_role'] ?? '')); }
ensureShopSchema();
$pageTitle = __('sales_orders');

if (isset($_GET['confirm'])) {
    $id = (int)$_GET['confirm'];
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND status = 'new' FOR UPDATE");
        $stmt->execute([$id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            throw new RuntimeException('order_not_found');
        }

        $pdo->prepare("UPDATE orders SET status='confirmed', sales_user_id=?, confirmed_at=NOW() WHERE id=?")
            ->execute([$_SESSION['user_id'], $id]);
        deductOrderStock($id, $pdo);
        createInvoiceFromOrder($id, (int) ($_SESSION['user_id'] ?? 0), $pdo);
        $pdo->commit();
        flash('success', __('shop_order_confirmed_invoice'));
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', $e->getMessage() === 'insufficient_stock' ? __('insufficient_stock') : __('error'));
    }
    redirect(url('orders/sales.php'));
}

if (isset($_GET['send_delivery'])) {
    $id = (int)$_GET['send_delivery'];
    $orderStmt = db()->prepare('SELECT * FROM orders WHERE id = ?');
    $orderStmt->execute([$id]);
    $orderRow = $orderStmt->fetch(PDO::FETCH_ASSOC);
    if (!$orderRow || !orderNeedsDelivery($orderRow)) {
        flash('error', __('error'));
        redirect(url('orders/sales.php'));
    }
    $stmt = db()->prepare("UPDATE orders SET status='ready_for_delivery', handed_at=NOW() WHERE id=? AND status='confirmed'");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 1) {
        createDeliveryFromOrder($id, (int)$_SESSION['user_id']);
        flash('success', __('success_saved'));
    } else {
        flash('error', __('error'));
    }
    redirect(url('orders/sales.php'));
}

if (isset($_GET['pickup_complete'])) {
    $id = (int) $_GET['pickup_complete'];
    try {
        markOrderPickupComplete($id);
        flash('success', __('pickup_complete_success'));
    } catch (Throwable) {
        flash('error', __('error'));
    }
    redirect(url('orders/sales.php'));
}

$newOrders = db()->query("SELECT * FROM orders WHERE status='new' ORDER BY created_at ASC")->fetchAll();
$confirmedDelivery = db()->query("SELECT * FROM orders WHERE status='confirmed' AND payment_method <> 'pickup' ORDER BY created_at ASC")->fetchAll();
$confirmedPickup = db()->query("SELECT * FROM orders WHERE status='confirmed' AND payment_method = 'pickup' ORDER BY created_at ASC")->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<h2 style="margin-bottom:1rem"><?= e(__('pending_orders')) ?></h2>
<?php if (empty($newOrders)): ?><p class="text-muted"><?= e(__('no_data')) ?></p><?php else: ?>
<div class="card"><div class="card-body table-wrap">
<table><thead><tr><th><?= e(__('order_number')) ?></th><th><?= e(__('customer')) ?></th><th><?= e(__('phone')) ?></th><th><?= e(__('payment_method')) ?></th><th><?= e(__('total')) ?></th><th><?= e(__('actions')) ?></th></tr></thead><tbody>
<?php foreach ($newOrders as $o): ?>
<tr>
<td><a href="<?= url('orders/view.php?id='.$o['id']) ?>"><?= e($o['order_number']) ?></a></td>
<td><?= e($o['customer_name']) ?></td>
<td><?= e($o['customer_phone']) ?></td>
<td><?= e(shopPaymentMethodLabel((string)($o['payment_method'] ?? 'cod'))) ?></td>
<td><?= formatMoney((float)$o['total']) ?></td>
<td class="table-actions">
<a href="<?= url('orders/sales.php?confirm='.$o['id']) ?>" class="btn btn-success btn-sm" data-confirm="<?= e(__('confirm_order')) ?>"><?= e(__('confirm_order')) ?></a>
<a href="<?= url('orders/view.php?id='.$o['id']) ?>" class="btn btn-secondary btn-sm"><?= e(__('view')) ?></a>
<?= orderWhatsAppButton($o, null, 'btn btn-whatsapp btn-sm') ?>
<?= orderWhatsAppButton($o, null, 'btn btn-secondary btn-sm', false) ?>
</td></tr>
<?php endforeach; ?>
</tbody></table></div></div>
<?php endif; ?>

<h2 style="margin:2rem 0 1rem"><?= e(__('confirmed')) ?> — <?= e(__('send_to_delivery')) ?></h2>
<?php if (empty($confirmedDelivery)): ?><p class="text-muted"><?= e(__('no_data')) ?></p><?php else: ?>
<div class="card"><div class="card-body table-wrap">
<table><thead><tr><th><?= e(__('order_number')) ?></th><th><?= e(__('customer')) ?></th><th><?= e(__('total')) ?></th><th><?= e(__('actions')) ?></th></tr></thead><tbody>
<?php foreach ($confirmedDelivery as $o): ?>
<tr>
<td><?= e($o['order_number']) ?></td><td><?= e($o['customer_name']) ?></td>
<td><?= formatMoney((float)$o['total']) ?></td>
<td><a href="<?= url('orders/sales.php?send_delivery='.$o['id']) ?>" class="btn btn-primary btn-sm"><?= e(__('send_to_delivery')) ?></a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div>
<?php endif; ?>

<h2 style="margin:2rem 0 1rem"><?= e(__('confirmed')) ?> — <?= e(__('shop_pay_pickup')) ?></h2>
<?php if (empty($confirmedPickup)): ?><p class="text-muted"><?= e(__('no_data')) ?></p><?php else: ?>
<div class="card"><div class="card-body table-wrap">
<table><thead><tr><th><?= e(__('order_number')) ?></th><th><?= e(__('customer')) ?></th><th><?= e(__('total')) ?></th><th><?= e(__('actions')) ?></th></tr></thead><tbody>
<?php foreach ($confirmedPickup as $o): ?>
<tr>
<td><?= e($o['order_number']) ?></td><td><?= e($o['customer_name']) ?></td>
<td><?= formatMoney((float)$o['total']) ?></td>
<td><a href="<?= url('orders/sales.php?pickup_complete='.$o['id']) ?>" class="btn btn-success btn-sm" data-confirm="<?= e(__('confirm_pickup_complete')) ?>"><?= e(__('mark_pickup_complete')) ?></a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
