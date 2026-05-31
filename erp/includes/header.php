<?php
requireAuth();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
$currentUser = currentUser();
$pageTitle = $pageTitle ?? __('dashboard');
$assetVersion = APP_VERSION . '-' . (@filemtime(BASE_PATH . '/assets/css/app.css') ?: time());
$scriptVersion = APP_VERSION . '-' . (@filemtime(BASE_PATH . '/assets/js/app.js') ?: time());
?>
<!DOCTYPE html>
<html lang="<?= lang() ?>" dir="<?= isRtl() ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <title><?= e($pageTitle) ?> | <?= e(__('app_name')) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>?v=<?= e((string) $assetVersion) ?>">
    <link rel="icon" href="<?= e(companyLogoUrl()) ?>" type="image/png">
</head>
<body data-script-version="<?= e((string) $scriptVersion) ?>">
<div class="app">
    <?php require __DIR__ . '/sidebar.php'; ?>
    <div class="main">
        <header class="topbar">
            <div class="topbar-left">
                <button class="sidebar-toggle" id="sidebarToggle" aria-label="<?= e(__('toggle_navigation')) ?>">
                    <?= faIcon('fa-solid fa-bars-staggered') ?>
                </button>
                <div class="topbar-title-wrap">
                    <span class="topbar-kicker"><?= e(__('navigation')) ?></span>
                    <h1><?= e($pageTitle) ?></h1>
                </div>
            </div>
            <div class="topbar-right">
                <?php require __DIR__ . '/quick_actions_bar.php'; ?>
                <form method="post" action="<?= url('set-lang.php') ?>" class="lang-switch">
                    <select name="lang" onchange="this.form.submit()" aria-label="<?= e(__('language')) ?>">
                        <option value="en" <?= lang() === 'en' ? 'selected' : '' ?>>English</option>
                        <option value="ar" <?= lang() === 'ar' ? 'selected' : '' ?>>العربية</option>
                    </select>
                </form>
                <span class="user-badge"><?= e($currentUser['name'] ?? '') ?></span>
                <a href="<?= url('logout.php') ?>" class="btn btn-logout btn-sm"><?= e(__('logout')) ?></a>
            </div>
        </header>
        <main class="content">
            <?php $flash = getFlash(); if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
            <?php endif; ?>
