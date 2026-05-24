<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requirePermission(PERM_ORDERS);
if (!canAccessOrders()) { redirect(homeUrlForRole($_SESSION['user_role'] ?? '')); }
$pageTitle = __('orders');
$status = $_GET['status'] ?? '';
$sql = 'SELECT * FROM orders WHERE 1=1';
$params = [];
if ($status !== '') { $sql .= ' AND status = ?'; $params[] = $status; }
$sql .= ' ORDER BY created_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="page-actions">
  <div class="tabs">
    <a href="<?= url('orders/index.php') ?>" class="tab <?= $status===''?'active':'' ?>"><?= e(__('all')) ?></a>
    <a href="<?= url('orders/index.php?status=new') ?>" class="tab <?= $status==='new'?'active':'' ?>"><?= e(__('new')) ?></a>
    <a href="<?= url('orders/index.php?status=confirmed') ?>" class="tab"><?= e(__('confirmed')) ?></a>
    <a href="<?= url('orders/index.php?status=ready_for_delivery') ?>" class="tab"><?= e(__('ready_for_delivery')) ?></a>
    <a href="<?= url('orders/index.php?status=delivered') ?>" class="tab"><?= e(__('delivered')) ?></a>
  </div>
  <a href="<?= url('orders/sales.php') ?>" class="btn btn-primary"><?= e(__('sales_orders')) ?></a>
</div>
<div class="card"><div class="card-body table-wrap">
<table><thead><tr>
<th><?= e(__('order_number')) ?></th><th><?= e(__('customer')) ?></th><th><?= e(__('phone')) ?></th><th><?= e(__('total')) ?></th><th><?= e(__('status')) ?></th><th><?= e(__('date')) ?></th><th><?= e(__('actions')) ?></th>
</tr></thead><tbody>
<?php foreach ($orders as $o): ?>
<tr>
<td><?= e($o['order_number']) ?></td>
<td><?= e($o['customer_name']) ?></td>
<td><?= e($o['customer_phone']) ?></td>
<td><?= formatMoney((float)$o['total']) ?></td>
<td><?= orderStatusBadge($o['status']) ?></td>
<td><?= formatDate($o['created_at']) ?></td>
<td><a href="<?= url('orders/view.php?id='.$o['id']) ?>" class="btn btn-secondary btn-sm"><?= e(__('view')) ?></a></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
