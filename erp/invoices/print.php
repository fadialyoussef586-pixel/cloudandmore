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

$itemsStmt = db()->prepare('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id ASC');
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll();

$customerName = trim((string) ($invoice['customer_name'] ?? ''));
if ($customerName === '') {
    $customerName = __('walk_in_customer');
}

$customerPhone = trim((string) ($invoice['phone'] ?? ''));
$hasDiscount = (float) ($invoice['discount'] ?? 0) > 0;
?>
<!DOCTYPE html>
<html lang="<?= lang() ?>" dir="<?= isRtl() ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($invoice['invoice_number']) ?> | <?= e(__('print')) ?></title>
    <style>
        :root {
            --bg: #eef3f8;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --line: #dbe4ee;
            --accent: #0f766e;
            --accent-dark: #0f172a;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: "Inter", "Tajawal", sans-serif;
            background: linear-gradient(180deg, #f4f7fb 0%, var(--bg) 100%);
            color: var(--text);
        }

        .print-shell {
            min-height: 100vh;
            padding: 2rem 1rem;
        }

        .print-actions {
            max-width: 980px;
            margin: 0 auto 1rem;
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0.75rem 1.1rem;
            border-radius: 14px;
            border: 1px solid transparent;
            font: inherit;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent-dark) 0%, var(--accent) 100%);
            color: #fff;
        }

        .btn-secondary {
            background: #fff;
            color: var(--text);
            border-color: var(--line);
        }

        .btn-whatsapp {
            background: #25d366;
            color: #fff;
        }

        .btn-whatsapp:hover {
            background: #1fb855;
            color: #fff;
        }

        .print-card {
            max-width: 980px;
            margin: 0 auto;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 28px 64px rgba(15, 23, 42, 0.12);
        }

        .print-hero {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1.5rem;
            padding: 1.6rem 1.8rem 1.2rem;
            background: linear-gradient(135deg, #0f172a 0%, #0f5b72 55%, #0ea5b7 100%);
            color: #fff;
        }

        .print-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .print-logo {
            width: 72px;
            height: 72px;
            object-fit: contain;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.16);
            padding: 0.45rem;
        }

        .print-brand h1 {
            margin: 0;
            font-size: 1.55rem;
            letter-spacing: 0.02em;
        }

        .print-brand p {
            margin: 0.3rem 0 0;
            color: rgba(255, 255, 255, 0.82);
            font-size: 0.95rem;
        }

        .print-total {
            min-width: 240px;
            padding: 1rem 1.15rem;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.18);
            text-align: end;
        }

        .print-total span {
            display: block;
            font-size: 0.8rem;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.78);
        }

        .print-total strong {
            display: block;
            margin-top: 0.35rem;
            font-size: 2rem;
            line-height: 1.15;
        }

        .print-body {
            padding: 1.35rem 1.8rem 1.8rem;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.4fr) minmax(280px, 0.9fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .meta-cards,
        .customer-card {
            display: grid;
            gap: 0.85rem;
        }

        .meta-row,
        .customer-box,
        .summary-box {
            background: linear-gradient(180deg, #ffffff 0%, #f9fbfd 100%);
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 1rem 1.05rem;
        }

        .meta-row-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.85rem;
        }

        .eyebrow {
            display: block;
            color: var(--muted);
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .value {
            display: block;
            margin-top: 0.35rem;
            font-size: 1rem;
            font-weight: 700;
        }

        .customer-box .value {
            font-size: 1.18rem;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 0.85rem;
            margin-bottom: 1rem;
        }

        .notes-box {
            margin-bottom: 1rem;
            padding: 0.9rem 1rem;
            border: 1px solid var(--line);
            border-radius: 18px;
            background: #fbfdff;
            color: var(--text);
        }

        .items-wrap,
        .invoice-print-table-wrap {
            border: 1px solid var(--line);
            border-radius: 22px;
            overflow: hidden;
            background: #fff;
        }

        .items-wrap .invoice-print-table {
            width: 100%;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 0.9rem 1rem;
            text-align: start;
            border-bottom: 1px solid var(--line);
            vertical-align: middle;
        }

        th {
            background: #f4f8fc;
            color: var(--muted);
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        td.num,
        th.num {
            text-align: end;
            white-space: nowrap;
        }

        tfoot td {
            font-weight: 700;
            background: #fbfdff;
        }

        .total-label {
            text-align: end !important;
        }

        .total-value {
            color: var(--accent-dark);
            font-size: 1.15rem;
        }

        .footer {
            margin-top: 1.2rem;
            padding-top: 1rem;
            border-top: 1px dashed var(--line);
            text-align: center;
            color: var(--muted);
            font-size: 0.82rem;
            line-height: 1.55;
        }

        .footer-title {
            display: block;
            margin-bottom: 0.35rem;
            color: var(--text);
            font-size: 1rem;
            font-weight: 700;
        }

        .footer-tagline {
            color: var(--accent-dark);
            font-weight: 600;
            margin-bottom: 0.35rem;
        }

        .footer-notice,
        .footer-support {
            max-width: 640px;
            margin: 0.35rem auto 0;
        }

        @media (max-width: 768px) {
            .print-shell {
                padding: 0.65rem 0.45rem;
            }

            .print-actions {
                justify-content: stretch;
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 0.45rem;
            }

            .print-actions .btn {
                flex: 1 1 auto;
                width: 100%;
                min-height: 42px;
                font-size: 0.82rem;
            }

            .print-actions .btn-whatsapp,
            .print-actions .btn-primary {
                grid-column: 1 / -1;
            }

            .print-card {
                border-radius: 18px;
            }

            .print-hero,
            .meta-grid {
                flex-direction: column;
            }

            .print-hero {
                padding: 1rem;
                gap: 0.85rem;
            }

            .print-logo {
                width: 52px;
                height: 52px;
            }

            .print-brand h1 {
                font-size: 1.15rem;
            }

            .print-brand p {
                font-size: 0.78rem;
            }

            .print-total {
                width: 100%;
                min-width: 0;
                text-align: start;
            }

            .print-total strong {
                font-size: 1.45rem;
            }

            .print-body {
                padding: 0.75rem;
            }

            .summary-grid {
                display: none;
            }

            .meta-row-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 0.55rem;
            }

            .items-wrap,
            .invoice-print-table-wrap {
                border: none;
                background: transparent;
                overflow: visible;
            }

            .invoice-print-table thead {
                display: none;
            }

            .invoice-print-table tbody tr {
                display: block;
                margin-bottom: 0.65rem;
                border: 1px solid var(--line);
                border-radius: 14px;
                background: #fff;
                overflow: hidden;
            }

            .invoice-print-table tbody td {
                display: flex;
                justify-content: space-between;
                gap: 0.75rem;
                padding: 0.45rem 0.75rem;
                border-bottom: 1px solid var(--line);
                font-size: 0.84rem;
            }

            .invoice-print-table tbody td::before {
                content: attr(data-label);
                font-weight: 700;
                color: var(--muted);
            }

            .invoice-print-table tbody td:first-child {
                display: none;
            }

            .invoice-print-table tfoot {
                display: block;
                border: 1px solid var(--line);
                border-radius: 14px;
                background: #fff;
            }

            .invoice-print-table tfoot .invoice-tfoot-row {
                display: flex;
                justify-content: space-between;
                padding: 0.55rem 0.75rem;
                border-bottom: 1px solid var(--line);
            }

            .invoice-print-table tfoot td[colspan] {
                display: none;
            }

            .footer {
                font-size: 0.76rem;
                margin-top: 0.75rem;
                padding-top: 0.65rem;
            }
        }

        @media print {
            @page {
                margin: 5mm;
                size: A4 portrait;
            }

            body {
                background: #fff;
                font-size: 9pt;
            }

            .no-print {
                display: none !important;
            }

            .print-shell {
                padding: 0;
            }

            .print-card {
                max-width: none;
                border: none;
                border-radius: 0;
                box-shadow: none;
            }

            .print-hero {
                break-inside: avoid;
                padding: 3mm 4mm 2mm;
                color: #000;
                background: #fff;
                border-bottom: 1px solid var(--line);
            }

            .print-logo {
                width: 12mm;
                height: 12mm;
            }

            .print-brand h1 {
                font-size: 11pt;
            }

            .print-brand p,
            .summary-grid {
                display: none !important;
            }

            .print-total strong {
                font-size: 12pt;
            }

            .print-body {
                padding: 2mm 4mm 3mm;
            }

            .meta-grid {
                grid-template-columns: 1fr 0.9fr;
                gap: 2mm;
                margin-bottom: 2mm;
            }

            .meta-row,
            .customer-box {
                padding: 2mm 3mm;
                border-radius: 6px;
            }

            .eyebrow {
                font-size: 7pt;
            }

            .value {
                font-size: 8.5pt;
            }

            .items-wrap,
            .meta-row,
            .customer-box,
            .notes-box {
                box-shadow: none;
            }

            .invoice-print-table thead {
                display: table-header-group;
            }

            .invoice-print-table tbody tr,
            .invoice-print-table tbody td,
            .invoice-print-table tfoot tr,
            .invoice-print-table tfoot td {
                display: table-row;
            }

            .invoice-print-table tbody td,
            .invoice-print-table tfoot td {
                display: table-cell;
            }

            .invoice-print-table tbody td::before {
                content: none;
            }

            .invoice-print-table tbody td:first-child {
                display: table-cell;
            }

            th, td {
                padding: 1mm 1.5mm;
                font-size: 8pt;
            }

            .footer {
                margin-top: 2mm;
                padding-top: 2mm;
                font-size: 7pt;
                line-height: 1.35;
            }

            .footer-title {
                font-size: 8pt;
            }
        }
    </style>
</head>
<body>
    <div class="print-shell">
        <div class="print-actions no-print">
            <a href="<?= url('invoices/preview.php?id=' . $id) ?>" class="btn btn-secondary"><?= e(__('view')) ?></a>
            <?= invoiceWhatsAppButton($id, $invoice, $items, 'btn btn-whatsapp') ?>
            <button type="button" class="btn btn-primary" onclick="window.print(); return false;"><?= e(__('print')) ?></button>
        </div>

        <article class="print-card">
            <section class="print-hero">
                <div class="print-brand">
                    <img src="<?= e(companyLogoUrl()) ?>" alt="<?= e(COMPANY_NAME) ?>" class="print-logo">
                    <div>
                        <h1><?= e(COMPANY_NAME) ?></h1>
                        <p><?= e(companyTagline()) ?></p>
                    </div>
                </div>
                <div class="print-total">
                    <span><?= e(__('total')) ?></span>
                    <strong><?= formatMoney((float) $invoice['total']) ?></strong>
                </div>
            </section>

            <div class="print-body">
                <section class="meta-grid">
                    <div class="meta-cards">
                        <div class="meta-row">
                            <div class="meta-row-grid">
                                <div>
                                    <span class="eyebrow"><?= e(__('invoice_number')) ?></span>
                                    <span class="value"><?= e($invoice['invoice_number']) ?></span>
                                </div>
                                <div>
                                    <span class="eyebrow"><?= e(__('date')) ?></span>
                                    <span class="value"><?= formatDate($invoice['created_at']) ?></span>
                                </div>
                                <div>
                                    <span class="eyebrow"><?= e(__('invoice_type')) ?></span>
                                    <span class="value"><?= e(invoiceTypeLabelEn($invoice['invoice_type'] ?? 'sale')) ?></span>
                                </div>
                                <div>
                                    <span class="eyebrow"><?= e(__('payment_method')) ?></span>
                                    <span class="value"><?= e(paymentMethodLabelEn($invoice['payment_method'] ?? 'cash')) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <aside class="customer-card">
                        <div class="customer-box">
                            <span class="eyebrow"><?= e(__('customer')) ?></span>
                            <span class="value"><?= e($customerName) ?></span>
                            <span class="eyebrow" style="margin-top:0.75rem"><?= e(__('phone')) ?></span>
                            <span class="value"><?= e($customerPhone !== '' ? $customerPhone : '-') ?></span>
                        </div>
                    </aside>
                </section>

                <section class="summary-grid">
                    <div class="summary-box">
                        <span class="eyebrow"><?= e(__('items')) ?></span>
                        <span class="value"><?= count($items) ?></span>
                    </div>
                    <div class="summary-box">
                        <span class="eyebrow"><?= e(__('subtotal')) ?></span>
                        <span class="value"><?= formatMoney((float) $invoice['subtotal']) ?></span>
                    </div>
                    <?php if ($hasDiscount): ?>
                    <div class="summary-box">
                        <span class="eyebrow"><?= e(__('discount')) ?></span>
                        <span class="value"><?= formatMoney((float) $invoice['discount']) ?></span>
                    </div>
                    <?php endif; ?>
                </section>

                <?php if (!empty($invoice['notes'])): ?>
                <section class="notes-box">
                    <strong><?= e(__('notes')) ?>:</strong> <?= e($invoice['notes']) ?>
                </section>
                <?php endif; ?>

                <?php renderInvoiceItemsTable($items, $invoice, 'items-wrap'); ?>

                <?php
                $footer = invoiceProfessionalFooterLines((string) ($invoice['invoice_number'] ?? ''));
                ?>
                <footer class="footer">
                    <span class="footer-title"><?= e($footer['title']) ?></span>
                    <div class="footer-tagline"><?= e($footer['tagline']) ?></div>
                    <div class="footer-notice"><?= e($footer['notice']) ?></div>
                    <div class="footer-support"><?= e($footer['support']) ?></div>
                </footer>
            </div>
        </article>
    </div>
</body>
</html>
