<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requirePermission(PERM_DELIVERY);

$id = (int) ($_GET['id'] ?? 0);
if (isset($_GET['action']) && $_GET['action'] === 'deliver') {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM deliveries WHERE id = ? FOR UPDATE');
        $stmt->execute([$id]);
        $delivery = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$delivery) {
            throw new RuntimeException('not_found');
        }

        $pdo->prepare("UPDATE deliveries SET status = 'delivered', delivered_at = NOW() WHERE id = ?")->execute([$id]);
        if (!empty($delivery['order_id'])) {
            $pdo->prepare("UPDATE orders SET status = 'delivered', delivered_at = NOW() WHERE id = ?")
                ->execute([(int) $delivery['order_id']]);
        }
        $pdo->commit();
        flash('success', __('success_saved'));
    } catch (Throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', __('error'));
    }
    redirect(url('delivery/view.php?id=' . $id));
}

$stmt = db()->prepare("SELECT d.*, c.name AS customer_name, c.phone FROM deliveries d LEFT JOIN customers c ON c.id = d.customer_id WHERE d.id = ?");
$stmt->execute([$id]);
$delivery = $stmt->fetch();
if (!$delivery) redirect(url('delivery/index.php'));

$items = db()->prepare('SELECT * FROM delivery_items WHERE delivery_id = ?');
$items->execute([$id]);
$items = $items->fetchAll();
$pageTitle = $delivery['delivery_number'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-actions">
<a href="<?= url('delivery/index.php') ?>" class="btn btn-secondary"><?= e(__('cancel')) ?></a>
<?php if ($delivery['status'] !== 'delivered'): ?>
<a href="<?= url('delivery/view.php?id='.$id.'&action=deliver') ?>" class="btn btn-success"><?= e(__('mark_delivered')) ?></a>
<?php endif; ?>
</div>
<div class="card"><div class="card-body">
<h2><?= e($delivery['delivery_number']) ?> <?= statusBadge($delivery['status']) ?></h2>
<p><strong><?= e(__('customer')) ?>:</strong> <?= e($delivery['customer_name'] ?? '-') ?> | <?= e($delivery['phone'] ?? '') ?></p>
<p><strong><?= e(__('driver')) ?>:</strong> <?= e($delivery['driver_name']) ?> | <strong><?= e(__('vehicle')) ?>:</strong> <?= e($delivery['vehicle_number']) ?></p>
<p><strong><?= e(__('address')) ?>:</strong> <?= e($delivery['delivery_address']) ?></p>
<p><strong><?= e(__('scheduled_date')) ?>:</strong> <?= formatDate($delivery['scheduled_date']) ?></p>
<table style="margin-top:1rem"><thead><tr><th><?= e(__('description')) ?></th><th><?= e(__('quantity')) ?></th></tr></thead>
<tbody><?php foreach ($items as $item): ?><tr><td><?= e($item['description']) ?></td><td><?= (int)$item['quantity'] ?></td></tr><?php endforeach; ?></tbody></table>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
