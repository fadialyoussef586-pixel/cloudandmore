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
    $pendingPath = BASE_PATH . '/database/migrate_invoice_pending.sql';
    if (is_file($pendingPath)) {
        runSqlFile($pdo, $pendingPath);
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

function normalizePaymentMethod(string $method): string
{
    return match ($method) {
        'transfer' => 'transfer',
        'deferred' => 'deferred',
        'pending' => 'pending',
        default => 'cash',
    };
}

function invoicePaymentIsDeferred(array $invoice): bool
{
    $method = $invoice['payment_method'] ?? 'cash';

    return in_array($method, ['deferred', 'pending'], true);
}

function invoiceAwaitingPayment(array $invoice): bool
{
    return ($invoice['invoice_type'] ?? 'sale') === 'sale'
        && ($invoice['status'] ?? '') !== 'paid'
        && ($invoice['status'] ?? '') !== 'cancelled'
        && (float) ($invoice['total'] ?? 0) > 0;
}

function invoiceStatusForNewSale(string $paymentMethod): string
{
    return 'sent';
}

function invoiceShouldCreateImmediateTreasuryEntry(string $invoiceType, string $paymentMethod): bool
{
    return false;
}

function markInvoiceAsPaid(int $invoiceId, ?PDO $pdo = null, ?int $userId = null): void
{
    $pdo = $pdo ?: db();
    $started = false;

    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $started = true;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT i.*, c.name AS customer_name
             FROM invoices i
             LEFT JOIN customers c ON c.id = i.customer_id
             WHERE i.id = ?
             FOR UPDATE"
        );
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$invoice || !invoiceAwaitingPayment($invoice)) {
            throw new RuntimeException('invoice_not_pending');
        }

        $pdo->prepare("UPDATE invoices SET status = 'paid' WHERE id = ?")->execute([$invoiceId]);

        recordInvoiceTreasuryDeposit(
            (float) ($invoice['total'] ?? 0),
            (string) ($invoice['invoice_number'] ?? ''),
            (string) ($invoice['customer_name'] ?? ''),
            $userId,
            $pdo
        );

        if ($started) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
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
        'pending' => __('payment_pending'),
        default => __('payment_cash'),
    };
}

function paymentMethodBadge(string $method): string
{
    $class = match ($method) {
        'deferred', 'pending' => 'badge-yellow',
        default => 'badge-blue',
    };

    return '<span class="badge ' . $class . '">' . e(paymentMethodLabel($method)) . '</span>';
}

