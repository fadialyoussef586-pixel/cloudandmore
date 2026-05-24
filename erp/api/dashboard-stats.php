<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$pdo = db();
$invoiceCount = (int) $pdo->query('SELECT COUNT(*) FROM invoices')->fetchColumn();
$treasuryRowCount = (int) $pdo->query('SELECT COUNT(*) FROM treasury_transactions')->fetchColumn();
$revenue = $invoiceCount === 0 ? 0.0 : monthlyRevenue($pdo);
$treasury = $treasuryRowCount === 0 ? 0.0 : treasuryBalanceFromDb($pdo);

echo json_encode([
    'invoices' => $invoiceCount,
    'treasury_rows' => $treasuryRowCount,
    'revenue' => $revenue,
    'treasury' => $treasury,
    'revenue_display' => formatMoney($revenue),
    'treasury_display' => formatMoney($treasury),
    'ts' => time(),
], JSON_UNESCAPED_UNICODE);
