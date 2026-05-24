<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
$pageTitle = __('create_invoice');
$customers = db()->query('SELECT * FROM customers ORDER BY name')->fetchAll();
$products = db()->query('SELECT id, sku, name_ar, name_en, sell_price FROM products ORDER BY name_en')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $customerId = (int) $_POST['customer_id'];
        $taxRate = (float) ($_POST['tax_rate'] ?? 15);
        $discount = (float) ($_POST['discount'] ?? 0);
        $subtotal = 0;
        $items = $_POST['items'] ?? [];
        foreach ($items as $item) {
            if (empty($item['description'])) continue;
            $subtotal += (float)$item['quantity'] * (float)$item['unit_price'];
        }
        $taxAmount = $subtotal * ($taxRate / 100);
        $total = $subtotal + $taxAmount - $discount;
        $invNum = generateNumber('INV');
        $pdo->prepare('INSERT INTO invoices (invoice_number, customer_id, subtotal, tax_rate, tax_amount, discount, total, status, due_date, notes, user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$invNum, $customerId, $subtotal, $taxRate, $taxAmount, $discount, $total, $_POST['status'] ?? 'draft', $_POST['due_date'] ?: null, trim($_POST['notes'] ?? ''), $_SESSION['user_id']]);
        $invoiceId = (int) $pdo->lastInsertId();
        foreach ($items as $item) {
            if (empty($item['description'])) continue;
            $lineTotal = (float)$item['quantity'] * (float)$item['unit_price'];
            $productId = !empty($item['product_id']) ? (int)$item['product_id'] : null;
            $pdo->prepare('INSERT INTO invoice_items (invoice_id, product_id, description, quantity, unit_price, total) VALUES (?,?,?,?,?,?)')
                ->execute([$invoiceId, $productId, $item['description'], (int)$item['quantity'], (float)$item['unit_price'], $lineTotal]);
            if ($productId && ($_POST['status'] ?? '') === 'paid') {
                $pdo->prepare('UPDATE products SET quantity = GREATEST(0, quantity - ?) WHERE id = ?')->execute([(int)$item['quantity'], $productId]);
            }
        }
        $pdo->commit();
        flash('success', __('success_saved'));
        redirect(url('invoices/view.php?id=' . $invoiceId));
    } catch (Exception $e) {
        $pdo->rollBack();
        flash('error', __('error'));
    }
}

require __DIR__ . '/../includes/header.php';
$options = '';
foreach ($products as $p) {
    $options .= '<option value="'.$p['id'].'" data-price="'.$p['sell_price'].'">'.htmlspecialchars(productName($p)).'</option>';
}
?>
<script>window.productsOptions = `<?= $options ?>`;</script>
<div class="card"><div class="card-body">
<form method="post">
<div class="form-grid">
<div class="form-group"><label><?= e(__('customer')) ?></label>
<select name="customer_id" required><option value="">--</option>
<?php foreach ($customers as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
</select></div>
<div class="form-group"><label><?= e(__('due_date')) ?></label><input type="date" name="due_date"></div>
<div class="form-group"><label><?= e(__('tax')) ?> %</label><input type="number" step="0.01" name="tax_rate" value="15"></div>
<div class="form-group"><label><?= e(__('discount')) ?></label><input type="number" step="0.01" name="discount" value="0"></div>
<div class="form-group"><label><?= e(__('status')) ?></label>
<select name="status"><option value="draft"><?= e(__('draft')) ?></option><option value="sent"><?= e(__('sent')) ?></option><option value="paid"><?= e(__('paid')) ?></option></select></div>
</div>
<h3 style="margin:1rem 0"><?= e(__('items')) ?></h3>
<div class="table-wrap invoice-items-table">
<table id="invoiceItems"><thead><tr><th><?= e(__('product')) ?></th><th><?= e(__('description')) ?></th><th><?= e(__('quantity')) ?></th><th><?= e(__('price')) ?></th><th><?= e(__('total')) ?></th><th></th></tr></thead>
<tbody><tr>
<td><select name="items[0][product_id]" onchange="fillProductPrice(this)"><option value="">--</option><?= $options ?></select></td>
<td><input name="items[0][description]" required></td>
<td><input type="number" name="items[0][quantity]" value="1" min="1" class="qty-input" onchange="calcRow(this)"></td>
<td><input type="number" name="items[0][unit_price]" value="0" step="0.01" class="price-input" onchange="calcRow(this)"></td>
<td class="row-total">0.00</td><td></td>
</tr></tbody></table>
<button type="button" class="btn btn-secondary" onclick="addInvoiceRow()"><?= e(__('add_item')) ?></button>
<div class="form-group" style="margin-top:1rem"><label><?= e(__('notes')) ?></label><textarea name="notes"></textarea></div>
<div class="form-actions"><button type="submit" class="btn btn-primary"><?= e(__('save')) ?></button></div>
</form>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
