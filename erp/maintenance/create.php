<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requirePermission(PERM_MAINTENANCE);

ensureMaintenanceTables();
$pageTitle = __('maint_new_ticket');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['customer_name'] ?? '');
    $phone = trim($_POST['customer_phone'] ?? '');
    $issue = trim($_POST['issue_description'] ?? '');

    if ($name === '' || $phone === '' || $issue === '') {
        flash('error', __('maint_form_required'));
        redirect(url('maintenance/create.php'));
    }

    $id = createMaintenanceTicket([
        'customer_name' => $name,
        'customer_phone' => $phone,
        'customer_email' => trim($_POST['customer_email'] ?? ''),
        'device_brand' => trim($_POST['device_brand'] ?? ''),
        'device_model' => trim($_POST['device_model'] ?? ''),
        'serial_number' => trim($_POST['serial_number'] ?? ''),
        'issue_description' => $issue,
        'priority' => ($_POST['priority'] ?? '') === 'urgent' ? 'urgent' : 'normal',
        'estimated_cost' => (float) ($_POST['estimated_cost'] ?? 0),
        'received_at' => $_POST['received_at'] ?? date('Y-m-d'),
        'source' => 'manual',
    ], $_SESSION['user_id'] ?? null);

    flash('success', __('success_saved'));
    redirect(url('maintenance/view.php?id=' . $id));
}

require __DIR__ . '/../includes/header.php';
?>

<div class="page-actions">
    <a href="<?= url('maintenance/index.php') ?>" class="btn btn-secondary"><?= e(__('cancel')) ?></a>
</div>

<div class="card">
    <div class="card-header"><h2><?= e(__('maint_new_ticket')) ?></h2></div>
    <div class="card-body">
        <form method="post">
            <div class="form-grid">
                <div class="form-group">
                    <label><?= e(__('customer_name_placeholder')) ?></label>
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
                <div class="form-group">
                    <label><?= e(__('maint_priority')) ?></label>
                    <select name="priority">
                        <option value="normal"><?= e(__('maint_priority_normal')) ?></option>
                        <option value="urgent"><?= e(__('maint_urgent')) ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label><?= e(__('maint_estimated_cost')) ?></label>
                    <input type="number" step="0.01" min="0" name="estimated_cost" value="<?= e($_POST['estimated_cost'] ?? '0') ?>">
                </div>
                <div class="form-group">
                    <label><?= e(__('maint_received_date')) ?></label>
                    <input type="date" name="received_at" value="<?= e($_POST['received_at'] ?? date('Y-m-d')) ?>">
                </div>
            </div>
            <div class="form-group">
                <label><?= e(__('maint_issue')) ?></label>
                <textarea name="issue_description" rows="4" required placeholder="<?= e(__('maint_issue_placeholder')) ?>"><?= e($_POST['issue_description'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary"><?= e(__('save')) ?></button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
