<?php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$message = '';
$error = '';
$installed = false;

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

    $ownerEmail = OWNER_EMAIL;
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $allPerms = json_encode([
        'orders', 'inventory', 'purchases', 'invoices', 'hr', 'delivery', 'treasury', 'reports',
    ]);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
    $stmt->execute([$ownerEmail]);
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->prepare('INSERT INTO users (name, email, password, role, permissions) VALUES (?, ?, ?, ?, ?)')
            ->execute(['Administrator', $ownerEmail, $hash, 'admin', $allPerms]);
    } else {
        $pdo->prepare('UPDATE users SET password = ?, role = ?, permissions = ? WHERE email = ?')
            ->execute([$hash, 'admin', $allPerms, $ownerEmail]);
    }

    $migratePath = __DIR__ . '/database/migrate_shop.sql';
    if (is_file($migratePath)) {
        runSqlFile($pdo, $migratePath);
    }

    $installed = true;
    $message = 'Database ready. Owner login: ' . $ownerEmail . ' — password: admin123 (change after first login).';
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$base = BASE_URL === '' ? '' : BASE_URL;
require_once __DIR__ . '/includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ERP Setup</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($base . '/assets/css/app.css') ?>">
</head>
<body>
<div class="login-page">
    <div class="login-box">
        <div class="login-logo-wrap"><?= companyLogoHtml('company-logo company-logo--login') ?></div>
        <h1 class="login-title">ERP Setup</h1>
        <?php if ($installed): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <a href="<?= htmlspecialchars($base . '/login.php') ?>" class="btn btn-primary" style="width:100%;text-align:center;margin-top:1rem;display:block;padding:0.75rem">Go to Login</a>
        <?php elseif ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <p class="text-muted" style="margin-top:1rem;font-size:0.85rem">Set DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD on Render then reload.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
