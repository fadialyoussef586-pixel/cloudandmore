<?php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/data_reset.php';

header('Content-Type: text/html; charset=utf-8');

$pdo = db();
$message = '';
$error = '';
$ran = false;
$after = [];

function tableRowCounts(PDO $pdo): array
{
    $rows = [];
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', (string) $table)) {
            continue;
        }
        $safe = str_replace('`', '``', $table);
        $rows[$table] = (int) $pdo->query('SELECT COUNT(*) FROM `' . $safe . '`')->fetchColumn();
    }

    return $rows;
}

$before = tableRowCounts($pdo);
$totalsBefore = financialTotals($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === 'ZERO') {
    try {
        $errors = nuclearZeroData($pdo);
        ensureOwnerAccount($pdo);
        $totalsAfter = financialTotals($pdo);
        $after = tableRowCounts($pdo);
        $ran = true;

        if ($errors !== []) {
            $error = implode(' | ', $errors);
        } elseif ($totalsAfter['treasury'] != 0.0 || $totalsAfter['revenue'] != 0.0) {
            $error = 'Treasury=' . $totalsAfter['treasury'] . ' Revenue=' . $totalsAfter['revenue'];
        } else {
            $message = 'تم تصفير كل الجداول بنجاح — الخزنة والإيرادات = 0';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$base = BASE_URL === '' ? '' : BASE_URL;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تصفير كامل</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f8fafc; color: #0f172a; padding: 1.5rem; max-width: 720px; margin: auto; }
        .box { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1rem; margin-bottom: 1rem; }
        .ok { color: #16a34a; } .bad { color: #dc2626; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td { border: 1px solid #e2e8f0; padding: 0.4rem 0.6rem; text-align: start; }
        .btn { background: #dc2626; color: #fff; border: none; padding: 0.75rem 1rem; border-radius: 8px; width: 100%; cursor: pointer; font-size: 1rem; }
        .link { display: inline-block; margin-top: 1rem; color: #00a8c9; }
        input { width: 100%; padding: 0.6rem; margin: 0.5rem 0 1rem; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; }
    </style>
</head>
<body>
    <h1>تصفير كامل للبيانات</h1>
    <p>قاعدة البيانات: <strong><?= htmlspecialchars(DB_NAME) ?></strong> @ <?= htmlspecialchars(DB_HOST) ?></p>

    <div class="box">
        <h2>الأرقام الحالية</h2>
        <p>إيرادات الشهر: <strong class="<?= $totalsBefore['revenue'] == 0 ? 'ok' : 'bad' ?>"><?= number_format($totalsBefore['revenue'], 2) ?> USD</strong></p>
        <p>رصيد الخزنة: <strong class="<?= $totalsBefore['treasury'] == 0 ? 'ok' : 'bad' ?>"><?= number_format($totalsBefore['treasury'], 2) ?> USD</strong></p>
        <p>عدد الفواتير: <?= (int) $totalsBefore['invoices'] ?> | حركات الخزنة: <?= (int) $totalsBefore['treasury_rows'] ?></p>
    </div>

    <?php if ($message): ?><div class="box ok"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="box bad"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="box">
        <h2>محتوى الجداول</h2>
        <table>
            <thead><tr><th>الجدول</th><th>قبل</th><?php if ($ran): ?><th>بعد</th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($before as $table => $count): ?>
                <tr>
                    <td><?= htmlspecialchars($table) ?></td>
                    <td><?= $count ?></td>
                    <?php if ($ran): ?><td><?= (int) ($after[$table] ?? 0) ?></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <form method="post" class="box" onsubmit="return confirm('حذف كل البيانات ما عدا حساب المالك؟')">
        <p>اكتب <strong>ZERO</strong> للتأكيد — يمسح كل الجداول ما عدا users</p>
        <input type="text" name="confirm" placeholder="ZERO" required pattern="ZERO" autocomplete="off">
        <button type="submit" class="btn">تصفير كامل الآن</button>
    </form>

    <a class="link" href="<?= htmlspecialchars($base . '/index.php') ?>">لوحة التحكم</a>
    · <a class="link" href="<?= htmlspecialchars($base . '/setup.php') ?>">setup.php</a>
</body>
</html>
