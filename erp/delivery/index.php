<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requirePermission(PERM_DELIVERY);
$pageTitle = __('delivery');
$deliveries = db()->query("SELECT d.*, c.name AS customer_name FROM deliveries d LEFT JOIN customers c ON c.id = d.customer_id ORDER BY d.created_at DESC")->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="page-actions"><span></span><a href="<?= url('delivery/create.php') ?>" class="btn btn-primary"><?= e(__('create_delivery')) ?></a></div>
<div class="card"><div class="card-body table-wrap">
<table><thead><tr><th><?= e(__('delivery_number')) ?></th><th><?= e(__('customer')) ?></th><th><?= e(__('driver')) ?></th><th><?= e(__('scheduled_date')) ?></th><th><?= e(__('status')) ?></th><th><?= e(__('actions')) ?></th></tr></thead>
<tbody>
<?php foreach ($deliveries as $d): ?>
<tr>
<td><?= e($d['delivery_number']) ?></td><td><?= e($d['customer_name'] ?? '-') ?></td><td><?= e($d['driver_name']) ?></td>
<td><?= formatDate($d['scheduled_date']) ?></td><td><?= statusBadge($d['status']) ?></td>
<td><a href="<?= url('delivery/view.php?id='.$d['id']) ?>" class="btn btn-secondary btn-sm"><?= e(__('view')) ?></a></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
