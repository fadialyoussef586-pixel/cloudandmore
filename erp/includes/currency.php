<?php

const CASH_ACCOUNT_MAIN = 1;
const CASH_ACCOUNT_RESERVE = 2;

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

function treasuryAccountCurrency(): string
{
    return in_array(CURRENCY_CODE, ['SAR', 'USD'], true) ? CURRENCY_CODE : 'USD';
}

function ensureTreasuryTables(): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $pdo = db();
    $path = BASE_PATH . '/database/migrate_currency_treasury.sql';
    if (is_file($path)) {
        runSqlFile($pdo, $path);
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS cash_accounts (
            id INT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            currency ENUM('SAR', 'USD') NOT NULL DEFAULT 'USD',
            balance DECIMAL(14,2) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )"
    );

    $columnExists = static function (PDO $pdo, string $table, string $column): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$table, $column]);

        return (int) $stmt->fetchColumn() > 0;
    };

    if (!$columnExists($pdo, 'treasury_transactions', 'cash_account_id')) {
        $pdo->exec(
            'ALTER TABLE treasury_transactions
             ADD COLUMN cash_account_id INT NOT NULL DEFAULT 1 AFTER user_id'
        );
    }

    if (!$columnExists($pdo, 'treasury_transactions', 'balance_after')) {
        $pdo->exec(
            'ALTER TABLE treasury_transactions
             ADD COLUMN balance_after DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER cash_account_id'
        );
    }

    $currency = treasuryAccountCurrency();
    $existingBalance = (float) $pdo->query(
        "SELECT COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount_sar ELSE -amount_sar END), 0)
         FROM treasury_transactions
         WHERE cash_account_id = 1 OR cash_account_id IS NULL"
    )->fetchColumn();

    $seedAccounts = [
        CASH_ACCOUNT_MAIN => ['Main Cash Account', $existingBalance],
        CASH_ACCOUNT_RESERVE => ['Reserve Fund', 0.0],
    ];

    foreach ($seedAccounts as $accountId => [$name, $balance]) {
        $exists = (int) $pdo->query('SELECT COUNT(*) FROM cash_accounts WHERE id = ' . (int) $accountId)->fetchColumn();
        if ($exists === 0) {
            $pdo->prepare(
                'INSERT INTO cash_accounts (id, name, currency, balance) VALUES (?, ?, ?, ?)'
            )->execute([(int) $accountId, $name, $currency, round((float) $balance, 2)]);
        }
    }

    $done = true;
}

function cashAccountIds(): array
{
    return [CASH_ACCOUNT_MAIN, CASH_ACCOUNT_RESERVE];
}

function cashAccountLabel(int $accountId): string
{
    return match ($accountId) {
        CASH_ACCOUNT_MAIN => __('main_treasury'),
        CASH_ACCOUNT_RESERVE => __('reserve_treasury'),
        default => cashAccountName($accountId),
    };
}

function cashAccountName(int $accountId): string
{
    ensureTreasuryTables();

    try {
        $stmt = db()->prepare('SELECT name FROM cash_accounts WHERE id = ? LIMIT 1');
        $stmt->execute([$accountId]);
        $name = trim((string) ($stmt->fetchColumn() ?: ''));

        return $name !== '' ? $name : cashAccountLabel($accountId);
    } catch (Throwable) {
        return cashAccountLabel($accountId);
    }
}

function cashAccountBalance(?PDO $pdo = null, int $accountId = CASH_ACCOUNT_MAIN): float
{
    ensureTreasuryTables();

    $pdo = $pdo ?: db();
    try {
        $stmt = $pdo->prepare('SELECT balance FROM cash_accounts WHERE id = ? LIMIT 1');
        $stmt->execute([$accountId]);
        $value = $stmt->fetchColumn();

        return round((float) $value, 2);
    } catch (Throwable) {
        return 0.0;
    }
}

function treasuryBalance(): float
{
    return cashAccountBalance(db(), CASH_ACCOUNT_MAIN);
}

