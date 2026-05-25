<?php

function ensureInvoiceReturnsSchema(): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $path = BASE_PATH . '/database/migrate_invoice_returns.sql';
    if (is_file($path)) {
        runSqlFile(db(), $path);
    }

    $done = true;
}

function returnedQtyForInvoiceItem(int $invoiceItemId): int
{
    ensureInvoiceReturnsSchema();

    $stmt = db()->prepare('SELECT COALESCE(SUM(return_quantity), 0) FROM invoice_returns WHERE invoice_item_id = ?');
    $stmt->execute([$invoiceItemId]);

    return (int) $stmt->fetchColumn();
}

function invoiceItemRemainingQty(array $item): int
{
    $soldQty = (int) ($item['quantity'] ?? 0);
    $returnedQty = returnedQtyForInvoiceItem((int) ($item['id'] ?? 0));

    return max(0, $soldQty - $returnedQty);
}

function replacementSerialExists(string $serial, ?int $excludeReturnId = null): bool
{
    ensureInvoiceReturnsSchema();

    $serial = trim($serial);
    if ($serial === '') {
        return false;
    }

    $sql = 'SELECT COUNT(*) FROM invoice_returns WHERE replacement_serial_number = ?';
    $params = [$serial];
    if ($excludeReturnId) {
        $sql .= ' AND id != ?';
        $params[] = $excludeReturnId;
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn() > 0;
}

function invoiceReturnHistory(int $invoiceId): array
{
    ensureInvoiceReturnsSchema();

    $stmt = db()->prepare(
        "SELECT r.*, ii.description AS original_product, ii.serial_number AS original_serial,
                rp.name_ar AS replacement_name_ar, rp.name_en AS replacement_name_en,
                u.name AS user_name
         FROM invoice_returns r
         JOIN invoice_items ii ON ii.id = r.invoice_item_id
         LEFT JOIN products rp ON rp.id = r.replacement_product_id
         LEFT JOIN users u ON u.id = r.user_id
         WHERE r.invoice_id = ?
         ORDER BY r.created_at DESC, r.id DESC"
    );
    $stmt->execute([$invoiceId]);

    return $stmt->fetchAll();
}

function processInvoiceReturnOrExchange(PDO $pdo, int $invoiceId, array $input, ?int $userId): void
{
    ensureInvoiceReturnsSchema();

    $itemId = (int) ($input['invoice_item_id'] ?? 0);
    $action = ($input['return_action'] ?? 'return') === 'exchange' ? 'exchange' : 'return';
    $quantity = max(1, (int) ($input['return_quantity'] ?? 1));
    $notes = trim($input['return_notes'] ?? '');

    if ($itemId < 1) {
        throw new RuntimeException('return_item_required');
    }

    $itemStmt = $pdo->prepare(
        'SELECT ii.*, p.quantity AS stock_quantity
         FROM invoice_items ii
         LEFT JOIN products p ON p.id = ii.product_id
         WHERE ii.id = ? AND ii.invoice_id = ?'
    );
    $itemStmt->execute([$itemId, $invoiceId]);
    $item = $itemStmt->fetch();
    if (!$item) {
        throw new RuntimeException('return_item_required');
    }

    $remainingQty = invoiceItemRemainingQty($item);
    if ($remainingQty < 1 || $quantity > $remainingQty) {
        throw new RuntimeException('return_qty_invalid');
    }

    $originalUnitPrice = round((float) ($item['unit_price'] ?? 0), 2);
    $returnTotal = round($originalUnitPrice * $quantity, 2);

    $replacementProductId = null;
    $replacementSerial = null;
    $replacementUnitPrice = 0.0;
    $replacementTotal = 0.0;
    $differenceAmount = 0.0;

    if (!empty($item['product_id'])) {
        $pdo->prepare('UPDATE products SET quantity = quantity + ? WHERE id = ?')
            ->execute([$quantity, (int) $item['product_id']]);
    }

    if ($action === 'exchange') {
        $replacementProductId = (int) ($input['replacement_product_id'] ?? 0);
        $replacementSerial = trim($input['replacement_serial_number'] ?? '');
        $replacementUnitPrice = round((float) ($input['replacement_unit_price'] ?? 0), 2);

        if ($replacementProductId < 1) {
            throw new RuntimeException('exchange_product_required');
        }
        if ($replacementSerial === '') {
            throw new RuntimeException('exchange_serial_required');
        }
        if (invoiceSerialExists($replacementSerial) || replacementSerialExists($replacementSerial)) {
            throw new RuntimeException('exchange_serial_duplicate');
        }

        $replacementStmt = $pdo->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
        $replacementStmt->execute([$replacementProductId]);
        $replacementProduct = $replacementStmt->fetch();
        if (!$replacementProduct) {
            throw new RuntimeException('exchange_product_required');
        }
        if ((int) ($replacementProduct['quantity'] ?? 0) < $quantity) {
            throw new RuntimeException('exchange_stock_insufficient');
        }

        if ($replacementUnitPrice <= 0) {
            $replacementUnitPrice = round((float) ($replacementProduct['sell_price'] ?? 0), 2);
        }
        if ($replacementUnitPrice <= 0) {
            throw new RuntimeException('exchange_price_required');
        }

        $replacementTotal = round($replacementUnitPrice * $quantity, 2);
        $differenceAmount = round($replacementTotal - $returnTotal, 2);

        $pdo->prepare('UPDATE products SET quantity = GREATEST(0, quantity - ?) WHERE id = ?')
            ->execute([$quantity, $replacementProductId]);
    }

    $insert = $pdo->prepare(
        'INSERT INTO invoice_returns (
            invoice_id, invoice_item_id, action, return_quantity, original_unit_price, return_total,
            replacement_product_id, replacement_serial_number, replacement_unit_price, replacement_total,
            difference_amount, notes, user_id
         ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $insert->execute([
        $invoiceId,
        $itemId,
        $action,
        $quantity,
        $originalUnitPrice,
        $returnTotal,
        $replacementProductId,
        $replacementSerial,
        $replacementUnitPrice,
        $replacementTotal,
        $differenceAmount,
        $notes !== '' ? $notes : null,
        $userId,
    ]);
}
