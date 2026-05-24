<?php

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireAuth();

ensureTreasuryTables();
$pdo = db();

$invoiceCount = (int) $pdo->query('SELECT COUNT(*) FROM invoices')->fetchColumn();
$treasuryRowCount = (int) $pdo->query('SELECT COUNT(*) FROM treasury_transactions')->fetchColumn();
$monthlyRevenue = $invoiceCount === 0 ? 0.0 : monthlyRevenue($pdo);
$treasuryBal = $treasuryRowCount === 0 ? 0.0 : treasuryBalanceFromDb($pdo);

$pageTitle = __('dashboard');

$stats = [
    'products' => (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn(),
    'low_stock' => (int) $pdo->query('SELECT COUNT(*) FROM products WHERE quantity <= min_stock')->fetchColumn(),
    'orders' => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'new'")->fetchColumn(),
    'invoices' => $invoiceCount,
    'deliveries' => (int) $pdo->query("SELECT COUNT(*) FROM deliveries WHERE status IN ('pending','in_transit')")->fetchColumn(),
    'employees' => (int) $pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'active'")->fetchColumn(),
    'revenue' => $monthlyRevenue,
    'treasury' => $treasuryBal,
    'treasury_rows' => $treasuryRowCount,
];

$recentOrders = db()->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 5")->fetchAll();

$recentInvoices = db()->query("
    SELECT i.*, c.name AS customer_name
    FROM invoices i
    JOIN customers c ON c.id = i.customer_id
    ORDER BY i.created_at DESC LIMIT 5
")->fetchAll();

$recentDeliveries = db()->query("
    SELECT d.*, c.name AS customer_name
    FROM deliveries d
    LEFT JOIN customers c ON c.id = d.customer_id
    ORDER BY d.created_at DESC LIMIT 5
")->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<p class="text-muted" style="margin-bottom:0.5rem"><?= e(__('welcome')) ?>, <?= e($_SESSION['user_name'] ?? '') ?>!</p>
<p class="text-muted" style="margin-bottom:1.5rem;font-size:0.875rem"><?= e(__('company_tagline')) ?></p>

<div class="stats-grid">
    <div class="stat-card primary">
        <div class="label"><?= e(__('total_products')) ?></div>
        <div class="value"><?= $stats['products'] ?></div>
    </div>
    <div class="stat-card warning">
        <div class="label"><?= e(__('low_stock')) ?></div>
        <div class="value"><?= $stats['low_stock'] ?></div>
    </div>
        <div class="stat-card warning">
        <div class="label"><?= e(__('pending_orders')) ?></div>
        <div class="value"><?= $stats['orders'] ?></div>
    </div>
<div class="stat-card">
        <div class="label"><?= e(__('total_invoices')) ?></div>
        <div class="value"><?= $stats['invoices'] ?></div>
    </div>
    <div class="stat-card warning">
        <div class="label"><?= e(__('pending_deliveries')) ?></div>
        <div class="value"><?= $stats['deliveries'] ?></div>
    </div>
    <div class="stat-card">
        <div class="label"><?= e(__('total_employees')) ?></div>
        <div class="value"><?= $stats['employees'] ?></div>
    </div>
    <div class="stat-card success">
        <div class="label"><?= e(__('revenue_month')) ?></div>
        <div class="value" id="dashboard-revenue"><?= formatMoney($invoiceCount === 0 ? 0.0 : $stats['revenue']) ?></div>
    </div>
    <?php if (in_array($_SESSION['user_role'] ?? '', ['admin', 'manager'], true)): ?>
    <div class="stat-card primary">
        <div class="label"><?= e(__('treasury_balance')) ?></div>
        <div class="value" id="dashboard-treasury" style="font-size:1.1rem"><?= formatMoney($treasuryRowCount === 0 ? 0.0 : $stats['treasury']) ?></div>
    </div>
    <?php endif; ?>
</div>


    <div class="card">
        <div class="card-header"><h2><?= e(__('recent_orders')) ?></h2></div>
        <div class="card-body table-wrap">
            <?php if (empty($recentOrders)): ?><p class="text-muted"><?= e(__('no_data')) ?></p>
            <?php else: ?><table><thead><tr><th><?= e(__('order_number')) ?></th><th><?= e(__('customer')) ?></th><th><?= e(__('total')) ?></th><th><?= e(__('status')) ?></th></tr></thead><tbody>
            <?php foreach ($recentOrders as $o): ?><tr><td><a href="<?= url('orders/view.php?id='.$o['id']) ?>"><?= e($o['order_number']) ?></a></td><td><?= e($o['customer_name']) ?></td><td><?= formatMoney((float)$o['total']) ?></td><td><?= orderStatusBadge($o['status']) ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
        </div>
    </div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h2><?= e(__('recent_invoices')) ?></h2></div>
        <div class="card-body table-wrap">
            <?php if (empty($recentInvoices)): ?>
                <p class="text-muted"><?= e(__('no_data')) ?></p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th><?= e(__('invoice_number')) ?></th>
                            <th><?= e(__('customer')) ?></th>
                            <th><?= e(__('total')) ?></th>
                            <th><?= e(__('status')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentInvoices as $inv): ?>
                            <tr>
                                <td><a href="<?= url('invoices/view.php?id=' . $inv['id']) ?>"><?= e($inv['invoice_number']) ?></a></td>
                                <td><?= e($inv['customer_name']) ?></td>
                                <td><?= formatMoney((float) $inv['total']) ?></td>
                                <td><?= statusBadge($inv['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2><?= e(__('recent_deliveries')) ?></h2></div>
        <div class="card-body table-wrap">
            <?php if (empty($recentDeliveries)): ?>
                <p class="text-muted"><?= e(__('no_data')) ?></p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th><?= e(__('delivery_number')) ?></th>
                            <th><?= e(__('customer')) ?></th>
                            <th><?= e(__('scheduled_date')) ?></th>
                            <th><?= e(__('status')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentDeliveries as $del): ?>
                            <tr>
                                <td><a href="<?= url('delivery/view.php?id=' . $del['id']) ?>"><?= e($del['delivery_number']) ?></a></td>
                                <td><?= e($del['customer_name'] ?? '-') ?></td>
                                <td><?= formatDate($del['scheduled_date']) ?></td>
                                <td><?= statusBadge($del['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function () {
  fetch('<?= url('api/dashboard-stats.php') ?>?_=' + Date.now(), { credentials: 'same-origin', cache: 'no-store' })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      var rev = document.getElementById('dashboard-revenue');
      var tr = document.getElementById('dashboard-treasury');
      if (rev && d.revenue_display) rev.textContent = d.revenue_display;
      if (tr && d.treasury_display) tr.textContent = d.treasury_display;
    })
    .catch(function () {});
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
