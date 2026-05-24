<?php

function ensureDailyExchangeRate(): float
{
    try {
        $pdo = db();
        $today = date('Y-m-d');
        $stmt = $pdo->prepare('SELECT usd_to_sar FROM exchange_rates WHERE rate_date = ?');
        $stmt->execute([$today]);
        $rate = $stmt->fetchColumn();

        if ($rate !== false) {
            return (float) $rate;
        }

        $fetched = fetchUsdToSarFromApi();
        if ($fetched > 0) {
            $pdo->prepare(
                'INSERT INTO exchange_rates (rate_date, usd_to_sar, source) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE usd_to_sar = VALUES(usd_to_sar), source = VALUES(source)'
            )->execute([$today, $fetched, 'frankfurter']);

            return $fetched;
        }

        $fallback = $pdo->query('SELECT usd_to_sar FROM exchange_rates ORDER BY rate_date DESC LIMIT 1')->fetchColumn();
        if ($fallback !== false) {
            return (float) $fallback;
        }
    } catch (Throwable $e) {
        // tables may not exist yet
    }

    return 3.75;
}

function fetchUsdToSarFromApi(): float
{
    $ctx = stream_context_create(['http' => ['timeout' => 8]]);
    $json = @file_get_contents('https://api.frankfurter.app/latest?from=USD&to=SAR', false, $ctx);
    if ($json === false) {
        return 0;
    }

    $data = json_decode($json, true);
    if (!empty($data['rates']['SAR'])) {
        return (float) $data['rates']['SAR'];
    }

    return 0;
}

function getUsdToSarRate(): float
{
    static $rate = null;
    if ($rate === null) {
        $rate = ensureDailyExchangeRate();
    }

    return $rate > 0 ? $rate : 3.75;
}

function sarToUsd(float $amountSar): float
{
    return round($amountSar / getUsdToSarRate(), 2);
}

function usdToSar(float $amountUsd): float
{
    return round($amountUsd * getUsdToSarRate(), 2);
}

function formatMoney(float $amountSar): string
{
    return formatDualMoney($amountSar);
}

function formatDualMoney(float $amountSar): string
{
    $sar = number_format($amountSar, 2) . ' SAR';
    $usd = number_format(sarToUsd($amountSar), 2) . ' USD';

    return $sar . ' · ' . $usd;
}

function formatMoneyHtml(float $amountSar): string
{
    $sar = e(number_format($amountSar, 2)) . ' <span class="currency-sar">SAR</span>';
    $usd = e(number_format(sarToUsd($amountSar), 2)) . ' <span class="currency-usd">USD</span>';

    return '<span class="dual-money">' . $sar . ' <span class="money-sep">·</span> ' . $usd . '</span>';
}

function treasuryBalance(): float
{
    try {
        return (float) db()->query(
            "SELECT COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount_sar ELSE -amount_sar END), 0)
             FROM treasury_transactions"
        )->fetchColumn();
    } catch (Throwable $e) {
        return 0.0;
    }
}

function ensureTreasuryTables(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $path = BASE_PATH . '/database/migrate_currency_treasury.sql';
    if (is_file($path)) {
        runSqlFile(db(), $path);
    }
    $done = true;
}
