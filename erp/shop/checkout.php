<?php
require_once __DIR__ . '/../includes/functions.php';
ensureShopSchema();
shopCartInit();

$cart = shopCartLines(false);
if ($cart['items'] === []) {
    redirect(shopUrl('cart.php'));
}

$error = '';
$posted = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['customer_name'] ?? '');
    $phone = trim($_POST['customer_phone'] ?? '');
    $email = trim($_POST['customer_email'] ?? '');
    $address = trim($_POST['delivery_address'] ?? '');
    $paymentMethod = normalizeShopPaymentMethod($_POST['payment_method'] ?? 'cod');

    if ($paymentMethod === 'pickup') {
        $address = trim($address) !== '' ? $address : __('shop_pickup_address_default');
    }

    if ($name === '' || $phone === '' || $address === '') {
        $error = __('shop_checkout_required');
    } elseif (!shopValidateCheckoutLines($cart)) {
        redirect(shopUrl('cart.php'));
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $customerId = findOrCreateCustomer($name, $phone, $email !== '' ? $email : null, $address);
            $orderNum = generateNumber('ORD');
            $subtotal = $cart['subtotal'];
            $deliveryFee = $paymentMethod === 'pickup' ? 0.0 : $cart['delivery_fee'];
            $total = round($subtotal + $deliveryFee, 2);

            $pdo->prepare(
                'INSERT INTO orders (order_number, customer_id, customer_name, customer_email, customer_phone, delivery_address, subtotal, total, delivery_fee, payment_method, status, source)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $orderNum,
                $customerId,
                $name,
                $email !== '' ? $email : null,
                $phone,
                $address,
                $subtotal,
                $total,
                $deliveryFee,
                $paymentMethod,
                'new',
                'website',
            ]);
            $orderId = dbLastInsertId($pdo);

            foreach ($cart['items'] as $row) {
                $p = $row['product'];
                $qty = (int) $row['qty'];
                $stockCheck = $pdo->prepare('SELECT quantity FROM products WHERE id = ? AND is_published = 1 FOR UPDATE');
                $stockCheck->execute([(int) $p['id']]);
                $available = (int) $stockCheck->fetchColumn();
                if ($available < $qty) {
                    throw new RuntimeException('insufficient_stock');
                }

                $pdo->prepare(
                    'INSERT INTO order_items (order_id, product_id, description, quantity, unit_price, total)
                     VALUES (?,?,?,?,?,?)'
                )->execute([
                    $orderId,
                    $p['id'],
                    productName($p),
                    $qty,
                    $p['sell_price'],
                    $row['line'],
                ]);
            }

            $pdo->commit();
            $_SESSION['cart'] = [];
            $_SESSION['last_order'] = $orderNum;
            $_SESSION['last_order_phone'] = $phone;
            redirect(shopUrl('success.php'));
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $ex->getMessage() === 'insufficient_stock' ? __('insufficient_stock') : __('shop_checkout_failed');
        }
    }

    $cart = shopCartLines(false);
}

$pageTitle = __('checkout');
$items = $cart['items'];
$subtotal = $cart['subtotal'];
$deliveryFee = $cart['delivery_fee'];
$total = $cart['total'];
$paymentMethods = shopPaymentMethods();

require __DIR__ . '/includes/header.php';
?>
<h1><?= e(__('checkout')) ?></h1>
<?php if ($error !== ''): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<div class="checkout-grid">
<form method="post" class="card checkout-form">
  <h3><?= e(__('customer_info')) ?></h3>
  <div class="form-group"><label><?= e(__('name')) ?> *</label><input name="customer_name" required value="<?= e($posted['customer_name'] ?? '') ?>"></div>
  <div class="form-group"><label><?= e(__('phone')) ?> *</label><input name="customer_phone" required value="<?= e($posted['customer_phone'] ?? '') ?>"></div>
  <div class="form-group"><label><?= e(__('email')) ?></label><input type="email" name="customer_email" value="<?= e($posted['customer_email'] ?? '') ?>"></div>
  <div class="form-group"><label><?= e(__('address')) ?> *</label><textarea name="delivery_address" required><?= e($posted['delivery_address'] ?? '') ?></textarea></div>

  <h3 style="margin-top:1.25rem"><?= e(__('payment_method')) ?></h3>
  <div class="shop-payment-options">
    <?php foreach ($paymentMethods as $value => $label): ?>
      <label class="shop-payment-option">
        <input type="radio" name="payment_method" value="<?= e($value) ?>" <?= ($posted['payment_method'] ?? 'cod') === $value ? 'checked' : '' ?>>
        <span><?= e($label) ?></span>
      </label>
    <?php endforeach; ?>
  </div>

  <button type="submit" class="btn btn-primary" style="margin-top:1.25rem"><?= e(__('place_order')) ?></button>
</form>

<div class="card checkout-summary">
  <h3><?= e(__('order_details')) ?></h3>
  <?php foreach ($items as $row): ?>
  <p><?= e(productName($row['product'])) ?> × <?= $row['qty'] ?> — <?= formatMoney($row['line']) ?></p>
  <?php endforeach; ?>
  <hr style="margin:1rem 0;border:none;border-top:1px solid var(--border)">
  <p><?= e(__('subtotal')) ?>: <strong><?= formatMoney($subtotal) ?></strong></p>
  <p><?= e(__('delivery_fee')) ?>: <strong><?= $deliveryFee > 0 ? formatMoney($deliveryFee) : e(__('shop_free_delivery')) ?></strong></p>
  <p style="margin-top:0.75rem;font-weight:700;font-size:1.1rem"><?= e(__('total')) ?>: <?= formatMoney($total) ?></p>
</div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
