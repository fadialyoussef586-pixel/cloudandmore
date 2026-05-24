<?php
/**
 * صفحة فحص — افتحها أولاً إذا النظام لا يعمل
 */
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/includes/functions.php';

$checks = [];

$checks[] = [
    'label' => 'PHP',
    'ok' => version_compare(PHP_VERSION, '8.0.0', '>='),
    'detail' => 'الإصدار: ' . PHP_VERSION,
];

$checks[] = [
    'label' => 'PDO MySQL',
    'ok' => extension_loaded('pdo_mysql'),
    'detail' => extension_loaded('pdo_mysql') ? 'مفعّل' : 'غير مفعّل — ثبّت pdo_mysql',
];

$checks[] = [
    'label' => 'مجلد المشروع',
    'ok' => is_dir(BASE_PATH),
    'detail' => BASE_PATH,
];

$checks[] = [
    'label' => 'BASE_URL (للروابط)',
    'ok' => true,
    'detail' => BASE_URL === '' ? '(فارغ = جذر السيرفر — صحيح مع php -S من مجلد erp)' : BASE_URL,
];

$dbOk = false;
$dbDetail = '';
try {
    $pdo = db();
    $dbOk = databaseTableExists($pdo, 'users');
    $dbDetail = $dbOk
        ? 'متصل — ' . DB_NAME . ' @ ' . DB_HOST
        : 'متصل — شغّل setup.php لإنشاء الجداول';
} catch (Throwable $e) {
    $dbDetail = $e->getMessage();
}

$checks[] = [
    'label' => 'MySQL',
    'ok' => $dbOk,
    'detail' => $dbDetail,
];

$loginUrl = url('login.php');
$setupUrl = url('setup.php');
$shopUrl = shopUrl();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فحص النظام | <?= e(APP_NAME) ?></title>
    <style>
        body { font-family: system-ui, sans-serif; background: #0f1419; color: #e8edf5; padding: 2rem; max-width: 640px; margin: auto; }
        h1 { margin-bottom: 0.5rem; }
        .item { background: #1a2332; border: 1px solid #2d3a4f; padding: 1rem; border-radius: 8px; margin-bottom: 0.75rem; display: flex; justify-content: space-between; gap: 1rem; }
        .ok { color: #22c55e; }
        .fail { color: #ef4444; }
        .detail { color: #8b9cb3; font-size: 0.85rem; margin-top: 0.35rem; }
        .links { margin-top: 1.5rem; display: flex; flex-wrap: wrap; gap: 0.5rem; }
        a { background: #3b82f6; color: #fff; padding: 0.6rem 1rem; border-radius: 6px; text-decoration: none; font-size: 0.9rem; }
        a.secondary { background: #243044; }
        code { background: #243044; padding: 0.15rem 0.4rem; border-radius: 4px; font-size: 0.85rem; }
    </style>
</head>
<body>
    <h1>فحص نظام <?= e(APP_NAME) ?></h1>
    <p style="color:#8b9cb3;margin-bottom:1.5rem">إذا في خانة حمراء، صلّحها ثم أعد التحميل.</p>

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
        <a href="<?= htmlspecialchars($setupUrl) ?>">setup.php — تثبيت قاعدة البيانات</a>
        <a href="<?= htmlspecialchars($loginUrl) ?>">تسجيل الدخول</a>
        <a href="<?= htmlspecialchars($shopUrl) ?>" class="secondary">المتجر</a>
    </div>

    <p style="color:#8b9cb3;font-size:0.85rem;margin-top:2rem">
        للتشغيل من الطرفية:<br>
        <code>cd erp && php -S localhost:8080</code><br>
        ثم افتح: <code>http://localhost:8080/check.php</code>
    </p>
</body>
</html>
