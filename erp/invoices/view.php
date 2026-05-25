<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requirePermission(PERM_INVOICES);

ensureInvoiceSchema();
ensureInvoiceReturnsSchema();
ensureTreasuryTables();

$id = (int) ($_GET['id'] ?? 0);

if (isset($_GET['pay'])) {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $paymentStmt = $pdo->prepare(
            "SELECT i.*, c.name AS customer_name
             FROM invoices i
             LEFT JOIN customers c ON c.id = i.customer_id
             WHERE i.id = ?
             FOR UPDATE"
        );
        $paymentStmt->execute([$id]);
        $paymentInvoice = $paymentStmt->fetch();

        if ($paymentInvoice
            && ($paymentInvoice['payment_method'] ?? '') === 'deferred'
            && ($paymentInvoice['invoice_type'] ?? 'sale') === 'sale'
            && ($paymentInvoice['status'] ?? '') !== 'paid'
        ) {
            $pdo->prepare("UPDATE invoices SET status = 'paid' WHERE id = ?")->execute([$id]);
            recordInvoiceTreasuryDeposit(
                (float) ($paymentInvoice['total'] ?? 0),
                (string) ($paymentInvoice['invoice_number'] ?? ''),
                (string) ($paymentInvoice['customer_name'] ?? ''),
                $_SESSION['user_id'] ?? null,
                $pdo
            );
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash('error', __('error'));
        redirect(url('invoices/view.php?id=' . $id));
    }

    flash('success', __('mark_paid'));
    redirect(url('invoices/view.php?id=' . $id));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_return'])) {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        processInvoiceReturnOrExchange($pdo, $id, $_POST, $_SESSION['user_id'] ?? null);
        $pdo->commit();
        flash('success', __('return_saved'));
    } catch (Throwable $e) {
        $pdo->rollBack();
        $errors = [
            'return_item_required' => __('return_item_required'),
            'return_qty_invalid' => __('return_qty_invalid'),
            'exchange_product_required' => __('exchange_product_required'),
            'exchange_serial_required' => __('exchange_serial_required'),
            'exchange_serial_duplicate' => __('exchange_serial_duplicate'),
            'exchange_stock_insufficient' => __('exchange_stock_insufficient'),
            'exchange_price_required' => __('exchange_price_required'),
        ];
        flash('error', $errors[$e->getMessage()] ?? __('error'));
    }
    redirect(url('invoices/view.php?id=' . $id));
}

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
$returnHistory = invoiceReturnHistory($id);
$allProducts = db()->query('SELECT id, sku, name_ar, name_en, sell_price, quantity FROM products ORDER BY name_en')->fetchAll();
$remainingQtyByItem = [];
$itemCount = count($items);
$totalQuantity = 0;
foreach ($items as $row) {
    $remainingQtyByItem[(int) $row['id']] = invoiceItemRemainingQty($row);
    $totalQuantity += (int) ($row['quantity'] ?? 0);
}
$customerNameDisplay = trim((string) ($invoice['customer_name'] ?? ''));
if ($customerNameDisplay === '') {
    $customerNameDisplay = __('walk_in_customer');
}
$customerPhone = trim((string) ($invoice['phone'] ?? ''));
$hasDiscount = (float) ($invoice['discount'] ?? 0) > 0;
$autoPrint = isset($_GET['autoprint']) && $_GET['autoprint'] === '1';

