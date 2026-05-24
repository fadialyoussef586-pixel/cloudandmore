<?php

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        try {
            if (!is_dir(DB_DIR)) {
                mkdir(DB_DIR, 0755, true);
            }

            $pdo = new PDO(DB_DSN, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA journal_mode = WAL');

            initializeDatabaseIfNeeded($pdo);
        } catch (PDOException $e) {
            $setupUrl = (BASE_URL === '' ? '' : BASE_URL) . '/setup.php';
            $msg = $e->getMessage();
            http_response_code(503);
            echo '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><title>Database Error</title>';
            echo '<style>body{font-family:sans-serif;background:#0f1419;color:#e8edf5;padding:2rem;max-width:560px;margin:auto}';
            echo 'a{color:#3b82f6}.box{background:#1a2332;border:1px solid #2d3a4f;padding:1.5rem;border-radius:10px}</style></head><body>';
            echo '<div class="box"><h1>خطأ في قاعدة البيانات</h1>';
            echo '<p>تأكد أن مجلد <code>database</code> قابل للكتابة ثم افتح:</p>';
            echo '<p><a href="' . htmlspecialchars($setupUrl) . '">setup.php</a></p>';
            echo '<p style="color:#8b9cb3;font-size:0.85rem;margin-top:1rem">' . htmlspecialchars($msg) . '</p>';
            echo '<p style="color:#8b9cb3;font-size:0.85rem">المسار: ' . htmlspecialchars(DB_PATH) . '</p></div></body></html>';
            exit;
        }
    }

    return $pdo;
}

function databaseTableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?");
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

function initializeDatabaseIfNeeded(PDO $pdo): void
{
    if (databaseTableExists($pdo, 'users')) {
        return;
    }

    if (!is_file(DB_SCHEMA_FILE)) {
        throw new PDOException('Schema file not found: ' . DB_SCHEMA_FILE);
    }

    $sql = file_get_contents(DB_SCHEMA_FILE);
    $pdo->exec($sql);
    seedDefaultUsers($pdo);
}

function seedDefaultUsers(PDO $pdo): void
{
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $users = [
        ['Administrator', 'admin@ikos.com', 'admin'],
        ['Sales Staff', 'sales@ikos.com', 'sales'],
        ['Delivery Driver', 'driver@ikos.com', 'driver'],
    ];

    $stmt = $pdo->prepare('INSERT OR IGNORE INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
    foreach ($users as [$name, $email, $role]) {
        $stmt->execute([$name, $email, $hash, $role]);
    }
}
