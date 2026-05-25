<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requirePermission(PERM_INVOICES);

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

$pageTitle = $invoice['invoice_number'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-actions no-print">
    <a href="<?= url('invoices/index.php') ?>" class="btn btn-secondary"><?= e(__('cancel')) ?></a>
    <a href="<?= url('invoices/create.php') ?>" class="btn btn-primary"><?= e(__('new_sale')) ?></a>
    <?php if (canDelete()): ?>
    <a href="<?= url('invoices/index.php?delete=' . $id) ?>" class="btn btn-danger" data-confirm="<?= e(__('confirm_delete')) ?>"><?= e(__('delete')) ?></a>
    <?php endif; ?>
    <button type="button" onclick="window.print()" class="btn btn-secondary"><?= e(__('print')) ?></button>
</div>

<article class="invoice-print card">
    <header class="invoice-print-head">
        <div class="invoice-print-brand">
            <?= companyLogoHtml('company-logo company-logo--invoice') ?>
            <p class="invoice-brand-text">cloud&amp;more</p>
        </div>
        <div class="invoice-print-meta">
            <h1><?= e(__('sales_invoice')) ?></h1>
            <p><strong><?= e(__('invoice_number')) ?>:</strong> <?= e($invoice['invoice_number']) ?></p>
            <p><strong><?= e(__('date')) ?>:</strong> <?= formatDate($invoice['created_at']) ?></p>
            <p><?= paymentMethodBadge($invoice['payment_method'] ?? 'cash') ?></p>
        </div>
    </header>

    <?php if (!empty($invoice['customer_name']) && $invoice['customer_name'] !== __('walk_in_customer')): ?>
    <section class="invoice-print-customer">
        <strong><?= e(__('customer')) ?>:</strong>
        <?= e($invoice['customer_name']) ?>
        <?php if (!empty($invoice['phone'])): ?> · <?= e($invoice['phone']) ?><?php endif; ?>
    </section>
    <?php endif; ?>

    <table class="invoice-print-table">
        <thead>
            <tr>
                <th>#</th>
                <th><?= e(__('product')) ?></th>
                <th><?= e(__('serial_number')) ?></th>
                <th class="num"><?= e(__('quantity')) ?></th>
                <th class="num"><?= e(__('price')) ?></th>
                <th class="num"><?= e(__('total')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $i => $row): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= e($row['description']) ?></td>
                <td><code class="serial-code"><?= e($row['serial_number'] ?? '-') ?></code></td>
                <td class="num"><?= (int) $row['quantity'] ?></td>
                <td class="num"><?= formatMoney((float) $row['unit_price']) ?></td>
                <td class="num"><?= formatMoney((float) $row['total']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <?php if ((float) ($invoice['discount'] ?? 0) > 0): ?>
            <tr>
                <td colspan="5" class="invoice-total-label"><?= e(__('subtotal')) ?></td>
                <td class="num"><?= formatMoney((float) $invoice['subtotal']) ?></td>
            </tr>
            <tr>
                <td colspan="5" class="invoice-total-label"><?= e(__('discount')) ?></td>
                <td class="num"><?= formatMoney((float) $invoice['discount']) ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td colspan="5" class="invoice-total-label"><?= e(__('total')) ?></td>
                <td class="num invoice-total-value"><?= formatMoney((float) $invoice['total']) ?></td>
            </tr>
        </tfoot>
    </table>

    <?php if (!empty($invoice['notes'])): ?>
    <p class="invoice-print-notes"><strong><?= e(__('notes')) ?>:</strong> <?= e($invoice['notes']) ?></p>
    <?php endif; ?>

    <footer class="invoice-print-footer">
        <p><?= e(__('invoice_thanks')) ?></p>
    </footer>
</article>
<?php require __DIR__ . '/../includes/footer.php'; ?>
