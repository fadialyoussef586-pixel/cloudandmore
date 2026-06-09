<?php

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/data_reset.php';

function requireAuth(): void
{
    if (!isset($_SESSION['user_id'])) {
        redirect(url('login.php'));
    }
}

function currentUser(): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    static $user = null;
    if ($user === null) {
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

function fetchUserByEmail(string $email): ?array
{
    $email = strtolower(trim($email));
    $stmt = db()->prepare('SELECT * FROM users WHERE LOWER(email) = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function completeLogin(array $user): void
{
    $legacy = ['admin@iqos.com', 'administrator@iqos.com'];
    if (in_array(strtolower($user['email']), $legacy, true)
        && strtolower($user['email']) !== strtolower(OWNER_EMAIL)) {
        ensureOwnerAccount(db());
        $user = fetchUserByEmail(OWNER_EMAIL) ?? $user;
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_role'] = $user['role'];
    syncUserPermissionsToSession($user);
}

function login(string $email, string $password): bool
{
    $normalized = strtolower(trim($email));
    $user = fetchUserByEmail($normalized);

    if (!$user && $normalized === strtolower(OWNER_EMAIL)) {
        $user = fetchUserByEmail('admin@iqos.com')
            ?? fetchUserByEmail('administrator@iqos.com');
    }

    if ($user && password_verify($password, $user['password'])) {
        completeLogin($user);
        return true;
    }

    return false;
}

function logout(): void
{
    session_destroy();
    redirect(url('login.php'));
}

function requireRole(array $roles): void
{
    requireAuth();
    if (isOwner()) {
        return;
    }
    $role = $_SESSION['user_role'] ?? '';
    if ($role === 'admin' || in_array($role, $roles, true)) {
        return;
    }
    flash('error', __('error'));
    redirect(homeUrlForRole($role));
}

function canAccessOrders(): bool
{
    $role = $_SESSION['user_role'] ?? '';
    return in_array($role, ['admin', 'manager', 'sales'], true);
}

function isDriver(): bool
{
    return ($_SESSION['user_role'] ?? '') === 'driver';
}
