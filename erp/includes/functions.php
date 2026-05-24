<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/shop_helpers.php';

session_start();

$lang = $_SESSION['lang'] ?? ($_COOKIE['lang'] ?? 'ar');
if (!in_array($lang, ['ar', 'en'], true)) {
    $lang = 'ar';
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
    return $_SESSION['lang'] ?? 'ar';
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

function formatMoney(float $amount): string
{
    return number_format($amount, 2) . ' SAR';
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
    return isRtl() ? $product['name_ar'] : $product['name_en'];
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

function productCategories(): array
{
    return [
        'IKOS Devices' => __('cat_devices'),
        'Accessories' => __('cat_accessories'),
        'Asma Cloud & More' => __('cat_asma_cloud'),
        'Consumables' => __('cat_consumables'),
    ];
}
