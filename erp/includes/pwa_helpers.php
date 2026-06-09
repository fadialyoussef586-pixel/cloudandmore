<?php

const PWA_THEME_COLOR = '#00a8c9';
const PWA_BG_COLOR = '#ffffff';

function pwaShortName(): string
{
    return defined('COMPANY_NAME') ? COMPANY_NAME : 'cloud&more';
}

function pwaAppName(): string
{
    return defined('APP_NAME') ? APP_NAME : pwaShortName();
}

function appIconSizes(): array
{
    return [180, 192, 512];
}

function appIconsDir(): string
{
    return BASE_PATH . '/assets/img/icons';
}

function appIconCachedPath(int $size): string
{
    return appIconsDir() . '/icon-' . $size . '.png';
}

function appIconUrl(int $size = 180): string
{
    $size = max(48, min(512, $size));
    $cached = appIconCachedPath($size);
    if (is_file($cached)) {
        return asset('img/icons/icon-' . $size . '.png');
    }

    return url('app-icon.php?size=' . $size);
}

function pwaManifestUrl(string $context = 'erp'): string
{
    return url('manifest.php?context=' . ($context === 'shop' ? 'shop' : 'erp'));
}

function pwaStartUrl(string $context = 'erp'): string
{
    return $context === 'shop' ? shopUrl() : url('login.php');
}

function pwaScopeUrl(string $context = 'erp'): string
{
    $base = BASE_URL === '' ? '/' : BASE_URL . '/';

    return $context === 'shop' ? rtrim($base, '/') . '/shop/' : rtrim($base, '/') . '/';
}

function localRasterLogoPath(): ?string
{
    $dir = BASE_PATH . '/assets/img';
    foreach (['app-icon.png', 'logo.png', 'logo.jpg', 'logo.jpeg', 'logo.webp'] as $name) {
        if (is_file($dir . '/' . $name)) {
            return $dir . '/' . $name;
        }
    }

    return null;
}

function loadRasterImage(string $path)
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    return match ($ext) {
        'jpg', 'jpeg' => @imagecreatefromjpeg($path),
        'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        default => @imagecreatefrompng($path),
    };
}

function generateAppIconPng(int $size, string $destPath, bool $maskable = false): bool
{
    if (!extension_loaded('gd')) {
        return false;
    }

    $size = max(48, min(512, $size));
    $img = imagecreatetruecolor($size, $size);
    if ($img === false) {
        return false;
    }

    imagesavealpha($img, true);
    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $transparent);

    $bg = imagecolorallocate($img, 255, 255, 255);
    imagefilledrectangle($img, 0, 0, $size, $size, $bg);

    $logoPath = localRasterLogoPath();
    if ($logoPath !== null && is_file($logoPath)) {
        $source = loadRasterImage($logoPath);
        if ($source !== false) {
            $srcW = imagesx($source);
            $srcH = imagesy($source);
            $inset = $maskable ? (int) round($size * 0.18) : (int) round($size * 0.12);
            $target = $size - ($inset * 2);
            $scale = min($target / max(1, $srcW), $target / max(1, $srcH));
            $drawW = (int) round($srcW * $scale);
            $drawH = (int) round($srcH * $scale);
            $dstX = (int) round(($size - $drawW) / 2);
            $dstY = (int) round(($size - $drawH) / 2);
            imagecopyresampled($img, $source, $dstX, $dstY, 0, 0, $drawW, $drawH, $srcW, $srcH);
            imagedestroy($source);
            imagepng($img, $destPath, 9);
            imagedestroy($img);

            return true;
        }
    }

    $inset = $maskable ? (int) round($size * 0.16) : (int) round($size * 0.14);
    $box = $size - ($inset * 2);
    $x1 = $inset;
    $y1 = $inset;
    $x2 = $inset + $box;
    $y2 = $inset + $box;

    for ($y = $y1; $y < $y2; $y++) {
        $ratio = ($y - $y1) / max(1, $box - 1);
        $r = (int) round(0 + $ratio * 0);
        $g = (int) round(168 - $ratio * 32);
        $b = (int) round(201 - $ratio * 0);
        $lineColor = imagecolorallocate($img, $r, $g, $b);
        imageline($img, $x1, $y, $x2, $y, $lineColor);
    }

    $white = imagecolorallocate($img, 255, 255, 255);
    $cx = (int) round($size / 2);
    $cy = (int) round($size / 2);
    $outer = (int) round($box * 0.28);
    $inner = (int) round($box * 0.1);
    imagearc($img, $cx, $cy, $outer * 2, $outer * 2, 200, 340, $white);
    imagesetthickness($img, max(2, (int) round($box * 0.05)));
    imagearc($img, $cx, $cy, $outer * 2, $outer * 2, 200, 340, $white);
    imagefilledellipse($img, $cx, $cy, $inner * 2, $inner * 2, $white);

    imagepng($img, $destPath, 9);
    imagedestroy($img);

    return true;
}

function ensureAppIcons(): void
{
    if (!extension_loaded('gd')) {
        return;
    }

    $dir = appIconsDir();
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    foreach (appIconSizes() as $size) {
        $path = appIconCachedPath($size);
        if (!is_file($path)) {
            generateAppIconPng($size, $path, $size >= 512);
        }
    }
}

function renderPwaHeadTags(string $context = 'erp'): void
{
    ensureAppIcons();

    $shortName = pwaShortName();
    $manifestUrl = pwaManifestUrl($context);
    $themeColor = PWA_THEME_COLOR;
    $icon180 = appIconUrl(180);
    $icon192 = appIconUrl(192);
    $icon512 = appIconUrl(512);
    $svgIcon = asset('img/app-icon.svg');
    ?>
    <meta name="application-name" content="<?= e($shortName) ?>">
    <meta name="apple-mobile-web-app-title" content="<?= e($shortName) ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="<?= e($themeColor) ?>">
    <meta name="msapplication-TileColor" content="<?= e($themeColor) ?>">
    <link rel="manifest" href="<?= e($manifestUrl) ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= e($icon180) ?>">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= e($icon192) ?>">
    <link rel="icon" type="image/png" sizes="512x512" href="<?= e($icon512) ?>">
    <link rel="icon" type="image/svg+xml" href="<?= e($svgIcon) ?>">
    <?php
}