function reserveTreasuryBalance(?PDO $pdo = null): float
{
    return cashAccountBalance($pdo, CASH_ACCOUNT_RESERVE);
}

function recordCashMovement(
    string $type,
    float $amountUsd,
    string $category,
    string $description,
    ?int $userId,
    ?PDO $pdo = null,
    int $accountId = CASH_ACCOUNT_MAIN
): void {
    ensureTreasuryTables();

    if ($amountUsd <= 0) {
        return;
    }

    if (!in_array($accountId, cashAccountIds(), true)) {
        throw new RuntimeException('cash_account_missing');
    }

    $pdo = $pdo ?: db();
    $movementType = $type === 'withdrawal' ? 'withdrawal' : 'deposit';
    $startedTransaction = false;

    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $startedTransaction = true;
    }

    try {
        $stmt = $pdo->prepare('SELECT id, balance FROM cash_accounts WHERE id = ? FOR UPDATE');
        $stmt->execute([$accountId]);
        $account = $stmt->fetch();
        if (!$account) {
            throw new RuntimeException('cash_account_missing');
        }

        $currentBalance = round((float) ($account['balance'] ?? 0), 2);
        $newBalance = $movementType === 'deposit'
            ? round($currentBalance + $amountUsd, 2)
            : round($currentBalance - $amountUsd, 2);

        if ($movementType === 'withdrawal' && $newBalance < 0) {
            throw new RuntimeException('insufficient_treasury');
        }

        $pdo->prepare('UPDATE cash_accounts SET balance = ? WHERE id = ?')->execute([
            $newBalance,
            (int) $account['id'],
        ]);

        $ref = generateNumber('CSH');
        $pdo->prepare(
            'INSERT INTO treasury_transactions (reference_number, type, category, currency, amount, amount_sar, description, user_id, cash_account_id, balance_after)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $ref,
            $movementType,
            $category,
            treasuryAccountCurrency(),
            $amountUsd,
            $amountUsd,
            $description,
            $userId,
            (int) $account['id'],
            $newBalance,
        ]);

        if ($startedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function recordTreasuryMovement(
    string $type,
    float $amountUsd,
    string $category,
    string $description,
    ?int $userId,
    ?PDO $pdo = null,
    int $accountId = CASH_ACCOUNT_MAIN
): void {
    recordCashMovement($type, $amountUsd, $category, $description, $userId, $pdo, $accountId);
}

function recordCashTransfer(
    int $fromAccountId,
    int $toAccountId,
    float $amount,
    string $description,
    ?int $userId,
    ?PDO $pdo = null
): void {
    if ($fromAccountId === $toAccountId) {
        throw new RuntimeException('same_treasury_account');
    }

    if (!in_array($fromAccountId, cashAccountIds(), true) || !in_array($toAccountId, cashAccountIds(), true)) {
        throw new RuntimeException('cash_account_missing');
    }

    if ($amount <= 0) {
        throw new RuntimeException('invalid_amount');
    }

    $pdo = $pdo ?: db();
    $startedTransaction = false;

    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $startedTransaction = true;
    }

    try {
        $transferRef = generateNumber('TRF');
        $fromLabel = cashAccountLabel($fromAccountId);
        $toLabel = cashAccountLabel($toAccountId);
        $note = $description !== ''
            ? $description
            : __('treasury_transfer_default');

        recordCashMovement(
            'withdrawal',
            $amount,
            'transfer_out',
            $note . ' → ' . $toLabel . ' [' . $transferRef . ']',
            $userId,
            $pdo,
            $fromAccountId
        );

        recordCashMovement(
            'deposit',
            $amount,
            'transfer_in',
            $note . ' ← ' . $fromLabel . ' [' . $transferRef . ']',
            $userId,
            $pdo,
            $toAccountId
        );

        if ($startedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function treasuryCategoryLabel(string $category): string
{
    $key = 'treasury_cat_' . $category;

    return __($key) !== $key ? __($key) : $category;
}
