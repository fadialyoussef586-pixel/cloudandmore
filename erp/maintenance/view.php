<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requirePermission(PERM_MAINTENANCE);

ensureMaintenanceTables();
ensureTreasuryTables();

$id = (int) ($_GET['id'] ?? 0);
$ticket = getMaintenanceTicket($id);
if (!$ticket) {
    flash('error', __('error'));
    redirect(url('maintenance/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update';
    $pdo = db();

    if ($action === 'payment') {
        if (!can(PERM_TREASURY)) {
            flash('error', __('error_permission'));
            redirect(url('maintenance/view.php?id=' . $id));
        }
        $amount = (float) ($_POST['payment_amount'] ?? 0);
        if ($amount > 0) {
            $pdo->beginTransaction();
            try {
                recordMaintenancePayment($id, $amount, $_SESSION['user_id'] ?? null, $pdo);
                $pdo->commit();
                flash('success', __('success_saved'));
            } catch (Throwable) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                flash('error', __('error'));
            }
        } else {
            flash('error', __('invalid_amount'));
        }
        redirect(url('maintenance/view.php?id=' . $id));
    }

    $status = $_POST['status'] ?? $ticket['status'];
    if (!in_array($status, maintenanceStatuses(), true)) {
        $status = $ticket['status'];
    }

    $repairCost = round((float) ($_POST['repair_cost'] ?? $ticket['repair_cost']), 2);
    $estimatedCost = round((float) ($_POST['estimated_cost'] ?? $ticket['estimated_cost']), 2);
    $technicianNotes = trim($_POST['technician_notes'] ?? '');
    $priority = ($_POST['priority'] ?? '') === 'urgent' ? 'urgent' : 'normal';

    $completedAt = $ticket['completed_at'];
    $deliveredAt = $ticket['delivered_at'];

    if ($status === 'ready' && !$completedAt) {
        $completedAt = date('Y-m-d H:i:s');
    }
    if ($status === 'delivered') {
        if (!$completedAt) {
            $completedAt = date('Y-m-d H:i:s');
        }
        $deliveredAt = date('Y-m-d H:i:s');
    }

    $updated = [
        'repair_cost' => $repairCost,
        'amount_paid' => (float) ($ticket['amount_paid'] ?? 0),
    ];
    syncMaintenancePaymentStatus($updated);

    $pdo->prepare(
        'UPDATE maintenance_tickets
         SET status = ?, priority = ?, estimated_cost = ?, repair_cost = ?,
             payment_status = ?, technician_notes = ?, completed_at = ?, delivered_at = ?
         WHERE id = ?'
    )->execute([
        $status,
        $priority,
        $estimatedCost,
        $repairCost,
        $updated['payment_status'],
        $technicianNotes !== '' ? $technicianNotes : null,
        $completedAt,
        $deliveredAt,
        $id,
    ]);

    flash('success', __('success_saved'));
    redirect(url('maintenance/view.php?id=' . $id));
}

$ticket = getMaintenanceTicket($id);
$pageTitle = $ticket['ticket_number'];

require __DIR__ . '/../includes/header.php';
?>

<div class="page-actions">
    <a href="<?= url('maintenance/index.php') ?>" class="btn btn-secondary"><?= e(__('maintenance')) ?></a>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h2><?= e($ticket['ticket_number']) ?></h2>
            <?= maintenanceStatusBadge($ticket['status']) ?>
        </div>
        <div class="card-body">
            <p><strong><?= e(__('customer')) ?>:</strong>
                <?php if (!empty($ticket['customer_id']) && can(PERM_CUSTOMERS)): ?>
                    <a href="<?= url('customers/view.php?id=' . (int) $ticket['customer_id']) ?>"><?= e($ticket['customer_name']) ?></a>
                <?php else: ?>
                    <?= e($ticket['customer_name']) ?>
                <?php endif; ?>
            </p>
            <p><strong><?= e(__('phone')) ?>:</strong> <?= e($ticket['customer_phone']) ?></p>
            <?php if (!empty($ticket['customer_email'])): ?>
                <p><strong><?= e(__('email')) ?>:</strong> <?= e($ticket['customer_email']) ?></p>
            <?php endif; ?>
            <p><strong><?= e(__('maint_device')) ?>:</strong>
                <?= e(trim(($ticket['device_brand'] ?? '') . ' ' . ($ticket['device_model'] ?? '')) ?: '-') ?>
            </p>
            <p><strong><?= e(__('serial_number')) ?>:</strong> <?= e($ticket['serial_number'] ?? '-') ?></p>
            <p><strong><?= e(__('maint_issue')) ?>:</strong></p>
            <p class="text-muted"><?= nl2br(e($ticket['issue_description'])) ?></p>
            <p><strong><?= e(__('maint_source')) ?>:</strong> <?= e($ticket['source'] === 'shop' ? __('maint_source_shop') : __('maint_source_manual')) ?></p>
            <p><strong><?= e(__('maint_received_date')) ?>:</strong> <?= formatDate($ticket['received_at']) ?></p>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2><?= e(__('maint_update_ticket')) ?></h2></div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="action" value="update">
                <div class="form-grid">
                    <div class="form-group">
                        <label><?= e(__('status')) ?></label>
                        <select name="status">
                            <?php foreach (maintenanceStatuses() as $st): ?>
                                <option value="<?= e($st) ?>" <?= ($ticket['status'] ?? '') === $st ? 'selected' : '' ?>><?= e(maintenanceStatusLabel($st)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?= e(__('maint_priority')) ?></label>
                        <select name="priority">
                            <option value="normal" <?= ($ticket['priority'] ?? '') === 'normal' ? 'selected' : '' ?>><?= e(__('maint_priority_normal')) ?></option>
                            <option value="urgent" <?= ($ticket['priority'] ?? '') === 'urgent' ? 'selected' : '' ?>><?= e(__('maint_urgent')) ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?= e(__('maint_estimated_cost')) ?></label>
                        <input type="number" step="0.01" min="0" name="estimated_cost" value="<?= e((string) ($ticket['estimated_cost'] ?? '0')) ?>">
                    </div>
                    <div class="form-group">
                        <label><?= e(__('maint_repair_cost')) ?></label>
                        <input type="number" step="0.01" min="0" name="repair_cost" value="<?= e((string) ($ticket['repair_cost'] ?? '0')) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label><?= e(__('maint_technician_notes')) ?></label>
                    <textarea name="technician_notes" rows="4"><?= e($ticket['technician_notes'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary"><?= e(__('save')) ?></button>
            </form>
        </div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h2><?= e(__('payment')) ?></h2></div>
        <div class="card-body">
            <p><?= e(__('maint_repair_cost')) ?>: <strong><?= formatMoney((float) ($ticket['repair_cost'] ?? 0)) ?></strong></p>
            <p><?= e(__('maint_amount_paid')) ?>: <strong><?= formatMoney((float) ($ticket['amount_paid'] ?? 0)) ?></strong></p>
            <p><?= maintenancePaymentBadge($ticket['payment_status'] ?? 'unpaid') ?></p>

            <?php if (can(PERM_TREASURY) && (float) ($ticket['repair_cost'] ?? 0) > (float) ($ticket['amount_paid'] ?? 0)): ?>
                <form method="post" style="margin-top:1rem">
                    <input type="hidden" name="action" value="payment">
                    <div class="form-group">
                        <label><?= e(__('maint_record_payment')) ?></label>
                        <input type="number" step="0.01" min="0.01" name="payment_amount" required
                            max="<?= e((string) max(0.01, (float) $ticket['repair_cost'] - (float) $ticket['amount_paid'])) ?>">
                    </div>
                    <button type="submit" class="btn btn-success"><?= e(__('maint_record_payment')) ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2><?= e(__('details')) ?></h2></div>
        <div class="card-body">
            <p><strong><?= e(__('date')) ?>:</strong> <?= formatDate($ticket['created_at']) ?></p>
            <?php if (!empty($ticket['user_name'])): ?>
                <p><strong><?= e(__('maint_registered_by')) ?>:</strong> <?= e($ticket['user_name']) ?></p>
            <?php endif; ?>
            <?php if (!empty($ticket['completed_at'])): ?>
                <p><strong><?= e(__('maint_completed_at')) ?>:</strong> <?= e($ticket['completed_at']) ?></p>
            <?php endif; ?>
            <?php if (!empty($ticket['delivered_at'])): ?>
                <p><strong><?= e(__('maint_delivered_at')) ?>:</strong> <?= e($ticket['delivered_at']) ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
