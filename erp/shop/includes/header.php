<?php
if (!isset($pageTitle)) {
    $pageTitle = __('shop');
}
ensureShopSchema();
shopCartInit();
$shopPhone = shopContactPhone();
$shopWhatsApp = $shopPhone !== '' ? orderWhatsAppShareUrl($shopPhone, __('shop_contact_whatsapp_msg')) : null;
?><!DOCTYPE html>
<html lang="<?= lang() ?>" dir="<?= isRtl() ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e($pageTitle) ?> | <?= e(COMPANY_NAME) ?> Store</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/shop.css') ?>">
    <?php renderPwaHeadTags('shop'); ?>
</head>
<body class="shop-body">
<div class="shop-trust-bar">
    <div class="shop-container shop-trust-inner">
        <?php if (defined('SHOP_HOURS') && SHOP_HOURS !== ''): ?>
            <span><i class="fa-regular fa-clock"></i> <?= e(SHOP_HOURS) ?></span>
        <?php endif; ?>
        <?php if ($shopPhone !== ''): ?>
            <a href="tel:<?= e(preg_replace('/\s+/', '', $shopPhone)) ?>"><i class="fa-solid fa-phone"></i> <?= e($shopPhone) ?></a>
        <?php endif; ?>
        <?php if ($shopWhatsApp): ?>
            <a href="<?= e($shopWhatsApp) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
        <?php endif; ?>
    </div>
</div>
<header class="shop-header">
    <div class="shop-container shop-nav">
        <a href="<?= shopUrl() ?>" class="shop-logo"><?= companyLogoWithTagline('company-logo company-logo--shop', false, 'shop-brand-tagline') ?></a>
        <nav class="shop-links">
            <a href="<?= shopUrl() ?>"><?= e(__('shop')) ?></a>
            <a href="<?= shopUrl('maintenance.php') ?>"><?= e(__('maintenance')) ?></a>
            <a href="<?= shopUrl('track.php') ?>"><?= e(__('track_order')) ?></a>
            <a href="<?= shopUrl('cart.php') ?>" class="shop-cart-link"><?= e(__('cart')) ?> <span class="shop-cart-count"><?= array_sum($_SESSION['cart'] ?? []) ?></span></a>
            <form method="post" action="<?= url('set-lang.php') ?>" class="shop-lang-form">
                <select name="lang" onchange="this.form.submit()" aria-label="<?= e(__('language')) ?>">
                    <option value="en" <?= lang() === 'en' ? 'selected' : '' ?>>EN</option>
                    <option value="ar" <?= lang() === 'ar' ? 'selected' : '' ?>>AR</option>
                </select>
            </form>
        </nav>
    </div>
</header>
<main class="shop-main shop-container">
