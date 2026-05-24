<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$navItems = [
    ['file' => 'index.php', 'icon' => '📊', 'label' => 'dashboard', 'path' => 'index.php'],
    ['file' => 'index.php', 'icon' => '🛒', 'label' => 'orders', 'path' => 'orders/index.php', 'dir' => 'orders', 'roles' => ['admin', 'manager', 'sales']],
    ['file' => 'index.php', 'icon' => '📦', 'label' => 'inventory', 'path' => 'inventory/index.php', 'dir' => 'inventory', 'roles' => ['admin', 'manager', 'staff', 'sales']],
    ['file' => 'index.php', 'icon' => '🧾', 'label' => 'invoices', 'path' => 'invoices/index.php', 'dir' => 'invoices'],
    ['file' => 'index.php', 'icon' => '👥', 'label' => 'hr', 'path' => 'hr/index.php', 'dir' => 'hr'],
    ['file' => 'index.php', 'icon' => '🚚', 'label' => 'delivery', 'path' => 'delivery/index.php', 'dir' => 'delivery'],
    ['file' => 'index.php', 'icon' => '🏦', 'label' => 'treasury', 'path' => 'treasury/index.php', 'dir' => 'treasury', 'roles' => ['admin', 'manager']],
    ['file' => 'index.php', 'icon' => '📈', 'label' => 'reports', 'path' => 'reports/index.php', 'dir' => 'reports'],
];

$currentDir = basename(dirname($_SERVER['PHP_SELF']));
$userRole = $_SESSION['user_role'] ?? 'staff';
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <span class="brand-icon">📱</span>
        <span class="brand-text">
            <span class="brand-name"><?= e(__('app_name')) ?></span>
            <small class="brand-tagline"><?= e(__('company_tagline')) ?></small>
        </span>
    </div>
    <nav class="sidebar-nav">
        <?php foreach ($navItems as $item):
            if (isset($item['roles']) && !in_array($userRole, $item['roles'], true) && $userRole !== 'admin') {
                continue;
            }
            $isActive = ($item['path'] === 'index.php' && $currentPage === 'index.php' && $currentDir === 'erp')
                || (isset($item['dir']) && $currentDir === $item['dir']);
        ?>
            <a href="<?= url($item['path']) ?>" class="nav-item <?= $isActive ? 'active' : '' ?>">
                <span class="nav-icon"><?= $item['icon'] ?></span>
                <span><?= e(__($item['label'])) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        <a href="<?= shopUrl() ?>" class="nav-item" target="_blank">
            <span class="nav-icon">🌐</span>
            <span><?= e(__('view_shop')) ?></span>
        </a>
        <a href="<?= url('logout.php') ?>" class="nav-item logout">
            <span class="nav-icon">🚪</span>
            <span><?= e(__('logout')) ?></span>
        </a>
    </div>
</aside>
