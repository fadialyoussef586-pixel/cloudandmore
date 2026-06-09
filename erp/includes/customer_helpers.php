<?php

function ensureCustomerSchema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo = db();
    $path = BASE_PATH . '/database/migrate_customers_enhanced.sql';
    if (!is_file($path)) {
        return;
    }

    $cols = $pdo->query("SHOW COLUMNS FROM customers LIKE 'notes'")->fetchAll();
    if ($cols === []) {
        runSqlFile($pdo, $path);
    }
}

function normalizeCustomerPhone(string $phone): string
{
    return preg_replace('/\s+/', '', trim($phone)) ?? '';
}

function getCustomer(int $id): ?array
{
    ensureCustomerSchema();
    $stmt = db()->prepare('SELECT * FROM customers WHERE id = ?');
    $stmt->execute([$id]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function findCustomerByPhone(string $phone): ?array
{
    ensureCustomerSchema();
    $phone = normalizeCustomerPhone($phone);
    if ($phone === '') {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM customers WHERE phone = ? LIMIT 1');
    $stmt->execute([$phone]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function findOrCreateCustomer(
    string $name,
    string $phone,
    ?string $email = null,
    ?string $address = null
): int {
    ensureCustomerSchema();

    $name = trim($name);
    $phone = normalizeCustomerPhone($phone);
    $email = $email !== null ? trim($email) : null;
    $address = $address !== null ? trim($address) : null;

    if ($phone !== '') {
        $existing = findCustomerByPhone($phone);
        if ($existing) {
            syncCustomerProfile((int) $existing['id'], $name, $email, $address);

            return (int) $existing['id'];
        }
    }

    if ($name === '') {
        $name = __('walk_in_customer');
    }

    db()->prepare(
        'INSERT INTO customers (name, phone, email, address) VALUES (?, ?, ?, ?)'
    )->execute([
        $name,
        $phone !== '' ? $phone : null,
        $email !== '' ? $email : null,
        $address !== '' ? $address : null,
    ]);

    return dbLastInsertId(db());
}

/** @deprecated Use findOrCreateCustomer() */
function findOrCreateQuickCustomer(string $name, string $phone): int
{
    return findOrCreateCustomer($name, $phone);
}

function syncCustomerProfile(int $customerId, string $name, ?string $email = null, ?string $address = null): void
{
    ensureCustomerSchema();

    $customer = getCustomer($customerId);
    if (!$customer) {
        return;
    }

    $updates = [];
    $params = [];

    if ($name !== '' && $name !== ($customer['name'] ?? '')) {
        $updates[] = 'name = ?';
        $params[] = $name;
    }

    if ($email !== null && $email !== '' && empty($customer['email'])) {
        $updates[] = 'email = ?';
        $params[] = $email;
    }

    if ($address !== null && $address !== '' && empty($customer['address'])) {
        $updates[] = 'address = ?';
        $params[] = $address;
    }

    if ($updates === []) {
        return;
    }

    $params[] = $customerId;
    db()->prepare('UPDATE customers SET ' . implode(', ', $updates) . ' WHERE id = ?')->execute($params);
}

function customerStats(int $customerId, ?PDO $pdo = null): array
{
    ensureCustomerSchema();
    $pdo = $pdo ?: db();
    $customer = getCustomer($customerId);
    if (!$customer) {
        return [
            'invoice_count' => 0,
            'order_count' => 0,
            'maintenance_count' => 0,
            'delivery_count' => 0,
            'total_spent' => 0.0,
            'outstanding' => 0.0,
            'pending_invoices' => 0,
            'first_purchase' => null,
            'last_purchase' => null,
            'avg_invoice' => 0.0,
        ];
    }

    $phone = normalizeCustomerPhone((string) ($customer['phone'] ?? ''));

    $invoiceStmt = $pdo->prepare(
        "SELECT
            COUNT(*) AS invoice_count,
            COALESCE(SUM(CASE WHEN status = 'paid' AND invoice_type = 'sale' THEN total ELSE 0 END), 0) AS total_spent,
            COALESCE(SUM(CASE WHEN status = 'sent' AND invoice_type = 'sale' THEN total ELSE 0 END), 0) AS outstanding,
            COALESCE(SUM(CASE WHEN status = 'sent' AND invoice_type = 'sale' THEN 1 ELSE 0 END), 0) AS pending_invoices,
            MIN(created_at) AS first_purchase,
            MAX(created_at) AS last_purchase
         FROM invoices
         WHERE customer_id = ?"
    );
    $invoiceStmt->execute([$customerId]);
    $invoiceRow = $invoiceStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $orderCount = 0;
    if ($phone !== '') {
        $orderStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM orders WHERE customer_id = ? OR customer_phone = ?'
        );
        $orderStmt->execute([$customerId, $phone]);
        $orderCount = (int) $orderStmt->fetchColumn();
    } else {
        $orderStmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE customer_id = ?');
        $orderStmt->execute([$customerId]);
        $orderCount = (int) $orderStmt->fetchColumn();
    }

    $maintStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM maintenance_tickets WHERE customer_id = ?'
    );
    $maintStmt->execute([$customerId]);
    $maintenanceCount = (int) $maintStmt->fetchColumn();

    $delStmt = $pdo->prepare('SELECT COUNT(*) FROM deliveries WHERE customer_id = ?');
    $delStmt->execute([$customerId]);
    $deliveryCount = (int) $delStmt->fetchColumn();

    $invoiceCount = (int) ($invoiceRow['invoice_count'] ?? 0);
    $totalSpent = round((float) ($invoiceRow['total_spent'] ?? 0), 2);

    return [
        'invoice_count' => $invoiceCount,
        'order_count' => $orderCount,
        'maintenance_count' => $maintenanceCount,
        'delivery_count' => $deliveryCount,
        'total_spent' => $totalSpent,
        'outstanding' => round((float) ($invoiceRow['outstanding'] ?? 0), 2),
        'pending_invoices' => (int) ($invoiceRow['pending_invoices'] ?? 0),
        'first_purchase' => $invoiceRow['first_purchase'] ?? null,
        'last_purchase' => $invoiceRow['last_purchase'] ?? null,
        'avg_invoice' => $invoiceCount > 0 ? round($totalSpent / $invoiceCount, 2) : 0.0,
    ];
}

function customerTier(array $customer, array $stats): array
{
    if ((int) ($customer['is_blocked'] ?? 0) === 1) {
        return ['blocked', 'badge-red', __('cust_tier_blocked')];
    }
    if (($stats['outstanding'] ?? 0) > 0) {
        return ['debt', 'badge-yellow', __('cust_tier_has_balance')];
    }
    if (($stats['total_spent'] ?? 0) >= 3000) {
        return ['vip', 'badge-green', __('cust_tier_vip')];
    }
    if (($stats['invoice_count'] ?? 0) + ($stats['order_count'] ?? 0) <= 1) {
        return ['new', 'badge-blue', __('cust_tier_new')];
    }

    return ['regular', 'badge-gray', __('cust_tier_regular')];
}

function customerRatingStars(float $avg, int $count = 0, bool $showCount = true): string
{
    $avg = max(0.0, min(5.0, $avg));
    $full = (int) floor($avg);
    $half = ($avg - $full) >= 0.5 ? 1 : 0;
    $empty = 5 - $full - $half;

    $html = '<span class="customer-stars" aria-label="' . e((string) round($avg, 1)) . '/5">';
    for ($i = 0; $i < $full; $i++) {
        $html .= '<i class="fa-solid fa-star"></i>';
    }
    if ($half) {
        $html .= '<i class="fa-solid fa-star-half-stroke"></i>';
    }
    for ($i = 0; $i < $empty; $i++) {
        $html .= '<i class="fa-regular fa-star"></i>';
    }
    if ($showCount) {
        $html .= '<span class="customer-stars-count">';
        $html .= $count > 0 ? e((string) round($avg, 1)) . ' (' . $count . ')' : e(__('cust_no_rating'));
        $html .= '</span>';
    }
    $html .= '</span>';

    return $html;
}

function customerRatingSourceLabel(string $source): string
{
    return match ($source) {
        'invoice' => __('invoices'),
        'maintenance' => __('maintenance'),
        'order' => __('orders'),
        default => __('cust_rating_manual'),
    };
}

function recordCustomerRating(
    int $customerId,
    int $score,
    ?string $comment = null,
    string $source = 'manual',
    ?int $referenceId = null,
    ?int $userId = null,
    ?PDO $pdo = null
): void {
    ensureCustomerSchema();
    $score = max(1, min(5, $score));
    $pdo = $pdo ?: db();

    $pdo->prepare(
        'INSERT INTO customer_ratings (customer_id, score, comment, source, reference_id, user_id)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([
        $customerId,
        $score,
        $comment !== null && trim($comment) !== '' ? trim($comment) : null,
        in_array($source, ['manual', 'invoice', 'maintenance', 'order'], true) ? $source : 'manual',
        $referenceId,
        $userId,
    ]);

    refreshCustomerRatingAverage($customerId, $pdo);
}

function refreshCustomerRatingAverage(int $customerId, ?PDO $pdo = null): void
{
    $pdo = $pdo ?: db();
    $stmt = $pdo->prepare(
        'SELECT COALESCE(AVG(score), 0) AS avg_score, COUNT(*) AS cnt FROM customer_ratings WHERE customer_id = ?'
    );
    $stmt->execute([$customerId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['avg_score' => 0, 'cnt' => 0];

    $pdo->prepare('UPDATE customers SET rating_avg = ?, rating_count = ? WHERE id = ?')->execute([
        round((float) $row['avg_score'], 2),
        (int) $row['cnt'],
        $customerId,
    ]);
}

function searchCustomers(string $query, int $limit = 20): array
{
    ensureCustomerSchema();
    $query = trim($query);
    if ($query === '') {
        return [];
    }

    $like = '%' . $query . '%';
    $phone = normalizeCustomerPhone($query);

    $stmt = db()->prepare(
        "SELECT c.*,
                (SELECT COALESCE(SUM(CASE WHEN i.status = 'paid' AND i.invoice_type = 'sale' THEN i.total ELSE 0 END), 0)
                 FROM invoices i WHERE i.customer_id = c.id) AS total_spent,
                (SELECT COALESCE(SUM(CASE WHEN i.status = 'sent' AND i.invoice_type = 'sale' THEN i.total ELSE 0 END), 0)
                 FROM invoices i WHERE i.customer_id = c.id) AS outstanding,
                (SELECT COUNT(*) FROM invoices i WHERE i.customer_id = c.id) AS invoice_count
         FROM customers c
         WHERE c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ? OR c.phone = ?
         ORDER BY c.name ASC
         LIMIT " . max(1, min(50, $limit))
    );
    $stmt->execute([$like, $like, $like, $phone !== '' ? $phone : $query]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function customerInvoices(int $customerId, int $limit = 50): array
{
    $stmt = db()->prepare(
        'SELECT * FROM invoices WHERE customer_id = ? ORDER BY created_at DESC LIMIT ' . max(1, min(100, $limit))
    );
    $stmt->execute([$customerId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function customerOrders(int $customerId, string $phone, int $limit = 50): array
{
    $phone = normalizeCustomerPhone($phone);
    if ($phone !== '') {
        $stmt = db()->prepare(
            'SELECT * FROM orders WHERE customer_id = ? OR customer_phone = ? ORDER BY created_at DESC LIMIT ' . max(1, min(100, $limit))
        );
        $stmt->execute([$customerId, $phone]);
    } else {
        $stmt = db()->prepare(
            'SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC LIMIT ' . max(1, min(100, $limit))
        );
        $stmt->execute([$customerId]);
    }

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function customerMaintenanceTickets(int $customerId, int $limit = 50): array
{
    $stmt = db()->prepare(
        'SELECT * FROM maintenance_tickets WHERE customer_id = ? ORDER BY created_at DESC LIMIT ' . max(1, min(100, $limit))
    );
    $stmt->execute([$customerId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function customerRatings(int $customerId, int $limit = 30): array
{
    ensureCustomerSchema();
    $stmt = db()->prepare(
        'SELECT r.*, u.name AS user_name
         FROM customer_ratings r
         LEFT JOIN users u ON u.id = r.user_id
         WHERE r.customer_id = ?
         ORDER BY r.created_at DESC
         LIMIT ' . max(1, min(100, $limit))
    );
    $stmt->execute([$customerId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function customerProfileUrl(int $customerId): string
{
    return url('customers/view.php?id=' . $customerId);
}

function customerProfileLink(int $customerId, string $label): string
{
    if ($customerId < 1) {
        return e($label);
    }

    return '<a href="' . e(customerProfileUrl($customerId)) . '">' . e($label) . '</a>';
}
