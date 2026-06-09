<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requirePermission(PERM_CUSTOMERS);

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    echo json_encode(['results' => []]);
    exit;
}

$results = [];
foreach (searchCustomers($q, 15) as $row) {
    $results[] = [
        'id' => (int) $row['id'],
        'name' => $row['name'] ?? '',
        'phone' => $row['phone'] ?? '',
        'email' => $row['email'] ?? '',
        'address' => $row['address'] ?? '',
        'rating_avg' => round((float) ($row['rating_avg'] ?? 0), 1),
        'rating_count' => (int) ($row['rating_count'] ?? 0),
        'total_spent' => round((float) ($row['total_spent'] ?? 0), 2),
        'outstanding' => round((float) ($row['outstanding'] ?? 0), 2),
        'invoice_count' => (int) ($row['invoice_count'] ?? 0),
        'is_blocked' => (int) ($row['is_blocked'] ?? 0) === 1,
        'profile_url' => customerProfileUrl((int) $row['id']),
    ];
}

echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE);
