<?php

function ensurePurchaseSchema(): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $path = BASE_PATH . '/database/migrate_purchases.sql';
    if (is_file($path)) {
        runSqlFile(db(), $path);
    }

    $done = true;
}

function treasuryWithdrawForPurchase(float $amountSar, string $description, ?int $userId): void
{
    ensureTreasuryTables();

    if ($amountSar <= 0) {
        return;
    }

    if (treasuryBalance() < $amountSar) {
        throw new RuntimeException('insufficient_treasury');
    }

    $ref = generateNumber('TRS');
    db()->prepare(
        'INSERT INTO treasury_transactions (reference_number, type, category, currency, amount, amount_sar, description, user_id)
         VALUES (?,?,?,?,?,?,?,?)'
    )->execute([
        $ref,
        'withdrawal',
        'purchase',
        'SAR',
        $amountSar,
        $amountSar,
        $description,
        $userId,
    ]);
}

function supplierDebtBalance(int $supplierId): float
{
    ensurePurchaseSchema();

    $stmt = db()->prepare('SELECT COALESCE(SUM(debt_balance), 0) FROM purchases WHERE supplier_id = ?');
    $stmt->execute([$supplierId]);

    return (float) $stmt->fetchColumn();
}

function totalSupplierDebts(): float
{
    ensurePurchaseSchema();

    return (float) db()->query('SELECT COALESCE(SUM(debt_balance), 0) FROM purchases')->fetchColumn();
}

function purchasePaymentLabel(string $method): string
{
    return $method === 'credit' ? __('payment_credit') : __('payment_cash');
}

function purchasePaymentBadge(string $method, float $debtBalance = 0): string
{
    if ($method === 'credit' && $debtBalance > 0) {
        return '<span class="badge badge-yellow">' . e(__('debt_open')) . '</span>';
    }

    if ($method === 'credit' && $debtBalance <= 0) {
        return '<span class="badge badge-green">' . e(__('debt_settled')) . '</span>';
    }

    return '<span class="badge badge-green">' . e(__('payment_cash')) . '</span>';
}

function purchaseLineDescription(array $item, array $productsById): string
{
    $productId = (int) ($item['product_id'] ?? 0);
    if ($productId > 0 && isset($productsById[$productId])) {
        return productName($productsById[$productId]);
    }

    return trim((string) ($item['description'] ?? ''));
}

function applyPurchaseToInventory(int $productId, int $qty, float $unitCost): void
{
    if ($productId < 1 || $qty < 1) {
        return;
    }

    db()->prepare('UPDATE products SET quantity = quantity + ?, cost_price = ? WHERE id = ?')
        ->execute([$qty, $unitCost, $productId]);
}

function payPurchaseDebt(int $purchaseId, float $amount, ?int $userId, string $notes = ''): void
{
    ensurePurchaseSchema();
    ensureTreasuryTables();

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare('SELECT * FROM purchases WHERE id = ? FOR UPDATE');
        $stmt->execute([$purchaseId]);
        $purchase = $stmt->fetch();
        if (!$purchase) {
            throw new RuntimeException('not_found');
        }

        $debt = (float) $purchase['debt_balance'];
        if ($debt <= 0) {
            throw new RuntimeException('no_debt');
        }

        $amount = round(min($amount, $debt), 2);
        if ($amount <= 0) {
            throw new RuntimeException('invalid_amount');
        }

        $supplier = db()->prepare('SELECT name FROM suppliers WHERE id = ?');
        $supplier->execute([(int) $purchase['supplier_id']]);
        $supplierName = $supplier->fetchColumn() ?: __('suppliers');

        treasuryWithdrawForPurchase(
            $amount,
            __('treasury_purchase_debt') . ' — ' . $purchase['purchase_number'] . ' / ' . $supplierName,
            $userId
        );

        $newDebt = round($debt - $amount, 2);
        $newPaid = round((float) $purchase['amount_paid'] + $amount, 2);

        $pdo->prepare('UPDATE purchases SET debt_balance = ?, amount_paid = ? WHERE id = ?')
            ->execute([$newDebt, $newPaid, $purchaseId]);

        $pdo->prepare(
            'INSERT INTO supplier_debt_payments (purchase_id, amount, notes, user_id) VALUES (?,?,?,?)'
        )->execute([
            $purchaseId,
            $amount,
            $notes !== '' ? $notes : null,
            $userId,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
