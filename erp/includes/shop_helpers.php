<?php

function ensureShopSchema(): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $pdo = db();
    $path = BASE_PATH . '/database/migrate_shop_enhanced.sql';
    if (is_file($path)) {
        runSqlFile($pdo, $path);
    }

    $columnExists = static function (PDO $pdo, string $table, string $column): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$table, $column]);

        return (int) $stmt->fetchColumn() > 0;
    };

    if (!$columnExists($pdo, 'orders', 'payment_method')) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN payment_method ENUM('cod','transfer','pickup') NOT NULL DEFAULT 'cod' AFTER total");
    }
    if (!$columnExists($pdo, 'orders', 'delivery_fee')) {
        $pdo->exec('ALTER TABLE orders ADD COLUMN delivery_fee DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER payment_method');
    }
    if (!$columnExists($pdo, 'orders', 'invoice_id')) {
        $pdo->exec('ALTER TABLE orders ADD COLUMN invoice_id INT NULL AFTER delivery_fee');
    }

    $done = true;
}

function shopUrl(string $path = ''): string
{
    if ($path === '') {
        return url('shop/index.php');
    }

    return url('shop/' . ltrim($path, '/'));
}

function shopContactPhone(): string
{
    return trim((string) (defined('COMPANY_SUPPORT_PHONE') ? COMPANY_SUPPORT_PHONE : ''));
}

function shopOrderAlertPhone(): string
{
    $phone = defined('SHOP_ORDER_ALERT_PHONE') ? trim((string) SHOP_ORDER_ALERT_PHONE) : '';

    return $phone !== '' ? $phone : shopContactPhone();
}

function shopDeliveryFee(float $subtotal): float
{
    $min = defined('SHOP_FREE_DELIVERY_MIN') ? (float) SHOP_FREE_DELIVERY_MIN : 0.0;
    if ($min > 0 && $subtotal >= $min) {
        return 0.0;
    }

    return round(max(0.0, defined('SHOP_DELIVERY_FEE') ? (float) SHOP_DELIVERY_FEE : 0.0), 2);
}

function shopPaymentMethods(): array
{
    return [
        'cod' => __('shop_pay_cod'),
        'transfer' => __('shop_pay_transfer'),
        'pickup' => __('shop_pay_pickup'),
    ];
}

function normalizeShopPaymentMethod(string $method): string
{
    return in_array($method, ['transfer', 'pickup'], true) ? $method : 'cod';
}

function shopPaymentMethodLabel(string $method): string
{
    $methods = shopPaymentMethods();

    return $methods[$method] ?? $method;
}

function orderPaymentToInvoiceMethod(string $shopMethod): string
{
    return $shopMethod === 'transfer' ? 'transfer' : 'cash';
}

function productDescription(array $product): string
{
    $primary = isRtl() ? ($product['description_ar'] ?? '') : ($product['description_en'] ?? '');
    $fallback = isRtl() ? ($product['description_en'] ?? '') : ($product['description_ar'] ?? '');
    $desc = trim((string) ($primary !== '' ? $primary : $fallback));

    return $desc;
}

function ensureProductImagesSchema(): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $pdo = db();
    $path = BASE_PATH . '/database/migrate_product_images.sql';
    if (is_file($path)) {
        runSqlFile($pdo, $path);
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS product_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            filename VARCHAR(255) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_primary TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            INDEX idx_product_images_product (product_id)
        )'
    );

    migrateLegacyProductImages($pdo);
    $done = true;
}

function migrateLegacyProductImages(?PDO $pdo = null): void
{
    $pdo = $pdo ?: db();
    $rows = $pdo->query(
        "SELECT p.id, p.image
         FROM products p
         WHERE p.image IS NOT NULL AND TRIM(p.image) <> ''
           AND NOT EXISTS (SELECT 1 FROM product_images pi WHERE pi.product_id = p.id)"
    )->fetchAll(PDO::FETCH_ASSOC);

    $insert = $pdo->prepare(
        'INSERT INTO product_images (product_id, filename, sort_order, is_primary) VALUES (?,?,?,1)'
    );
    foreach ($rows as $row) {
        $insert->execute([(int) $row['id'], $row['image'], 0]);
    }
}

