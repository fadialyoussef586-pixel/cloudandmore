<?php

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
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
            echo '<p>شغّل MySQL ثم افتح صفحة الإعداد:</p>';
            echo '<p><a href="' . htmlspecialchars($setupUrl) . '">فتح setup.php</a></p>';
            echo '<p style="color:#8b9cb3;font-size:0.85rem;margin-top:1rem">' . htmlspecialchars($msg) . '</p>';
            echo '<p style="color:#8b9cb3;font-size:0.85rem">عدّل بيانات الاتصال في: erp/config/config.php</p></div></body></html>';
            exit;
        }
    }

    return $pdo;
}
