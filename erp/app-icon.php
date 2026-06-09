<?php

require_once __DIR__ . '/includes/functions.php';

$size = (int) ($_GET['size'] ?? 180);
$size = max(48, min(512, $size));
$maskable = isset($_GET['maskable']);

$cached = appIconCachedPath($size);
if (!is_file($cached)) {
    ensureAppIcons();
    if (!is_file($cached)) {
        $dir = appIconsDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        generateAppIconPng($size, $cached, $maskable || $size >= 512);
    }
}

if (is_file($cached)) {
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=604800, immutable');
    readfile($cached);
    exit;
}

$svgPath = BASE_PATH . '/assets/img/app-icon.svg';
if (is_file($svgPath)) {
    header('Content-Type: image/svg+xml');
    header('Cache-Control: public, max-age=604800');
    readfile($svgPath);
    exit;
}

http_response_code(404);
exit;
