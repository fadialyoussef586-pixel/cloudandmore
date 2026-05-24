<?php
/**
 * تصفير الخزنة وإيرادات الشهر — يمسح الجداول التراكمية ويصفّر الحقول المالية.
 * المسار: /fix-zero.php  أو  /erp/fix-zero.php (يشير لهذا الملف)
 */

declare(strict_types=1);

require_once __DIR__ . '/erp/config/config.php';
require_once __DIR__ . '/erp/config/database.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$pdo = db();
$message = '';
$error = '';
$ran = false;
$log = [];

function safeTable(string $table): ?string
{
    return preg_match('/^[a-zA-Z0-9_]+$/', $table) ? str_replace('`', '``', $table) : null;
}

function tableExists(PDO $pdo, string $table): bool
{
    $safe = safeTable($table);
    if ($safe === null) {
        return false;
    }
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);

    return (int) $stmt->fetchColumn() > 0;
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);

    return (int) $stmt->fetchColumn() > 0;
}

function treasurySum(PDO $pdo): float
{
    if (!tableExists($pdo, 'treasury_transactions')) {
        return 0.0;
    }
  try {
        return (float) $pdo->query(
            "SELECT COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount_sar ELSE -amount_sar END), 0)
             FROM treasury_transactions"
        )->fetchColumn();
    } catch (Throwable) {
        return 0.0;
    }
}

function monthlyRevenueSum(PDO $pdo): float
{
    if (!tableExists($pdo, 'invoices')) {
        return 0.0;
    }
    try {
        return (float) $pdo->query(
            "SELECT COALESCE(SUM(total), 0) FROM invoices
             WHERE status = 'paid'
               AND MONTH(created_at) = MONTH(CURRENT_DATE())
               AND YEAR(created_at) = YEAR(CURRENT_DATE())"
        )->fetchColumn();
    } catch (Throwable) {
        return 0.0;
    }
}

function zeroFinancialColumns(PDO $pdo, array &$log): void
{
    $stmt = $pdo->query(
        "SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME NOT IN ('users', 'exchange_rates')
           AND COLUMN_NAME IN (
               'balance', 'revenue', 'debt_balance', 'amount_paid',
               'total', 'subtotal', 'tax_amount', 'discount',
               'amount_sar', 'amount', 'net_salary', 'base_salary',
               'bonus', 'deductions', 'unit_cost', 'unit_price', 'cost_price', 'sell_price'
           )
           AND DATA_TYPE IN ('decimal', 'float', 'double', 'int', 'bigint', 'smallint', 'mediumint', 'tinyint')"
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $table = safeTable($row['TABLE_NAME']);
        $col = safeTable($row['COLUMN_NAME']);
        if ($table === null || $col === null) {
            continue;
        }
        try {
            $pdo->exec("UPDATE `{$table}` SET `{$col}` = 0");
            $log[] = "UPDATE {$row['TABLE_NAME']}.{$row['COLUMN_NAME']} = 0";
        } catch (PDOException $e) {
            $log[] = "SKIP {$row['TABLE_NAME']}.{$row['COLUMN_NAME']}: " . $e->getMessage();
        }
    }
}

