<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/shop_helpers.php';
require_once __DIR__ . '/ui_icons.php';
require_once __DIR__ . '/currency.php';
require_once __DIR__ . '/data_reset.php';
require_once __DIR__ . '/customer_helpers.php';
require_once __DIR__ . '/invoice_helpers.php';
require_once __DIR__ . '/returns_helpers.php';
require_once __DIR__ . '/purchase_helpers.php';
require_once __DIR__ . '/maintenance_helpers.php';
require_once __DIR__ . '/current_accounts_helpers.php';
require_once __DIR__ . '/pwa_helpers.php';
require_once __DIR__ . '/backup_helpers.php';
require_once __DIR__ . '/quick_actions.php';
require_once __DIR__ . '/permissions.php';

session_start();
ensureUserPermissionsSchema();

$defaultLang = defined('APP_LANG_DEFAULT') ? APP_LANG_DEFAULT : 'en';
$lang = $_SESSION['lang'] ?? ($_COOKIE['lang'] ?? $defaultLang);
if (!in_array($lang, ['ar', 'en'], true)) {
    $lang = $defaultLang;
}
$_SESSION['lang'] = $lang;

$translations = require __DIR__ . '/../lang/' . $lang . '.php';

function __($key): string
{
    global $translations;
    return $translations[$key] ?? $key;
}

function lang(): string
{
    return $_SESSION['lang'] ?? (defined('APP_LANG_DEFAULT') ? APP_LANG_DEFAULT : 'en');
}

function isRtl(): bool
{
    return lang() === 'ar';
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    if (BASE_URL === '') {
        return $path === '' ? '/' : '/' . $path;
    }
    return $path === '' ? BASE_URL : BASE_URL . '/' . $path;
}

function asset(string $path): string
{
    $path = ltrim($path, '/');
    if (BASE_URL === '') {
        return '/assets/' . $path;
    }
    return BASE_URL . '/assets/' . $path;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function formatDate(?string $date): string
{
    if (!$date) {
        return '-';
    }
    return date('Y-m-d', strtotime($date));
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function generateNumber(string $prefix): string
{
    return $prefix . '-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
}

function productName(array $product): string
{
    if (isRtl()) {
        $name = trim($product['name_ar'] ?? '');
        if ($name !== '') {
            return $name;
        }
    } else {
        $name = trim($product['name_en'] ?? '');
        if ($name !== '') {
            return $name;
        }
    }

    return trim($product['name_en'] ?? '') ?: trim($product['name_ar'] ?? '');
}

function employeeName(array $employee): string
{
    return isRtl() ? $employee['name_ar'] : $employee['name_en'];
}

function statusBadge(string $status): string
{
    $map = [
        'draft' => 'badge-gray',
        'sent' => 'badge-blue',
        'paid' => 'badge-green',
        'cancelled' => 'badge-red',
        'pending' => 'badge-yellow',
        'in_transit' => 'badge-blue',
        'delivered' => 'badge-green',
        'new' => 'badge-yellow',
        'confirmed' => 'badge-blue',
        'ready_for_delivery' => 'badge-blue',
        'out_for_delivery' => 'badge-yellow',
        'active' => 'badge-green',
        'inactive' => 'badge-gray',
        'terminated' => 'badge-red',
    ];
    $class = $map[$status] ?? 'badge-gray';
    return '<span class="badge ' . $class . '">' . e(__($status)) . '</span>';
}

function months(): array
{
    if (isRtl()) {
        return [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
        ];
    }
    return [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    ];
}

function productCategories(?string $include = null): array
{
    static $cache = null;
    if ($cache === null) {
        ensureDefaultCategoriesRemoved();
        $cache = [];
        try {
            $rows = db()->query(
                "SELECT DISTINCT TRIM(category) AS category
                 FROM products
                 WHERE category IS NOT NULL AND TRIM(category) != ''
                 ORDER BY category ASC"
            )->fetchAll(PDO::FETCH_COLUMN);

            foreach ($rows as $cat) {
                $cache[$cat] = $cat;
            }
        } catch (Throwable) {
            $cache = [];
        }
    }

    if ($include === null || trim($include) === '' || isset($cache[$include])) {
        return $cache;
    }

    $merged = $cache;
    $merged[trim($include)] = trim($include);

    return $merged;
}

function ensureDefaultCategoriesRemoved(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $flag = BASE_PATH . '/storage/categories_reset_v1.flag';
    if (is_file($flag)) {
        return;
    }

    $dir = dirname($flag);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    try {
        db()->exec("UPDATE products SET category = NULL WHERE category IS NOT NULL AND TRIM(category) <> ''");
        file_put_contents($flag, '1');
    } catch (Throwable) {
        // DB may not be ready during install
    }
}

function resolveProductCategoryFromPost(array $post): string
{
    $custom = trim($post['custom_category'] ?? '');
    if ($custom !== '') {
        return $custom;
    }

    return trim($post['category'] ?? '');
}

function companyLogoFile(): ?string
{
    static $file = null;
    if ($file !== null) {
        return $file === '' ? null : $file;
    }

    $dir = BASE_PATH . '/assets/img';
    foreach (['logo.png', 'logo.jpg', 'logo.jpeg', 'logo.webp', 'logo.svg'] as $name) {
        if (is_file($dir . '/' . $name)) {
            $file = $name;
            return $file;
        }
    }

    $file = '';
    return null;
}

function companyLogoUrl(): string
{
    if (COMPANY_LOGO_URL !== '') {
        return COMPANY_LOGO_URL;
    }

    $local = companyLogoFile();
    if ($local !== null) {
        return asset('img/' . $local);
    }

    return asset('img/logo.svg');
}

function companyTagline(): string
{
    return defined('COMPANY_TAGLINE') && COMPANY_TAGLINE !== ''
        ? COMPANY_TAGLINE
        : __('company_tagline');
}

function companyLogoWithTagline(string $logoClass = 'company-logo', bool $linkToHome = false, string $taglineClass = 'brand-tagline'): string
{
    $logo = companyLogoHtml($logoClass, $linkToHome);
    $tagline = '<small class="' . e($taglineClass) . '">' . e(companyTagline()) . '</small>';

    if ($linkToHome) {
        return '<div class="brand-lockup">' . $logo . $tagline . '</div>';
    }

    return '<div class="brand-lockup">' . $logo . $tagline . '</div>';
}

function companyLogoHtml(string $class = 'company-logo', bool $linkToHome = false): string
{
    $img = '<img src="' . e(companyLogoUrl()) . '" alt="' . e(COMPANY_NAME) . '" class="' . e($class) . '">';

    if (!$linkToHome) {
        return $img;
    }

    $href = e(url('index.php'));
    return '<a href="' . $href . '" class="company-logo-link">' . $img . '</a>';
}
