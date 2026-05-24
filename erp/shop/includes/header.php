<?php
if (!isset($pageTitle)) {
    $pageTitle = __('shop');
}
?><!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e($pageTitle) ?> | <?= e(COMPANY_NAME) ?> Store</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/shop.css') ?>">
    <link rel="icon" href="<?= e(companyLogoUrl()) ?>" type="image/png">
</head>
<body class="shop-body">
<header class="shop-header">
    <div class="shop-container shop-nav">
        <a href="<?= shopUrl() ?>" class="shop-logo"><?= companyLogoHtml('company-logo company-logo--shop') ?></a>
        <nav class="shop-links">
            <a href="<?= shopUrl() ?>"><?= e(__('shop')) ?></a>
            <a href="<?= shopUrl('cart.php') ?>"><?= e(__('cart')) ?> (<?= array_sum($_SESSION['cart'] ?? []) ?>)</a>
        </nav>
    </div>
</header>
<main class="shop-main shop-container">
