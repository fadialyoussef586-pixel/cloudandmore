<?php

const PERM_ORDERS = 'orders';
const PERM_INVENTORY = 'inventory';
const PERM_PURCHASES = 'purchases';
const PERM_INVOICES = 'invoices';
const PERM_HR = 'hr';
const PERM_DELIVERY = 'delivery';
const PERM_TREASURY = 'treasury';
const PERM_REPORTS = 'reports';
const PERM_MAINTENANCE = 'maintenance';
const PERM_CURRENT_ACCOUNTS = 'current_accounts';
const PERM_CUSTOMERS = 'customers';

function allPermissionKeys(): array
{
    return [
        PERM_ORDERS,
        PERM_INVENTORY,
        PERM_PURCHASES,
        PERM_INVOICES,
        PERM_HR,
        PERM_DELIVERY,
        PERM_TREASURY,
        PERM_REPORTS,
        PERM_MAINTENANCE,
        PERM_CURRENT_ACCOUNTS,
        PERM_CUSTOMERS,
    ];
}

function ensureUserPermissionsSchema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $cols = db()->query("SHOW COLUMNS FROM users LIKE 'permissions'")->fetchAll();
    if ($cols !== []) {
        return;
    }

    $path = __DIR__ . '/../database/migrate_permissions.sql';
    if (is_file($path)) {
        runSqlFile(db(), $path);
    }
}

function ownerEmail(): string
{
    return defined('OWNER_EMAIL') ? OWNER_EMAIL : 'fadialyoussef586@gmail.com';
}

function isOwner(): bool
{
    $user = currentUser();
    if (!$user) {
        return false;
    }

    return strtolower(trim($user['email'] ?? '')) === ownerEmail();
}

function defaultPermissionsForRole(string $role): array
{
    return match ($role) {
        'manager' => [PERM_ORDERS, PERM_INVENTORY, PERM_PURCHASES, PERM_INVOICES, PERM_HR, PERM_DELIVERY, PERM_TREASURY, PERM_REPORTS, PERM_MAINTENANCE, PERM_CURRENT_ACCOUNTS, PERM_CUSTOMERS],
        'sales' => [PERM_ORDERS, PERM_INVENTORY, PERM_INVOICES, PERM_MAINTENANCE, PERM_CUSTOMERS],
        'driver' => [PERM_DELIVERY, PERM_ORDERS],
        'staff' => [PERM_INVENTORY],
        default => allPermissionKeys(),
    };
}

function decodeUserPermissions(array $user): array
{
    if (!empty($user['permissions'])) {
        $decoded = json_decode($user['permissions'], true);
        if (is_array($decoded)) {
            return array_values(array_intersect($decoded, allPermissionKeys()));
        }
    }

    return defaultPermissionsForRole($user['role'] ?? 'staff');
}

function syncUserPermissionsToSession(?array $user = null): void
{
    $user = $user ?? currentUser();
    $_SESSION['permissions'] = $user ? decodeUserPermissions($user) : [];
}

function userPermissions(): array
{
    if (!isset($_SESSION['permissions'])) {
        syncUserPermissionsToSession();
    }

    return $_SESSION['permissions'] ?? [];
}

function can(string $permission): bool
{
    if (isOwner()) {
        return true;
    }

    return in_array($permission, userPermissions(), true);
}

function canDelete(): bool
{
    return isOwner();
}

function canManageUsers(): bool
{
    return isOwner();
}

function requireOwner(): void
{
    requireAuth();
    if (!canManageUsers()) {
        flash('error', __('error_permission'));
        redirect(url('index.php'));
    }
}

function requirePermission(string $permission): void
{
    requireAuth();
    if (can($permission)) {
        return;
    }
    flash('error', __('error_permission'));
    redirect(homeUrlForRole($_SESSION['user_role'] ?? 'staff'));
}

function requireDelete(): void
{
    requireAuth();
    if (!canDelete()) {
        flash('error', __('error_permission'));
        redirect(url('index.php'));
    }
}

function permissionLabel(string $key): string
{
    return match ($key) {
        PERM_ORDERS => __('orders'),
        PERM_INVENTORY => __('inventory'),
        PERM_PURCHASES => __('purchases'),
        PERM_INVOICES => __('invoices'),
        PERM_HR => __('hr'),
        PERM_DELIVERY => __('delivery'),
        PERM_TREASURY => __('treasury'),
        PERM_REPORTS => __('reports'),
        PERM_MAINTENANCE => __('maintenance'),
        PERM_CURRENT_ACCOUNTS => __('current_accounts'),
        PERM_CUSTOMERS => __('customers'),
        default => $key,
    };
}
