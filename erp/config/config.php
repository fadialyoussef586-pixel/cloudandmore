<?php

define('APP_NAME', 'IKOS ERP');
define('COMPANY_NAME', 'IKOS');
define('COMPANY_TAGLINE', 'Devices · Accessories · Asma Cloud & More');
define('APP_VERSION', '1.0.0');
define('BASE_PATH', dirname(__DIR__));

// مسار المشروع من جذر السيرفر (يعمل مع php -S و XAMPP و Herd و Render)
$docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
$erpRoot = str_replace('\\', '/', realpath(BASE_PATH) ?: BASE_PATH);
$baseUrl = '';
if ($docRoot !== '' && str_starts_with($erpRoot, $docRoot)) {
    $baseUrl = substr($erpRoot, strlen($docRoot));
}
$baseUrl = rtrim(str_replace('\\', '/', $baseUrl), '/');
define('BASE_URL', $baseUrl);

// Render يوفّر DATABASE_URL — أو استخدم المتغيرات المنفصلة محلياً
$databaseUrl = getenv('DATABASE_URL');
if ($databaseUrl !== false && $databaseUrl !== '') {
    $databaseUrl = preg_replace('#^postgres://#', 'postgresql://', $databaseUrl);
    $parsed = parse_url($databaseUrl);
    define('DB_HOST', $parsed['host'] ?? '127.0.0.1');
    define('DB_PORT', (string) ($parsed['port'] ?? 5432));
    define('DB_NAME', ltrim($parsed['path'] ?? '', '/'));
    define('DB_USER', urldecode($parsed['user'] ?? 'postgres'));
    define('DB_PASS', urldecode($parsed['pass'] ?? ''));
    define('DB_SSLMODE', 'require');
} else {
    define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
    define('DB_PORT', getenv('DB_PORT') ?: '5432');
    define('DB_NAME', getenv('DB_NAME') ?: 'titan_db');
    define('DB_USER', getenv('DB_USER') ?: 'postgres');
    define('DB_PASS', getenv('DB_PASS') ?: '');
    define('DB_SSLMODE', getenv('DB_SSLMODE') ?: 'prefer');
}

define('DB_CHARSET', 'utf8');

date_default_timezone_set('Asia/Riyadh');
