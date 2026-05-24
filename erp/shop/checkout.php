<?php
require_once __DIR__ . '/../includes/functions.php';
if (empty($_SESSION['cart'])) redirect(shopUrl('cart.php'));

$items = []; $total = 0;
foreach ($_SESSION['cart'] as $pid => $qty) {
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ? AND is_published = 1 AND quantity >= ?');
    $stmt->execute([(int)$pid, (int)$qty]);
    if ($p = $stmt->fetch()) {
        $line = (float)$p['sell_price'] * $qty;
        $items[] = ['product' => $p, 'qty' => $qty, 'line' => $line];
        $total += $line;
    }
}
if (empty($items)) { $_SESSION['cart'] = []; redirect(shopUrl('cart.php')); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['customer_name'] ?? '');
    $phone = trim($_POST['customer_phone'] ?? '');
    $email = trim($_POST['customer_email'] ?? '');
    $address = trim($_POST['delivery_address'] ?? '');
    if ($name && $phone && $address) {
        $pdo = db(); $pdo->beginTransaction();
        try {
            $customerId = null;
            if ($email) {
                $c = $pdo->prepare('SELECT id FROM customers WHERE email = ?');
                $c->execute([$email]);
                $customerId = $c->fetchColumn();
            }
            if (!$customerId) {
                $pdo->prepare('INSERT INTO customers (name, email, phone, address) VALUES (?,?,?,?)')->execute([$name, $email ?: null, $phone, $address]);
                $customerId = dbLastInsertId($pdo);
            }
            $orderNum = generateNumber('ORD');
            $pdo->prepare('INSERT INTO orders (order_number, customer_id, customer_name, customer_email, customer_phone, delivery_address, subtotal, total, status, source) VALUES (?,?,?,?,?,?,?,?,?,?)')
                ->execute([$orderNum, $customerId, $name, $email ?: null, $phone, $address, $total, $total, 'new', 'website']);
            $orderId = dbLastInsertId($pdo);
            foreach ($items as $row) {
                $p = $row['product'];
                $pdo->prepare('INSERT INTO order_items (order_id, product_id, description, quantity, unit_price, total) VALUES (?,?,?,?,?,?)')
                    ->execute([$orderId, $p['id'], productName($p), $row['qty'], $p['sell_price'], $row['line']]);
            }
            $pdo->commit();
            $_SESSION['cart'] = [];
            $_SESSION['last_order'] = $orderNum;
            redirect(shopUrl('success.php'));
        } catch (Exception $ex) {
            $pdo->rollBack();
        }
    }
}

$pageTitle = __('checkout');
require __DIR__ . '/includes/header.php';
?>
<h1><?= e(__('checkout')) ?></h1>
<div class="checkout-grid">
<form method="post" class="card" style="padding:1.25rem">
  <h3><?= e(__('customer_info')) ?></h3>
  <div class="form-group"><label><?= e(__('name')) ?></label><input name="customer_name" required></div>
  <div class="form-group"><label><?= e(__('phone')) ?></label><input name="customer_phone" required></div>
  <div class="form-group"><label><?= e(__('email')) ?></label><input type="email" name="customer_email"></div>
  <div class="form-group"><label><?= e(__('address')) ?></label><textarea name="delivery_address" required></textarea></div>
  <button type="submit" class="btn btn-primary"><?= e(__('place_order')) ?></button>
</form>
<div class="card" style="padding:1.25rem">
  <h3><?= e(__('order_details')) ?></h3>
  <?php foreach ($items as $row): ?>
  <p><?= e(productName($row['product'])) ?> × <?= $row['qty'] ?> — <?= formatMoney($row['line']) ?></p>
  <?php endforeach; ?>
  <p style="margin-top:1rem;font-weight:700"><?= e(__('total')) ?>: <?= formatMoney($total) ?></p>
</div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
