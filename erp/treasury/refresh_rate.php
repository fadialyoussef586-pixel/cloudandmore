<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin', 'manager']);

ensureTreasuryTables();

$today = date('Y-m-d');
$rate = fetchUsdToSarFromApi();

if ($rate > 0) {
    db()->prepare(
        'INSERT INTO exchange_rates (rate_date, usd_to_sar, source) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE usd_to_sar = VALUES(usd_to_sar), source = VALUES(source)'
    )->execute([$today, $rate, 'frankfurter']);
    flash('success', __('rate_updated') . ': 1 USD = ' . number_format($rate, 4) . ' SAR');
} else {
    flash('error', __('rate_update_failed'));
}

redirect(url('treasury/index.php'));
