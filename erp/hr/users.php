<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireOwner();

$pageTitle = __('user_accounts');
$hrTab = 'users';
$owner = ownerEmail();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'staff';

        if ($name === '' || $email === '' || strlen($password) < 6) {
            flash('error', __('user_form_invalid'));
        } elseif ($email === $owner) {
            flash('error', __('user_owner_reserved'));
        } else {
            $perms = [];
            foreach (allPermissionKeys() as $key) {
                if (!empty($_POST['perm_' . $key])) {
                    $perms[] = $key;
                }
            }
            if ($perms === []) {
                $perms = defaultPermissionsForRole($role);
            }
            try {
                db()->prepare('INSERT INTO users (name, email, password, role, permissions) VALUES (?,?,?,?,?)')
                    ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role, json_encode($perms)]);
                flash('success', __('success_saved'));
            } catch (PDOException) {
                flash('error', __('user_email_exists'));
            }
        }
        redirect(url('hr/users.php'));
    }

    if ($action === 'save_permissions') {
        $id = (int) ($_POST['user_id'] ?? 0);
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user || strtolower($user['email']) === $owner) {
            flash('error', __('error'));
        } else {
            $perms = [];
            foreach (allPermissionKeys() as $key) {
                if (!empty($_POST['perm_' . $key])) {
                    $perms[] = $key;
                }
            }
            $role = $_POST['role'] ?? $user['role'];
            db()->prepare('UPDATE users SET role = ?, permissions = ? WHERE id = ?')
                ->execute([$role, json_encode($perms), $id]);
            flash('success', __('success_saved'));
        }
        redirect(url('hr/users.php'));
    }

    if ($action === 'delete_user') {
        requireOwner();
        $id = (int) ($_POST['user_id'] ?? 0);
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if ($user && strtolower($user['email']) !== $owner) {
            db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            flash('success', __('success_deleted'));
        }
        redirect(url('hr/users.php'));
    }
}

$users = db()->query('SELECT * FROM users ORDER BY name')->fetchAll();
$showAdd = isset($_GET['action']) && $_GET['action'] === 'add';

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/_tabs.php';
?>

<div class="page-actions">
    <span></span>
    <a href="<?= url('hr/users.php?action=add') ?>" class="btn btn-primary"><?= e(__('add_user')) ?></a>
</div>

<?php if ($showAdd): ?>
<div class="card"><div class="card-body">
<form method="post">
<input type="hidden" name="action" value="add">
<div class="form-grid">
    <div class="form-group"><label><?= e(__('name')) ?></label><input name="name" required></div>
    <div class="form-group"><label><?= e(__('email')) ?></label><input type="email" name="email" required></div>
    <div class="form-group"><label><?= e(__('password')) ?></label><input type="password" name="password" minlength="6" required></div>
    <div class="form-group"><label><?= e(__('role')) ?></label>
        <select name="role" id="newUserRole">
            <option value="manager">manager</option>
            <option value="sales">sales</option>
            <option value="driver">driver</option>
            <option value="staff" selected>staff</option>
        </select>
    </div>
</div>
<fieldset class="permissions-fieldset">
    <legend><?= e(__('permissions')) ?></legend>
    <div class="permissions-grid">
        <?php foreach (allPermissionKeys() as $key): ?>
        <label><input type="checkbox" name="perm_<?= e($key) ?>" value="1" class="perm-check" data-role-default> <?= e(permissionLabel($key)) ?></label>
        <?php endforeach; ?>
    </div>
    <p class="text-muted" style="font-size:0.85rem;margin-top:0.5rem"><?= e(__('permissions_hint')) ?></p>
</fieldset>
<button type="submit" class="btn btn-primary"><?= e(__('save')) ?></button>
</form>
</div></div>
<?php endif; ?>

<div class="card"><div class="card-body table-wrap">
<table>
<thead>
<tr>
    <th><?= e(__('name')) ?></th>
    <th><?= e(__('email')) ?></th>
    <th><?= e(__('role')) ?></th>
    <th><?= e(__('permissions')) ?></th>
    <th><?= e(__('actions')) ?></th>
</tr>
</thead>
<tbody>
<?php foreach ($users as $u):
    $isOwnerRow = strtolower($u['email']) === $owner;
    $perms = decodeUserPermissions($u);
?>
<tr>
    <td><?= e($u['name']) ?><?= $isOwnerRow ? ' <span class="badge badge-green">' . e(__('owner')) . '</span>' : '' ?></td>
    <td><?= e($u['email']) ?></td>
    <td><?= e($u['role']) ?></td>
    <td>
        <?php if ($isOwnerRow): ?>
            <span class="text-muted"><?= e(__('all_permissions')) ?></span>
        <?php else: ?>
            <?= e(implode(', ', array_map('permissionLabel', $perms))) ?>
        <?php endif; ?>
    </td>
    <td>
        <?php if (!$isOwnerRow): ?>
        <details>
            <summary class="btn btn-secondary btn-sm"><?= e(__('edit')) ?></summary>
            <form method="post" style="margin-top:0.75rem;padding:0.75rem;background:var(--bg-alt);border-radius:8px">
                <input type="hidden" name="action" value="save_permissions">
                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                <div class="form-group">
                    <label><?= e(__('role')) ?></label>
                    <select name="role">
                        <?php foreach (['manager', 'sales', 'driver', 'staff'] as $r): ?>
                        <option value="<?= e($r) ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= e($r) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="permissions-grid">
                    <?php foreach (allPermissionKeys() as $key): ?>
                    <label><input type="checkbox" name="perm_<?= e($key) ?>" value="1" <?= in_array($key, $perms, true) ? 'checked' : '' ?>> <?= e(permissionLabel($key)) ?></label>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="btn btn-primary btn-sm"><?= e(__('save')) ?></button>
            </form>
            <?php if (isOwner()): ?>
            <form method="post" style="display:inline;margin-top:0.5rem" onsubmit="return confirm('<?= e(__('confirm_delete')) ?>')">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm"><?= e(__('delete')) ?></button>
            </form>
            <?php endif; ?>
        </details>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div></div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
