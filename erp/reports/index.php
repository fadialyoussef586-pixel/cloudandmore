<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requirePermission(PERM_REPORTS);
ensureInvoiceSchema();
ensureInvoiceReturnsSchema();
$pageTitle = __('reports');

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$type = $_GET['type'] ?? 'sales';

$sales = $inventory = $payroll = $deliveryReport = [];
$salesSummary = [
    'invoice_count' => 0,
    'subtotal' => 0.0,
    'discount' => 0.0,
    'total' => 0.0,
    'average' => 0.0,
];
$paymentBreakdown = [];
$topProducts = [];
$returnsSummary = [
    'entries' => 0,
    'return_total' => 0.0,
    'exchange_diff' => 0.0,
];
$recentReturns = [];
$netSales = 0.0;

if ($type === 'sales') {
    $stmt = db()->prepare("SELECT i.*, c.name AS customer_name
                           FROM invoices i
                           JOIN customers c ON c.id = i.customer_id
                           WHERE DATE(i.created_at) BETWEEN ? AND ?
                             AND i.invoice_type = 'sale'
                             AND i.status != 'cancelled'
                           ORDER BY i.created_at DESC");
    $stmt->execute([$from, $to]);
    $sales = $stmt->fetchAll();

    $stmt = db()->prepare("SELECT COUNT(*) AS invoice_count,
                                  COALESCE(SUM(subtotal), 0) AS subtotal,
                                  COALESCE(SUM(discount), 0) AS discount,
                                  COALESCE(SUM(total), 0) AS total,
                                  COALESCE(AVG(total), 0) AS average_total
                           FROM invoices
                           WHERE DATE(created_at) BETWEEN ? AND ?
                             AND invoice_type = 'sale'
                             AND status != 'cancelled'");
    $stmt->execute([$from, $to]);
    $salesSummary = $stmt->fetch() ?: $salesSummary;

    $stmt = db()->prepare("SELECT payment_method,
                                  COUNT(*) AS invoice_count,
                                  COALESCE(SUM(total), 0) AS total
                           FROM invoices
                           WHERE DATE(created_at) BETWEEN ? AND ?
                             AND invoice_type = 'sale'
                             AND status != 'cancelled'
                           GROUP BY payment_method
                           ORDER BY total DESC");
    $stmt->execute([$from, $to]);
    $paymentBreakdown = $stmt->fetchAll();

    $stmt = db()->prepare("SELECT ii.description,
                                  SUM(ii.quantity) AS total_qty,
                                  COALESCE(SUM(ii.total), 0) AS total_amount
                           FROM invoice_items ii
                           JOIN invoices i ON i.id = ii.invoice_id
                           WHERE DATE(i.created_at) BETWEEN ? AND ?
                             AND i.invoice_type = 'sale'
                             AND i.status != 'cancelled'
                           GROUP BY ii.description
                           ORDER BY total_amount DESC, total_qty DESC
                           LIMIT 5");
    $stmt->execute([$from, $to]);
    $topProducts = $stmt->fetchAll();

    $stmt = db()->prepare("SELECT COUNT(*) AS entries,
                                  COALESCE(SUM(r.return_total), 0) AS return_total,
                                  COALESCE(SUM(CASE WHEN r.action = 'exchange' THEN r.difference_amount ELSE 0 END), 0) AS exchange_diff
                           FROM invoice_returns r
                           JOIN invoices i ON i.id = r.invoice_id
                           WHERE DATE(r.created_at) BETWEEN ? AND ?
                             AND i.invoice_type = 'sale'");
    $stmt->execute([$from, $to]);
    $returnsSummary = $stmt->fetch() ?: $returnsSummary;

    $stmt = db()->prepare(
        "SELECT r.*, i.invoice_number, ii.description AS original_product,
                rp.name_ar AS replacement_name_ar, rp.name_en AS replacement_name_en
         FROM invoice_returns r
         JOIN invoices i ON i.id = r.invoice_id
         JOIN invoice_items ii ON ii.id = r.invoice_item_id
         LEFT JOIN products rp ON rp.id = r.replacement_product_id
         WHERE DATE(r.created_at) BETWEEN ? AND ?
           AND i.invoice_type = 'sale'
         ORDER BY r.created_at DESC
         LIMIT 10"
    );
    $stmt->execute([$from, $to]);
    $recentReturns = $stmt->fetchAll();

    $netSales = (float) $salesSummary['total'] - (float) $returnsSummary['return_total'] + (float) $returnsSummary['exchange_diff'];
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

<?php if ($type === 'sales'): ?>
    <div class="stats-grid">
        <div class="stat-card primary">
            <div class="label"><?= e(__('sales_count')) ?></div>
            <div class="value"><?= (int) ($salesSummary['invoice_count'] ?? 0) ?></div>
        </div>
        <div class="stat-card success">
            <div class="label"><?= e(__('subtotal')) ?></div>
            <div class="value" style="font-size:1.05rem"><?= formatMoney((float) ($salesSummary['subtotal'] ?? 0)) ?></div>
        </div>
        <div class="stat-card warning">
            <div class="label"><?= e(__('discount')) ?></div>
            <div class="value" style="font-size:1.05rem"><?= formatMoney((float) ($salesSummary['discount'] ?? 0)) ?></div>
        </div>
        <div class="stat-card">
            <div class="label"><?= e(__('returned_amount')) ?></div>
            <div class="value" style="font-size:1.05rem"><?= formatMoney((float) ($returnsSummary['return_total'] ?? 0)) ?></div>
        </div>
        <div class="stat-card primary">
            <div class="label"><?= e(__('net_sales_after_returns')) ?></div>
            <div class="value" style="font-size:1.05rem"><?= formatMoney($netSales) ?></div>
        </div>
        <div class="stat-card success">
            <div class="label"><?= e(__('average_invoice')) ?></div>
            <div class="value" style="font-size:1.05rem"><?= formatMoney((float) ($salesSummary['average_total'] ?? 0)) ?></div>
        </div>
    </div>

    <div class="grid-2">
        <div class="card">
            <div class="card-header"><h2><?= e(__('payment_breakdown')) ?></h2></div>
            <div class="card-body table-wrap">
                <?php if (empty($paymentBreakdown)): ?>
                    <p class="text-muted"><?= e(__('no_data')) ?></p>
                <?php else: ?>
                    <table>
                        <thead><tr><th><?= e(__('payment_method')) ?></th><th><?= e(__('invoices')) ?></th><th><?= e(__('total')) ?></th></tr></thead>
                        <tbody>
                            <?php foreach ($paymentBreakdown as $row): ?>
                                <tr>
                                    <td><?= paymentMethodBadge($row['payment_method']) ?></td>
                                    <td><?= (int) $row['invoice_count'] ?></td>
                                    <td><?= formatMoney((float) $row['total']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2><?= e(__('top_products')) ?></h2></div>
            <div class="card-body table-wrap">
                <?php if (empty($topProducts)): ?>
                    <p class="text-muted"><?= e(__('no_data')) ?></p>
                <?php else: ?>
                    <table>
                        <thead><tr><th><?= e(__('product')) ?></th><th><?= e(__('quantity')) ?></th><th><?= e(__('total')) ?></th></tr></thead>
                        <tbody>
                            <?php foreach ($topProducts as $row): ?>
                                <tr>
                                    <td><?= e($row['description']) ?></td>
                                    <td><?= (int) $row['total_qty'] ?></td>
                                    <td><?= formatMoney((float) $row['total_amount']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2><?= e(__('recent_returns')) ?></h2></div>
        <div class="card-body table-wrap">
            <?php if (empty($recentReturns)): ?>
                <p class="text-muted"><?= e(__('no_returns_yet')) ?></p>
            <?php else: ?>
                <table>
                    <thead><tr><th><?= e(__('invoice_number')) ?></th><th><?= e(__('return_action')) ?></th><th><?= e(__('product')) ?></th><th><?= e(__('quantity')) ?></th><th><?= e(__('amount')) ?></th><th><?= e(__('difference_amount')) ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($recentReturns as $row): ?>
                            <?php
                                $replacementName = '';
                                if (!empty($row['replacement_name_ar']) || !empty($row['replacement_name_en'])) {
                                    $replacementName = isRtl()
                                        ? trim((string) ($row['replacement_name_ar'] ?: $row['replacement_name_en']))
                                        : trim((string) ($row['replacement_name_en'] ?: $row['replacement_name_ar']));
                                }
                            ?>
                            <tr>
                                <td><?= e($row['invoice_number']) ?></td>
                                <td><?= e($row['action'] === 'exchange' ? __('exchange_item') : __('return_item')) ?></td>
                                <td>
                                    <?= e($row['original_product']) ?>
                                    <?php if ($replacementName !== ''): ?>
                                        <br><span class="text-muted"><?= e(__('replacement_product')) ?>: <?= e($replacementName) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int) $row['return_quantity'] ?></td>
                                <td><?= formatMoney((float) $row['return_total']) ?></td>
                                <td><?= formatMoney((float) $row['difference_amount']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="card"><div class="card-header"><h2><?= e(__('sales_report')) ?></h2></div><div class="card-body table-wrap">
    <table><thead><tr><th><?= e(__('invoice_number')) ?></th><th><?= e(__('customer')) ?></th><th><?= e(__('payment_method')) ?></th><th><?= e(__('discount')) ?></th><th><?= e(__('total')) ?></th><th><?= e(__('status')) ?></th><th><?= e(__('date')) ?></th></tr></thead>
    <tbody><?php foreach ($sales as $row): ?><tr><td><?= e($row['invoice_number']) ?></td><td><?= e($row['customer_name']) ?></td><td><?= paymentMethodBadge($row['payment_method']) ?></td><td><?= formatMoney((float)$row['discount']) ?></td><td><?= formatMoney((float)$row['total']) ?></td><td><?= statusBadge($row['status']) ?></td><td><?= formatDate($row['created_at']) ?></td></tr><?php endforeach; ?></tbody></table>
    </div></div>
<?php elseif ($type === 'inventory'): ?>
<div class="card"><div class="card-body table-wrap">
    <table><thead><tr><th><?= e(__('sku')) ?></th><th><?= e(__('name')) ?></th><th><?= e(__('category')) ?></th><th><?= e(__('quantity')) ?></th><th><?= e(__('sell_price')) ?></th></tr></thead>
    <tbody><?php foreach ($inventory as $row): ?><tr><td><?= e($row['sku']) ?></td><td><?= e(productName($row)) ?></td><td><?= e($row['category']) ?></td><td class="<?= $row['quantity']<=$row['min_stock']?'low-stock':'' ?>"><?= (int)$row['quantity'] ?></td><td><?= formatMoney((float)$row['sell_price']) ?></td></tr><?php endforeach; ?></tbody></table>
    </div></div>
<?php elseif ($type === 'payroll'): ?>
<div class="card"><div class="card-body table-wrap">
    <table><thead><tr><th>Code</th><th><?= e(__('name')) ?></th><th><?= e(__('net_salary')) ?></th><th><?= e(__('status')) ?></th></tr></thead>
    <tbody><?php foreach ($payroll as $row): ?><tr><td><?= e($row['employee_code']) ?></td><td><?= e(employeeName($row)) ?></td><td><?= formatMoney((float)$row['net_salary']) ?></td><td><?= statusBadge($row['status']) ?></td></tr><?php endforeach; ?></tbody></table>
    </div></div>
<?php else: ?>
<div class="card"><div class="card-body table-wrap">
    <table><thead><tr><th><?= e(__('delivery_number')) ?></th><th><?= e(__('customer')) ?></th><th><?= e(__('driver')) ?></th><th><?= e(__('status')) ?></th><th><?= e(__('date')) ?></th></tr></thead>
    <tbody><?php foreach ($deliveryReport as $row): ?><tr><td><?= e($row['delivery_number']) ?></td><td><?= e($row['customer_name'] ?? '-') ?></td><td><?= e($row['driver_name']) ?></td><td><?= statusBadge($row['status']) ?></td><td><?= formatDate($row['created_at']) ?></td></tr><?php endforeach; ?></tbody></table>
    </div></div>
<?php endif; ?>
<?php if (empty($sales) && empty($inventory) && empty($payroll) && empty($deliveryReport)): ?>
    <?php if ($type !== 'sales'): ?><p class="text-muted"><?= e(__('no_data')) ?></p><?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
