<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requirePermission(PERM_PURCHASES);

ensurePurchaseSchema();
ensureTreasuryTables();

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare(
    'SELECT p.*, s.name AS supplier_name, s.phone AS supplier_phone
     FROM purchases p
     JOIN suppliers s ON s.id = p.supplier_id
     WHERE p.id = ?'
);
$stmt->execute([$id]);
$purchase = $stmt->fetch();
if (!$purchase) {
    redirect(url('purchases/index.php'));
}

$pageTitle = $purchase['purchase_number'];

require __DIR__ . '/../includes/header.php';
?>
<div class="page-actions no-print">
    <a href="<?= url('purchases/index.php') ?>" class="btn btn-secondary"><?= e(__('cancel')) ?></a>
    <a href="<?= url('purchases/create.php') ?>" class="btn btn-primary"><?= e(__('new_purchase')) ?></a>
    <a href="<?= url('purchases/print.php?id=' . $id) ?>" class="btn btn-secondary" target="_blank" rel="noopener"><?= e(__('print')) ?></a>
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
            <strong><?= formatMoney((float) $purchase['total']) ?></strong>
            <div class="invoice-hero-badges">
                <?= purchasePaymentBadge((string) $purchase['payment_method'], (float) $purchase['debt_balance']) ?>
            </div>
        </div>
    </section>

    <header class="invoice-print-head">
        <div class="invoice-print-meta">
            <h1><?= e(__('new_purchase')) ?></h1>
            <div class="invoice-meta-grid">
                <div class="invoice-meta-card">
                    <span><?= e(__('purchase_number')) ?></span>
                    <strong><?= e($purchase['purchase_number']) ?></strong>
                </div>
                <div class="invoice-meta-card">
                    <span><?= e(__('date')) ?></span>
                    <strong><?= formatDate($purchase['created_at']) ?></strong>
                </div>
                <div class="invoice-meta-card">
                    <span><?= e(__('payment_method')) ?></span>
                    <strong><?= e(purchasePaymentLabel((string) $purchase['payment_method'])) ?></strong>
                </div>
            </div>
        </div>
        <aside class="invoice-customer-card">
            <span class="invoice-section-label"><?= e(__('supplier_name')) ?></span>
            <strong class="invoice-customer-name"><?= e($purchase['supplier_name']) ?></strong>
            <div class="invoice-customer-detail">
                <span><?= e(__('phone')) ?></span>
                <strong><?= e($purchase['supplier_phone'] ?? '-') ?></strong>
            </div>
        </aside>
    </header>

    <section class="invoice-summary-grid">
        <div class="invoice-summary-card">
            <span><?= e(__('quantity')) ?></span>
            <strong><?= (int) $purchase['quantity'] ?></strong>
        </div>
        <div class="invoice-summary-card">
            <span><?= e(__('unit_cost')) ?></span>
            <strong><?= formatMoney((float) $purchase['unit_cost']) ?></strong>
        </div>
        <div class="invoice-summary-card">
            <span><?= e(__('amount')) ?></span>
            <strong><?= formatMoney((float) $purchase['amount_paid']) ?></strong>
        </div>
        <div class="invoice-summary-card">
            <span><?= e(__('debt_balance')) ?></span>
            <strong><?= formatMoney((float) $purchase['debt_balance']) ?></strong>
        </div>
    </section>

    <?php if (!empty($purchase['notes'])): ?>
    <section class="invoice-inline-note">
        <span><strong><?= e(__('notes')) ?>:</strong> <?= e($purchase['notes']) ?></span>
    </section>
    <?php endif; ?>

    <div class="invoice-print-table-wrap">
        <table class="invoice-print-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th><?= e(__('product')) ?></th>
                    <th class="num"><?= e(__('quantity')) ?></th>
                    <th class="num"><?= e(__('unit_cost')) ?></th>
                    <th class="num"><?= e(__('total')) ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td><?= e($purchase['description']) ?></td>
                    <td class="num"><?= (int) $purchase['quantity'] ?></td>
                    <td class="num"><?= formatMoney((float) $purchase['unit_cost']) ?></td>
                    <td class="num"><?= formatMoney((float) $purchase['total']) ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <footer class="invoice-print-footer">
        <p class="invoice-footer-title"><?= e(COMPANY_NAME) ?></p>
        <p><?= e(__('suppliers')) ?></p>
    </footer>
</article>

<?php require __DIR__ . '/../includes/footer.php'; ?>
