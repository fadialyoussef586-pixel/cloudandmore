<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requirePermission(PERM_INVOICES);

ensureInvoiceSchema();
ensureTreasuryTables();

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

$itemsStmt = db()->prepare('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id ASC');
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll();

$itemCount = count($items);
$totalQuantity = 0;
foreach ($items as $item) {
    $totalQuantity += (int) ($item['quantity'] ?? 0);
}

$customerNameDisplay = trim((string) ($invoice['customer_name'] ?? ''));
if ($customerNameDisplay === '') {
    $customerNameDisplay = __('walk_in_customer');
}
$customerPhone = trim((string) ($invoice['phone'] ?? ''));
$hasDiscount = (float) ($invoice['discount'] ?? 0) > 0;

$pageTitle = $invoice['invoice_number'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-actions no-print">
    <a href="<?= url('invoices/index.php') ?>" class="btn btn-secondary"><?= e(__('cancel')) ?></a>
    <a href="<?= url('invoices/create.php') ?>" class="btn btn-primary"><?= e(__('new_sale')) ?></a>
    <a href="<?= url('invoices/print.php?id=' . $id) ?>" class="btn btn-secondary" target="_blank" rel="noopener"><?= e(__('print')) ?></a>
    <a href="<?= url('invoices/view.php?id=' . $id) ?>" class="btn btn-secondary"><?= e(__('returns_and_exchanges')) ?></a>
    <?php if (($invoice['payment_method'] ?? '') === 'deferred' && ($invoice['status'] ?? '') !== 'paid' && ($invoice['invoice_type'] ?? 'sale') === 'sale'): ?>
        <a href="<?= url('invoices/view.php?id=' . $id . '&pay=1') ?>" class="btn btn-success"><?= e(__('mark_paid')) ?></a>
    <?php endif; ?>
</div>

<article class="invoice-print card">
    <section class="invoice-print-hero">
        <div class="invoice-print-hero__brand">
            <?= companyLogoHtml('company-logo company-logo--invoice') ?>
            <div>
                <p class="invoice-brand-text">cloud&amp;more</p>
                <p class="invoice-brand-subtitle"><?= e(COMPANY_TAGLINE) ?></p>
            </div>
        </div>
        <div class="invoice-print-hero__total">
            <span><?= e(__('total')) ?></span>
            <strong><?= formatMoney((float) $invoice['total']) ?></strong>
            <div class="invoice-hero-badges">
                <?= invoiceTypeBadge($invoice['invoice_type'] ?? 'sale') ?>
                <?php if (($invoice['invoice_type'] ?? 'sale') === 'sale'): ?>
                    <?= paymentMethodBadge($invoice['payment_method'] ?? 'cash') ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <header class="invoice-print-head">
        <div class="invoice-print-meta">
            <h1><?= e(invoiceTitleLabel($invoice['invoice_type'] ?? 'sale')) ?></h1>
            <div class="invoice-meta-grid">
                <div class="invoice-meta-card">
                    <span><?= e(__('invoice_number')) ?></span>
                    <strong><?= e($invoice['invoice_number']) ?></strong>
                </div>
                <div class="invoice-meta-card">
                    <span><?= e(__('date')) ?></span>
                    <strong><?= formatDate($invoice['created_at']) ?></strong>
                </div>
                <div class="invoice-meta-card">
                    <span><?= e(__('payment_method')) ?></span>
                    <strong><?= e(paymentMethodLabel($invoice['payment_method'] ?? 'cash')) ?></strong>
                </div>
            </div>
        </div>
        <aside class="invoice-customer-card">
            <span class="invoice-section-label"><?= e(__('customer')) ?></span>
            <strong class="invoice-customer-name"><?= e($customerNameDisplay) ?></strong>
            <div class="invoice-customer-detail">
                <span><?= e(__('phone')) ?></span>
                <strong><?= e($customerPhone !== '' ? $customerPhone : '-') ?></strong>
            </div>
        </aside>
    </header>

    <section class="invoice-summary-grid">
        <div class="invoice-summary-card">
            <span><?= e(__('items')) ?></span>
            <strong><?= $itemCount ?></strong>
        </div>
        <div class="invoice-summary-card">
            <span><?= e(__('quantity')) ?></span>
            <strong><?= $totalQuantity ?></strong>
        </div>
        <div class="invoice-summary-card">
            <span><?= e(__('subtotal')) ?></span>
            <strong><?= formatMoney((float) $invoice['subtotal']) ?></strong>
        </div>
        <?php if ($hasDiscount): ?>
        <div class="invoice-summary-card">
            <span><?= e(__('discount')) ?></span>
            <strong><?= formatMoney((float) $invoice['discount']) ?></strong>
        </div>
        <?php endif; ?>
    </section>

    <?php if ($customerPhone !== '' || !empty($invoice['notes'])): ?>
    <section class="invoice-inline-note">
        <?php if ($customerPhone !== ''): ?>
            <span><strong><?= e(__('phone')) ?>:</strong> <?= e($customerPhone) ?></span>
        <?php endif; ?>
        <?php if (!empty($invoice['notes'])): ?>
            <span><strong><?= e(__('notes')) ?>:</strong> <?= e($invoice['notes']) ?></span>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <div class="invoice-print-table-wrap">
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
                <?php foreach ($items as $index => $item): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><?= e($item['description']) ?></td>
                    <td><code class="serial-code"><?= e($item['serial_number'] ?? '-') ?></code></td>
                    <td class="num"><?= (int) $item['quantity'] ?></td>
                    <td class="num"><?= formatMoney((float) $item['unit_price']) ?></td>
                    <td class="num"><?= formatMoney((float) $item['total']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <?php if ($hasDiscount): ?>
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
    </div>

    <footer class="invoice-print-footer">
        <p class="invoice-footer-title"><?= e(__('invoice_thanks')) ?></p>
        <p><?= e(COMPANY_NAME) ?></p>
    </footer>
</article>

<?php require __DIR__ . '/../includes/footer.php'; ?>
