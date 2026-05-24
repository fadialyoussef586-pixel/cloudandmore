<?php

function formatMoney(float $amount): string
{
    return CURRENCY_SYMBOL . number_format($amount, 2) . ' ' . CURRENCY_CODE;
}

function formatMoneyHtml(float $amount): string
{
    return '<span class="money-usd">' . e(CURRENCY_SYMBOL . number_format($amount, 2)) . ' <span class="currency-usd">' . CURRENCY_CODE . '</span></span>';
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
