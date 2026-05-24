<?php

function shopUrl(string $path = ''): string
{
    if ($path === '') {
        return url('shop/index.php');
    }
    return url('shop/' . ltrim($path, '/'));
}

function productImageUrl(?array $product): string
{
    if (!empty($product['image'])) {
        $file = BASE_PATH . '/uploads/products/' . $product['image'];
        if (is_file($file)) {
            return url('uploads/products/' . rawurlencode($product['image']));
        }
    }
    return asset('img/product-placeholder.svg');
}

function saveProductImage(array $file, string $sku): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = mime_content_type($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        return null;
    }

    $dir = BASE_PATH . '/uploads/products';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '', $sku) . '_' . time() . '.' . $allowed[$mime];
    if (move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
        return $filename;
    }

    return null;
}

function publishedProductsQuery(): string
{
    return "SELECT * FROM products WHERE is_published = 1 AND quantity > 0 ORDER BY created_at DESC";
}

function orderStatusBadge(string $status): string
{
    $map = [
        'new' => 'badge-yellow',
        'confirmed' => 'badge-blue',
        'ready_for_delivery' => 'badge-blue',
        'out_for_delivery' => 'badge-yellow',
        'delivered' => 'badge-green',
        'cancelled' => 'badge-red',
    ];
    $class = $map[$status] ?? 'badge-gray';
    return '<span class="badge ' . $class . '">' . e(__($status)) . '</span>';
}

function deductOrderStock(int $orderId): void
{
    $items = db()->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ? AND product_id IS NOT NULL');
    $items->execute([$orderId]);
    foreach ($items->fetchAll() as $item) {
        db()->prepare('UPDATE products SET quantity = CASE WHEN quantity - ? < 0 THEN 0 ELSE quantity - ? END WHERE id = ?')
            ->execute([(int) $item['quantity'], (int) $item['quantity'], (int) $item['product_id']]);
    }
}

function createDeliveryFromOrder(int $orderId, int $userId): void
{
    $order = db()->prepare('SELECT * FROM orders WHERE id = ?');
    $order->execute([$orderId]);
    $order = $order->fetch();
    if (!$order) {
        return;
    }

    $existing = db()->prepare('SELECT id FROM deliveries WHERE order_id = ?');
    $existing->execute([$orderId]);
    if ($existing->fetch()) {
        return;
    }

    $num = generateNumber('DEL');
    db()->prepare('INSERT INTO deliveries (delivery_number, customer_id, order_id, delivery_address, status, scheduled_date, user_id) VALUES (?,?,?,?,?,?,?)')
        ->execute([$num, $order['customer_id'], $orderId, $order['delivery_address'], 'pending', date('Y-m-d'), $userId]);

    $deliveryId = (int) db()->lastInsertId();
    $items = db()->prepare('SELECT * FROM order_items WHERE order_id = ?');
    $items->execute([$orderId]);
    foreach ($items->fetchAll() as $item) {
        db()->prepare('INSERT INTO delivery_items (delivery_id, product_id, description, quantity) VALUES (?,?,?,?)')
            ->execute([$deliveryId, $item['product_id'], $item['description'], $item['quantity']]);
    }
}

function homeUrlForRole(?string $role): string
{
    return match ($role) {
        'driver' => url('driver/index.php'),
        'sales' => url('orders/sales.php'),
        default => url('index.php'),
    };
}
