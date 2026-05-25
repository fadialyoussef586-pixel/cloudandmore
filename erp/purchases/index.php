<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requirePermission(PERM_PURCHASES);

ensurePurchaseSchema();
ensureTreasuryTables();

$pageTitle = __('purchases');

$totalDebts = totalSupplierDebts();
$treasuryBal = cashAccountBalance();

if (isset($_GET['delete'])) {
    requireDelete();
    $id = (int) $_GET['delete'];
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'SELECT p.*, s.name AS supplier_name
             FROM purchases p
             JOIN suppliers s ON s.id = p.supplier_id
             WHERE p.id = ?
             FOR UPDATE'
        );
        $stmt->execute([$id]);
        $purchase = $stmt->fetch();
        if (!$purchase) {
            throw new RuntimeException('not_found');
        }

        $paymentStmt = $pdo->prepare('SELECT COUNT(*) FROM supplier_debt_payments WHERE purchase_id = ?');
        $paymentStmt->execute([$id]);
        if ((int) $paymentStmt->fetchColumn() > 0) {
            throw new RuntimeException('purchase_has_payments');
        }

        $productId = (int) ($purchase['product_id'] ?? 0);
        $qty = (int) ($purchase['quantity'] ?? 0);
        if ($productId > 0 && $qty > 0) {
            $productStmt = $pdo->prepare('SELECT quantity FROM products WHERE id = ? FOR UPDATE');
            $productStmt->execute([$productId]);
            $currentQty = (int) $productStmt->fetchColumn();
            if ($currentQty < $qty) {
                throw new RuntimeException('purchase_stock_used');
            }

            $pdo->prepare('UPDATE products SET quantity = quantity - ? WHERE id = ?')
                ->execute([$qty, $productId]);
        }

        $amountPaid = (float) ($purchase['amount_paid'] ?? 0);
        if ($amountPaid > 0) {
            recordTreasuryMovement(
                'deposit',
                $amountPaid,
                'purchase_reversal',
                __('delete') . ' — ' . (string) ($purchase['purchase_number'] ?? '') . ' / ' . (string) ($purchase['supplier_name'] ?? ''),
                $_SESSION['user_id'] ?? null,
                $pdo
            );
        }

        $pdo->prepare('DELETE FROM purchases WHERE id = ?')->execute([$id]);
        $pdo->commit();
        flash('success', __('success_deleted'));
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors = [
            'purchase_has_payments' => __('error'),
            'purchase_stock_used' => __('error'),
        ];
        flash('error', $errors[$e->getMessage()] ?? __('error'));
    }
    redirect(url('purchases/index.php'));
}

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
                    <th><?= e(__('actions')) ?></th>
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
                    <td class="table-actions">
                        <a href="<?= url('purchases/view.php?id=' . $p['id']) ?>" class="btn btn-secondary btn-sm"><?= e(__('view')) ?></a>
                        <a href="<?= url('purchases/view.php?id=' . $p['id'] . '&autoprint=1') ?>" class="btn btn-secondary btn-sm" onclick="window.open(this.href, '_blank', 'noopener'); return false;"><?= e(__('print')) ?></a>
                        <?php if (canDelete()): ?>
                            <a href="<?= url('purchases/index.php?delete=' . $p['id']) ?>" class="btn btn-danger btn-sm" data-confirm="<?= e(__('confirm_delete')) ?>"><?= e(__('delete')) ?></a>
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
