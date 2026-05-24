<?php

define('APP_NAME', 'IKOS ERP');
define('COMPANY_NAME', 'IKOS');
define('COMPANY_TAGLINE', 'Devices · Accessories · Asma Cloud & More');
define('APP_VERSION', '1.0.0');
define('BASE_PATH', dirname(__DIR__));

// مسار المشروع من جذر السيرفر (يعمل مع php -S و XAMPP و Herd و Docker)
$docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
$erpRoot = str_replace('\\', '/', realpath(BASE_PATH) ?: BASE_PATH);
$baseUrl = '';
if ($docRoot !== '' && str_starts_with($erpRoot, $docRoot)) {
    $baseUrl = substr($erpRoot, strlen($docRoot));
}
$baseUrl = rtrim(str_replace('\\', '/', $baseUrl), '/');
define('BASE_URL', $baseUrl);

$inDocker = file_exists('/.dockerenv');

define('DB_HOST', getenv('DB_HOST') ?: ($inDocker ? 'mysql' : '127.0.0.1'));
define('DB_NAME', getenv('DB_NAME') ?: 'titan_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: ($inDocker ? 'titan_root_pass' : ''));
define('DB_CHARSET', 'utf8mb4');

date_default_timezone_set('Asia/Riyadh');
