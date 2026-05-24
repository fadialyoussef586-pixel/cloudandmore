<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
$pageTitle = __('employees');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    db()->prepare('INSERT INTO employees (employee_code, name_ar, name_en, email, phone, department, job_title, salary, hire_date, status) VALUES (?,?,?,?,?,?,?,?,?,?)')
        ->execute([
            trim($_POST['employee_code']), trim($_POST['name_ar']), trim($_POST['name_en']),
            trim($_POST['email'] ?? ''), trim($_POST['phone'] ?? ''), trim($_POST['department'] ?? ''),
            trim($_POST['job_title'] ?? ''), (float)($_POST['salary'] ?? 0), $_POST['hire_date'] ?: null, 'active'
        ]);
    flash('success', __('success_saved'));
    redirect(url('hr/employees.php'));
}

if (isset($_GET['delete'])) {
    db()->prepare("UPDATE employees SET status = 'terminated' WHERE id = ?")->execute([(int)$_GET['delete']]);
    flash('success', __('success_deleted'));
    redirect(url('hr/employees.php'));
}

$employees = db()->query('SELECT * FROM employees ORDER BY name_en')->fetchAll();
$showAdd = isset($_GET['action']) && $_GET['action'] === 'add';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-actions"><span></span><a href="<?= url('hr/employees.php?action=add') ?>" class="btn btn-primary"><?= e(__('add_employee')) ?></a></div>
<?php if ($showAdd): ?>
<div class="card"><div class="card-body">
<form method="post">
<input type="hidden" name="action" value="add">
<div class="form-grid">
<div class="form-group"><label><?= e(__('employee_code')) ?></label><input name="employee_code" required></div>
<div class="form-group"><label><?= e(__('name')) ?> AR</label><input name="name_ar" required></div>
<div class="form-group"><label><?= e(__('name')) ?> EN</label><input name="name_en" required></div>
<div class="form-group"><label><?= e(__('email')) ?></label><input type="email" name="email"></div>
<div class="form-group"><label><?= e(__('phone')) ?></label><input name="phone"></div>
<div class="form-group"><label><?= e(__('department')) ?></label><input name="department"></div>
<div class="form-group"><label><?= e(__('job_title')) ?></label><input name="job_title"></div>
<div class="form-group"><label><?= e(__('salary')) ?></label><input type="number" step="0.01" name="salary"></div>
<div class="form-group"><label><?= e(__('hire_date')) ?></label><input type="date" name="hire_date"></div>
</div>
<button type="submit" class="btn btn-primary"><?= e(__('save')) ?></button>
</form>
</div></div>
<?php endif; ?>
<div class="card"><div class="card-body table-wrap">
<table><thead><tr><th>Code</th><th><?= e(__('name')) ?></th><th><?= e(__('department')) ?></th><th><?= e(__('status')) ?></th><th><?= e(__('actions')) ?></th></tr></thead>
<tbody>
<?php foreach ($employees as $e): ?>
<tr>
<td><?= e($e['employee_code']) ?></td><td><?= e(employeeName($e)) ?></td><td><?= e($e['department']) ?></td>
<td><?= statusBadge($e['status']) ?></td>
<td><?php if ($e['status'] === 'active'): ?><a href="<?= url('hr/employees.php?delete='.$e['id']) ?>" class="btn btn-danger btn-sm" data-confirm="<?= e(__('confirm_delete')) ?>"><?= e(__('delete')) ?></a><?php endif; ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
