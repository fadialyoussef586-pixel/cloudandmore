<?php

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireOwner();
require_once __DIR__ . '/includes/data_reset.php';

$checks = [];

$checks[] = [
    'label' => 'PHP',
    'ok' => version_compare(PHP_VERSION, '8.0.0', '>='),
    'detail' => 'Version: ' . PHP_VERSION,
];

$checks[] = [
    'label' => 'PDO MySQL',
    'ok' => extension_loaded('pdo_mysql'),
    'detail' => extension_loaded('pdo_mysql') ? 'Enabled' : 'Missing — install pdo_mysql',
];

$checks[] = [
    'label' => 'Project folder',
    'ok' => is_dir(BASE_PATH),
    'detail' => BASE_PATH,
];

$checks[] = [
    'label' => 'BASE_URL',
    'ok' => true,
    'detail' => BASE_URL === '' ? '(empty = web root)' : BASE_URL,
];

$dbOk = false;
$dbDetail = '';
try {
    $pdo = db();
    $dbOk = databaseTableExists($pdo, 'users');
    $dbDetail = $dbOk
        ? 'Connected — ' . DB_NAME . ' @ ' . DB_HOST
        : 'Connected — run setup.php to create tables';
} catch (Throwable $e) {
    $dbDetail = $e->getMessage();
}

$checks[] = [
    'label' => 'Database',
    'ok' => $dbOk,
    'detail' => $dbDetail,
];

$loginUrl = url('login.php');
$setupUrl = url('setup.php');
$fixZeroUrl = url('fix-zero.php');
$shopUrl = shopUrl();
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Check | <?= e(APP_NAME) ?></title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f8fafc; color: #0f172a; padding: 2rem; max-width: 640px; margin: auto; }
        h1 { margin-bottom: 0.5rem; }
        .item { background: #fff; border: 1px solid #e2e8f0; padding: 1rem; border-radius: 8px; margin-bottom: 0.75rem; display: flex; justify-content: space-between; gap: 1rem; }
        .ok { color: #16a34a; }
        .fail { color: #dc2626; }
        .detail { color: #64748b; font-size: 0.85rem; margin-top: 0.35rem; }
        .links { margin-top: 1.5rem; display: flex; flex-wrap: wrap; gap: 0.5rem; }
        a { background: #00a8c9; color: #fff; padding: 0.6rem 1rem; border-radius: 6px; text-decoration: none; font-size: 0.9rem; }
        a.secondary { background: #e2e8f0; color: #0f172a; }
        code { background: #f1f5f9; padding: 0.15rem 0.4rem; border-radius: 4px; font-size: 0.85rem; }
    </style>
</head>
<body>
    <h1><?= e(APP_NAME) ?> — System Check</h1>
    <p style="color:#64748b;margin-bottom:1.5rem">Fix any failed item below, then reload.</p>

    <?php foreach ($checks as $c): ?>
        <div class="item">
            <div>
                <strong><?= htmlspecialchars($c['label']) ?></strong>
                <div class="detail"><?= htmlspecialchars($c['detail']) ?></div>
            </div>
            <span class="<?= $c['ok'] ? 'ok' : 'fail' ?>"><?= $c['ok'] ? '✓' : '✗' ?></span>
        </div>
    <?php endforeach; ?>

    <div class="links">
        <a href="<?= htmlspecialchars($setupUrl) ?>">setup.php — Install database</a>
        <a href="<?= htmlspecialchars($fixZeroUrl) ?>">fix-zero.php — Full reset</a>
        <a href="<?= htmlspecialchars($loginUrl) ?>">Login</a>
        <a href="<?= htmlspecialchars($shopUrl) ?>" class="secondary">Shop</a>
    </div>

    <p style="color:#64748b;font-size:0.85rem;margin-top:2rem">
        Local dev:<br>
        <code>cd erp && php -S localhost:8080</code><br>
        Then open: <code>http://localhost:8080/check.php</code>
    </p>
</body>
</html>
