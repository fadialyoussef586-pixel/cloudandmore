<?php

require_once __DIR__ . '/includes/functions.php';

$context = ($_GET['context'] ?? 'erp') === 'shop' ? 'shop' : 'erp';
ensureAppIcons();

$manifest = [
    'name' => $context === 'shop' ? pwaShortName() . ' Store' : pwaAppName(),
    'short_name' => pwaShortName(),
    'description' => defined('COMPANY_TAGLINE') ? COMPANY_TAGLINE : pwaAppName(),
    'start_url' => pwaStartUrl($context),
    'scope' => pwaScopeUrl($context),
    'display' => 'standalone',
    'orientation' => 'portrait-primary',
    'background_color' => PWA_BG_COLOR,
    'theme_color' => PWA_THEME_COLOR,
    'lang' => lang(),
    'dir' => isRtl() ? 'rtl' : 'ltr',
    'icons' => [],
];

foreach ([
    ['size' => 192, 'purpose' => 'any'],
    ['size' => 512, 'purpose' => 'any'],
    ['size' => 512, 'purpose' => 'maskable'],
] as $icon) {
    $manifest['icons'][] = [
        'src' => appIconUrl($icon['size']) . ($icon['purpose'] === 'maskable' ? '&maskable=1' : ''),
        'sizes' => $icon['size'] . 'x' . $icon['size'],
        'type' => 'image/png',
        'purpose' => $icon['purpose'],
    ];
}

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=86400');
echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
