<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requirePermission(PERM_ORDERS);
ensureShopSchema();

$id = (int)($_GET['id'] ?? 0);

if (isset($_GET['cancel']) && canAccessOrders()) {
    try {
        cancelOrder($id, (int) ($_SESSION['user_id'] ?? 0));
        flash('success', __('order_cancelled_success'));
    } catch (Throwable $e) {
        $errors = [
            'order_not_found' => __('error'),
            'order_cannot_cancel' => __('order_cannot_cancel'),
        ];
        flash('error', $errors[$e->getMessage()] ?? __('error'));
    }
    redirect(url('orders/view.php?id=' . $id));
}

if (isset($_GET['create_invoice']) && canAccessOrders()) {
    try {
        $invoiceId = createInvoiceFromOrder($id, (int) ($_SESSION['user_id'] ?? 0));
        flash('success', __('shop_invoice_created'));
        redirect(url('invoices/view.php?id=' . $invoiceId));
    } catch (Throwable $e) {
        flash('error', __('error'));
        redirect(url('orders/view.php?id=' . $id));
    }
}

$stmt = db()->prepare('SELECT * FROM orders WHERE id = ?');
$stmt->execute([$id]);
$order = $stmt->fetch();
if (!$order) redirect(url('orders/index.php'));
$items = fetchOrderItems($id);
$pageTitle = $order['order_number'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-actions">
<a href="<?= url('orders/index.php') ?>" class="btn btn-secondary"><?= e(__('cancel')) ?></a>
<?php if ($order['status']==='new' && canAccessOrders()): ?>
<a href="<?= url('orders/sales.php?confirm='.$id) ?>" class="btn btn-success" data-confirm="<?= e(__('confirm_order')) ?>"><?= e(__('confirm_order')) ?></a>
<?php endif; ?>
<?php if ($order['status']==='confirmed' && canAccessOrders()): ?>
<a href="<?= url('orders/sales.php?send_delivery='.$id) ?>" class="btn btn-primary"><?= e(__('send_to_delivery')) ?></a>
<?php endif; ?>
<?php if (!in_array($order['status'], ['delivered', 'cancelled'], true) && canAccessOrders()): ?>
<a href="<?= url('orders/view.php?id='.$id.'&cancel=1') ?>" class="btn btn-danger" data-confirm="<?= e(__('confirm_cancel_order')) ?>"><?= e(__('cancel_order')) ?></a>
<?php endif; ?>
<?php if (empty($order['invoice_id']) && can(PERM_INVOICES)): ?>
<a href="<?= url('orders/view.php?id='.$id.'&create_invoice=1') ?>" class="btn btn-secondary"><?= e(__('create_invoice_from_order')) ?></a>
<?php endif; ?>
<?php if (!empty($order['invoice_id']) && can(PERM_INVOICES)): ?>
<a href="<?= url('invoices/view.php?id=' . (int) $order['invoice_id']) ?>" class="btn btn-secondary"><?= e(__('view_invoice')) ?></a>
<?php endif; ?>
<?= orderWhatsAppButton($order, $items, 'btn btn-whatsapp') ?>
<?= orderWhatsAppButton($order, $items, 'btn btn-secondary', false) ?>
</div>
<div class="card"><div class="card-body">
<h2><?= e($order['order_number']) ?> <?= orderStatusBadge($order['status']) ?></h2>
<p><strong><?= e(__('customer')) ?>:</strong>
<?php if (!empty($order['customer_id']) && can(PERM_CUSTOMERS)): ?>
<a href="<?= url('customers/view.php?id=' . (int) $order['customer_id']) ?>"><?= e($order['customer_name']) ?></a>
<?php else: ?>
<?= e($order['customer_name']) ?>
<?php endif; ?>
| <?= e($order['customer_phone']) ?></p>
<p><strong><?= e(__('email')) ?>:</strong> <?= e($order['customer_email'] ?: '-') ?></p>
<p><strong><?= e(__('address')) ?>:</strong> <?= e($order['delivery_address']) ?></p>
<p><strong><?= e(__('payment_method')) ?>:</strong> <?= e(shopPaymentMethodLabel((string)($order['payment_method'] ?? 'cod'))) ?></p>
<p><strong><?= e(__('source')) ?>:</strong> <?= e(__($order['source'] ?? 'website')) ?></p>
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
<div style="text-align:end;margin-top:1rem">
  <p><?= e(__('subtotal')) ?>: <?= formatMoney((float)($order['subtotal'] ?? 0)) ?></p>
  <?php if ((float)($order['delivery_fee'] ?? 0) > 0): ?>
  <p><?= e(__('delivery_fee')) ?>: <?= formatMoney((float)$order['delivery_fee']) ?></p>
  <?php endif; ?>
  <p><strong><?= e(__('total')) ?>: <?= formatMoney((float)$order['total']) ?></strong></p>
</div>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
