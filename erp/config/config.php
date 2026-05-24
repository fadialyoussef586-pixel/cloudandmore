<?php

define('APP_NAME', 'IQOS ERP');
define('COMPANY_NAME', 'IQOS');
define('COMPANY_TAGLINE', 'Devices · Accessories · Cloud and More');
define('APP_VERSION', '1.0.0');
define('BASE_PATH', dirname(__DIR__));

// MySQL خارجي — متغيرات البيئة فقط (Render / Aiven / محلي)
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'titan_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '');
define('DB_CHARSET', 'utf8mb4');

// BASE_URL: من Environment أو تلقائي من مسار المشروع
$baseUrl = getenv('BASE_URL') ?: '';
if ($baseUrl !== '') {
    define('BASE_URL', rtrim($baseUrl, '/'));
} else {
    $docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
    $erpRoot = str_replace('\\', '/', realpath(BASE_PATH) ?: BASE_PATH);
    $path = '';
    if ($docRoot !== '' && str_starts_with($erpRoot, $docRoot)) {
        $path = substr($erpRoot, strlen($docRoot));
    }
    define('BASE_URL', rtrim(str_replace('\\', '/', $path), '/'));
}

date_default_timezone_set('Asia/Riyadh');