function invoicePaymentStatusBadge(array $invoice): string
{
    if (($invoice['invoice_type'] ?? 'sale') !== 'sale') {
        return statusBadge($invoice['status'] ?? 'draft');
    }

    if (($invoice['status'] ?? '') === 'paid') {
        return '<span class="badge badge-green">' . e(__('payment_received')) . '</span>';
    }

    if (invoiceAwaitingPayment($invoice)) {
        return '<span class="badge badge-yellow">' . e(__('payment_pending_status')) . '</span>';
    }

    return statusBadge($invoice['status'] ?? 'draft');
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

function recordInvoiceTreasuryDeposit(
    float $amountUsd,
    string $invoiceNumber,
    string $customerName,
    ?int $userId,
    ?PDO $pdo = null
): void
{
    if ($amountUsd <= 0) {
        return;
    }

    $description = __('sales_invoice') . ' — ' . trim($invoiceNumber);
    $customerName = trim($customerName);
    if ($customerName !== '') {
        $description .= ' / ' . $customerName;
    }

    recordTreasuryMovement('deposit', $amountUsd, 'sale', $description, $userId, $pdo);
}

function reverseInvoiceTreasuryOnDelete(array $invoice, ?int $userId, ?PDO $pdo = null): void
{
    if (($invoice['invoice_type'] ?? 'sale') !== 'sale') {
        return;
    }
    if (($invoice['status'] ?? '') !== 'paid') {
        return;
    }

    $total = round((float) ($invoice['total'] ?? 0), 2);
    if ($total <= 0) {
        return;
    }

    $pdo = $pdo ?: db();
    $balance = cashAccountBalance($pdo);
    $invoiceNumber = (string) ($invoice['invoice_number'] ?? '');
    $description = __('delete') . ' — ' . $invoiceNumber;

    if ($balance >= $total) {
        recordTreasuryMovement('withdrawal', $total, 'sale_reversal', $description, $userId, $pdo);
        return;
    }

    if ($balance > 0) {
        recordTreasuryMovement(
            'withdrawal',
            $balance,
            'sale_reversal',
            $description . ' (' . __('partial_reversal') . ')',
            $userId,
            $pdo
        );
    }
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

function formatMoneyPlain(float $amount): string
{
    $symbol = safeCurrencySymbol();

    return ($symbol !== '' ? $symbol : '$') . number_format($amount, 2) . ' ' . CURRENCY_CODE;
}

function paymentMethodLabelEn(string $method): string
{
    return match ($method) {
        'transfer' => 'Bank Transfer',
        'deferred' => 'Deferred Payment',
        'pending' => 'Payment on Hold',
        default => 'Cash',
    };
}

function invoiceTypeLabelEn(string $type): string
{
    return $type === 'gift' ? 'Gift Invoice' : 'Sales Invoice';
}

function invoiceProfessionalFooterLines(string $invoiceNumber = ''): array
{
    $supportPhone = defined('COMPANY_SUPPORT_PHONE') ? trim(COMPANY_SUPPORT_PHONE) : '';
    $supportLine = 'For billing or warranty support, contact Cloud and More and quote your invoice number.';
    if ($invoiceNumber !== '') {
        $supportLine .= ' Ref: ' . $invoiceNumber;
    }
    if ($supportPhone !== '') {
        $supportLine .= ' · Tel: ' . $supportPhone;
    }

    return [
        'title' => 'Official Sales Receipt',
        'tagline' => 'Cloud and More — Devices, Accessories & Services',
        'notice' => 'This document is valid proof of purchase. Please retain it for warranty claims and after-sales support.',
        'support' => $supportLine,
    ];
}

function renderInvoiceProfessionalFooter(string $invoiceNumber = '', string $wrapperClass = 'invoice-print-footer'): string
{
    $footer = invoiceProfessionalFooterLines($invoiceNumber);

    return '<footer class="' . e($wrapperClass) . '">'
        . '<p class="invoice-footer-title">' . e($footer['title']) . '</p>'
        . '<p class="invoice-footer-tagline">' . e($footer['tagline']) . '</p>'
        . '<p class="invoice-footer-notice">' . e($footer['notice']) . '</p>'
        . '<p class="invoice-footer-support">' . e($footer['support']) . '</p>'
        . '</footer>';
}

function normalizeWhatsAppPhone(string $phone): ?string
{
    $phone = trim($phone);
    if ($phone === '') {
        return null;
    }

    $digits = preg_replace('/[^\d+]/', '', $phone);
    if ($digits === '') {
        return null;
    }

    if (str_starts_with($digits, '+')) {
        $digits = substr($digits, 1);
    } elseif (str_starts_with($digits, '00')) {
        $digits = substr($digits, 2);
    }

    if ($digits === '') {
        return null;
    }

    if (strlen($digits) >= 11 && !str_starts_with($digits, '0')) {
        return $digits;
    }

    $local = ltrim($digits, '0');
    if ($local === '') {
        return null;
    }

    $country = defined('WHATSAPP_DEFAULT_COUNTRY') ? WHATSAPP_DEFAULT_COUNTRY : '961';

    return $country . $local;
}

function buildInvoiceWhatsAppMessage(array $invoice, array $items, string $customerName): string
{
    $invoiceNumber = (string) ($invoice['invoice_number'] ?? '');
    $lines = [
        '*Cloud and More*',
        invoiceTypeLabelEn($invoice['invoice_type'] ?? 'sale'),
        '',
        'Invoice: ' . $invoiceNumber,
        'Date: ' . formatDate($invoice['created_at'] ?? date('Y-m-d')),
        'Customer: ' . $customerName,
        'Payment: ' . paymentMethodLabelEn($invoice['payment_method'] ?? 'cash'),
        '',
        '*Items*',
    ];

    foreach ($items as $index => $item) {
        $desc = trim((string) ($item['description'] ?? ''));
        $serial = trim((string) ($item['serial_number'] ?? ''));
        $lineTotal = formatMoneyPlain((float) ($item['total'] ?? 0));
        $line = ($index + 1) . '. ' . $desc;
        if ($serial !== '') {
            $line .= ' (SN: ' . $serial . ')';
        }
        $line .= ' — ' . $lineTotal;
        $lines[] = $line;
    }

    $lines[] = '';
    if ((float) ($invoice['discount'] ?? 0) > 0) {
        $lines[] = 'Subtotal: ' . formatMoneyPlain((float) ($invoice['subtotal'] ?? 0));
        $lines[] = 'Discount: ' . formatMoneyPlain((float) ($invoice['discount'] ?? 0));
    }
    $lines[] = '*Total: ' . formatMoneyPlain((float) ($invoice['total'] ?? 0)) . '*';
    $lines[] = '';
    $lines[] = 'Official receipt issued by Cloud and More.';
    $lines[] = 'For warranty or billing inquiries, reply with invoice ' . $invoiceNumber . '.';

    return implode("\n", $lines);
}

function invoiceWhatsAppShareUrl(?string $phone, string $message): ?string
{
    $normalized = normalizeWhatsAppPhone($phone ?? '');
    if ($normalized === null || trim($message) === '') {
        return null;
    }

    return 'https://wa.me/' . $normalized . '?text=' . rawurlencode($message);
}

function invoiceWhatsAppLinkFor(int $invoiceId, ?array $invoice = null, ?array $items = null, ?string $customerName = null): ?string
{
    if ($invoice === null) {
        $stmt = db()->prepare(
            'SELECT i.*, c.name AS customer_name, c.phone
             FROM invoices i
             JOIN customers c ON c.id = i.customer_id
             WHERE i.id = ?'
        );
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$invoice) {
        return null;
    }

    if ($items === null) {
        $itemsStmt = db()->prepare('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id ASC');
        $itemsStmt->execute([$invoiceId]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $name = $customerName ?? trim((string) ($invoice['customer_name'] ?? ''));
    if ($name === '') {
        $name = 'Walk-in Customer';
    }

    $phone = trim((string) ($invoice['phone'] ?? ''));
    $message = buildInvoiceWhatsAppMessage($invoice, $items, $name);

    return invoiceWhatsAppShareUrl($phone, $message);
}

function invoiceWhatsAppButton(int $invoiceId, ?array $invoice = null, ?array $items = null, string $class = 'btn btn-whatsapp btn-sm'): string
{
    $url = invoiceWhatsAppLinkFor($invoiceId, $invoice, $items);
    if ($url === null) {
        return '';
    }

    return '<a href="' . e($url) . '" class="' . e($class) . '" target="_blank" rel="noopener" title="' . e(__('send_whatsapp')) . '">'
        . faIcon('fa-brands fa-whatsapp', 'fa-btn-icon')
        . e(__('send_whatsapp'))
        . '</a>';
}

function renderInvoiceItemsTable(array $items, array $invoice, string $wrapClass = 'invoice-print-table-wrap'): void
{
    include BASE_PATH . '/includes/partials/invoice_items_table.php';
}