$pageTitle = $invoice['invoice_number'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-actions no-print">
    <a href="<?= url('invoices/index.php') ?>" class="btn btn-secondary"><?= e(__('cancel')) ?></a>
    <a href="<?= url('invoices/create.php') ?>" class="btn btn-primary"><?= e(__('new_sale')) ?></a>
    <?php if (($invoice['payment_method'] ?? '') === 'deferred' && ($invoice['status'] ?? '') !== 'paid' && ($invoice['invoice_type'] ?? 'sale') === 'sale'): ?>
    <a href="<?= url('invoices/view.php?id=' . $id . '&pay=1') ?>" class="btn btn-success"><?= e(__('mark_paid')) ?></a>
    <?php endif; ?>
    <?php if (canDelete()): ?>
    <a href="<?= url('invoices/index.php?delete=' . $id) ?>" class="btn btn-danger" data-confirm="<?= e(__('confirm_delete')) ?>"><?= e(__('delete')) ?></a>
    <?php endif; ?>
    <button type="button" onclick="window.print(); return false;" class="btn btn-secondary"><?= e(__('print')) ?></button>
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
                <strong>
                    <?php if ($customerPhone !== ''): ?>
                        <a href="tel:<?= e($customerPhone) ?>" class="invoice-contact-link"><?= e($customerPhone) ?></a>
                    <?php else: ?>
                        <?= e('-') ?>
                    <?php endif; ?>
                </strong>
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
    <?php if (!empty($invoice['customer_name']) && $invoice['customer_name'] !== __('walk_in_customer')): ?>
    <div class="invoice-print-signature">
        <span><?= e(__('customer')) ?></span>
        <strong><?= e($customerNameDisplay) ?></strong>
    </div>
    <?php endif; ?>
</article>

<section class="card no-print" style="margin-top:1rem">
    <div class="card-header"><h2><?= e(__('returns_and_exchanges')) ?></h2></div>
    <div class="card-body">
        <?php if (empty($items)): ?>
            <p class="text-muted"><?= e(__('no_data')) ?></p>
        <?php else: ?>
            <div class="grid-2">
                <?php foreach ($items as $row): ?>
                    <?php $remainingQty = $remainingQtyByItem[(int) $row['id']] ?? 0; ?>
                    <form method="post" class="card return-form-card" style="margin-bottom:1rem">
                        <div class="card-body">
                            <input type="hidden" name="process_return" value="1">
                            <input type="hidden" name="invoice_item_id" value="<?= (int) $row['id'] ?>">

                            <p><strong><?= e(__('product')) ?>:</strong> <?= e($row['description']) ?></p>
                            <p><strong><?= e(__('serial_number')) ?>:</strong> <code class="serial-code"><?= e($row['serial_number'] ?? '-') ?></code></p>
                            <p><strong><?= e(__('quantity_sold')) ?>:</strong> <?= (int) $row['quantity'] ?> | <strong><?= e(__('remaining_return_qty')) ?>:</strong> <?= $remainingQty ?></p>

                            <div class="form-grid form-grid-2">
                                <div class="form-group">
                                    <label><?= e(__('return_action')) ?></label>
                                    <select name="return_action" class="return-action-selector" <?= $remainingQty < 1 ? 'disabled' : '' ?>>
                                        <option value="return"><?= e(__('return_item')) ?></option>
                                        <option value="exchange"><?= e(__('exchange_item')) ?></option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label><?= e(__('quantity')) ?></label>
                                    <input type="number" name="return_quantity" min="1" max="<?= max(1, $remainingQty) ?>" value="<?= min(1, max(1, $remainingQty)) ?>" <?= $remainingQty < 1 ? 'disabled' : 'required' ?>>
                                </div>
                            </div>

                            <div class="exchange-fields" hidden>
                                <div class="form-grid form-grid-2">
                                    <div class="form-group">
                                        <label><?= e(__('replacement_product')) ?></label>
                                        <select name="replacement_product_id" class="replacement-product">
                                            <option value="">--</option>
                                            <?php foreach ($allProducts as $product): ?>
                                                <option value="<?= (int) $product['id'] ?>" data-price="<?= e((string) $product['sell_price']) ?>">
                                                    <?= e(trim(($product['sku'] ?? '') . ' - ' . productName($product) . ' - ' . $product['quantity'])) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label><?= e(__('replacement_serial_number')) ?></label>
                                        <input type="text" name="replacement_serial_number" placeholder="<?= e(__('serial_number_placeholder')) ?>" autocomplete="off">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label><?= e(__('replacement_price')) ?></label>
                                    <input type="number" name="replacement_unit_price" class="replacement-price" min="0.01" step="0.01" value="0.00">
                                </div>
                            </div>

                            <div class="form-group">
                                <label><?= e(__('notes')) ?></label>
                                <input type="text" name="return_notes" placeholder="<?= e(__('return_notes_placeholder')) ?>">
                            </div>

                            <?php if ($remainingQty > 0): ?>
                                <button type="submit" class="btn btn-primary"><?= e(__('save_return_action')) ?></button>
                            <?php else: ?>
                                <div class="text-muted"><?= e(__('return_completed')) ?></div>
                            <?php endif; ?>
                        </div>
                    </form>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="card no-print" style="margin-top:1rem">
    <div class="card-header"><h2><?= e(__('returns_history')) ?></h2></div>
    <div class="card-body table-wrap">
        <?php if (empty($returnHistory)): ?>
            <p class="text-muted"><?= e(__('no_returns_yet')) ?></p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th><?= e(__('return_action')) ?></th>
                        <th><?= e(__('product')) ?></th>
                        <th><?= e(__('quantity')) ?></th>
                        <th><?= e(__('amount')) ?></th>
                        <th><?= e(__('replacement_product')) ?></th>
                        <th><?= e(__('difference_amount')) ?></th>
                        <th><?= e(__('user')) ?></th>
                        <th><?= e(__('date')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($returnHistory as $entry): ?>
                        <?php
                            $replacementName = '';
                            if (!empty($entry['replacement_name_ar']) || !empty($entry['replacement_name_en'])) {
                                $replacementName = isRtl()
                                    ? trim((string) ($entry['replacement_name_ar'] ?: $entry['replacement_name_en']))
                                    : trim((string) ($entry['replacement_name_en'] ?: $entry['replacement_name_ar']));
                            }
                        ?>
                        <tr>
                            <td><?= e($entry['action'] === 'exchange' ? __('exchange_item') : __('return_item')) ?></td>
                            <td>
                                <?= e($entry['original_product']) ?>
                                <?php if (!empty($entry['original_serial'])): ?>
                                    <br><code class="serial-code"><?= e($entry['original_serial']) ?></code>
                                <?php endif; ?>
                            </td>
                            <td><?= (int) $entry['return_quantity'] ?></td>
                            <td><?= formatMoney((float) $entry['return_total']) ?></td>
                            <td>
                                <?= e($replacementName !== '' ? $replacementName : '-') ?>
                                <?php if (!empty($entry['replacement_serial_number'])): ?>
                                    <br><code class="serial-code"><?= e($entry['replacement_serial_number']) ?></code>
                                <?php endif; ?>
                            </td>
                            <td><?= formatMoney((float) $entry['difference_amount']) ?></td>
                            <td><?= e($entry['user_name'] ?? '-') ?></td>
                            <td><?= formatDate($entry['created_at']) ?></td>
                        </tr>
                        <?php if (!empty($entry['notes'])): ?>
                        <tr>
                            <td colspan="8" class="text-muted"><strong><?= e(__('notes')) ?>:</strong> <?= e($entry['notes']) ?></td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</section>

<script>
(function () {
  document.querySelectorAll('.return-form-card').forEach(function (formCard) {
    const actionSelect = formCard.querySelector('.return-action-selector');
    const exchangeFields = formCard.querySelector('.exchange-fields');
    const replacementProduct = formCard.querySelector('.replacement-product');
    const replacementPrice = formCard.querySelector('.replacement-price');
    const replacementSerial = formCard.querySelector('[name="replacement_serial_number"]');

    if (!actionSelect || !exchangeFields || !replacementProduct || !replacementPrice || !replacementSerial) {
      return;
    }

    function syncExchangeFields() {
      const isExchange = actionSelect.value === 'exchange';
      exchangeFields.hidden = !isExchange;
      replacementProduct.required = isExchange;
      replacementSerial.required = isExchange;
      replacementPrice.required = isExchange;
      if (!isExchange) {
        replacementProduct.value = '';
        replacementSerial.value = '';
        replacementPrice.value = '0.00';
      }
    }

    replacementProduct.addEventListener('change', function () {
      const option = replacementProduct.selectedOptions[0];
      if (option && option.dataset.price) {
        replacementPrice.value = parseFloat(option.dataset.price).toFixed(2);
      }
    });

    actionSelect.addEventListener('change', syncExchangeFields);
    syncExchangeFields();
  });
})();
</script>
<?php if ($autoPrint): ?>
<script>
window.addEventListener('load', function () {
  window.print();
});
</script>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
