<?php

function ensureInvoiceSchema(): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $pdo = db();
    $path = BASE_PATH . '/database/migrate_invoice_simple.sql';
    if (is_file($path)) {
        runSqlFile($pdo, $path);
    }
    $workflowPath = BASE_PATH . '/database/migrate_invoice_workflow.sql';
    if (is_file($workflowPath)) {
        runSqlFile($pdo, $workflowPath);
    }

    $done = true;
}

/** @return array{subtotal: float, tax_rate: float, tax_amount: float, discount: float, total: float} */
function invoiceTotalsFromLines(float $subtotal, float $discount = 0.0): array
{
    $subtotal = round($subtotal, 2);
    $discount = round(max(0.0, $discount), 2);
    $total = round(max(0.0, $subtotal - $discount), 2);

    return [
        'subtotal' => $subtotal,
        'tax_rate' => 0.0,
        'tax_amount' => 0.0,
        'discount' => $discount,
        'total' => $total,
    ];
}

function findOrCreateQuickCustomer(string $name, string $phone): int
{
    $name = trim($name);
    $phone = trim($phone);

    if ($phone !== '') {
        $stmt = db()->prepare('SELECT id FROM customers WHERE phone = ? LIMIT 1');
        $stmt->execute([$phone]);
        $existing = $stmt->fetchColumn();
        if ($existing) {
            return (int) $existing;
        }
    }

    if ($name === '') {
        $name = __('walk_in_customer');
    }

    db()->prepare('INSERT INTO customers (name, phone) VALUES (?, ?)')->execute([
        $name,
        $phone !== '' ? $phone : null,
    ]);

    return dbLastInsertId(db());
}

function normalizePaymentMethod(string $method): string
{
    return match ($method) {
        'transfer' => 'transfer',
        'deferred' => 'deferred',
        default => 'cash',
    };
}

function normalizeInvoiceType(string $type): string
{
    return $type === 'gift' ? 'gift' : 'sale';
}

function paymentMethodLabel(string $method): string
{
    return match ($method) {
        'transfer' => __('payment_transfer'),
        'deferred' => __('payment_deferred'),
        default => __('payment_cash'),
    };
}

function paymentMethodBadge(string $method): string
{
    $class = match ($method) {
        'transfer' => 'badge-blue',
        'deferred' => 'badge-yellow',
        default => 'badge-green',
    };

    return '<span class="badge ' . $class . '">' . e(paymentMethodLabel($method)) . '</span>';
}

function invoiceTypeLabel(string $type): string
{
    return $type === 'gift' ? __('invoice_type_gift') : __('invoice_type_sale');
}

function invoiceTypeBadge(string $type): string
{
    $class = $type === 'gift' ? 'badge-yellow' : 'badge-green';

    return '<span class="badge ' . $class . '">' . e(invoiceTypeLabel($type)) . '</span>';
}

function invoiceTitleLabel(string $type): string
{
    return $type === 'gift' ? __('gift_invoice') : __('sales_invoice');
}

function invoiceSerialExists(string $serial, ?int $excludeInvoiceId = null): bool
{
    $serial = trim($serial);
    if ($serial === '') {
        return false;
    }

    $sql = 'SELECT COUNT(*) FROM invoice_items ii
            JOIN invoices i ON i.id = ii.invoice_id
            WHERE ii.serial_number = ? AND i.status != ?';
    $params = [$serial, 'cancelled'];

    if ($excludeInvoiceId) {
        $sql .= ' AND ii.invoice_id != ?';
        $params[] = $excludeInvoiceId;
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn() > 0;
}
