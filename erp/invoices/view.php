<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();

$id = (int) ($_GET['id'] ?? 0);
if (isset($_GET['action']) && $_GET['action'] === 'pay') {
    db()->prepare("UPDATE invoices SET status = 'paid' WHERE id = ?")->execute([$id]);
    flash('success', __('success_saved'));
    redirect(url('invoices/view.php?id=' . $id));
}

$stmt = db()->prepare("SELECT i.*, c.name AS customer_name, c.phone, c.address FROM invoices i JOIN customers c ON c.id = i.customer_id WHERE i.id = ?");
$stmt->execute([$id]);
$invoice = $stmt->fetch();
if (!$invoice) redirect(url('invoices/index.php'));

$items = db()->prepare('SELECT * FROM invoice_items WHERE invoice_id = ?');
$items->execute([$id]);
$items = $items->fetchAll();
$pageTitle = $invoice['invoice_number'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-actions">
<a href="<?= url('invoices/index.php') ?>" class="btn btn-secondary"><?= e(__('cancel')) ?></a>
<?php if ($invoice['status'] !== 'paid'): ?>
<a href="<?= url('invoices/view.php?id='.$id.'&action=pay') ?>" class="btn btn-success"><?= e(__('mark_paid')) ?></a>
<?php endif; ?>
<button onclick="window.print()" class="btn btn-primary"><?= e(__('print')) ?></button>
</div>
<div class="card"><div class="card-body">
<h2><?= e(__('invoice_number')) ?>: <?= e($invoice['invoice_number']) ?></h2>
<p><strong><?= e(__('customer')) ?>:</strong> <?= e($invoice['customer_name']) ?> | <?= e($invoice['phone']) ?></p>
<p><strong><?= e(__('address')) ?>:</strong> <?= e($invoice['address']) ?></p>
<p><strong><?= e(__('date')) ?>:</strong> <?= formatDate($invoice['created_at']) ?> | <strong><?= e(__('status')) ?>:</strong> <?= statusBadge($invoice['status']) ?></p>
<table style="margin-top:1.5rem"><thead><tr><th><?= e(__('description')) ?></th><th><?= e(__('quantity')) ?></th><th><?= e(__('price')) ?></th><th><?= e(__('total')) ?></th></tr></thead>
<tbody>
<?php foreach ($items as $item): ?>
<tr><td><?= e($item['description']) ?></td><td><?= (int)$item['quantity'] ?></td><td><?= formatMoney((float)$item['unit_price']) ?></td><td><?= formatMoney((float)$item['total']) ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<div style="margin-top:1.5rem;text-align:end">
<p><?= e(__('subtotal')) ?>: <?= formatMoney((float)$invoice['subtotal']) ?></p>
<p><?= e(__('tax')) ?> (<?= e($invoice['tax_rate']) ?>%): <?= formatMoney((float)$invoice['tax_amount']) ?></p>
<p><?= e(__('discount')) ?>: <?= formatMoney((float)$invoice['discount']) ?></p>
<p><strong><?= e(__('total')) ?>: <?= formatMoney((float)$invoice['total']) ?></strong></p>
</div>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
