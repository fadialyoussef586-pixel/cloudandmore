<?php

if (!function_exists('appEnv')) {
    function appEnv(string $key, string $default = '', bool $allowEmpty = false): string
    {
        $values = [];

        if (array_key_exists($key, $_ENV)) {
            $values[] = $_ENV[$key];
        }
        if (array_key_exists($key, $_SERVER)) {
            $values[] = $_SERVER[$key];
        }

        $envValue = getenv($key);
        if ($envValue !== false) {
            $values[] = $envValue;
        }

        foreach ($values as $value) {
            if (!is_string($value)) {
                continue;
            }
            if ($value !== '' || $allowEmpty) {
                return $value;
            }
        }

        return $default;
    }
}

define('APP_NAME', 'Cloud and More ERP');
define('COMPANY_NAME', 'Cloud and More');
define('COMPANY_TAGLINE', 'Everything is simple');
define('APP_VERSION', '1.0.1');
define('BASE_PATH', dirname(__DIR__));

// MySQL خارجي — متغيرات البيئة فقط (Render / Aiven / محلي)
define('DB_HOST', appEnv('DB_HOST', '127.0.0.1'));
define('DB_PORT', appEnv('DB_PORT', '3306'));
define('DB_NAME', appEnv('DB_NAME', 'titan_db'));
define('DB_USER', appEnv('DB_USER', 'root'));
define('DB_PASSWORD', appEnv('DB_PASSWORD', '', true));
define('DB_CHARSET', 'utf8mb4');

// BASE_URL: from environment or auto-detected from project path
if (!defined('BASE_URL')) {
    $baseUrl = appEnv('BASE_URL', '', true);
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
define('COMPANY_LOGO_URL', appEnv('COMPANY_LOGO_URL', '', true));

// حساب المالك — الحذف وإدارة المستخدمين (يمكن تجاوزه بـ OWNER_EMAIL على Render)
if (!defined('OWNER_EMAIL')) {
    define('OWNER_EMAIL', strtolower(trim(appEnv('OWNER_EMAIL', 'fadialyoussef586@gmail.com'))));
}

// WhatsApp: رمز الدولة الافتراضي للأرقام بدون + (961 لبنان، 966 سعودية...)
if (!defined('WHATSAPP_DEFAULT_COUNTRY')) {
    define('WHATSAPP_DEFAULT_COUNTRY', preg_replace('/\D/', '', appEnv('WHATSAPP_DEFAULT_COUNTRY', '961')));
}

if (!defined('COMPANY_SUPPORT_PHONE')) {
    define('COMPANY_SUPPORT_PHONE', appEnv('COMPANY_SUPPORT_PHONE', '', true));
}
