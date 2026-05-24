<?php

define('APP_NAME', 'Cloud and More ERP');
define('COMPANY_NAME', 'Cloud and More');
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

// BASE_URL: from environment or auto-detected from project path
if (!defined('BASE_URL')) {
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
}

date_default_timezone_set('Asia/Riyadh');

if (!defined('CURRENCY_CODE')) {
    define('CURRENCY_CODE', 'USD');
}
if (!defined('CURRENCY_SYMBOL')) {
    define('CURRENCY_SYMBOL', '$');
}
if (!defined('APP_LANG_DEFAULT')) {
    define('APP_LANG_DEFAULT', 'en');
}

// شعار الشركة: ملف محلي في assets/img/ أو رابط COMPANY_LOGO_URL
define('COMPANY_LOGO_URL', getenv('COMPANY_LOGO_URL') ?: '');

// حساب المالك — الحذف وإدارة المستخدمين (يمكن تجاوزه بـ OWNER_EMAIL على Render)
if (!defined('OWNER_EMAIL')) {
    define('OWNER_EMAIL', strtolower(trim(getenv('OWNER_EMAIL') ?: 'fadialyoussef586@gmail.com')));
}
