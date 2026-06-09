<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requirePermission(PERM_CUSTOMERS);

ensureCustomerSchema();
$pageTitle = __('cust_new_customer');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($name === '') {
        flash('error', __('cust_name_required'));
        redirect(url('customers/create.php'));
    }

    if ($phone !== '' && findCustomerByPhone($phone)) {
        flash('error', __('cust_phone_exists'));
        redirect(url('customers/create.php'));
    }

    db()->prepare(
        'INSERT INTO customers (name, phone, email, address, notes) VALUES (?, ?, ?, ?, ?)'
    )->execute([
        $name,
        $phone !== '' ? normalizeCustomerPhone($phone) : null,
        $email !== '' ? $email : null,
        $address !== '' ? $address : null,
        $notes !== '' ? $notes : null,
    ]);

    flash('success', __('success_saved'));
    redirect(url('customers/view.php?id=' . dbLastInsertId(db())));
}

require __DIR__ . '/../includes/header.php';
?>

<div class="page-actions">
    <a href="<?= url('customers/index.php') ?>" class="btn btn-secondary"><?= e(__('customers')) ?></a>
</div>

<div class="card">
    <div class="card-header"><h2><?= e(__('cust_new_customer')) ?></h2></div>
    <div class="card-body">
        <form method="post" class="form-grid">
            <div class="form-group">
                <label><?= e(__('name')) ?> *</label>
                <input name="name" required value="<?= e($_POST['name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label><?= e(__('phone')) ?></label>
                <input name="phone" value="<?= e($_POST['phone'] ?? '') ?>" placeholder="05xxxxxxxx">
            </div>
            <div class="form-group">
                <label><?= e(__('email')) ?></label>
                <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label><?= e(__('address')) ?></label>
                <input name="address" value="<?= e($_POST['address'] ?? '') ?>">
            </div>
            <div class="form-group" style="grid-column:1/-1">
                <label><?= e(__('notes')) ?></label>
                <textarea name="notes" rows="3"><?= e($_POST['notes'] ?? '') ?></textarea>
            </div>
            <div>
                <button type="submit" class="btn btn-primary"><?= e(__('save')) ?></button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
