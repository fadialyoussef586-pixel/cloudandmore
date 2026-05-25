<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$invoiceCount = 0;
$treasuryRowCount = 0;
$revenue = 0.0;
$treasury = 0.0;

echo json_encode([
    'invoices' => $invoiceCount,
    'treasury_rows' => $treasuryRowCount,
    'revenue' => $revenue,
    'treasury' => $treasury,
    'revenue_display' => formatMoney($revenue),
    'treasury_display' => formatMoney($treasury),
    'forced_zero' => true,
    'ts' => time(),
], JSON_UNESCAPED_UNICODE);
