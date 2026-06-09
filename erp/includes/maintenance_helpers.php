<?php

function ensureMaintenanceTables(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $path = BASE_PATH . '/database/migrate_maintenance.sql';
    if (is_file($path)) {
        runSqlFile(db(), $path);
    }
}

function maintenanceStatuses(): array
{
    return ['received', 'diagnosing', 'waiting_parts', 'in_repair', 'ready', 'delivered', 'cancelled'];
}

function maintenanceStatusLabel(string $status): string
{
    $key = 'maint_' . $status;
    $label = __($key);

    return $label !== $key ? $label : $status;
}

function maintenanceStatusBadge(string $status): string
{
    $class = match ($status) {
        'received' => 'badge-yellow',
        'diagnosing', 'waiting_parts', 'in_repair' => 'badge-blue',
        'ready' => 'badge-green',
        'delivered' => 'badge-green',
        'cancelled' => 'badge-red',
        default => 'badge-gray',
    };

    return '<span class="badge ' . $class . '">' . e(maintenanceStatusLabel($status)) . '</span>';
}

function maintenancePaymentBadge(string $status): string
{
    $class = match ($status) {
        'paid' => 'badge-green',
        'partial' => 'badge-yellow',
        default => 'badge-red',
    };
    $key = 'maint_pay_' . $status;
    $label = __($key);

    return '<span class="badge ' . $class . '">' . e($label !== $key ? $label : $status) . '</span>';
}

function syncMaintenancePaymentStatus(array &$ticket): void
{
    $cost = round((float) ($ticket['repair_cost'] ?? 0), 2);
    $paid = round((float) ($ticket['amount_paid'] ?? 0), 2);

    if ($cost <= 0 || $paid <= 0) {
        $ticket['payment_status'] = 'unpaid';
    } elseif ($paid >= $cost) {
        $ticket['payment_status'] = 'paid';
    } else {
        $ticket['payment_status'] = 'partial';
    }
}

function findOrCreateMaintenanceCustomer(string $name, string $phone, ?string $email = null): ?int
{
    $phone = trim($phone);
    if ($phone === '') {
        return null;
    }

    return findOrCreateCustomer($name, $phone, $email);
}

function createMaintenanceTicket(array $data, ?int $userId = null): int
{
    ensureMaintenanceTables();

    $ticketNumber = generateNumber('MNT');
    $customerId = $data['customer_id'] ?? findOrCreateMaintenanceCustomer(
        (string) ($data['customer_name'] ?? ''),
        (string) ($data['customer_phone'] ?? ''),
        $data['customer_email'] ?? null
    );

    $stmt = db()->prepare(
        'INSERT INTO maintenance_tickets
         (ticket_number, customer_id, customer_name, customer_phone, customer_email,
          device_brand, device_model, serial_number, issue_description, status, priority,
          estimated_cost, received_at, source, user_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $ticketNumber,
        $customerId,
        trim((string) ($data['customer_name'] ?? '')),
        trim((string) ($data['customer_phone'] ?? '')),
        trim((string) ($data['customer_email'] ?? '')) ?: null,
        trim((string) ($data['device_brand'] ?? '')) ?: null,
        trim((string) ($data['device_model'] ?? '')) ?: null,
        trim((string) ($data['serial_number'] ?? '')) ?: null,
        trim((string) ($data['issue_description'] ?? '')),
        in_array($data['status'] ?? '', maintenanceStatuses(), true) ? $data['status'] : 'received',
        ($data['priority'] ?? '') === 'urgent' ? 'urgent' : 'normal',
        round((float) ($data['estimated_cost'] ?? 0), 2),
        ($data['received_at'] ?? '') !== '' ? $data['received_at'] : date('Y-m-d'),
        ($data['source'] ?? '') === 'shop' ? 'shop' : 'manual',
        $userId,
    ]);

    return dbLastInsertId(db());
}

function getMaintenanceTicket(int $id): ?array
{
    ensureMaintenanceTables();

    $stmt = db()->prepare(
        'SELECT m.*, u.name AS user_name
         FROM maintenance_tickets m
         LEFT JOIN users u ON u.id = m.user_id
         WHERE m.id = ?'
    );
    $stmt->execute([$id]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function recordMaintenancePayment(int $ticketId, float $amount, ?int $userId, ?PDO $pdo = null): void
{
    if ($amount <= 0) {
        return;
    }

    $pdo = $pdo ?: db();
    $stmt = $pdo->prepare('SELECT * FROM maintenance_tickets WHERE id = ? FOR UPDATE');
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ticket) {
        throw new RuntimeException('not_found');
    }

    $newPaid = round((float) ($ticket['amount_paid'] ?? 0) + $amount, 2);
    $repairCost = round((float) ($ticket['repair_cost'] ?? 0), 2);
    $paymentStatus = 'partial';
    if ($repairCost <= 0 || $newPaid >= $repairCost) {
        $paymentStatus = $repairCost > 0 ? 'paid' : 'unpaid';
    } elseif ($newPaid <= 0) {
        $paymentStatus = 'unpaid';
    }

    $pdo->prepare(
        'UPDATE maintenance_tickets SET amount_paid = ?, payment_status = ? WHERE id = ?'
    )->execute([$newPaid, $paymentStatus, $ticketId]);

    recordTreasuryMovement(
        'deposit',
        $amount,
        'maintenance',
        __('maintenance') . ' — ' . (string) ($ticket['ticket_number'] ?? '') . ' / ' . (string) ($ticket['customer_name'] ?? ''),
        $userId,
        $pdo
    );
}

function maintenanceOpenCount(?PDO $pdo = null): int
{
    ensureMaintenanceTables();
    $pdo = $pdo ?: db();

    return (int) $pdo->query(
        "SELECT COUNT(*) FROM maintenance_tickets WHERE status NOT IN ('delivered', 'cancelled')"
    )->fetchColumn();
}
