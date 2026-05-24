<?php
$hrTab = $hrTab ?? 'overview';
?>
<div class="tabs">
    <a href="<?= url('hr/index.php') ?>" class="tab <?= $hrTab === 'overview' ? 'active' : '' ?>"><?= e(__('overview')) ?></a>
    <a href="<?= url('hr/employees.php') ?>" class="tab <?= $hrTab === 'employees' ? 'active' : '' ?>"><?= e(__('employees')) ?></a>
    <a href="<?= url('hr/payroll.php') ?>" class="tab <?= $hrTab === 'payroll' ? 'active' : '' ?>"><?= e(__('payroll')) ?></a>
    <?php if (canManageUsers()): ?>
    <a href="<?= url('hr/users.php') ?>" class="tab <?= $hrTab === 'users' ? 'active' : '' ?>"><?= e(__('user_accounts')) ?></a>
    <a href="<?= url('reset-data.php') ?>" class="tab <?= $hrTab === 'reset' ? 'active' : '' ?>"><?= e(__('reset_data')) ?></a>
    <?php endif; ?>
</div>
