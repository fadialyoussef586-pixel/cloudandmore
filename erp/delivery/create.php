<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requirePermission(PERM_DELIVERY);
$pageTitle = __('create_delivery');
$customers = db()->query('SELECT * FROM customers ORDER BY name')->fetchAll();
$invoices = db()->query("SELECT id, invoice_number FROM invoices ORDER BY created_at DESC LIMIT 20")->fetchAll();
$products = db()->query('SELECT id, sku, name_ar, name_en FROM products ORDER BY name_en')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $num = generateNumber('DEL');
        $pdo->prepare('INSERT INTO deliveries (delivery_number, customer_id, invoice_id, driver_name, vehicle_number, delivery_address, status, scheduled_date, notes, user_id) VALUES (?,?,?,?,?,?,?,?,?,?)')
            ->execute([
                $num, (int)($_POST['customer_id'] ?: 0) ?: null, (int)($_POST['invoice_id'] ?: 0) ?: null,
                trim($_POST['driver_name'] ?? ''), trim($_POST['vehicle_number'] ?? ''),
                trim($_POST['delivery_address']), $_POST['status'] ?? 'pending',
                $_POST['scheduled_date'] ?: null, trim($_POST['notes'] ?? ''), $_SESSION['user_id']
            ]);
        $delId = dbLastInsertId($pdo);
        foreach ($_POST['items'] ?? [] as $item) {
            if (empty($item['description'])) continue;
            $pdo->prepare('INSERT INTO delivery_items (delivery_id, product_id, description, quantity) VALUES (?,?,?,?)')
                ->execute([$delId, (int)($item['product_id'] ?: 0) ?: null, $item['description'], (int)$item['quantity']]);
        }
        $pdo->commit();
        flash('success', __('success_saved'));
        redirect(url('delivery/view.php?id=' . $delId));
    } catch (Exception $e) {
        $pdo->rollBack();
        flash('error', __('error'));
    }
}

require __DIR__ . '/../includes/header.php';
$options = '';
foreach ($products as $p) {
    $options .= '<option value="'.$p['id'].'">'.htmlspecialchars(productName($p)).'</option>';
}
?>
<script>window.productsOptions = `<?= $options ?>`;</script>
<div class="card"><div class="card-body">
<form method="post">
<div class="form-grid">
<div class="form-group"><label><?= e(__('customer')) ?></label><select name="customer_id"><option value="">--</option><?php foreach ($customers as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
<div class="form-group"><label><?= e(__('invoice_number')) ?></label><select name="invoice_id"><option value="">--</option><?php foreach ($invoices as $i): ?><option value="<?= $i['id'] ?>"><?= e($i['invoice_number']) ?></option><?php endforeach; ?></select></div>
<div class="form-group"><label><?= e(__('driver')) ?></label><input name="driver_name"></div>
<div class="form-group"><label><?= e(__('vehicle')) ?></label><input name="vehicle_number"></div>
<div class="form-group"><label><?= e(__('scheduled_date')) ?></label><input type="date" name="scheduled_date"></div>
<div class="form-group"><label><?= e(__('status')) ?></label><select name="status"><option value="pending"><?= e(__('pending')) ?></option><option value="in_transit"><?= e(__('in_transit')) ?></option></select></div>
<div class="form-group" style="grid-column:1/-1"><label><?= e(__('address')) ?></label><textarea name="delivery_address" required></textarea></div>
</div>
<h3><?= e(__('items')) ?></h3>
<table id="deliveryItems"><thead><tr><th><?= e(__('product')) ?></th><th><?= e(__('description')) ?></th><th><?= e(__('quantity')) ?></th><th></th></tr></thead>
<tbody><tr>
<td><select name="items[0][product_id]" onchange="fillDeliveryDesc(this)"><option value="">--</option><?= $options ?></select></td>
<td><input name="items[0][description]" required></td>
<td><input type="number" name="items[0][quantity]" value="1" min="1"></td><td></td>
</tr></tbody></table>
<button type="button" class="btn btn-secondary" onclick="addDeliveryRow()"><?= e(__('add_item')) ?></button>
<div class="form-group" style="margin-top:1rem"><label><?= e(__('notes')) ?></label><textarea name="notes"></textarea></div>
<div class="form-actions"><button type="submit" class="btn btn-primary"><?= e(__('save')) ?></button></div>
</form>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
