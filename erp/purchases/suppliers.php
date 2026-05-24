<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requirePermission(PERM_PURCHASES);

ensurePurchaseSchema();

$pageTitle = __('suppliers');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        flash('error', __('supplier_name_required'));
    } else {
        db()->prepare('INSERT INTO suppliers (name, phone, notes) VALUES (?,?,?)')->execute([
            $name,
            trim($_POST['phone'] ?? '') ?: null,
            trim($_POST['notes'] ?? '') ?: null,
        ]);
        flash('success', __('success_saved'));
    }
    redirect(url('purchases/suppliers.php'));
}

if (isset($_GET['delete'])) {
    requireDelete();
    $id = (int) $_GET['delete'];
    $debt = supplierDebtBalance($id);
    if ($debt > 0) {
        flash('error', __('supplier_has_debt'));
    } else {
        $count = db()->prepare('SELECT COUNT(*) FROM purchases WHERE supplier_id = ?');
        $count->execute([$id]);
        if ((int) $count->fetchColumn() > 0) {
            flash('error', __('supplier_has_purchases'));
        } else {
            db()->prepare('DELETE FROM suppliers WHERE id = ?')->execute([$id]);
            flash('success', __('success_deleted'));
        }
    }
    redirect(url('purchases/suppliers.php'));
}

$suppliers = db()->query('SELECT * FROM suppliers ORDER BY name')->fetchAll();
$showAdd = isset($_GET['action']) && $_GET['action'] === 'add';

require __DIR__ . '/../includes/header.php';
?>
<div class="page-actions">
    <a href="<?= url('purchases/index.php') ?>" class="btn btn-secondary"><?= e(__('purchases')) ?></a>
    <a href="<?= url('purchases/suppliers.php?action=add') ?>" class="btn btn-primary"><?= e(__('add_supplier')) ?></a>
</div>

<?php if ($showAdd): ?>
<div class="card">
    <div class="card-header"><h2><?= e(__('add_supplier')) ?></h2></div>
    <div class="card-body">
        <form method="post" class="sale-form">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label><?= e(__('supplier_name')) ?> *</label>
                <input type="text" name="name" required placeholder="<?= e(__('supplier_name_placeholder')) ?>">
            </div>
            <div class="form-grid form-grid-2">
                <div class="form-group">
                    <label><?= e(__('phone')) ?></label>
                    <input type="text" name="phone" placeholder="05xxxxxxxx">
                </div>
                <div class="form-group">
                    <label><?= e(__('notes')) ?></label>
                    <input type="text" name="notes">
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><?= e(__('save')) ?></button>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h2><?= e(__('suppliers')) ?></h2></div>
    <div class="card-body table-wrap">
        <?php if (empty($suppliers)): ?>
            <p class="text-muted"><?= e(__('no_suppliers')) ?></p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th><?= e(__('supplier_name')) ?></th>
                    <th><?= e(__('phone')) ?></th>
                    <th><?= e(__('debt_balance')) ?></th>
                    <th><?= e(__('actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($suppliers as $s):
                    $debt = supplierDebtBalance((int) $s['id']);
                ?>
                <tr>
                    <td><?= e($s['name']) ?></td>
                    <td><?= e($s['phone'] ?? '-') ?></td>
                    <td class="<?= $debt > 0 ? 'text-danger' : 'text-success' ?>">
                        <?= formatMoney($debt) ?>
                    </td>
                    <td>
                        <?php if ($debt <= 0 && canDelete()): ?>
                        <a href="<?= url('purchases/suppliers.php?delete=' . $s['id']) ?>"
                           class="btn btn-danger btn-sm"
                           data-confirm="<?= e(__('confirm_delete')) ?>"><?= e(__('delete')) ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