function productImages(int $productId): array
{
    ensureProductImagesSchema();

    $stmt = db()->prepare(
        'SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC'
    );
    $stmt->execute([$productId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function productImageFilenameUrl(string $filename): string
{
    $file = BASE_PATH . '/uploads/products/' . $filename;
    if ($filename !== '' && is_file($file)) {
        return url('uploads/products/' . rawurlencode($filename));
    }

    return asset('img/product-placeholder.svg');
}

function productImageUrl(?array $product): string
{
    if (!empty($product['id'])) {
        $images = productImages((int) $product['id']);
        if ($images !== []) {
            return productImageFilenameUrl((string) $images[0]['filename']);
        }
    }

    if (!empty($product['image'])) {
        return productImageFilenameUrl((string) $product['image']);
    }

    return asset('img/product-placeholder.svg');
}

function productImageUrlsForProduct(int $productId, ?array $product = null): array
{
    $urls = [];
    foreach (productImages($productId) as $row) {
        $urls[] = [
            'id' => (int) $row['id'],
            'url' => productImageFilenameUrl((string) $row['filename']),
            'filename' => (string) $row['filename'],
            'is_primary' => !empty($row['is_primary']),
        ];
    }

    if ($urls === [] && $product !== null && !empty($product['image'])) {
        $urls[] = [
            'id' => 0,
            'url' => productImageFilenameUrl((string) $product['image']),
            'filename' => (string) $product['image'],
            'is_primary' => true,
        ];
    }

    return $urls;
}

function normalizeUploadedFilesArray(array $files): array
{
    if (!isset($files['name']) || !is_array($files['name'])) {
        if (($files['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [];
        }

        return [$files];
    }

    $normalized = [];
    foreach ($files['name'] as $index => $name) {
        $normalized[] = [
            'name' => $name,
            'type' => $files['type'][$index] ?? '',
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$index] ?? 0,
        ];
    }

    return $normalized;
}

function saveProductImage(array $file, string $sku, ?string $suffix = null): ?string
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

    $safeSku = preg_replace('/[^a-zA-Z0-9_-]/', '', $sku);
    $unique = $suffix !== null ? $suffix : (time() . '_' . bin2hex(random_bytes(3)));
    $filename = $safeSku . '_' . $unique . '.' . $allowed[$mime];
    if (move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
        return $filename;
    }

    return null;
}

function syncProductCoverImage(int $productId, ?PDO $pdo = null): void
{
    ensureProductImagesSchema();
    $pdo = $pdo ?: db();

    $stmt = $pdo->prepare(
        'SELECT filename FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC LIMIT 1'
    );
    $stmt->execute([$productId]);
    $filename = $stmt->fetchColumn();

    $pdo->prepare('UPDATE products SET image = ? WHERE id = ?')->execute([
        $filename !== false && $filename !== '' ? $filename : null,
        $productId,
    ]);
}

function addProductImagesFromUploads(array $files, string $sku, int $productId, ?PDO $pdo = null): int
{
    ensureProductImagesSchema();
    $pdo = $pdo ?: db();
    $uploads = normalizeUploadedFilesArray($files);
    if ($uploads === []) {
        return 0;
    }

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM product_images WHERE product_id = ?');
    $countStmt->execute([$productId]);
    $existingCount = (int) $countStmt->fetchColumn();

    $insert = $pdo->prepare(
        'INSERT INTO product_images (product_id, filename, sort_order, is_primary) VALUES (?,?,?,?)'
    );

    $added = 0;
    foreach ($uploads as $index => $file) {
        $filename = saveProductImage($file, $sku, time() . '_' . ($existingCount + $index));
        if ($filename === null) {
            continue;
        }

        $isPrimary = ($existingCount + $added) === 0 ? 1 : 0;
        $insert->execute([$productId, $filename, $existingCount + $added, $isPrimary]);
        $added++;
    }

    if ($added > 0) {
        syncProductCoverImage($productId, $pdo);
    }

    return $added;
}

function deleteProductImage(int $imageId, int $productId): void
{
    ensureProductImagesSchema();
    $pdo = db();

    $stmt = $pdo->prepare('SELECT * FROM product_images WHERE id = ? AND product_id = ?');
    $stmt->execute([$imageId, $productId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return;
    }

    $wasPrimary = !empty($row['is_primary']);
    $path = BASE_PATH . '/uploads/products/' . $row['filename'];
    if (is_file($path)) {
        @unlink($path);
    }

    $pdo->prepare('DELETE FROM product_images WHERE id = ?')->execute([$imageId]);

    if ($wasPrimary) {
        $next = $pdo->prepare('SELECT id FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC LIMIT 1');
        $next->execute([$productId]);
        $nextId = (int) $next->fetchColumn();
        if ($nextId > 0) {
            setPrimaryProductImage($nextId, $productId);
        } else {
            syncProductCoverImage($productId, $pdo);
        }
    } else {
        syncProductCoverImage($productId, $pdo);
    }
}

function setPrimaryProductImage(int $imageId, int $productId): void
{
    ensureProductImagesSchema();
    $pdo = db();

    $stmt = $pdo->prepare('SELECT id FROM product_images WHERE id = ? AND product_id = ?');
    $stmt->execute([$imageId, $productId]);
    if (!$stmt->fetchColumn()) {
        return;
    }

    $pdo->prepare('UPDATE product_images SET is_primary = 0 WHERE product_id = ?')->execute([$productId]);
    $pdo->prepare('UPDATE product_images SET is_primary = 1 WHERE id = ?')->execute([$imageId]);
    syncProductCoverImage($productId, $pdo);
}

function publishedProductsQuery(): string
{
    return 'SELECT * FROM products WHERE is_published = 1 AND quantity > 0 ORDER BY created_at DESC';
}

function shopCartInit(): void
{
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
}

function shopFetchPublishedProduct(int $productId): ?array
{
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ? AND is_published = 1 AND quantity > 0 LIMIT 1');
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    return $product ?: null;
}

function shopCartLines(bool $adjustToStock = true): array
{
    shopCartInit();
    $items = [];
    $subtotal = 0.0;

    foreach ($_SESSION['cart'] as $pid => $qty) {
        $productId = (int) $pid;
        $requestedQty = max(0, (int) $qty);
        if ($productId < 1 || $requestedQty < 1) {
            unset($_SESSION['cart'][$productId]);
            continue;
        }

        $product = shopFetchPublishedProduct($productId);
        if (!$product) {
            unset($_SESSION['cart'][$productId]);
            continue;
        }

        $available = (int) ($product['quantity'] ?? 0);
        $finalQty = $adjustToStock ? min($requestedQty, $available) : $requestedQty;
        if ($finalQty < 1) {
            unset($_SESSION['cart'][$productId]);
            continue;
        }

        if ($adjustToStock && $finalQty !== $requestedQty) {
            $_SESSION['cart'][$productId] = $finalQty;
        }

        $line = round((float) $product['sell_price'] * $finalQty, 2);
        $items[] = [
            'product' => $product,
            'qty' => $finalQty,
            'line' => $line,
        ];
        $subtotal += $line;
    }

    $subtotal = round($subtotal, 2);
    $deliveryFee = shopDeliveryFee($subtotal);

    return [
        'items' => $items,
        'subtotal' => $subtotal,
        'delivery_fee' => $deliveryFee,
        'total' => round($subtotal + $deliveryFee, 2),
    ];
}

function shopAddToCart(int $productId, int $qty): bool
{
    shopCartInit();
    $product = shopFetchPublishedProduct($productId);
    if (!$product) {
        flash('error', __('shop_product_unavailable'));

        return false;
    }

    $qty = max(1, $qty);
    $available = (int) ($product['quantity'] ?? 0);
    $current = (int) ($_SESSION['cart'][$productId] ?? 0);
    $next = $current + $qty;

    if ($next > $available) {
        flash('error', __('insufficient_stock'));

        return false;
    }

    $_SESSION['cart'][$productId] = $next;
    flash('success', __('success_saved'));

    return true;
}

function shopUpdateCartQuantities(array $quantities): void
{
    shopCartInit();

    foreach ($quantities as $id => $qty) {
        $productId = (int) $id;
        $qty = (int) $qty;
        if ($productId < 1) {
            continue;
        }

        if ($qty <= 0) {
            unset($_SESSION['cart'][$productId]);
            continue;
        }

        $product = shopFetchPublishedProduct($productId);
        if (!$product) {
            unset($_SESSION['cart'][$productId]);
            continue;
        }

        $available = (int) ($product['quantity'] ?? 0);
        if ($qty > $available) {
            flash('error', __('insufficient_stock'));
            $qty = $available;
        }

        if ($qty > 0) {
            $_SESSION['cart'][$productId] = $qty;
        } else {
            unset($_SESSION['cart'][$productId]);
        }
    }
}

function shopValidateCheckoutLines(array $cart): bool
{
    foreach ($cart['items'] as $row) {
        $product = $row['product'];
        $qty = (int) $row['qty'];
        if ($qty < 1 || $qty > (int) ($product['quantity'] ?? 0)) {
            flash('error', __('insufficient_stock'));

            return false;
        }
    }

    return $cart['items'] !== [];
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

function orderStockWasDeducted(string $status): bool
{
    return in_array($status, ['confirmed', 'ready_for_delivery', 'out_for_delivery', 'delivered'], true);
}

function restockOrderItems(int $orderId, ?PDO $pdo = null): void
{
    $pdo = $pdo ?: db();
    $items = $pdo->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ? AND product_id IS NOT NULL');
    $items->execute([$orderId]);
    foreach ($items->fetchAll() as $item) {
        $pdo->prepare('UPDATE products SET quantity = quantity + ? WHERE id = ?')
            ->execute([(int) $item['quantity'], (int) $item['product_id']]);
    }
}

function deductOrderStock(int $orderId, ?PDO $pdo = null): void
{
    $pdo = $pdo ?: db();
    $items = $pdo->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ? AND product_id IS NOT NULL');
    $items->execute([$orderId]);
    $update = $pdo->prepare('UPDATE products SET quantity = quantity - ? WHERE id = ? AND quantity >= ?');
    foreach ($items->fetchAll() as $item) {
        $qty = (int) $item['quantity'];
        $productId = (int) $item['product_id'];
        $update->execute([$qty, $productId, $qty]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('insufficient_stock');
        }
    }
}

function markOrderPickupComplete(int $orderId, ?PDO $pdo = null): void
{
    $pdo = $pdo ?: db();
    $stmt = $pdo->prepare(
        "UPDATE orders SET status = 'delivered', delivered_at = NOW()
         WHERE id = ? AND status = 'confirmed' AND payment_method = 'pickup'"
    );
    $stmt->execute([$orderId]);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('order_not_found');
    }
}

function orderNeedsDelivery(array $order): bool
{
    return ($order['payment_method'] ?? 'cod') !== 'pickup';
}

function cancelOrder(int $orderId, ?int $userId = null, ?PDO $pdo = null): void
{
    ensureShopSchema();
    $pdo = $pdo ?: db();
    $started = false;

    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $started = true;
    }

    try {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            throw new RuntimeException('order_not_found');
        }

        $status = (string) ($order['status'] ?? '');
        if ($status === 'delivered' || $status === 'cancelled') {
            throw new RuntimeException('order_cannot_cancel');
        }

        if (orderStockWasDeducted($status)) {
            restockOrderItems($orderId, $pdo);
        }

        $note = trim((string) ($order['notes'] ?? ''));
        $cancelNote = __('order_cancelled_note');
        $notes = $note !== '' ? $note . ' | ' . $cancelNote : $cancelNote;

        $pdo->prepare("UPDATE orders SET status = 'cancelled', notes = ? WHERE id = ?")
            ->execute([$notes, $orderId]);

        if ($started) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function createInvoiceFromOrder(int $orderId, ?int $userId = null, ?PDO $pdo = null): int
{
    ensureShopSchema();
    ensureInvoiceSchema();
    $pdo = $pdo ?: db();
    $started = false;

    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $started = true;
    }

    try {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            throw new RuntimeException('order_not_found');
        }

        if (!empty($order['invoice_id'])) {
            if ($started) {
                $pdo->commit();
            }

            return (int) $order['invoice_id'];
        }

        if (!orderStockWasDeducted((string) ($order['status'] ?? ''))) {
            throw new RuntimeException('order_not_confirmed');
        }

        $itemsStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC');
        $itemsStmt->execute([$orderId]);
        $orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        if ($orderItems === []) {
            throw new RuntimeException('order_items_missing');
        }

        $subtotal = round((float) ($order['subtotal'] ?? 0), 2);
        $deliveryFee = round((float) ($order['delivery_fee'] ?? 0), 2);
        $total = round((float) ($order['total'] ?? 0), 2);
        $totals = invoiceTotalsFromLines($subtotal + $deliveryFee, 0);
        $totals['total'] = $total;
        $totals['subtotal'] = $subtotal + $deliveryFee;

        $invNum = generateNumber('INV');
        $paymentMethod = orderPaymentToInvoiceMethod((string) ($order['payment_method'] ?? 'cod'));
        $customerId = (int) ($order['customer_id'] ?? 0);
        if ($customerId < 1) {
            $customerId = findOrCreateCustomer(
                (string) ($order['customer_name'] ?? ''),
                (string) ($order['customer_phone'] ?? ''),
                $order['customer_email'] ?? null,
                (string) ($order['delivery_address'] ?? '')
            );
        }

        $notes = __('shop_order_invoice_note') . ' ' . ($order['order_number'] ?? '');

        $pdo->prepare(
            'INSERT INTO invoices (invoice_number, customer_id, subtotal, tax_rate, tax_amount, discount, total, status, payment_method, invoice_type, notes, user_id)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $invNum,
            $customerId,
            $totals['subtotal'],
            $totals['tax_rate'],
            $totals['tax_amount'],
            $totals['discount'],
            $totals['total'],
            'sent',
            $paymentMethod,
            'sale',
            $notes,
            $userId,
        ]);
        $invoiceId = dbLastInsertId($pdo);

        $insertItem = $pdo->prepare(
            'INSERT INTO invoice_items (invoice_id, product_id, description, serial_number, quantity, unit_price, total)
             VALUES (?,?,?,?,?,?,?)'
        );

        foreach ($orderItems as $item) {
            $insertItem->execute([
                $invoiceId,
                $item['product_id'],
                $item['description'],
                'WEB-' . ($order['order_number'] ?? '') . '-' . ($item['id'] ?? 0),
                (int) $item['quantity'],
                (float) $item['unit_price'],
                (float) $item['total'],
            ]);
        }

        if ($deliveryFee > 0) {
            $insertItem->execute([
                $invoiceId,
                null,
                __('delivery_fee'),
                'WEB-DEL-' . ($order['order_number'] ?? ''),
                1,
                $deliveryFee,
                $deliveryFee,
            ]);
        }

        $pdo->prepare('UPDATE orders SET invoice_id = ? WHERE id = ?')->execute([$invoiceId, $orderId]);

        if ($started) {
            $pdo->commit();
        }

        return $invoiceId;
    } catch (Throwable $e) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function fetchOrderItems(int $orderId): array
{
    $stmt = db()->prepare(
        'SELECT oi.*, p.image, p.name_ar, p.name_en
         FROM order_items oi
         LEFT JOIN products p ON p.id = oi.product_id
         WHERE oi.order_id = ?
         ORDER BY oi.id ASC'
    );
    $stmt->execute([$orderId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function lookupOrderByNumberAndPhone(string $orderNumber, string $phone): ?array
{
    ensureShopSchema();
    $orderNumber = trim($orderNumber);
    $phone = preg_replace('/\s+/', '', trim($phone));
    if ($orderNumber === '' || $phone === '') {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT * FROM orders
         WHERE order_number = ?
           AND REPLACE(REPLACE(customer_phone, " ", ""), "-", "") = REPLACE(REPLACE(?, " ", ""), "-", "")
         LIMIT 1'
    );
    $stmt->execute([$orderNumber, $phone]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    return $order ?: null;
}

function buildOrderWhatsAppMessage(array $order, array $items, bool $forCustomer = false): string
{
    $lines = [
        '*Cloud and More*',
        $forCustomer ? __('shop_order_update_whatsapp') : __('shop_new_order_whatsapp'),
        '',
        __('order_number') . ': ' . ($order['order_number'] ?? ''),
        __('customer') . ': ' . ($order['customer_name'] ?? ''),
        __('phone') . ': ' . ($order['customer_phone'] ?? ''),
        __('address') . ': ' . ($order['delivery_address'] ?? ''),
        __('payment_method') . ': ' . shopPaymentMethodLabel((string) ($order['payment_method'] ?? 'cod')),
        __('status') . ': ' . __($order['status'] ?? 'new'),
        '',
        '*' . __('items') . '*',
    ];

    foreach ($items as $index => $item) {
        $lines[] = ($index + 1) . '. ' . ($item['description'] ?? '-')
            . ' × ' . (int) ($item['quantity'] ?? 0)
            . ' — ' . formatMoneyPlain((float) ($item['total'] ?? 0));
    }

    $lines[] = '';
    if ((float) ($order['delivery_fee'] ?? 0) > 0) {
        $lines[] = __('delivery_fee') . ': ' . formatMoneyPlain((float) $order['delivery_fee']);
    }
    $lines[] = '*' . __('total') . ': ' . formatMoneyPlain((float) ($order['total'] ?? 0)) . '*';

    if (!$forCustomer) {
        $lines[] = '';
        $lines[] = shopUrl('track.php');
    }

    return implode("\n", $lines);
}

function orderWhatsAppShareUrl(?string $phone, string $message): ?string
{
    $normalized = normalizeWhatsAppPhone($phone ?? '');
    if ($normalized === null || trim($message) === '') {
        return null;
    }

    return 'https://wa.me/' . $normalized . '?text=' . rawurlencode($message);
}

function orderWhatsAppLinkFor(array $order, ?array $items = null, ?string $phone = null, bool $forCustomer = true): ?string
{
    if ($items === null) {
        $items = fetchOrderItems((int) ($order['id'] ?? 0));
    }

    $targetPhone = $phone ?? ($forCustomer ? ($order['customer_phone'] ?? '') : shopOrderAlertPhone());
    $message = buildOrderWhatsAppMessage($order, $items, $forCustomer);

    return orderWhatsAppShareUrl($targetPhone, $message);
}

function orderWhatsAppButton(array $order, ?array $items = null, string $class = 'btn btn-whatsapp btn-sm', bool $forCustomer = true): string
{
    $url = orderWhatsAppLinkFor($order, $items, null, $forCustomer);
    if ($url === null) {
        return '';
    }

    $label = $forCustomer ? __('send_whatsapp') : __('shop_alert_owner_whatsapp');

    return '<a href="' . e($url) . '" class="' . e($class) . '" target="_blank" rel="noopener">'
        . faIcon('fa-brands fa-whatsapp', 'fa-btn-icon')
        . e($label)
        . '</a>';
}

function createDeliveryFromOrder(int $orderId, int $userId): void
{
    $order = db()->prepare('SELECT * FROM orders WHERE id = ?');
    $order->execute([$orderId]);
    $order = $order->fetch();
    if (!$order || !orderNeedsDelivery($order)) {
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
    $deliveryId = dbLastInsertId(db());
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
