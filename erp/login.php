<?php

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (isset($_SESSION['user_id'])) {
    redirect(homeUrlForRole($_SESSION['user_role'] ?? ''));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (login($email, $password)) {
        redirect(homeUrlForRole($_SESSION['user_role'] ?? ''));
    }
    $error = __('invalid_credentials');
}
?>
<!DOCTYPE html>
<html lang="<?= lang() ?>" dir="<?= isRtl() ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e(__('login')) ?> | <?= e(__('app_name')) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <?php renderPwaHeadTags('erp'); ?>
</head>
<body>
<div class="login-page">
    <div class="login-box">
        <div class="login-logo-wrap">
            <?= companyLogoWithTagline('company-logo company-logo--login', false, 'brand-tagline') ?>
        </div>
        <h1 class="login-title"><?= e(__('login')) ?></h1>

        <?php if ($error): ?>
            <div class="login-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label><?= e(__('email')) ?></label>
                <input type="email" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label><?= e(__('password')) ?></label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:0.5rem;padding:0.75rem">
                <?= e(__('login')) ?>
            </button>
        </form>

        <p class="text-muted" style="text-align:center;margin-top:0.75rem;font-size:0.8rem;line-height:1.6">
            <a href="<?= url('setup.php') ?>"><?= e(__('login_setup_link')) ?></a>
            · <a href="<?= url('fix-zero.php') ?>"><?= e(__('fix_zero_link')) ?></a>
            · <a href="<?= shopUrl() ?>"><?= e(__('view_shop')) ?></a>
        </p>
        <form method="post" action="<?= url('set-lang.php') ?>" class="lang-switch" style="margin-top:1rem;text-align:center">
            <select name="lang" onchange="this.form.submit()" aria-label="<?= e(__('language')) ?>">
                <option value="en" <?= lang() === 'en' ? 'selected' : '' ?>>English</option>
                <option value="ar" <?= lang() === 'ar' ? 'selected' : '' ?>>العربية</option>
            </select>
        </form>
    </div>
</div>
</body>
</html>
