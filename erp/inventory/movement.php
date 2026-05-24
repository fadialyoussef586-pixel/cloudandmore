<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();

$pageTitle = __('movements');
$productId = (int) ($_GET['product_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid = (int) $_POST['product_id'];
    $type = $_POST['type'];
    $qty = (int) $_POST['quantity'];
    if ($pid && $qty > 0 && in_array($type, ['in', 'out', 'adjustment'], true)) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT quantity FROM products WHERE id = ? FOR UPDATE');
            $stmt->execute([$pid]);
            $current = (int) $stmt->fetchColumn();
            if ($type === 'in') $new = $current + $qty;
            elseif ($type === 'out') $new = max(0, $current - $qty);
            else $new = $qty;
            $pdo->prepare('UPDATE products SET quantity = ? WHERE id = ?')->execute([$new, $pid]);
            $pdo->prepare('INSERT INTO stock_movements (product_id, type, quantity, reference, notes, user_id) VALUES (?,?,?,?,?,?)')
                ->execute([$pid, $type, $qty, trim($_POST['reference'] ?? ''), trim($_POST['notes'] ?? ''), $_SESSION['user_id']]);
            $pdo->commit();
            flash('success', __('success_saved'));
        } catch (Exception $e) {
            $pdo->rollBack();
            flash('error', __('error'));
        }
    }
    redirect(url('inventory/movement.php' . ($productId ? '?product_id=' . $productId : '')));
}

$products = db()->query('SELECT id, sku, name_ar, name_en FROM products ORDER BY name_en')->fetchAll();
$sql = 'SELECT sm.*, p.sku, p.name_ar, p.name_en FROM stock_movements sm JOIN products p ON p.id = sm.product_id';
$params = [];
if ($productId) { $sql .= ' WHERE sm.product_id = ?'; $params[] = $productId; }
$sql .= ' ORDER BY sm.created_at DESC LIMIT 50';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$movements = $stmt->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<div class="grid-2">
<div class="card"><div class="card-header"><h2><?= e(__('stock_in')) ?> / <?= e(__('stock_out')) ?></h2></div>
<div class="card-body">
<form method="post">
    <div class="form-group"><label><?= e(__('product')) ?></label>
    <select name="product_id" required>
        <option value="">--</option>
        <?php foreach ($products as $p): ?>
        <option value="<?= $p['id'] ?>" <?= $productId === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['sku'] . ' - ' . productName($p)) ?></option>
        <?php endforeach; ?>
    </select></div>
    <div class="form-group"><label><?= e(__('status')) ?></label>
    <select name="type"><option value="in"><?= e(__('stock_in')) ?></option><option value="out"><?= e(__('stock_out')) ?></option><option value="adjustment">Adjustment</option></select></div>
    <div class="form-group"><label><?= e(__('quantity')) ?></label><input type="number" name="quantity" min="1" required></div>
    <div class="form-group"><label>Reference</label><input name="reference"></div>
    <div class="form-group"><label><?= e(__('notes')) ?></label><textarea name="notes"></textarea></div>
    <button type="submit" class="btn btn-primary"><?= e(__('save')) ?></button>
</form>
</div></div>
<div class="card"><div class="card-header"><h2><?= e(__('movements')) ?></h2></div>
<div class="card-body table-wrap">
<table><thead><tr><th><?= e(__('date')) ?></th><th><?= e(__('product')) ?></th><th>Type</th><th><?= e(__('quantity')) ?></th><th><?= e(__('notes')) ?></th></tr></thead>
<tbody>
<?php foreach ($movements as $m): ?>
<tr>
<td><?= formatDate($m['created_at']) ?></td>
<td><?= e($m['sku']) ?></td>
<td><?= e($m['type']) ?></td>
<td><?= (int)$m['quantity'] ?></td>
<td><?= e($m['notes']) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
