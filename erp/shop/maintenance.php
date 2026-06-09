<?php

require_once __DIR__ . '/../includes/functions.php';

ensureMaintenanceTables();
$pageTitle = __('maint_request');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['customer_name'] ?? '');
    $phone = trim($_POST['customer_phone'] ?? '');
    $issue = trim($_POST['issue_description'] ?? '');

    if ($name === '' || $phone === '' || $issue === '') {
        flash('error', __('maint_form_required'));
        redirect(shopUrl('maintenance.php'));
    }

    $id = createMaintenanceTicket([
        'customer_name' => $name,
        'customer_phone' => $phone,
        'customer_email' => trim($_POST['customer_email'] ?? ''),
        'device_brand' => trim($_POST['device_brand'] ?? ''),
        'device_model' => trim($_POST['device_model'] ?? ''),
        'serial_number' => trim($_POST['serial_number'] ?? ''),
        'issue_description' => $issue,
        'source' => 'shop',
    ]);

    $ticket = getMaintenanceTicket($id);
    $_SESSION['last_maintenance_ticket'] = $ticket['ticket_number'] ?? '';
    redirect(shopUrl('maintenance-success.php'));
}

require __DIR__ . '/includes/header.php';
$flash = getFlash();
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>

<section class="shop-hero shop-hero--compact">
    <h1><?= e(__('maint_request')) ?></h1>
    <p><?= e(__('maint_request_hint')) ?></p>
</section>

<div class="shop-form-card card">
    <form method="post">
        <div class="form-grid">
            <div class="form-group">
                <label><?= e(__('name')) ?></label>
                <input name="customer_name" required value="<?= e($_POST['customer_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label><?= e(__('phone')) ?></label>
                <input name="customer_phone" required value="<?= e($_POST['customer_phone'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label><?= e(__('email')) ?></label>
                <input type="email" name="customer_email" value="<?= e($_POST['customer_email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label><?= e(__('maint_device_brand')) ?></label>
                <input name="device_brand" placeholder="IQOS / iPhone / ..." value="<?= e($_POST['device_brand'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label><?= e(__('maint_device_model')) ?></label>
                <input name="device_model" value="<?= e($_POST['device_model'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label><?= e(__('serial_number')) ?></label>
                <input name="serial_number" value="<?= e($_POST['serial_number'] ?? '') ?>">
            </div>
        </div>
        <div class="form-group">
            <label><?= e(__('maint_issue')) ?></label>
            <textarea name="issue_description" rows="5" required placeholder="<?= e(__('maint_issue_placeholder')) ?>"><?= e($_POST['issue_description'] ?? '') ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary"><?= e(__('maint_submit_request')) ?></button>
    </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
