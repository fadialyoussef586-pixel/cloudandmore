<?php

/** @var array<int, array<string, mixed>> $items */
/** @var array<string, mixed> $invoice */
$hasDiscount = (float) ($invoice['discount'] ?? 0) > 0;
$wrapClass = $wrapClass ?? 'invoice-print-table-wrap';
?>
<div class="<?= e($wrapClass) ?>">
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
                    <td data-label="#"><?= $index + 1 ?></td>
                    <td data-label="<?= e(__('product')) ?>"><?= e(invoiceItemDescription($item)) ?></td>
                    <td data-label="<?= e(__('serial_number')) ?>"><code class="serial-code"><?= e($item['serial_number'] ?? '-') ?></code></td>
                    <td class="num" data-label="<?= e(__('quantity')) ?>"><?= (int) ($item['quantity'] ?? 0) ?></td>
                    <td class="num" data-label="<?= e(__('price')) ?>"><?= formatMoney((float) ($item['unit_price'] ?? 0)) ?></td>
                    <td class="num" data-label="<?= e(__('total')) ?>"><?= formatMoney((float) ($item['total'] ?? 0)) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <?php if ($hasDiscount): ?>
                <tr class="invoice-tfoot-row">
                    <td colspan="5" class="invoice-total-label"><?= e(__('subtotal')) ?></td>
                    <td class="num" data-label="<?= e(__('subtotal')) ?>"><?= formatMoney((float) $invoice['subtotal']) ?></td>
                </tr>
                <tr class="invoice-tfoot-row">
                    <td colspan="5" class="invoice-total-label"><?= e(__('discount')) ?></td>
                    <td class="num" data-label="<?= e(__('discount')) ?>"><?= formatMoney((float) $invoice['discount']) ?></td>
                </tr>
            <?php endif; ?>
            <tr class="invoice-tfoot-row invoice-tfoot-row--total">
                <td colspan="5" class="invoice-total-label"><?= e(__('total')) ?></td>
                <td class="num invoice-total-value" data-label="<?= e(__('total')) ?>"><?= formatMoney((float) $invoice['total']) ?></td>
            </tr>
        </tfoot>
    </table>
</div>
