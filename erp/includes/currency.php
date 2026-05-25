<?php

function safeCurrencySymbol(): string
{
    $symbol = defined('CURRENCY_SYMBOL') ? trim((string) CURRENCY_SYMBOL) : '';

    // Ignore malformed symbols like numeric leftovers from bad env/config values.
    if ($symbol === '' || preg_match('/[0-9]/', $symbol)) {
        return '';
    }

    return $symbol;
}

function formatMoney(float $amount): string
{
    $symbol = safeCurrencySymbol();
    $value = number_format($amount, 2);

    return ($symbol !== '' ? $symbol : '') . $value . ' ' . CURRENCY_CODE;
}

function formatMoneyHtml(float $amount): string
{
    $symbol = safeCurrencySymbol();
    $value = ($symbol !== '' ? $symbol : '') . number_format($amount, 2);

    return '<span class="money-usd">' . e($value) . ' <span class="currency-usd">' . CURRENCY_CODE . '</span></span>';
}

function treasuryBalance(): float
{
    return treasuryBalanceFromDb(db());
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

function recordTreasuryMovement(string $type, float $amountUsd, string $category, string $description, ?int $userId): void
{
    ensureTreasuryTables();

    if ($amountUsd <= 0) {
        return;
    }

    $ref = generateNumber('TRS');
    db()->prepare(
        'INSERT INTO treasury_transactions (reference_number, type, category, currency, amount, amount_sar, description, user_id)
         VALUES (?,?,?,?,?,?,?,?)'
    )->execute([
        $ref,
        $type,
        $category,
        CURRENCY_CODE,
        $amountUsd,
        $amountUsd,
        $description,
        $userId,
    ]);
}
