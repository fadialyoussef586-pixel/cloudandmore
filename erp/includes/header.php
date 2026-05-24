<?php
requireAuth();
$currentUser = currentUser();
$pageTitle = $pageTitle ?? __('dashboard');
?>
<!DOCTYPE html>
<html lang="<?= lang() ?>" dir="<?= isRtl() ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | <?= e(__('app_name')) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <link rel="icon" href="<?= e(companyLogoUrl()) ?>" type="image/png">
</head>
<body>
<div class="app">
    <?php require __DIR__ . '/sidebar.php'; ?>
    <div class="main">
        <header class="topbar">
            <div class="topbar-left">
                <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">☰</button>
                <h1><?= e($pageTitle) ?></h1>
            </div>
            <div class="topbar-right">
                <span class="exchange-rate-badge">1 USD = <?= e(number_format(getUsdToSarRate(), 4)) ?> SAR</span>
                <form method="post" action="<?= url('set-lang.php') ?>" class="lang-switch">
                    <select name="lang" onchange="this.form.submit()">
                        <option value="ar" <?= lang() === 'ar' ? 'selected' : '' ?>>العربية</option>
                        <option value="en" <?= lang() === 'en' ? 'selected' : '' ?>>English</option>
                    </select>
                </form>
                <span class="user-badge"><?= e($currentUser['name'] ?? '') ?></span>
            </div>
        </header>
        <main class="content">
            <?php $flash = getFlash(); if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
            <?php endif; ?>
