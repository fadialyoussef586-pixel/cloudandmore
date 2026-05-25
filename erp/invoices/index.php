<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requirePermission(PERM_INVOICES);

ensureInvoiceSchema();

if (isset($_GET['pay'])) {
    $id = (int) $_GET['pay'];
    db()->prepare("UPDATE invoices
                   SET status = 'paid'
                   WHERE id = ?
                     AND payment_method = 'deferred'
                     AND invoice_type = 'sale'
                     AND status != 'paid'")
        ->execute([$id]);
    flash('success', __('mark_paid'));
    redirect(url('invoices/index.php'));
}

if (isset($_GET['delete'])) {
    requireDelete();
    $id = (int) $_GET['delete'];
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $items = $pdo->prepare('SELECT * FROM invoice_items WHERE invoice_id = ?');
        $items->execute([$id]);
        foreach ($items->fetchAll() as $row) {
            if (!empty($row['product_id'])) {
                $pdo->prepare('UPDATE products SET quantity = quantity + ? WHERE id = ?')
                    ->execute([(int) $row['quantity'], (int) $row['product_id']]);
            }
        }
        $pdo->prepare('DELETE FROM invoices WHERE id = ?')->execute([$id]);
        $pdo->commit();
        flash('success', __('success_deleted'));
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash('error', __('error'));
    }
    redirect(url('invoices/index.php'));
}

$pageTitle = __('invoices');

$invoices = db()->query("
    SELECT i.*, c.name AS customer_name,
           MIN(ii.description) AS product_name,
           MIN(ii.serial_number) AS serial_number,
           COUNT(ii.id) AS item_count
    FROM invoices i
    JOIN customers c ON c.id = i.customer_id
    LEFT JOIN invoice_items ii ON ii.invoice_id = i.id
    GROUP BY i.id, c.name
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
                    <th><?= e(__('invoice_type')) ?></th>
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
                <?php
                    $itemCount = (int) ($inv['item_count'] ?? 0);
                    $productSummary = trim((string) ($inv['product_name'] ?? ''));
                    if ($productSummary === '') {
                        $productSummary = '-';
                    } elseif ($itemCount > 1) {
                        $productSummary .= ' +' . ($itemCount - 1);
                    }
                ?>
                <tr>
                    <td><?= e($inv['invoice_number']) ?></td>
                    <td><?= invoiceTypeBadge($inv['invoice_type'] ?? 'sale') ?></td>
                    <td><?= e($productSummary) ?></td>
                    <td><code class="serial-code"><?= e($itemCount > 1 ? '-' : ($inv['serial_number'] ?? '-')) ?></code></td>
                    <td><?= paymentMethodBadge($inv['payment_method'] ?? 'cash') ?></td>
                    <td><?= formatMoney((float) $inv['total']) ?></td>
                    <td><?= formatDate($inv['created_at']) ?></td>
                    <td>
                        <a href="<?= url('invoices/view.php?id=' . $inv['id']) ?>" class="btn btn-secondary btn-sm"><?= e(__('view')) ?></a>
                        <?php if (($inv['payment_method'] ?? '') === 'deferred' && ($inv['status'] ?? '') !== 'paid' && ($inv['invoice_type'] ?? 'sale') === 'sale'): ?>
                        <a href="<?= url('invoices/index.php?pay=' . $inv['id']) ?>" class="btn btn-success btn-sm"><?= e(__('mark_paid')) ?></a>
                        <?php endif; ?>
                        <?php if (canDelete()): ?>
                        <a href="<?= url('invoices/index.php?delete=' . $inv['id']) ?>" class="btn btn-danger btn-sm" data-confirm="<?= e(__('confirm_delete')) ?>"><?= e(__('delete')) ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
