<?php

function ensureCurrentAccountTables(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $path = BASE_PATH . '/database/migrate_current_accounts.sql';
    if (is_file($path)) {
        runSqlFile(db(), $path);
    }
}

function currentAccountBalance(int $accountId, ?PDO $pdo = null): float
{
    ensureCurrentAccountTables();
    $pdo = $pdo ?: db();

    $stmt = $pdo->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END), 0) -
            COALESCE(SUM(CASE WHEN type = 'repayment' THEN amount ELSE 0 END), 0) AS balance
         FROM current_account_movements
         WHERE account_id = ?"
    );
    $stmt->execute([$accountId]);

    return round((float) $stmt->fetchColumn(), 2);
}

function currentAccountTotals(int $accountId, ?PDO $pdo = null): array
{
    ensureCurrentAccountTables();
    $pdo = $pdo ?: db();

    $stmt = $pdo->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END), 0) AS withdrawn,
            COALESCE(SUM(CASE WHEN type = 'repayment' THEN amount ELSE 0 END), 0) AS repaid
         FROM current_account_movements
         WHERE account_id = ?"
    );
    $stmt->execute([$accountId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['withdrawn' => 0, 'repaid' => 0];

    $withdrawn = round((float) $row['withdrawn'], 2);
    $repaid = round((float) $row['repaid'], 2);

    return [
        'withdrawn' => $withdrawn,
        'repaid' => $repaid,
        'balance' => round($withdrawn - $repaid, 2),
    ];
}

function getCurrentAccount(int $id): ?array
{
    ensureCurrentAccountTables();
    $stmt = db()->prepare('SELECT * FROM current_accounts WHERE id = ?');
    $stmt->execute([$id]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function recordCurrentAccountWithdrawal(
    int $accountId,
    float $amount,
    string $description,
    ?int $userId,
    ?PDO $pdo = null
): void {
    ensureCurrentAccountTables();
    if ($amount <= 0) {
        throw new RuntimeException('invalid_amount');
    }

    $pdo = $pdo ?: db();
    $account = getCurrentAccount($accountId);
    if (!$account || !(int) ($account['is_active'] ?? 0)) {
        throw new RuntimeException('account_inactive');
    }

    $started = false;
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $started = true;
    }

    try {
        $ref = generateNumber('CAW');
        recordTreasuryMovement(
            'withdrawal',
            $amount,
            'current_account',
            __('current_accounts') . ' — ' . ($account['name'] ?? '') . ' / ' . $description,
            $userId,
            $pdo
        );

        $pdo->prepare(
            'INSERT INTO current_account_movements (account_id, type, amount, description, treasury_reference, user_id)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $accountId,
            'withdrawal',
            round($amount, 2),
            $description,
            $ref,
            $userId,
        ]);

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

function recordCurrentAccountRepayment(
    int $accountId,
    float $amount,
    string $description,
    ?int $userId,
    ?PDO $pdo = null
): void {
    ensureCurrentAccountTables();
    if ($amount <= 0) {
        throw new RuntimeException('invalid_amount');
    }

    $pdo = $pdo ?: db();
    $account = getCurrentAccount($accountId);
    if (!$account) {
        throw new RuntimeException('not_found');
    }

    $balance = currentAccountBalance($accountId, $pdo);
    if ($amount > $balance) {
        throw new RuntimeException('repayment_exceeds_balance');
    }

    $started = false;
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $started = true;
    }

    try {
        $ref = generateNumber('CAR');
        recordTreasuryMovement(
            'deposit',
            $amount,
            'current_account_repay',
            __('ca_repayment') . ' — ' . ($account['name'] ?? '') . ' / ' . $description,
            $userId,
            $pdo
        );

        $pdo->prepare(
            'INSERT INTO current_account_movements (account_id, type, amount, description, treasury_reference, user_id)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $accountId,
            'repayment',
            round($amount, 2),
            $description,
            $ref,
            $userId,
        ]);

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

function currentAccountMovementLabel(string $type): string
{
    return $type === 'repayment' ? __('ca_repayment') : __('ca_withdrawal');
}
