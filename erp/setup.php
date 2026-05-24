<?php

require_once __DIR__ . '/config/config.php';

$message = '';
$error = '';
$installed = false;

try {
    $dsn = 'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `' . DB_NAME . '`');

    $sql = file_get_contents(__DIR__ . '/database/install.sql');
    $sql = preg_replace('/CREATE DATABASE.*?;/s', '', $sql);
    $sql = preg_replace('/USE erp_system;/', '', $sql);

    $statements = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($statements as $statement) {
        if ($statement !== '') {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate') === false) {
                    throw $e;
                }
            }
        }
    }

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

    $migrate = file_get_contents(__DIR__ . '/database/migrate_shop.sql');
    foreach (array_filter(array_map('trim', explode(';', $migrate))) as $statement) {
        if ($statement !== '') {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate') === false && strpos($e->getMessage(), 'already exists') === false) {
                    // ignore on re-run
                }
            }
        }
    }

    try { $pdo->exec("ALTER TABLE products ADD COLUMN image VARCHAR(255) DEFAULT NULL"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE products ADD COLUMN is_published TINYINT(1) DEFAULT 1"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE deliveries ADD COLUMN order_id INT NULL"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE users MODIFY role ENUM('admin','manager','sales','driver','staff') DEFAULT 'staff'"); } catch (PDOException $e) {}

    $installed = true;
    $message = 'Database ready. Logins: admin@ikos.com / sales@ikos.com / driver@ikos.com — password: admin123';
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
            <p class="text-muted" style="margin-top:1rem;font-size:0.85rem">Make sure MySQL is running and update credentials in config/config.php</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