function fixZeroAll(PDO $pdo, array &$log): void
{
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    $deleteFirst = [
        'supplier_debt_payments',
        'treasury_transactions',
        'invoice_items',
        'invoices',
        'order_items',
        'orders',
        'delivery_items',
        'deliveries',
        'purchases',
        'stock_movements',
        'payroll',
        'products',
        'customers',
        'employees',
        'suppliers',
    ];

    foreach ($deleteFirst as $table) {
        if (!tableExists($pdo, $table)) {
            continue;
        }
        $safe = safeTable($table);
        try {
            $pdo->exec("DELETE FROM `{$safe}`");
            $log[] = "DELETE FROM {$table}";
        } catch (PDOException $e) {
            $log[] = "DELETE {$table} failed: " . $e->getMessage();
        }
    }

    zeroFinancialColumns($pdo, $log);

    if (tableExists($pdo, 'products')) {
        $sets = [];
        foreach (['quantity', 'cost_price', 'sell_price'] as $col) {
            if (columnExists($pdo, 'products', $col)) {
                $sets[] = "`{$col}` = 0";
            }
        }
        if ($sets !== []) {
            $pdo->exec('UPDATE products SET ' . implode(', ', $sets));
            $log[] = 'UPDATE products quantities/prices = 0';
        }
    }

    if (tableExists($pdo, 'purchases') && columnExists($pdo, 'purchases', 'debt_balance')) {
        $pdo->exec('UPDATE purchases SET debt_balance = 0, amount_paid = 0, total = 0, unit_cost = 0');
        $log[] = 'UPDATE purchases financial columns = 0';
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

$beforeTreasury = treasurySum($pdo);
$beforeRevenue = monthlyRevenueSum($pdo);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['confirm'] ?? '') === 'ZERO') {
    try {
        fixZeroAll($pdo, $log);
        $ran = true;
        $afterTreasury = treasurySum($pdo);
        $afterRevenue = monthlyRevenueSum($pdo);

        if ($afterTreasury == 0.0 && $afterRevenue == 0.0) {
            $message = 'تم تصفير الخزنة وإيرادات الشهر بنجاح — الرصيد والإيراد = 0';
        } else {
            $error = sprintf(
                'بقي رصيد=%s إيراد=%s — راجع السجل أدناه',
                number_format($afterTreasury, 2),
                number_format($afterRevenue, 2)
            );
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$base = defined('BASE_URL') && BASE_URL !== '' ? BASE_URL : '/erp';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>fix-zero — تصفير الخزنة والإيرادات</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f8fafc; color: #0f172a; padding: 1.5rem; max-width: 760px; margin: auto; line-height: 1.6; }
        .box { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1rem; }
        .ok { color: #16a34a; font-weight: 700; }
        .bad { color: #dc2626; font-weight: 700; }
        .btn { background: #dc2626; color: #fff; border: none; padding: 0.85rem 1rem; border-radius: 8px; width: 100%; cursor: pointer; font-size: 1rem; }
        input { width: 100%; padding: 0.65rem; margin: 0.5rem 0 1rem; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; }
        pre { background: #f1f5f9; padding: 0.75rem; border-radius: 6px; font-size: 0.78rem; overflow: auto; max-height: 220px; }
        a { color: #00a8c9; }
        ul { margin: 0.5rem 0; padding-inline-start: 1.25rem; }
    </style>
</head>
<body>
    <h1>fix-zero.php — تصفير الخزنة والإيرادات</h1>
    <p>قاعدة البيانات: <strong><?= htmlspecialchars(DB_NAME) ?></strong> @ <?= htmlspecialchars(DB_HOST) ?></p>

    <div class="box">
        <h2>قبل التصفير</h2>
        <p>رصيد الخزنة (treasury_transactions): <span class="<?= $beforeTreasury == 0 ? 'ok' : 'bad' ?>"><?= number_format($beforeTreasury, 2) ?> USD</span></p>
        <p>إيرادات الشهر (invoices): <span class="<?= $beforeRevenue == 0 ? 'ok' : 'bad' ?>"><?= number_format($beforeRevenue, 2) ?> USD</span></p>
    </div>

    <?php if ($message): ?><div class="box ok"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="box bad"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="box">
        <h2>ماذا يفعل هذا الملف؟</h2>
        <ul>
            <li><strong>DELETE</strong> من <code>treasury_transactions</code> (رصيد الخزنة التراكمي)</li>
            <li><strong>DELETE</strong> من <code>invoices</code> و <code>invoice_items</code> (إيرادات الشهر)</li>
            <li><strong>UPDATE</strong> كل الحقول <code>balance</code>, <code>revenue</code>, <code>debt_balance</code>, <code>total</code>, <code>amount_sar</code> … = <strong>0</strong></li>
        </ul>
    </div>

    <?php if ($ran && $log !== []): ?>
    <div class="box">
        <h2>سجل التنفيذ</h2>
        <pre><?= htmlspecialchars(implode("\n", $log)) ?></pre>
    </div>
    <?php endif; ?>

    <form method="post" class="box" onsubmit="return confirm('تصفير الخزنة والإيرادات نهائياً؟')">
        <p>اكتب <strong>ZERO</strong> للتأكيد:</p>
        <input type="text" name="confirm" placeholder="ZERO" required pattern="ZERO" autocomplete="off">
        <button type="submit" class="btn">تصفير الخزنة والإيرادات الآن</button>
    </form>

    <p style="margin-top:1.25rem">
        <a href="<?= htmlspecialchars($base . '/index.php?fresh=' . time()) ?>">لوحة التحكم</a>
        · <a href="<?= htmlspecialchars($base . '/check.php') ?>">check.php</a>
    </p>
</body>
</html>
