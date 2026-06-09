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
    <?= invoiceWhatsAppButton($id, $invoice, $items, 'btn btn-whatsapp') ?>
    <a href="<?= url('invoices/view.php?id=' . $id) ?>" class="btn btn-secondary"><?= e(__('returns_and_exchanges')) ?></a>
    <?php if (!empty($invoice['customer_id']) && can(PERM_CUSTOMERS)): ?>
        <a href="<?= url('customers/view.php?id=' . (int) $invoice['customer_id']) ?>" class="btn btn-secondary"><?= e(__('cust_view_profile')) ?></a>
    <?php endif; ?>
    <?php if (invoiceAwaitingPayment($invoice)): ?>
        <a href="<?= url('invoices/index.php?pay=' . $id) ?>" class="btn btn-success" data-confirm="<?= e(__('confirm_mark_paid')) ?>"><?= e(__('mark_paid')) ?></a>
    <?php endif; ?>
</div>

<article class="invoice-print card">
    <section class="invoice-print-hero">
        <div class="invoice-print-hero__brand">
            <?= companyLogoHtml('company-logo company-logo--invoice') ?>
            <div>
                <p class="invoice-brand-text">cloud&amp;more</p>
                <p class="invoice-brand-subtitle"><?= e(companyTagline()) ?></p>
            </div>
        </div>
        <div class="invoice-print-hero__total">
            <span><?= e(__('total')) ?></span>
            <strong><?= formatMoney((float) $invoice['total']) ?></strong>
            <div class="invoice-hero-badges">
                <?= invoiceTypeBadge($invoice['invoice_type'] ?? 'sale') ?>
                <?= invoicePaymentStatusBadge($invoice) ?>
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
            <strong class="invoice-customer-name">
                <?php if (!empty($invoice['customer_id']) && can(PERM_CUSTOMERS)): ?>
                    <a href="<?= url('customers/view.php?id=' . (int) $invoice['customer_id']) ?>"><?= e($customerNameDisplay) ?></a>
                <?php else: ?>
                    <?= e($customerNameDisplay) ?>
                <?php endif; ?>
            </strong>
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

    <?php renderInvoiceItemsTable($items, $invoice); ?>

    <footer class="invoice-print-footer">
        <?php
        $invoiceNumber = $invoice['invoice_number'] ?? '';
        $footer = invoiceProfessionalFooterLines($invoiceNumber);
        ?>
        <p class="invoice-footer-title"><?= e($footer['title']) ?></p>
        <p class="invoice-footer-tagline"><?= e($footer['tagline']) ?></p>
        <p class="invoice-footer-notice"><?= e($footer['notice']) ?></p>
        <p class="invoice-footer-support"><?= e($footer['support']) ?></p>
    </footer>
</article>

<?php require __DIR__ . '/../includes/footer.php'; ?>
