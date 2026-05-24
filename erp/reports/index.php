<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
$pageTitle = __('reports');

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$type = $_GET['type'] ?? 'sales';

$sales = $inventory = $payroll = $deliveryReport = [];
$salesTotal = 0;

if ($type === 'sales') {
    $stmt = db()->prepare("SELECT i.*, c.name AS customer_name FROM invoices i JOIN customers c ON c.id = i.customer_id WHERE DATE(i.created_at) BETWEEN ? AND ? ORDER BY i.created_at DESC");
    $stmt->execute([$from, $to]);
    $sales = $stmt->fetchAll();
    foreach ($sales as $s) { $salesTotal += (float) $s['total']; }
} elseif ($type === 'inventory') {
    $inventory = db()->query('SELECT * FROM products ORDER BY quantity ASC')->fetchAll();
} elseif ($type === 'payroll') {
    $stmt = db()->prepare("SELECT p.*, e.name_ar, e.name_en, e.employee_code FROM payroll p JOIN employees e ON e.id = p.employee_id WHERE STR_TO_DATE(CONCAT(p.year, '-', LPAD(p.month, 2, '0'), '-01'), '%Y-%m-%d') BETWEEN ? AND ?");
    $stmt->execute([$from, $to]);
    $payroll = $stmt->fetchAll();
} elseif ($type === 'delivery') {
    $stmt = db()->prepare("SELECT d.*, c.name AS customer_name FROM deliveries d LEFT JOIN customers c ON c.id = d.customer_id WHERE DATE(d.created_at) BETWEEN ? AND ? ORDER BY d.created_at DESC");
    $stmt->execute([$from, $to]);
    $deliveryReport = $stmt->fetchAll();
}

require __DIR__ . '/../includes/header.php';
?>
<div class="tabs">
    <a href="<?= url('reports/index.php?type=sales&from='.$from.'&to='.$to) ?>" class="tab <?= $type==='sales'?'active':'' ?>"><?= e(__('sales_report')) ?></a>
    <a href="<?= url('reports/index.php?type=inventory') ?>" class="tab <?= $type==='inventory'?'active':'' ?>"><?= e(__('inventory_report')) ?></a>
    <a href="<?= url('reports/index.php?type=payroll&from='.$from.'&to='.$to) ?>" class="tab <?= $type==='payroll'?'active':'' ?>"><?= e(__('payroll_report')) ?></a>
    <a href="<?= url('reports/index.php?type=delivery&from='.$from.'&to='.$to) ?>" class="tab <?= $type==='delivery'?'active':'' ?>"><?= e(__('delivery_report')) ?></a>
</div>

<?php if ($type !== 'inventory'): ?>
<div class="card"><div class="card-body">
<form method="get" class="search-bar">
    <input type="hidden" name="type" value="<?= e($type) ?>">
    <label><?= e(__('from_date')) ?></label>
    <input type="date" name="from" value="<?= e($from) ?>">
    <label><?= e(__('to_date')) ?></label>
    <input type="date" name="to" value="<?= e($to) ?>">
    <button type="submit" class="btn btn-primary"><?= e(__('generate')) ?></button>
</form>
</div></div>
<?php endif; ?>

<div class="card"><div class="card-body table-wrap">
<?php if ($type === 'sales'): ?>
    <?php if ($sales): ?><p><strong><?= e(__('total')) ?>:</strong> <?= formatMoney($salesTotal) ?></p><?php endif; ?>
    <table><thead><tr><th><?= e(__('invoice_number')) ?></th><th><?= e(__('customer')) ?></th><th><?= e(__('total')) ?></th><th><?= e(__('status')) ?></th><th><?= e(__('date')) ?></th></tr></thead>
    <tbody><?php foreach ($sales as $row): ?><tr><td><?= e($row['invoice_number']) ?></td><td><?= e($row['customer_name']) ?></td><td><?= formatMoney((float)$row['total']) ?></td><td><?= statusBadge($row['status']) ?></td><td><?= formatDate($row['created_at']) ?></td></tr><?php endforeach; ?></tbody></table>
<?php elseif ($type === 'inventory'): ?>
    <table><thead><tr><th><?= e(__('sku')) ?></th><th><?= e(__('name')) ?></th><th><?= e(__('category')) ?></th><th><?= e(__('quantity')) ?></th><th><?= e(__('sell_price')) ?></th></tr></thead>
    <tbody><?php foreach ($inventory as $row): ?><tr><td><?= e($row['sku']) ?></td><td><?= e(productName($row)) ?></td><td><?= e($row['category']) ?></td><td class="<?= $row['quantity']<=$row['min_stock']?'low-stock':'' ?>"><?= (int)$row['quantity'] ?></td><td><?= formatMoney((float)$row['sell_price']) ?></td></tr><?php endforeach; ?></tbody></table>
<?php elseif ($type === 'payroll'): ?>
    <table><thead><tr><th>Code</th><th><?= e(__('name')) ?></th><th><?= e(__('net_salary')) ?></th><th><?= e(__('status')) ?></th></tr></thead>
    <tbody><?php foreach ($payroll as $row): ?><tr><td><?= e($row['employee_code']) ?></td><td><?= e(employeeName($row)) ?></td><td><?= formatMoney((float)$row['net_salary']) ?></td><td><?= statusBadge($row['status']) ?></td></tr><?php endforeach; ?></tbody></table>
<?php else: ?>
    <table><thead><tr><th><?= e(__('delivery_number')) ?></th><th><?= e(__('customer')) ?></th><th><?= e(__('driver')) ?></th><th><?= e(__('status')) ?></th><th><?= e(__('date')) ?></th></tr></thead>
    <tbody><?php foreach ($deliveryReport as $row): ?><tr><td><?= e($row['delivery_number']) ?></td><td><?= e($row['customer_name'] ?? '-') ?></td><td><?= e($row['driver_name']) ?></td><td><?= statusBadge($row['status']) ?></td><td><?= formatDate($row['created_at']) ?></td></tr><?php endforeach; ?></tbody></table>
<?php endif; ?>
<?php if (empty($sales) && empty($inventory) && empty($payroll) && empty($deliveryReport)): ?>
    <p class="text-muted"><?= e(__('no_data')) ?></p>
<?php endif; ?>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
