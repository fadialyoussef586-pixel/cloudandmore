<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/backup_helpers.php';

requireOwner();

if (isset($_GET['download'])) {
    try {
        sendBackupDownload(db());
    } catch (Throwable) {
        flash('error', __('backup_failed'));
        redirect(url('backup/index.php'));
    }
}

$pageTitle = __('backup_export');
$hrTab = 'backup';
$tables = backupTableNames();
$pdo = db();
$tableCounts = [];

foreach ($tables as $table) {
    if (!databaseTableExists($pdo, $table)) {
        continue;
    }
    try {
        $tableCounts[$table] = (int) $pdo->query('SELECT COUNT(*) FROM `' . str_replace('`', '``', $table) . '`')->fetchColumn();
    } catch (Throwable) {
        $tableCounts[$table] = 0;
    }
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../hr/_tabs.php';
?>

<div class="page-actions">
    <a href="<?= url('hr/users.php') ?>" class="btn btn-secondary"><?= e(__('user_accounts')) ?></a>
    <a href="<?= url('backup/index.php?download=1') ?>" class="btn btn-primary">
        <?= faIcon('fa-solid fa-download', 'fa-btn-icon') ?>
        <?= e(__('backup_download')) ?>
    </a>
</div>

<div class="card">
    <div class="card-header"><h2><?= e(__('backup_export')) ?></h2></div>
    <div class="card-body">
        <p class="text-muted"><?= e(__('backup_hint')) ?></p>
        <ul class="text-muted" style="margin:1rem 0;padding-inline-start:1.25rem;line-height:1.7">
            <li><?= e(__('backup_owner_only')) ?></li>
            <li><?= e(__('backup_usb_hint')) ?></li>
            <li><?= e(__('backup_includes_users')) ?></li>
        </ul>

        <div class="stats-grid" style="margin-top:1rem">
            <div class="stat-card primary">
                <div class="label"><?= e(__('backup_tables')) ?></div>
                <div class="value"><?= count($tableCounts) ?></div>
            </div>
            <div class="stat-card">
                <div class="label"><?= e(__('total_records')) ?></div>
                <div class="value"><?= array_sum($tableCounts) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2><?= e(__('backup_contents')) ?></h2></div>
    <div class="card-body table-wrap">
        <table>
            <thead>
                <tr>
                    <th><?= e(__('backup_table')) ?></th>
                    <th><?= e(__('backup_rows')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tableCounts as $table => $count): ?>
                    <tr>
                        <td><code><?= e($table) ?></code></td>
                        <td><?= (int) $count ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
