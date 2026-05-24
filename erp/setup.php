<?php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/data_reset.php';

$message = '';
$error = '';
$installed = false;
$ownerEmail = OWNER_EMAIL;
$didReset = false;

try {
    $pdo = db();

    runSqlFile($pdo, __DIR__ . '/database/install.sql');
    runSqlFile($pdo, __DIR__ . '/database/migrate_currency_treasury.sql');
    runSqlFile($pdo, __DIR__ . '/database/migrate_invoice_simple.sql');
    runSqlFile($pdo, __DIR__ . '/database/migrate_purchases.sql');

    $migratePerm = __DIR__ . '/database/migrate_permissions.sql';
    if (is_file($migratePerm)) {
        runSqlFile($pdo, $migratePerm);
    }

    $migratePath = __DIR__ . '/database/migrate_shop.sql';
    if (is_file($migratePath)) {
        runSqlFile($pdo, $migratePath);
    }

    ensureOwnerAccount($pdo);
    removeDemoUsers($pdo);

    $wantReset = ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === 'RESET')
        || (isset($_GET['reset']) && $_GET['reset'] === 'RESET');

    if ($wantReset) {
        $ok = resetBusinessDataVerified($pdo);
        ensureOwnerAccount($pdo);
        $didReset = true;
        $message = 'Owner account: ' . $ownerEmail . ' — password: admin123';
        if ($ok) {
            $message .= ' | All business data has been reset to zero.';
        } else {
            $message .= ' | WARNING: Some financial data may remain — try reset again or contact support.';
        }
    } else {
        $message = 'Owner account: ' . $ownerEmail . ' — password: admin123';
        $message .= ' | To clear products, sales, employees: submit RESET below.';
    }

    $installed = true;
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$base = BASE_URL === '' ? '' : BASE_URL;
require_once __DIR__ . '/includes/functions.php';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ERP Setup</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($base . '/assets/css/app.css') ?>">
</head>
<body>
<div class="login-page">
    <div class="login-box" style="max-width:420px">
        <div class="login-logo-wrap"><?= companyLogoHtml('company-logo company-logo--login') ?></div>
        <h1 class="login-title">إعداد النظام / Setup</h1>

        <?php if ($installed): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <p class="text-muted" style="font-size:0.9rem;margin-top:1rem;line-height:1.6">
                سجّل الدخول بـ:<br>
                <strong><?= htmlspecialchars($ownerEmail) ?></strong><br>
                كلمة المرور: <strong>admin123</strong>
            </p>
            <a href="<?= htmlspecialchars($base . '/login.php') ?>" class="btn btn-primary" style="width:100%;text-align:center;margin-top:1rem;display:block;padding:0.75rem">تسجيل الدخول</a>
            <a href="<?= htmlspecialchars($base . '/fix-zero.php') ?>" class="btn btn-danger" style="width:100%;text-align:center;margin-top:0.75rem;display:block;padding:0.75rem">تصفير كامل (fix-zero)</a>

            <hr style="margin:1.5rem 0;border:none;border-top:1px solid var(--border)">

            <h2 style="font-size:1rem;margin-bottom:0.5rem">تصفير كل البيانات</h2>
            <p class="text-muted" style="font-size:0.85rem;margin-bottom:1rem">
                يحذف: المنتجات، الفواتير، إيرادات الشهر، حركات الخزنة (treasury_transactions)، الموظفين.
                يبقى حساب المالك فقط.
            </p>
            <form method="post" onsubmit="return confirm('حذف كل البيانات؟')">
                <div class="form-group">
                    <label>اكتب RESET للتأكيد</label>
                    <input type="text" name="confirm" placeholder="RESET" required pattern="RESET" autocomplete="off">
                </div>
                <button type="submit" class="btn btn-danger" style="width:100%">تصفير البيانات</button>
            </form>
        <?php elseif ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <p class="text-muted" style="margin-top:1rem;font-size:0.85rem">تحقق من DB_HOST و DB_NAME على Render ثم أعد التحميل.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
