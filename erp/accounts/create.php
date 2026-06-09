<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requirePermission(PERM_CURRENT_ACCOUNTS);

ensureCurrentAccountTables();
$pageTitle = __('ca_new_account');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($name === '') {
        flash('error', __('ca_name_required'));
        redirect(url('accounts/create.php'));
    }

    db()->prepare('INSERT INTO current_accounts (name, phone, notes) VALUES (?, ?, ?)')->execute([
        $name,
        $phone !== '' ? $phone : null,
        $notes !== '' ? $notes : null,
    ]);

    flash('success', __('success_saved'));
    redirect(url('accounts/index.php'));
}

require __DIR__ . '/../includes/header.php';
?>

<div class="page-actions">
    <a href="<?= url('accounts/index.php') ?>" class="btn btn-secondary"><?= e(__('cancel')) ?></a>
</div>

<div class="card">
    <div class="card-header"><h2><?= e(__('ca_new_account')) ?></h2></div>
    <div class="card-body">
        <form method="post">
            <div class="form-grid">
                <div class="form-group">
                    <label><?= e(__('name')) ?> *</label>
                    <input name="name" required value="<?= e($_POST['name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label><?= e(__('phone')) ?></label>
                    <input name="phone" value="<?= e($_POST['phone'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label><?= e(__('notes')) ?></label>
                <textarea name="notes" rows="3"><?= e($_POST['notes'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary"><?= e(__('save')) ?></button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
