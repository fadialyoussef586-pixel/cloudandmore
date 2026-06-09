<?php
if (!isset($pageTitle)) {
    $pageTitle = __('shop');
}
?><!DOCTYPE html>
<html lang="<?= lang() ?>" dir="<?= isRtl() ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e($pageTitle) ?> | <?= e(COMPANY_NAME) ?> Store</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/shop.css') ?>">
    <?php renderPwaHeadTags('shop'); ?>
</head>
<body class="shop-body">
<header class="shop-header">
    <div class="shop-container shop-nav">
        <a href="<?= shopUrl() ?>" class="shop-logo"><?= companyLogoWithTagline('company-logo company-logo--shop', false, 'shop-brand-tagline') ?></a>
        <nav class="shop-links">
            <a href="<?= shopUrl() ?>"><?= e(__('shop')) ?></a>
            <a href="<?= shopUrl('maintenance.php') ?>"><?= e(__('maintenance')) ?></a>
            <a href="<?= shopUrl('cart.php') ?>" class="shop-cart-link"><?= e(__('cart')) ?> <span class="shop-cart-count"><?= array_sum($_SESSION['cart'] ?? []) ?></span></a>
            <form method="post" action="<?= url('set-lang.php') ?>" style="display:inline">
                <select name="lang" onchange="this.form.submit()" aria-label="<?= e(__('language')) ?>">
                    <option value="en" <?= lang() === 'en' ? 'selected' : '' ?>>EN</option>
                    <option value="ar" <?= lang() === 'ar' ? 'selected' : '' ?>>AR</option>
                </select>
            </form>
        </nav>
    </div>
</header>
<main class="shop-main shop-container">
