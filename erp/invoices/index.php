<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();

ensureInvoiceSchema();

$pageTitle = __('invoices');

$invoices = db()->query("
    SELECT i.*, c.name AS customer_name,
           ii.description AS product_name,
           ii.serial_number
    FROM invoices i
    JOIN customers c ON c.id = i.customer_id
    LEFT JOIN invoice_items ii ON ii.invoice_id = i.id
    ORDER BY i.created_at DESC
")->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<div class="page-actions">
    <span></span>
    <a href="<?= url('invoices/create.php') ?>" class="btn btn-primary"><?= e(__('new_sale')) ?></a>
</div>
<div class="card">
    <div class="card-body table-wrap">
        <?php if (empty($invoices)): ?>
            <p class="text-muted"><?= e(__('no_data')) ?></p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th><?= e(__('invoice_number')) ?></th>
                    <th><?= e(__('product')) ?></th>
                    <th><?= e(__('serial_number')) ?></th>
                    <th><?= e(__('payment_method')) ?></th>
                    <th><?= e(__('total')) ?></th>
                    <th><?= e(__('date')) ?></th>
                    <th><?= e(__('actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $inv): ?>
                <tr>
                    <td><?= e($inv['invoice_number']) ?></td>
                    <td><?= e($inv['product_name'] ?? '-') ?></td>
                    <td><code class="serial-code"><?= e($inv['serial_number'] ?? '-') ?></code></td>
                    <td><?= paymentMethodBadge($inv['payment_method'] ?? 'cash') ?></td>
                    <td><?= formatMoney((float) $inv['total']) ?></td>
                    <td><?= formatDate($inv['created_at']) ?></td>
                    <td>
                        <a href="<?= url('invoices/view.php?id=' . $inv['id']) ?>" class="btn btn-secondary btn-sm"><?= e(__('view')) ?></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
