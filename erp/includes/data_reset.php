<?php

require_once __DIR__ . '/../config/database.php';

function businessDataTables(): array
{
    return [
        'supplier_debt_payments',
        'purchases',
        'suppliers',
        'treasury_transactions',
        'exchange_rates',
        'delivery_items',
        'deliveries',
        'order_items',
        'orders',
        'invoice_items',
        'invoices',
        'stock_movements',
        'products',
        'customers',
        'payroll',
        'employees',
    ];
}

function clearTable(PDO $pdo, string $table): void
{
    if (!databaseTableExists($pdo, $table)) {
        return;
    }

    try {
        $pdo->exec('TRUNCATE TABLE `' . $table . '`');
    } catch (PDOException) {
        $pdo->exec('DELETE FROM `' . $table . '`');
        try {
            $pdo->exec('ALTER TABLE `' . $table . '` AUTO_INCREMENT = 1');
        } catch (PDOException) {
            // ignore
        }
    }
}

function resetBusinessData(PDO $pdo): void
{
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach (businessDataTables() as $table) {
        clearTable($pdo, $table);
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    // تأكيد إضافي: الخزنة والإيرادات = صفر
    foreach (['treasury_transactions', 'invoice_items', 'invoices', 'orders', 'order_items', 'purchases', 'supplier_debt_payments'] as $table) {
        if (databaseTableExists($pdo, $table)) {
            $pdo->exec('DELETE FROM `' . $table . '`');
        }
    }
}

function financialTotals(PDO $pdo): array
{
    $treasury = 0.0;
    $revenue = 0.0;
    $invoices = 0;

    if (databaseTableExists($pdo, 'treasury_transactions')) {
        $treasury = (float) $pdo->query(
            "SELECT COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount_sar ELSE -amount_sar END), 0)
             FROM treasury_transactions"
        )->fetchColumn();
    }

    if (databaseTableExists($pdo, 'invoices')) {
        $revenue = (float) $pdo->query(
            "SELECT COALESCE(SUM(total), 0) FROM invoices
             WHERE status = 'paid'
               AND MONTH(created_at) = MONTH(CURRENT_DATE())
               AND YEAR(created_at) = YEAR(CURRENT_DATE())"
        )->fetchColumn();
        $invoices = (int) $pdo->query('SELECT COUNT(*) FROM invoices')->fetchColumn();
    }

    return [
        'treasury' => $treasury,
        'revenue' => $revenue,
        'invoices' => $invoices,
    ];
}

function ownerAccountPayload(): array
{
    return [
        'hash' => password_hash('admin123', PASSWORD_DEFAULT),
        'permissions' => json_encode([
            'orders', 'inventory', 'purchases', 'invoices', 'hr', 'delivery', 'treasury', 'reports',
        ]),
    ];
}

/**
 * Ensures exactly one owner user (OWNER_EMAIL). Migrates legacy admin@iqos.com if needed.
 */
function ensureOwnerAccount(PDO $pdo): void
{
    $ownerEmail = strtolower(trim(OWNER_EMAIL));
    $payload = ownerAccountPayload();

    $find = $pdo->prepare('SELECT id, email FROM users WHERE LOWER(email) = ? LIMIT 1');
    $find->execute([$ownerEmail]);
    $owner = $find->fetch();

    if (!$owner) {
        $legacyEmails = ['admin@iqos.com', 'administrator@iqos.com'];
        foreach ($legacyEmails as $legacy) {
            $find->execute([$legacy]);
            $legacyUser = $find->fetch();
            if ($legacyUser) {
                $pdo->prepare(
                    'UPDATE users SET name = ?, email = ?, password = ?, role = ?, permissions = ? WHERE id = ?'
                )->execute([
                    'Administrator',
                    $ownerEmail,
                    $payload['hash'],
                    'admin',
                    $payload['permissions'],
                    $legacyUser['id'],
                ]);
                $owner = ['id' => $legacyUser['id'], 'email' => $ownerEmail];
                break;
            }
        }
    }

    if (!$owner) {
        $pdo->prepare(
            'INSERT INTO users (name, email, password, role, permissions) VALUES (?, ?, ?, ?, ?)'
        )->execute([
            'Administrator',
            $ownerEmail,
            $payload['hash'],
            'admin',
            $payload['permissions'],
        ]);
        $ownerId = (int) $pdo->lastInsertId();
    } else {
        $ownerId = (int) $owner['id'];
        $pdo->prepare('UPDATE users SET name = ?, password = ?, role = ?, permissions = ? WHERE id = ?')
            ->execute(['Administrator', $payload['hash'], 'admin', $payload['permissions'], $ownerId]);
    }

    $pdo->prepare('DELETE FROM users WHERE id <> ?')->execute([$ownerId]);
}

function removeDemoUsers(PDO $pdo): int
{
    $demo = ['sales@iqos.com', 'driver@iqos.com', 'admin@iqos.com'];
    $owner = strtolower(trim(OWNER_EMAIL));
    $removed = 0;
    foreach ($demo as $email) {
        if (strtolower($email) === $owner) {
            continue;
        }
        $stmt = $pdo->prepare('DELETE FROM users WHERE LOWER(email) = ?');
        $stmt->execute([strtolower($email)]);
        $removed += $stmt->rowCount();
    }

    return $removed;
}

function ensureSchemasBeforeReset(PDO $pdo): void
{
    $files = [
        __DIR__ . '/../database/migrate_currency_treasury.sql',
        __DIR__ . '/../database/migrate_invoice_simple.sql',
        __DIR__ . '/../database/migrate_purchases.sql',
        __DIR__ . '/../database/migrate_shop.sql',
        __DIR__ . '/../database/migrate_permissions.sql',
    ];
    foreach ($files as $path) {
        if (is_file($path)) {
            runSqlFile($pdo, $path);
        }
    }
}
