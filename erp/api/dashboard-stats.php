<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

ensureTreasuryTables();

$invoiceCount = (int) db()->query('SELECT COUNT(*) FROM invoices')->fetchColumn();
$cashMovementCount = (int) db()->query('SELECT COUNT(*) FROM treasury_transactions')->fetchColumn();
$cashBalance = cashAccountBalance();

echo json_encode([
    'invoices' => $invoiceCount,
    'cash_rows' => $cashMovementCount,
    'cash_balance' => $cashBalance,
    'cash_display' => formatMoney($cashBalance),
    'treasury_rows' => $cashMovementCount,
    'treasury_display' => formatMoney($cashBalance),
    'ts' => time(),
], JSON_UNESCAPED_UNICODE);
