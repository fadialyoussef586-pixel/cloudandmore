<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();

ensureInvoiceSchema();

$id = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare(
    "SELECT i.*, c.name AS customer_name, c.phone
     FROM invoices i
     JOIN customers c ON c.id = i.customer_id
     WHERE i.id = ?"
);
$stmt->execute([$id]);
$invoice = $stmt->fetch();
if (!$invoice) {
    redirect(url('invoices/index.php'));
}

$items = db()->prepare('SELECT * FROM invoice_items WHERE invoice_id = ?');
$items->execute([$id]);
$items = $items->fetchAll();
$item = $items[0] ?? null;

$pageTitle = $invoice['invoice_number'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-actions">
    <a href="<?= url('invoices/index.php') ?>" class="btn btn-secondary"><?= e(__('cancel')) ?></a>
    <a href="<?= url('invoices/create.php') ?>" class="btn btn-primary"><?= e(__('new_sale')) ?></a>
    <button type="button" onclick="window.print()" class="btn btn-secondary"><?= e(__('print')) ?></button>
</div>

<div class="card sale-receipt">
    <div class="card-body">
        <div class="sale-receipt-header">
            <div>
                <h2><?= e(__('invoice_number')) ?>: <?= e($invoice['invoice_number']) ?></h2>
                <p class="text-muted"><?= formatDate($invoice['created_at']) ?></p>
            </div>
            <?= paymentMethodBadge($invoice['payment_method'] ?? 'cash') ?>
        </div>

        <?php if ($item): ?>
        <dl class="sale-receipt-details">
            <div><dt><?= e(__('product')) ?></dt><dd><?= e($item['description']) ?></dd></div>
            <div><dt><?= e(__('quantity')) ?></dt><dd><?= (int) $item['quantity'] ?></dd></div>
            <div><dt><?= e(__('serial_number')) ?></dt><dd><code class="serial-code"><?= e($item['serial_number'] ?? '-') ?></code></dd></div>
            <div><dt><?= e(__('payment_method')) ?></dt><dd><?= e(paymentMethodLabel($invoice['payment_method'] ?? 'cash')) ?></dd></div>
            <?php if (!empty($invoice['customer_name']) && $invoice['customer_name'] !== __('walk_in_customer')): ?>
            <div><dt><?= e(__('customer')) ?></dt><dd><?= e($invoice['customer_name']) ?><?= $invoice['phone'] ? ' · ' . e($invoice['phone']) : '' ?></dd></div>
            <?php endif; ?>
        </dl>

        <div class="sale-receipt-total">
            <strong><?= e(__('total')) ?>: <?= formatMoney((float) $invoice['total']) ?></strong>
        </div>
        <?php endif; ?>

        <?php if (!empty($invoice['notes'])): ?>
        <p class="text-muted" style="margin-top:1rem"><strong><?= e(__('notes')) ?>:</strong> <?= e($invoice['notes']) ?></p>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
