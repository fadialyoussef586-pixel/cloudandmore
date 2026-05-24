<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
$pageTitle = __('invoices');

$invoices = db()->query("SELECT i.*, c.name AS customer_name FROM invoices i JOIN customers c ON c.id = i.customer_id ORDER BY i.created_at DESC")->fetchAll();
require __DIR__ . '/../includes/header.php';
?>
<div class="page-actions">
    <span></span>
    <a href="<?= url('invoices/create.php') ?>" class="btn btn-primary"><?= e(__('create_invoice')) ?></a>
</div>
<div class="card"><div class="card-body table-wrap">
<table><thead><tr>
<th><?= e(__('invoice_number')) ?></th><th><?= e(__('customer')) ?></th><th><?= e(__('total')) ?></th><th><?= e(__('status')) ?></th><th><?= e(__('date')) ?></th><th><?= e(__('actions')) ?></th>
</tr></thead><tbody>
<?php foreach ($invoices as $inv): ?>
<tr>
<td><?= e($inv['invoice_number']) ?></td>
<td><?= e($inv['customer_name']) ?></td>
<td><?= formatMoney((float)$inv['total']) ?></td>
<td><?= statusBadge($inv['status']) ?></td>
<td><?= formatDate($inv['created_at']) ?></td>
<td>
<a href="<?= url('invoices/view.php?id='.$inv['id']) ?>" class="btn btn-secondary btn-sm"><?= e(__('view')) ?></a>
<?php if ($inv['status'] !== 'paid'): ?>
<a href="<?= url('invoices/view.php?id='.$inv['id'].'&action=pay') ?>" class="btn btn-success btn-sm"><?= e(__('mark_paid')) ?></a>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
