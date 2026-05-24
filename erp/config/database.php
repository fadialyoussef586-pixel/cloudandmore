<?php

require_once __DIR__ . '/config.php';

function mysqlDsn(): string
{
    return sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        try {
            $pdo = new PDO(mysqlDsn(), DB_USER, DB_PASSWORD, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            $setupUrl = (BASE_URL === '' ? '' : BASE_URL) . '/setup.php';
            $msg = $e->getMessage();

            http_response_code(503);
            echo '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><title>Database Error</title>';
            echo '<style>body{font-family:sans-serif;background:#0f1419;color:#e8edf5;padding:2rem;max-width:560px;margin:auto}';
            echo 'a{color:#3b82f6}.box{background:#1a2332;border:1px solid #2d3a4f;padding:1.5rem;border-radius:10px}</style></head><body>';
            echo '<div class="box"><h1>قاعدة البيانات غير متصلة</h1>';
            echo '<p>تأكد من متغيرات البيئة: DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD</p>';
            echo '<p><a href="' . htmlspecialchars($setupUrl) . '">فتح setup.php</a></p>';
            echo '<p style="color:#8b9cb3;font-size:0.85rem;margin-top:1rem">' . htmlspecialchars($msg) . '</p>';
            echo '<p style="color:#8b9cb3;font-size:0.85rem">Host: ' . htmlspecialchars(DB_HOST) . ':' . htmlspecialchars(DB_PORT) . ' | DB: ' . htmlspecialchars(DB_NAME) . '</p>';
            echo '</div></body></html>';
            exit;
        }
    }

    return $pdo;
}

function dbLastInsertId(PDO $pdo): int
{
    return (int) $pdo->lastInsertId();
}

function runSqlFile(PDO $pdo, string $path): void
{
    if (!is_file($path)) {
        return;
    }

    $sql = file_get_contents($path);
    $sql = preg_replace('/CREATE DATABASE.*?;/s', '', $sql);
    $sql = preg_replace('/USE\s+\w+\s*;/i', '', $sql);

    foreach (preg_split('/;\s*\n/', $sql) as $statement) {
        $statement = trim($statement);
        if ($statement === '' || str_starts_with($statement, '--')) {
            continue;
        }

        try {
            $pdo->exec($statement);
        } catch (PDOException $e) {
            $message = $e->getMessage();
            if (
                stripos($message, 'already exists') === false
                && stripos($message, 'duplicate') === false
            ) {
                throw $e;
            }
        }
    }
}

function databaseTableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
    $stmt->execute([$table]);

    return (bool) $stmt->fetch();
}
