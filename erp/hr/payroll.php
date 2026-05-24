<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requirePermission(PERM_HR);
$pageTitle = __('payroll');
$hrTab = 'payroll';
$month = (int) ($_GET['month'] ?? date('n'));
$year = (int) ($_GET['year'] ?? date('Y'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $empId = (int) $_POST['employee_id'];
    $base = (float) $_POST['base_salary'];
    $bonus = (float) ($_POST['bonus'] ?? 0);
    $ded = (float) ($_POST['deductions'] ?? 0);
    $net = $base + $bonus - $ded;
    db()->prepare('INSERT INTO payroll (employee_id, month, year, base_salary, bonus, deductions, net_salary, status) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE base_salary=VALUES(base_salary), bonus=VALUES(bonus), deductions=VALUES(deductions), net_salary=VALUES(net_salary)')
        ->execute([$empId, $month, $year, $base, $bonus, $ded, $net, 'pending']);
    flash('success', __('success_saved'));
    redirect(url('hr/payroll.php?month='.$month.'&year='.$year));
}

if (isset($_GET['pay'])) {
    db()->prepare("UPDATE payroll SET status = 'paid', paid_at = CURDATE() WHERE id = ?")->execute([(int)$_GET['pay']]);
    flash('success', __('success_saved'));
    redirect(url('hr/payroll.php?month='.$month.'&year='.$year));
}

$employees = db()->query("SELECT * FROM employees WHERE status = 'active' ORDER BY name_en")->fetchAll();
$payrolls = db()->prepare('SELECT p.*, e.name_ar, e.name_en, e.employee_code FROM payroll p JOIN employees e ON e.id = p.employee_id WHERE p.month = ? AND p.year = ?');
$payrolls->execute([$month, $year]);
$payrolls = $payrolls->fetchAll();
$months = months();
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/_tabs.php';
?>
<div class="card"><div class="card-body">
<form method="get" class="search-bar">
<select name="month"><?php foreach ($months as $n=>$l): ?><option value="<?= $n ?>" <?= $month==$n?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select>
<input type="number" name="year" value="<?= $year ?>" min="2020" max="2099">
<button class="btn btn-secondary"><?= e(__('generate')) ?></button>
</form>
</div></div>
<div class="card"><div class="card-header"><h2><?= e(__('add')) ?> <?= e(__('payroll')) ?></h2></div>
<div class="card-body">
<form method="post" class="form-grid">
<div class="form-group"><label><?= e(__('employees')) ?></label>
<select name="employee_id" required><?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>"><?= e(employeeName($e)) ?> (<?= formatMoney((float)$e['salary']) ?>)</option><?php endforeach; ?></select></div>
<div class="form-group"><label><?= e(__('salary')) ?></label><input type="number" step="0.01" name="base_salary" required></div>
<div class="form-group"><label><?= e(__('bonus')) ?></label><input type="number" step="0.01" name="bonus" value="0"></div>
<div class="form-group"><label><?= e(__('deductions')) ?></label><input type="number" step="0.01" name="deductions" value="0"></div>
<div class="form-actions"><button type="submit" class="btn btn-primary"><?= e(__('save')) ?></button></div>
</form>
</div></div>
<div class="card"><div class="card-body table-wrap">
<table><thead><tr><th>Code</th><th><?= e(__('name')) ?></th><th><?= e(__('salary')) ?></th><th><?= e(__('bonus')) ?></th><th><?= e(__('deductions')) ?></th><th><?= e(__('net_salary')) ?></th><th><?= e(__('status')) ?></th><th></th></tr></thead>
<tbody>
<?php foreach ($payrolls as $p): ?>
<tr>
<td><?= e($p['employee_code']) ?></td><td><?= e(employeeName($p)) ?></td>
<td><?= formatMoney((float)$p['base_salary']) ?></td><td><?= formatMoney((float)$p['bonus']) ?></td>
<td><?= formatMoney((float)$p['deductions']) ?></td><td><?= formatMoney((float)$p['net_salary']) ?></td>
<td><?= statusBadge($p['status']) ?></td>
<td><?php if ($p['status']==='pending'): ?><a href="<?= url('hr/payroll.php?pay='.$p['id'].'&month='.$month.'&year='.$year) ?>" class="btn btn-success btn-sm"><?= e(__('mark_paid')) ?></a><?php endif; ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
