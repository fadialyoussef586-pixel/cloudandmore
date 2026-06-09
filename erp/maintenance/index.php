<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requirePermission(PERM_MAINTENANCE);

ensureMaintenanceTables();

$statusFilter = trim($_GET['status'] ?? '');
$params = [];
$sql = 'SELECT * FROM maintenance_tickets WHERE 1=1';

if ($statusFilter !== '' && in_array($statusFilter, maintenanceStatuses(), true)) {
    $sql .= ' AND status = ?';
    $params[] = $statusFilter;
} elseif ($statusFilter === 'open') {
    $sql .= " AND status NOT IN ('delivered', 'cancelled')";
}

$sql .= ' ORDER BY FIELD(priority, "urgent", "normal"), created_at DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$openCount = maintenanceOpenCount();
$pageTitle = __('maintenance');

require __DIR__ . '/../includes/header.php';
?>

<div class="stats-grid">
    <div class="stat-card warning">
        <div class="label"><?= e(__('maint_open_tickets')) ?></div>
        <div class="value"><?= $openCount ?></div>
    </div>
    <div class="stat-card">
        <div class="label"><?= e(__('total')) ?></div>
        <div class="value"><?= count($tickets) ?></div>
    </div>
</div>

<div class="page-actions">
    <div class="shop-filters" style="margin:0">
        <a href="<?= url('maintenance/index.php') ?>" class="filter-chip <?= $statusFilter === '' ? 'active' : '' ?>"><?= e(__('all')) ?></a>
        <a href="<?= url('maintenance/index.php?status=open') ?>" class="filter-chip <?= $statusFilter === 'open' ? 'active' : '' ?>"><?= e(__('maint_open_tickets')) ?></a>
        <?php foreach (maintenanceStatuses() as $st): ?>
            <a href="<?= url('maintenance/index.php?status=' . urlencode($st)) ?>" class="filter-chip <?= $statusFilter === $st ? 'active' : '' ?>"><?= e(maintenanceStatusLabel($st)) ?></a>
        <?php endforeach; ?>
    </div>
    <a href="<?= url('maintenance/create.php') ?>" class="btn btn-primary"><?= e(__('maint_new_ticket')) ?></a>
</div>

<div class="card">
    <div class="card-body table-wrap">
        <?php if ($tickets === []): ?>
            <p class="text-muted"><?= e(__('no_data')) ?></p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th><?= e(__('maint_ticket_number')) ?></th>
                        <th><?= e(__('customer')) ?></th>
                        <th><?= e(__('phone')) ?></th>
                        <th><?= e(__('maint_device')) ?></th>
                        <th><?= e(__('status')) ?></th>
                        <th><?= e(__('maint_repair_cost')) ?></th>
                        <th><?= e(__('date')) ?></th>
                        <th><?= e(__('actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $t): ?>
                        <tr>
                            <td>
                                <a href="<?= url('maintenance/view.php?id=' . $t['id']) ?>"><?= e($t['ticket_number']) ?></a>
                                <?php if (($t['priority'] ?? '') === 'urgent'): ?>
                                    <span class="badge badge-red"><?= e(__('maint_urgent')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($t['customer_name']) ?></td>
                            <td><?= e($t['customer_phone']) ?></td>
                            <td><?= e(trim(($t['device_brand'] ?? '') . ' ' . ($t['device_model'] ?? '')) ?: '-') ?></td>
                            <td><?= maintenanceStatusBadge($t['status']) ?></td>
                            <td><?= formatMoney((float) ($t['repair_cost'] ?? 0)) ?></td>
                            <td><?= formatDate($t['received_at'] ?? $t['created_at']) ?></td>
                            <td>
                                <a href="<?= url('maintenance/view.php?id=' . $t['id']) ?>" class="btn btn-secondary btn-sm"><?= e(__('view')) ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
