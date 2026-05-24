<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
$pageTitle = __('hr');
$employees = db()->query("SELECT * FROM employees WHERE status = 'active' ORDER BY name_en")->fetchAll();
$payrollTotal = (float) db()->query("SELECT COALESCE(SUM(net_salary),0) FROM payroll WHERE month = MONTH(CURRENT_DATE()) AND year = YEAR(CURRENT_DATE())")->fetchColumn();
require __DIR__ . '/../includes/header.php';
?>
<div class="stats-grid">
    <div class="stat-card">
        <div class="label"><?= e(__('total_employees')) ?></div>
        <div class="value"><?= count($employees) ?></div>
    </div>
    <div class="stat-card success">
        <div class="label"><?= e(__('payroll')) ?> (<?= e(__('month')) ?>)</div>
        <div class="value"><?= formatMoney($payrollTotal) ?></div>
    </div>
</div>
<div class="tabs">
    <a href="<?= url('hr/index.php') ?>" class="tab active"><?= e(__('overview')) ?></a>
    <a href="<?= url('hr/employees.php') ?>" class="tab"><?= e(__('employees')) ?></a>
    <a href="<?= url('hr/payroll.php') ?>" class="tab"><?= e(__('payroll')) ?></a>
</div>
<div class="card">
    <div class="card-header"><h2><?= e(__('employees')) ?></h2></div>
    <div class="card-body table-wrap">
        <table>
            <thead><tr><th><?= e(__('employee_code')) ?></th><th><?= e(__('name')) ?></th><th><?= e(__('department')) ?></th><th><?= e(__('salary')) ?></th></tr></thead>
            <tbody>
                <?php foreach ($employees as $e): ?>
                <tr>
                    <td><?= e($e['employee_code']) ?></td>
                    <td><?= e(employeeName($e)) ?></td>
                    <td><?= e($e['department']) ?></td>
                    <td><?= formatMoney((float) $e['salary']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
