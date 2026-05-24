<?php

define('APP_NAME', 'IKOS ERP');
define('COMPANY_NAME', 'IKOS');
define('COMPANY_TAGLINE', 'Devices · Accessories · Asma Cloud & More');
define('APP_VERSION', '1.0.0');
define('BASE_PATH', dirname(__DIR__));

// مسار المشروع من جذر السيرفر (php -S / XAMPP / Render)
$docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
$erpRoot = str_replace('\\', '/', realpath(BASE_PATH) ?: BASE_PATH);
$baseUrl = '';
if ($docRoot !== '' && str_starts_with($erpRoot, $docRoot)) {
    $baseUrl = substr($erpRoot, strlen($docRoot));
}
$baseUrl = rtrim(str_replace('\\', '/', $baseUrl), '/');
define('BASE_URL', $baseUrl);

/**
 * قراءة متغير بيئة (Render يضع DATABASE_URL هنا)
 */
function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }
    if (!empty($_ENV[$key])) {
        return (string) $_ENV[$key];
    }
    if (!empty($_SERVER[$key])) {
        return (string) $_SERVER[$key];
    }

    return $default;
}

/**
 * تحليل DATABASE_URL من Render: postgresql://user:pass@host:5432/dbname
 */
function parseDatabaseUrl(string $url): array
{
    $url = preg_replace('#^postgres://#', 'postgresql://', trim($url));
    $parts = parse_url($url);

    if ($parts === false || empty($parts['host'])) {
        throw new InvalidArgumentException('DATABASE_URL غير صالح');
    }

    $query = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }

    return [
        'host' => $parts['host'],
        'port' => (string) ($parts['port'] ?? 5432),
        'name' => ltrim($parts['path'] ?? '', '/'),
        'user' => isset($parts['user']) ? urldecode($parts['user']) : 'postgres',
        'pass' => isset($parts['pass']) ? urldecode($parts['pass']) : '',
        'sslmode' => $query['sslmode'] ?? 'require',
    ];
}

$databaseUrl = env('DATABASE_URL');

if ($databaseUrl !== null) {
    $db = parseDatabaseUrl($databaseUrl);
    define('DATABASE_URL', $databaseUrl);
    define('DB_HOST', $db['host']);
    define('DB_PORT', $db['port']);
    define('DB_NAME', $db['name']);
    define('DB_USER', $db['user']);
    define('DB_PASS', $db['pass']);
    define('DB_SSLMODE', $db['sslmode']);
} else {
    define('DATABASE_URL', '');
    define('DB_HOST', env('DB_HOST', '127.0.0.1') ?? '127.0.0.1');
    define('DB_PORT', env('DB_PORT', '5432') ?? '5432');
    define('DB_NAME', env('DB_NAME', 'titan_db') ?? 'titan_db');
    define('DB_USER', env('DB_USER', 'postgres') ?? 'postgres');
    define('DB_PASS', env('DB_PASS', '') ?? '');
    define('DB_SSLMODE', env('DB_SSLMODE', 'prefer') ?? 'prefer');
}

define('DB_CHARSET', 'utf8');

date_default_timezone_set('Asia/Riyadh');
