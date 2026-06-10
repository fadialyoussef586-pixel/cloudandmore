<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requirePermission(PERM_TREASURY);

ensureTreasuryTables();

$pageTitle = __('treasury');
$mainBalance = cashAccountBalance(db(), CASH_ACCOUNT_MAIN);
$reserveBalance = cashAccountBalance(db(), CASH_ACCOUNT_RESERVE);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'movement';
    $amount = round((float) ($_POST['amount'] ?? 0), 2);
    $description = trim($_POST['description'] ?? '');
    $userId = $_SESSION['user_id'] ?? null;

    try {
        if ($action === 'external_funding') {
            if ($amount <= 0) {
                throw new RuntimeException('invalid_amount');
            }

            recordTreasuryMovement(
                'deposit',
                $amount,
                'external_funding',
                $description !== '' ? $description : __('external_funding_default'),
                $userId,
                db(),
                CASH_ACCOUNT_MAIN
            );
            flash('success', __('external_funding_saved'));
        } elseif ($action === 'transfer') {
            $fromAccount = (int) ($_POST['from_account'] ?? 0);
            $toAccount = (int) ($_POST['to_account'] ?? 0);

            recordCashTransfer($fromAccount, $toAccount, $amount, $description, $userId, db());
            flash('success', __('treasury_transfer_success'));
        } else {
            $type = ($_POST['type'] ?? '') === 'withdrawal' ? 'withdrawal' : 'deposit';
            $accountId = (int) ($_POST['account_id'] ?? CASH_ACCOUNT_MAIN);

            if ($amount <= 0) {
                throw new RuntimeException('invalid_amount');
            }

            recordTreasuryMovement(
                $type,
                $amount,
                $type === 'deposit' ? 'cash_manual_in' : 'cash_manual_out',
                $description !== '' ? $description : ($type === 'deposit' ? __('external_funding_default') : __('treasury_withdrawal')),
                $userId,
                db(),
                $accountId
            );
            flash('success', __('success_saved'));
        }
    } catch (Throwable $e) {
        $errors = [
            'insufficient_treasury' => __('insufficient_treasury'),
            'same_treasury_account' => __('same_treasury_account'),
            'cash_account_missing' => __('error'),
            'invalid_amount' => __('invalid_amount'),
        ];
        flash('error', $errors[$e->getMessage()] ?? __('error'));
    }

    redirect(url('treasury/index.php'));
}

$transactions = db()->query(
    'SELECT t.*, u.name AS user_name, ca.name AS account_name
     FROM treasury_transactions t
     LEFT JOIN users u ON u.id = t.user_id
     LEFT JOIN cash_accounts ca ON ca.id = t.cash_account_id
     ORDER BY t.created_at DESC
     LIMIT 100'
)->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<div class="stats-grid">
    <div class="stat-card success">
        <div class="label"><?= e(__('main_treasury_balance')) ?></div>
        <div class="value money-usd-lg"><?= formatMoneyHtml($mainBalance) ?></div>
    </div>
    <div class="stat-card primary">
        <div class="label"><?= e(__('reserve_treasury_balance')) ?></div>
        <div class="value money-usd-lg"><?= formatMoneyHtml($reserveBalance) ?></div>
    </div>
</div>

<div class="card" style="margin-bottom:1rem">
    <div class="card-header"><h2><?= e(__('external_funding_title')) ?></h2></div>
    <div class="card-body">
        <p class="text-muted" style="margin-bottom:1rem"><?= e(__('external_funding_hint')) ?></p>
        <form method="post" class="form-grid form-grid-2">
            <input type="hidden" name="action" value="external_funding">
            <div class="form-group">
                <label><?= e(__('amount')) ?> (<?= e(CURRENCY_CODE) ?>)</label>
                <input type="number" name="amount" step="0.01" min="0.01" required>
            </div>
            <div class="form-group">
                <label><?= e(__('description')) ?></label>
                <input type="text" name="description" placeholder="<?= e(__('external_funding_placeholder')) ?>">
            </div>
            <div class="form-group" style="grid-column:1/-1">
                <button type="submit" class="btn btn-primary"><?= e(__('record_external_funding')) ?></button>
            </div>
        </form>
    </div>
</div>

