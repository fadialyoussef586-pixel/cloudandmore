<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requirePermission(PERM_CUSTOMERS);

ensureCustomerSchema();

$pageTitle = __('customers');
$search = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? 'all';

$sql = 'SELECT c.* FROM customers c WHERE 1=1';
$params = [];

if ($search !== '') {
    $sql .= ' AND (c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)';
    $like = '%' . $search . '%';
    $params = [$like, $like, $like];
}

if ($filter === 'outstanding') {
    $sql .= " AND EXISTS (
        SELECT 1 FROM invoices i
        WHERE i.customer_id = c.id AND i.status = 'sent' AND i.invoice_type = 'sale' AND i.total > 0
    )";
} elseif ($filter === 'vip') {
    $sql .= " AND EXISTS (
        SELECT 1 FROM invoices i
        WHERE i.customer_id = c.id AND i.status = 'paid' AND i.invoice_type = 'sale'
        GROUP BY i.customer_id
        HAVING COALESCE(SUM(i.total), 0) >= 3000
    )";
} elseif ($filter === 'blocked') {
    $sql .= ' AND c.is_blocked = 1';
}

$sql .= ' ORDER BY c.name ASC LIMIT 500';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

$rows = [];
$totalOutstanding = 0.0;
foreach ($customers as $customer) {
    $stats = customerStats((int) $customer['id']);
    $tier = customerTier($customer, $stats);
    $rows[] = [
        'customer' => $customer,
        'stats' => $stats,
        'tier' => $tier,
    ];
    $totalOutstanding += $stats['outstanding'];
}

require __DIR__ . '/../includes/header.php';
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="label"><?= e(__('customers')) ?></div>
        <div class="value"><?= count($rows) ?></div>
    </div>
    <div class="stat-card warning">
        <div class="label"><?= e(__('cust_total_outstanding')) ?></div>
        <div class="value"><?= formatMoney($totalOutstanding) ?></div>
    </div>
</div>

<div class="page-actions">
    <form method="get" class="search-inline-form">
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="<?= e(__('cust_search_placeholder')) ?>">
        <select name="filter">
            <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>><?= e(__('cust_filter_all')) ?></option>
            <option value="outstanding" <?= $filter === 'outstanding' ? 'selected' : '' ?>><?= e(__('cust_filter_outstanding')) ?></option>
            <option value="vip" <?= $filter === 'vip' ? 'selected' : '' ?>><?= e(__('cust_filter_vip')) ?></option>
            <option value="blocked" <?= $filter === 'blocked' ? 'selected' : '' ?>><?= e(__('cust_filter_blocked')) ?></option>
        </select>
        <button type="submit" class="btn btn-secondary"><?= e(__('search')) ?></button>
    </form>
    <a href="<?= url('customers/create.php') ?>" class="btn btn-primary"><?= e(__('cust_new_customer')) ?></a>
</div>

<div class="card">
    <div class="card-body table-wrap">
        <?php if ($rows === []): ?>
            <p class="text-muted"><?= e(__('no_data')) ?></p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th><?= e(__('name')) ?></th>
                        <th><?= e(__('phone')) ?></th>
                        <th><?= e(__('cust_rating')) ?></th>
                        <th><?= e(__('cust_total_spent')) ?></th>
                        <th><?= e(__('cust_outstanding')) ?></th>
                        <th><?= e(__('invoices')) ?></th>
                        <th><?= e(__('orders')) ?></th>
                        <th><?= e(__('cust_tier')) ?></th>
                        <th><?= e(__('actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $customer = $row['customer'];
                        $stats = $row['stats'];
                        [$tierKey, $tierClass, $tierLabel] = $row['tier'];
                        ?>
                        <tr>
                            <td>
                                <a href="<?= url('customers/view.php?id=' . $customer['id']) ?>"><?= e($customer['name']) ?></a>
                            </td>
                            <td><?= e($customer['phone'] ?? '-') ?></td>
                            <td><?= customerRatingStars((float) ($customer['rating_avg'] ?? 0), (int) ($customer['rating_count'] ?? 0)) ?></td>
                            <td><?= formatMoney((float) $stats['total_spent']) ?></td>
                            <td>
                                <?php if ($stats['outstanding'] > 0): ?>
                                    <strong class="text-warning"><?= formatMoney((float) $stats['outstanding']) ?></strong>
                                <?php else: ?>
                                    <?= formatMoney(0) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= (int) $stats['invoice_count'] ?></td>
                            <td><?= (int) $stats['order_count'] ?></td>
                            <td><span class="badge <?= e($tierClass) ?>"><?= e($tierLabel) ?></span></td>
                            <td>
                                <a href="<?= url('customers/view.php?id=' . $customer['id']) ?>" class="btn btn-secondary btn-sm"><?= e(__('view')) ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
