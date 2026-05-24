<?php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$message = '';
$error = '';
$installed = false;

try {
    $pdo = db();

    runSqlFile($pdo, __DIR__ . '/database/install.sql');

    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $demoUsers = [
        ['Administrator', 'admin@ikos.com', 'admin'],
        ['Sales Staff', 'sales@ikos.com', 'sales'],
        ['Delivery Driver', 'driver@ikos.com', 'driver'],
    ];
    foreach ($demoUsers as [$name, $email, $role]) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ((int) $stmt->fetchColumn() === 0) {
            $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)')
                ->execute([$name, $email, $hash, $role]);
        } else {
            $pdo->prepare('UPDATE users SET password = ?, role = ? WHERE email = ?')->execute([$hash, $role, $email]);
        }
    }

    $migratePath = __DIR__ . '/database/migrate_shop.sql';
    if (is_file($migratePath)) {
        runSqlFile($pdo, $migratePath);
    }

    $installed = true;
    $message = 'MySQL database ready. Logins: admin@ikos.com / sales@ikos.com / driver@ikos.com — password: admin123';
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$base = BASE_URL === '' ? '' : BASE_URL;
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
        <h1>ERP Setup</h1>
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