<div class="card" style="margin-bottom:1rem">
    <div class="card-header"><h2><?= e(__('treasury_transfer_title')) ?></h2></div>
    <div class="card-body">
        <p class="text-muted" style="margin-bottom:1rem"><?= e(__('treasury_transfer_hint')) ?></p>
        <form method="post" class="form-grid form-grid-2">
            <input type="hidden" name="action" value="transfer">
            <div class="form-group">
                <label><?= e(__('transfer_from')) ?></label>
                <select name="from_account" required>
                    <option value="<?= CASH_ACCOUNT_MAIN ?>"><?= e(__('main_treasury')) ?></option>
                    <option value="<?= CASH_ACCOUNT_RESERVE ?>"><?= e(__('reserve_treasury')) ?></option>
                </select>
            </div>
            <div class="form-group">
                <label><?= e(__('transfer_to')) ?></label>
                <select name="to_account" required>
                    <option value="<?= CASH_ACCOUNT_RESERVE ?>"><?= e(__('reserve_treasury')) ?></option>
                    <option value="<?= CASH_ACCOUNT_MAIN ?>"><?= e(__('main_treasury')) ?></option>
                </select>
            </div>
            <div class="form-group">
                <label><?= e(__('amount')) ?> (<?= e(CURRENCY_CODE) ?>)</label>
                <input type="number" name="amount" step="0.01" min="0.01" required>
            </div>
            <div class="form-group">
                <label><?= e(__('description')) ?></label>
                <input type="text" name="description" placeholder="<?= e(__('treasury_transfer_placeholder')) ?>">
            </div>
            <div class="form-group" style="grid-column:1/-1">
                <button type="submit" class="btn btn-secondary"><?= e(__('record_transfer')) ?></button>
            </div>
        </form>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h2><?= e(__('add_treasury_deposit')) ?></h2></div>
        <div class="card-body">
            <p class="text-muted" style="margin-bottom:1rem"><?= e(__('treasury_deposit_hint')) ?></p>
            <form method="post">
                <input type="hidden" name="action" value="movement">
                <input type="hidden" name="type" value="deposit">
                <div class="form-group">
                    <label><?= e(__('cash_account')) ?></label>
                    <select name="account_id" required>
                        <option value="<?= CASH_ACCOUNT_MAIN ?>"><?= e(__('main_treasury')) ?></option>
                        <option value="<?= CASH_ACCOUNT_RESERVE ?>"><?= e(__('reserve_treasury')) ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label><?= e(__('amount')) ?> (<?= e(CURRENCY_CODE) ?>)</label>
                    <input type="number" name="amount" step="0.01" min="0.01" required>
                </div>
                <div class="form-group">
                    <label><?= e(__('description')) ?></label>
                    <textarea name="description" placeholder="<?= e(__('external_funding_placeholder')) ?>"></textarea>
                </div>
                <button type="submit" class="btn btn-primary"><?= e(__('record_deposit')) ?></button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2><?= e(__('treasury_withdrawal')) ?></h2></div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="action" value="movement">
                <input type="hidden" name="type" value="withdrawal">
                <div class="form-group">
                    <label><?= e(__('cash_account')) ?></label>
                    <select name="account_id" required>
                        <option value="<?= CASH_ACCOUNT_MAIN ?>"><?= e(__('main_treasury')) ?></option>
                        <option value="<?= CASH_ACCOUNT_RESERVE ?>"><?= e(__('reserve_treasury')) ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label><?= e(__('amount')) ?> (<?= e(CURRENCY_CODE) ?>)</label>
                    <input type="number" name="amount" step="0.01" min="0.01" required>
                </div>
                <div class="form-group">
                    <label><?= e(__('description')) ?></label>
                    <textarea name="description"></textarea>
                </div>
                <button type="submit" class="btn btn-secondary"><?= e(__('record_withdrawal')) ?></button>
            </form>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2><?= e(__('treasury_ledger')) ?></h2></div>
    <div class="card-body table-wrap">
        <table>
            <thead>
                <tr>
                    <th><?= e(__('reference')) ?></th>
                    <th><?= e(__('cash_account')) ?></th>
                    <th><?= e(__('type')) ?></th>
                    <th><?= e(__('amount')) ?></th>
                    <th><?= e(__('description')) ?></th>
                    <th><?= e(__('user')) ?></th>
                    <th><?= e(__('date')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $t): ?>
                <tr>
                    <td><?= e($t['reference_number']) ?></td>
                    <td><?= e(cashAccountLabel((int) ($t['cash_account_id'] ?? CASH_ACCOUNT_MAIN))) ?></td>
                    <td><?= e(__($t['type'])) ?></td>
                    <td><?= formatMoneyHtml((float) $t['amount_sar']) ?></td>
                    <td><?= e($t['description'] ?? '-') ?></td>
                    <td><?= e($t['user_name'] ?? '-') ?></td>
                    <td><?= formatDate($t['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($transactions)): ?>
                <tr><td colspan="7" class="text-muted"><?= e(__('no_records')) ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
