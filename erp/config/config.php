<?php

define('APP_NAME', 'IKOS ERP');
define('COMPANY_NAME', 'IKOS');
define('COMPANY_TAGLINE', 'Devices · Accessories · Asma Cloud & More');
define('APP_VERSION', '1.0.0');
define('BASE_PATH', dirname(__DIR__));

// مسار المشروع من جذر السيرفر (يعمل مع php -S و Apache و Docker)
$docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
$erpRoot = str_replace('\\', '/', realpath(BASE_PATH) ?: BASE_PATH);
$baseUrl = '';
if ($docRoot !== '' && str_starts_with($erpRoot, $docRoot)) {
    $baseUrl = substr($erpRoot, strlen($docRoot));
}
$baseUrl = rtrim(str_replace('\\', '/', $baseUrl), '/');
define('BASE_URL', $baseUrl);

// SQLite — قاعدة بيانات محلية
define('DB_DIR', BASE_PATH . '/database');
define('DB_FILE', 'titan_production.sqlite');
define('DB_PATH', DB_DIR . '/' . DB_FILE);
define('DB_DSN', 'sqlite:' . DB_PATH);
define('DB_SCHEMA_FILE', DB_DIR . '/install.sqlite.sql');

date_default_timezone_set('Asia/Riyadh');
