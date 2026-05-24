<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requireRole(['admin', 'manager']);

ensurePurchaseSchema();
ensureTreasuryTables();

$pageTitle = __('purchases');

$totalDebts = totalSupplierDebts();
$treasuryBal = treasuryBalance();

$purchases = db()->query("
    SELECT p.*, s.name AS supplier_name
    FROM purchases p
    JOIN suppliers s ON s.id = p.supplier_id
    ORDER BY p.created_at DESC
    LIMIT 100
")->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<div class="page-actions">
    <a href="<?= url('purchases/debts.php') ?>" class="btn btn-secondary"><?= e(__('supplier_debts')) ?></a>
    <a href="<?= url('purchases/suppliers.php') ?>" class="btn btn-secondary"><?= e(__('suppliers')) ?></a>
    <a href="<?= url('purchases/create.php') ?>" class="btn btn-primary"><?= e(__('new_purchase')) ?></a>
</div>

<div class="stats-grid">
    <div class="stat-card primary">
        <div class="label"><?= e(__('treasury_balance')) ?></div>
        <div class="value" style="font-size:1.1rem"><?= formatMoney($treasuryBal) ?></div>
    </div>
    <div class="stat-card warning">
        <div class="label"><?= e(__('total_supplier_debts')) ?></div>
        <div class="value" style="font-size:1.1rem"><?= formatMoney($totalDebts) ?></div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2><?= e(__('purchases')) ?></h2></div>
    <div class="card-body table-wrap">
        <?php if (empty($purchases)): ?>
            <p class="text-muted"><?= e(__('no_purchases')) ?></p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th><?= e(__('purchase_number')) ?></th>
                    <th><?= e(__('supplier_name')) ?></th>
                    <th><?= e(__('product')) ?></th>
                    <th><?= e(__('quantity')) ?></th>
                    <th><?= e(__('total')) ?></th>
                    <th><?= e(__('payment_method')) ?></th>
                    <th><?= e(__('debt_balance')) ?></th>
                    <th><?= e(__('date')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($purchases as $p): ?>
                <tr>
                    <td><?= e($p['purchase_number']) ?></td>
                    <td><?= e($p['supplier_name']) ?></td>
                    <td><?= e($p['description']) ?></td>
                    <td><?= (int) $p['quantity'] ?></td>
                    <td><?= formatMoney((float) $p['total']) ?></td>
                    <td><?= purchasePaymentBadge($p['payment_method'], (float) $p['debt_balance']) ?></td>
                    <td class="<?= (float) $p['debt_balance'] > 0 ? 'text-danger' : '' ?>">
                        <?= formatMoney((float) $p['debt_balance']) ?>
                    </td>
                    <td><?= formatDate($p['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
