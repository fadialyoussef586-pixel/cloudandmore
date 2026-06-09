<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requirePermission(PERM_CUSTOMERS);

ensureCustomerSchema();
ensureInvoiceSchema();
ensureMaintenanceTables();

$id = (int) ($_GET['id'] ?? 0);
$customer = getCustomer($id);
if (!$customer) {
    flash('error', __('error'));
    redirect(url('customers/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update';

    if ($action === 'rate') {
        $score = (int) ($_POST['score'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        if ($score >= 1 && $score <= 5) {
            recordCustomerRating($id, $score, $comment, 'manual', null, $_SESSION['user_id'] ?? null);
            flash('success', __('cust_rating_saved'));
        } else {
            flash('error', __('cust_rating_invalid'));
        }
        redirect(url('customers/view.php?id=' . $id));
    }

    if ($action === 'toggle_block') {
        $blocked = (int) ($customer['is_blocked'] ?? 0) === 1 ? 0 : 1;
        db()->prepare('UPDATE customers SET is_blocked = ? WHERE id = ?')->execute([$blocked, $id]);
        flash('success', __('success_saved'));
        redirect(url('customers/view.php?id=' . $id));
    }

    $name = trim($_POST['name'] ?? '');
    $phone = normalizeCustomerPhone(trim($_POST['phone'] ?? ''));
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($name === '') {
        flash('error', __('cust_name_required'));
        redirect(url('customers/view.php?id=' . $id));
    }

    if ($phone !== '') {
        $other = findCustomerByPhone($phone);
        if ($other && (int) $other['id'] !== $id) {
            flash('error', __('cust_phone_exists'));
            redirect(url('customers/view.php?id=' . $id));
        }
    }

    db()->prepare(
        'UPDATE customers SET name = ?, phone = ?, email = ?, address = ?, notes = ? WHERE id = ?'
    )->execute([
        $name,
        $phone !== '' ? $phone : null,
        $email !== '' ? $email : null,
        $address !== '' ? $address : null,
        $notes !== '' ? $notes : null,
        $id,
    ]);

    flash('success', __('success_saved'));
    redirect(url('customers/view.php?id=' . $id));
}

$customer = getCustomer($id);
$stats = customerStats($id);
[$tierKey, $tierClass, $tierLabel] = customerTier($customer, $stats);
$invoices = customerInvoices($id);
$orders = customerOrders($id, (string) ($customer['phone'] ?? ''));
$maintenance = customerMaintenanceTickets($id);
$ratings = customerRatings($id);

$pageTitle = $customer['name'];

require __DIR__ . '/../includes/header.php';
?>

<div class="page-actions">
    <a href="<?= url('customers/index.php') ?>" class="btn btn-secondary"><?= e(__('customers')) ?></a>
    <a href="<?= url('invoices/create.php') ?>" class="btn btn-primary"><?= e(__('new_sale')) ?></a>
    <form method="post" style="display:inline">
        <input type="hidden" name="action" value="toggle_block">
        <button type="submit" class="btn btn-<?= (int) ($customer['is_blocked'] ?? 0) ? 'secondary' : 'danger' ?> btn-sm">
            <?= e((int) ($customer['is_blocked'] ?? 0) ? __('cust_unblock') : __('cust_block')) ?>
        </button>
    </form>
</div>

<div class="customer-profile-header card">
    <div class="card-body">
        <div class="customer-profile-top">
            <div>
                <h1 class="customer-profile-name"><?= e($customer['name']) ?></h1>
                <p class="text-muted customer-profile-meta">
                    <?php if (!empty($customer['phone'])): ?>
                        <span><?= faIcon('fa-solid fa-phone') ?> <?= e($customer['phone']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($customer['email'])): ?>
                        <span><?= faIcon('fa-solid fa-envelope') ?> <?= e($customer['email']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($customer['address'])): ?>
                        <span><?= faIcon('fa-solid fa-location-dot') ?> <?= e($customer['address']) ?></span>
                    <?php endif; ?>
                </p>
                <div class="customer-profile-badges">
                    <span class="badge <?= e($tierClass) ?>"><?= e($tierLabel) ?></span>
                    <?php if ((int) ($customer['is_blocked'] ?? 0) === 1): ?>
                        <span class="badge badge-red"><?= e(__('cust_blocked')) ?></span>
                    <?php endif; ?>
                    <?= customerRatingStars((float) ($customer['rating_avg'] ?? 0), (int) ($customer['rating_count'] ?? 0)) ?>
                </div>
            </div>
            <div class="customer-profile-since text-muted">
                <?= e(__('cust_member_since')) ?>: <?= formatDate($customer['created_at']) ?>
            </div>
        </div>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card success">
        <div class="label"><?= e(__('cust_total_spent')) ?></div>
        <div class="value"><?= formatMoney((float) $stats['total_spent']) ?></div>
    </div>
    <div class="stat-card warning">
        <div class="label"><?= e(__('cust_outstanding')) ?></div>
        <div class="value"><?= formatMoney((float) $stats['outstanding']) ?></div>
    </div>
    <div class="stat-card">
        <div class="label"><?= e(__('invoices')) ?></div>
        <div class="value"><?= (int) $stats['invoice_count'] ?></div>
    </div>
    <div class="stat-card">
        <div class="label"><?= e(__('orders')) ?></div>
        <div class="value"><?= (int) $stats['order_count'] ?></div>
    </div>
    <div class="stat-card">
        <div class="label"><?= e(__('maintenance')) ?></div>
        <div class="value"><?= (int) $stats['maintenance_count'] ?></div>
    </div>
    <div class="stat-card">
        <div class="label"><?= e(__('cust_avg_invoice')) ?></div>
        <div class="value"><?= formatMoney((float) $stats['avg_invoice']) ?></div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h2><?= e(__('cust_edit_profile')) ?></h2></div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="action" value="update">
                <div class="form-group">
                    <label><?= e(__('name')) ?> *</label>
                    <input name="name" required value="<?= e($customer['name']) ?>">
                </div>
                <div class="form-grid form-grid-2">
                    <div class="form-group">
                        <label><?= e(__('phone')) ?></label>
                        <input name="phone" value="<?= e($customer['phone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label><?= e(__('email')) ?></label>
                        <input type="email" name="email" value="<?= e($customer['email'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label><?= e(__('address')) ?></label>
                    <input name="address" value="<?= e($customer['address'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label><?= e(__('notes')) ?></label>
                    <textarea name="notes" rows="3"><?= e($customer['notes'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary"><?= e(__('save')) ?></button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2><?= e(__('cust_rate_customer')) ?></h2></div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="action" value="rate">
                <div class="form-group">
                    <label><?= e(__('cust_rating')) ?></label>
                    <div class="rating-input">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <label class="rating-star-input">
                                <input type="radio" name="score" value="<?= $i ?>" <?= $i === 5 ? 'checked' : '' ?>>
                                <span><?= faIcon('fa-solid fa-star') ?></span>
                            </label>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label><?= e(__('cust_rating_comment')) ?></label>
                    <textarea name="comment" rows="3" placeholder="<?= e(__('cust_rating_comment_placeholder')) ?>"></textarea>
                </div>
                <button type="submit" class="btn btn-secondary"><?= e(__('cust_save_rating')) ?></button>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2><?= e(__('invoices')) ?></h2></div>
    <div class="card-body table-wrap">
        <?php if ($invoices === []): ?>
            <p class="text-muted"><?= e(__('no_data')) ?></p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th><?= e(__('invoice_number')) ?></th>
                        <th><?= e(__('total')) ?></th>
                        <th><?= e(__('payment')) ?></th>
                        <th><?= e(__('payment_method')) ?></th>
                        <th><?= e(__('date')) ?></th>
                        <th><?= e(__('actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $inv): ?>
                        <tr>
                            <td><?= e($inv['invoice_number']) ?></td>
                            <td><?= formatMoney((float) $inv['total']) ?></td>
                            <td><?= invoicePaymentStatusBadge($inv) ?></td>
                            <td><?= paymentMethodBadge($inv['payment_method'] ?? 'cash') ?></td>
                            <td><?= formatDate($inv['created_at']) ?></td>
                            <td>
                                <a href="<?= url('invoices/preview.php?id=' . $inv['id']) ?>" class="btn btn-secondary btn-sm"><?= e(__('view')) ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2><?= e(__('orders')) ?></h2></div>
    <div class="card-body table-wrap">
        <?php if ($orders === []): ?>
            <p class="text-muted"><?= e(__('no_data')) ?></p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th><?= e(__('order_number')) ?></th>
                        <th><?= e(__('total')) ?></th>
                        <th><?= e(__('status')) ?></th>
                        <th><?= e(__('date')) ?></th>
                        <th><?= e(__('actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?= e($order['order_number']) ?></td>
                            <td><?= formatMoney((float) $order['total']) ?></td>
                            <td><?= statusBadge($order['status'] ?? 'new') ?></td>
                            <td><?= formatDate($order['created_at']) ?></td>
                            <td>
                                <a href="<?= url('orders/view.php?id=' . $order['id']) ?>" class="btn btn-secondary btn-sm"><?= e(__('view')) ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php if ($maintenance !== []): ?>
<div class="card">
    <div class="card-header"><h2><?= e(__('maintenance')) ?></h2></div>
    <div class="card-body table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th><?= e(__('product')) ?></th>
                    <th><?= e(__('status')) ?></th>
                    <th><?= e(__('date')) ?></th>
                    <th><?= e(__('actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($maintenance as $ticket): ?>
                    <tr>
                        <td><?= e($ticket['ticket_number']) ?></td>
                        <td><?= e(trim(($ticket['device_brand'] ?? '') . ' ' . ($ticket['device_model'] ?? ''))) ?></td>
                        <td><?= maintenanceStatusBadge($ticket['status'] ?? 'received') ?></td>
                        <td><?= formatDate($ticket['created_at']) ?></td>
                        <td>
                            <a href="<?= url('maintenance/view.php?id=' . $ticket['id']) ?>" class="btn btn-secondary btn-sm"><?= e(__('view')) ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h2><?= e(__('cust_rating_history')) ?></h2></div>
    <div class="card-body table-wrap">
        <?php if ($ratings === []): ?>
            <p class="text-muted"><?= e(__('cust_no_ratings_yet')) ?></p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th><?= e(__('date')) ?></th>
                        <th><?= e(__('cust_rating')) ?></th>
                        <th><?= e(__('cust_rating_source')) ?></th>
                        <th><?= e(__('cust_rating_comment')) ?></th>
                        <th><?= e(__('user')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ratings as $rating): ?>
                        <tr>
                            <td><?= formatDate($rating['created_at']) ?></td>
                            <td><?= customerRatingStars((float) $rating['score'], 0, false) ?></td>
                            <td><?= e(customerRatingSourceLabel($rating['source'] ?? 'manual')) ?></td>
                            <td><?= e($rating['comment'] ?? '-') ?></td>
                            <td><?= e($rating['user_name'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
