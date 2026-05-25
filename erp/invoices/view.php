<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requirePermission(PERM_INVOICES);

ensureInvoiceSchema();
ensureInvoiceReturnsSchema();

$id = (int) ($_GET['id'] ?? 0);

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
foreach ($items as $row) {
    $remainingQtyByItem[(int) $row['id']] = invoiceItemRemainingQty($row);
}

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
<?php require __DIR__ . '/../includes/footer.php'; ?>
