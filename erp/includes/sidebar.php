<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$navItems = [
    ['file' => 'index.php', 'icon' => '📊', 'label' => 'dashboard', 'path' => 'index.php'],
    ['file' => 'index.php', 'icon' => '🛒', 'label' => 'orders', 'path' => 'orders/index.php', 'dir' => 'orders', 'perm' => PERM_ORDERS],
    ['file' => 'index.php', 'icon' => '📦', 'label' => 'inventory', 'path' => 'inventory/index.php', 'dir' => 'inventory', 'perm' => PERM_INVENTORY],
    ['file' => 'index.php', 'icon' => '🛍️', 'label' => 'purchases', 'path' => 'purchases/index.php', 'dir' => 'purchases', 'perm' => PERM_PURCHASES],
    ['file' => 'index.php', 'icon' => '🧾', 'label' => 'invoices', 'path' => 'invoices/index.php', 'dir' => 'invoices', 'perm' => PERM_INVOICES],
    ['file' => 'index.php', 'icon' => '👥', 'label' => 'hr', 'path' => 'hr/index.php', 'dir' => 'hr', 'perm' => PERM_HR],
    ['file' => 'index.php', 'icon' => '🚚', 'label' => 'delivery', 'path' => 'delivery/index.php', 'dir' => 'delivery', 'perm' => PERM_DELIVERY],
    ['file' => 'index.php', 'icon' => '🏦', 'label' => 'treasury', 'path' => 'treasury/index.php', 'dir' => 'treasury', 'perm' => PERM_TREASURY],
    ['file' => 'index.php', 'icon' => '📈', 'label' => 'reports', 'path' => 'reports/index.php', 'dir' => 'reports', 'perm' => PERM_REPORTS],
];

$currentDir = basename(dirname($_SERVER['PHP_SELF']));
$userRole = $_SESSION['user_role'] ?? 'staff';
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <?= companyLogoHtml('company-logo company-logo--sidebar', true) ?>
        <span class="brand-text">
            <small class="brand-tagline"><?= e(__('company_tagline')) ?></small>
        </span>
    </div>
    <nav class="sidebar-nav">
        <?php foreach ($navItems as $item):
            if (isset($item['perm']) && !can($item['perm'])) {
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
<div class="sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>
