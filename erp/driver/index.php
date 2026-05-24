<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requireRole(['driver', 'admin', 'manager']);

if (isset($_GET['accept'])) {
    $id = (int)$_GET['accept'];
    db()->prepare("UPDATE orders SET status='out_for_delivery', delivery_user_id=? WHERE id=? AND status='ready_for_delivery'")
        ->execute([$_SESSION['user_id'], $id]);
    db()->prepare("UPDATE deliveries SET status='in_transit', driver_name=? WHERE order_id=?")
        ->execute([$_SESSION['user_name'] ?? 'Driver', $id]);
    flash('success', __('success_saved'));
    redirect(url('driver/index.php'));
}
if (isset($_GET['complete'])) {
    $id = (int)$_GET['complete'];
    db()->prepare("UPDATE orders SET status='delivered', delivered_at=NOW() WHERE id=? AND status='out_for_delivery'")
        ->execute([$id]);
    db()->prepare("UPDATE deliveries SET status='delivered', delivered_at=NOW() WHERE order_id=?")->execute([$id]);
    flash('success', __('success_saved'));
    redirect(url('driver/index.php'));
}

$ready = db()->query("SELECT * FROM orders WHERE status='ready_for_delivery' ORDER BY created_at ASC")->fetchAll();
$active = db()->prepare("SELECT * FROM orders WHERE status='out_for_delivery' AND (delivery_user_id=? OR delivery_user_id IS NULL) ORDER BY created_at ASC");
$active->execute([$_SESSION['user_id']]);
$active = $active->fetchAll();
$pageTitle = __('driver_portal');
?>
<!DOCTYPE html>
<html lang="<?= lang() ?>" dir="<?= isRtl() ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> | <?= e(COMPANY_NAME) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Tajawal:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>"><link rel="stylesheet" href="<?= asset('css/shop.css') ?>">
</head>
<body class="driver-layout">
<header class="driver-header">
  <div class="driver-header-brand">
    <?= companyLogoHtml('company-logo company-logo--driver') ?>
    <div>
      <strong><?= e(__('driver_portal')) ?></strong><br>
      <small class="text-muted"><?= e($_SESSION['user_name'] ?? '') ?></small>
    </div>
  </div>
  <div style="display:flex;gap:0.5rem">
    <a href="<?= url('logout.php') ?>" class="btn btn-secondary btn-sm"><?= e(__('logout')) ?></a>
  </div>
</header>
<?php $flash = getFlash(); if ($flash): ?><div class="alert alert-<?= e($flash['type']) ?>" style="margin:1rem"><?= e($flash['message']) ?></div><?php endif; ?>
<section class="driver-cards">
<h2><?= e(__('ready_for_delivery')) ?></h2>
<?php if (empty($ready)): ?><p class="text-muted"><?= e(__('no_data')) ?></p><?php endif; ?>
<?php foreach ($ready as $o): ?>
<div class="driver-order-card">
  <h3><?= e($o['order_number']) ?></h3>
  <p><strong><?= e(__('customer')) ?>:</strong> <?= e($o['customer_name']) ?> — <?= e($o['customer_phone']) ?></p>
  <p><strong><?= e(__('address')) ?>:</strong> <?= e($o['delivery_address']) ?></p>
  <p><strong><?= e(__('total')) ?>:</strong> <?= formatMoney((float)$o['total']) ?></p>
  <div class="driver-actions">
    <a href="<?= url('driver/index.php?accept='.$o['id']) ?>" class="btn btn-primary btn-sm"><?= e(__('accept_delivery')) ?></a>
    <a href="<?= url('orders/view.php?id='.$o['id']) ?>" class="btn btn-secondary btn-sm"><?= e(__('view')) ?></a>
  </div>
</div>
<?php endforeach; ?>

<h2 style="margin-top:2rem"><?= e(__('out_for_delivery')) ?></h2>
<?php if (empty($active)): ?><p class="text-muted"><?= e(__('no_data')) ?></p><?php endif; ?>
<?php foreach ($active as $o): ?>
<div class="driver-order-card">
  <h3><?= e($o['order_number']) ?> <?= orderStatusBadge($o['status']) ?></h3>
  <p><strong><?= e(__('customer')) ?>:</strong> <?= e($o['customer_name']) ?> — <?= e($o['customer_phone']) ?></p>
  <p><strong><?= e(__('address')) ?>:</strong> <?= e($o['delivery_address']) ?></p>
  <div class="driver-actions">
    <a href="<?= url('driver/index.php?complete='.$o['id']) ?>" class="btn btn-success btn-sm"><?= e(__('complete_delivery')) ?></a>
  </div>
</div>
<?php endforeach; ?>
</section>
</body></html>
