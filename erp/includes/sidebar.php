<?php
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$currentDir = basename(dirname($_SERVER['PHP_SELF'] ?? ''));

$isItemActive = static function (array $item) use ($currentPage, $currentDir): bool {
    if (($item['path'] ?? '') === 'index.php') {
        return $currentPage === 'index.php' && $currentDir === 'erp';
    }

    return isset($item['dir']) && $currentDir === $item['dir'];
};

$filterVisible = static function (array $items): array {
    $visible = [];
    foreach ($items as $item) {
        if (isset($item['perm']) && !can($item['perm'])) {
            continue;
        }
        $visible[] = $item;
    }

    return $visible;
};

$dashboardLink = [
    'label' => 'dashboard',
    'path' => 'index.php',
    'icon_class' => 'fa-solid fa-gauge-high',
];

$navGroups = [
    [
        'label' => 'sales_operations',
        'icon_class' => 'fa-solid fa-bag-shopping',
        'items' => [
            ['label' => 'orders', 'path' => 'orders/index.php', 'dir' => 'orders', 'perm' => PERM_ORDERS, 'icon_class' => 'fa-solid fa-cart-shopping'],
            ['label' => 'invoices', 'path' => 'invoices/index.php', 'dir' => 'invoices', 'perm' => PERM_INVOICES, 'icon_class' => 'fa-solid fa-file-invoice-dollar'],
            ['label' => 'delivery', 'path' => 'delivery/index.php', 'dir' => 'delivery', 'perm' => PERM_DELIVERY, 'icon_class' => 'fa-solid fa-truck-fast'],
        ],
    ],
    [
        'label' => 'inventory_supply',
        'icon_class' => 'fa-solid fa-boxes-stacked',
        'items' => [
            ['label' => 'inventory', 'path' => 'inventory/index.php', 'dir' => 'inventory', 'perm' => PERM_INVENTORY, 'icon_class' => 'fa-solid fa-box-open'],
            ['label' => 'purchases', 'path' => 'purchases/index.php', 'dir' => 'purchases', 'perm' => PERM_PURCHASES, 'icon_class' => 'fa-solid fa-cart-plus'],
        ],
    ],
    [
        'label' => 'finance_team',
        'icon_class' => 'fa-solid fa-briefcase',
        'items' => [
            ['label' => 'treasury', 'path' => 'treasury/index.php', 'dir' => 'treasury', 'perm' => PERM_TREASURY, 'icon_class' => 'fa-solid fa-wallet'],
            ['label' => 'reports', 'path' => 'reports/index.php', 'dir' => 'reports', 'perm' => PERM_REPORTS, 'icon_class' => 'fa-solid fa-chart-line'],
            ['label' => 'hr', 'path' => 'hr/index.php', 'dir' => 'hr', 'perm' => PERM_HR, 'icon_class' => 'fa-solid fa-users-gear'],
        ],
    ],
];

$footerLinks = [
    ['label' => 'view_shop', 'href' => shopUrl(), 'icon_class' => 'fa-solid fa-store', 'target' => '_blank'],
    ['label' => 'logout', 'href' => url('logout.php'), 'icon_class' => 'fa-solid fa-right-from-bracket', 'class' => 'logout'],
];
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <?= companyLogoHtml('company-logo company-logo--sidebar', true) ?>
        <div class="sidebar-brand-copy">
            <strong>cloud&amp;more</strong>
            <small class="brand-tagline"><?= e(__('company_tagline')) ?></small>
        </div>
    </div>

    <nav class="sidebar-nav" aria-label="<?= e(__('navigation')) ?>">
        <div class="sidebar-section">
            <span class="sidebar-section-title"><?= e(__('overview')) ?></span>
            <?php $dashboardActive = $isItemActive($dashboardLink); ?>
            <a
                href="<?= url($dashboardLink['path']) ?>"
                class="nav-item nav-link <?= $dashboardActive ? 'active' : '' ?>"
                title="<?= e(__($dashboardLink['label'])) ?>"
            >
                <span class="nav-icon"><?= faIcon($dashboardLink['icon_class']) ?></span>
                <span class="nav-label"><?= e(__($dashboardLink['label'])) ?></span>
            </a>
        </div>

        <div class="sidebar-section">
            <span class="sidebar-section-title"><?= e(__('navigation')) ?></span>
            <?php foreach ($navGroups as $index => $group): ?>
                <?php
                $items = $filterVisible($group['items']);
                if ($items === []) {
                    continue;
                }
                $groupId = 'sidebar-group-' . $index;
                $groupActive = false;
                foreach ($items as $child) {
                    if ($isItemActive($child)) {
                        $groupActive = true;
                        break;
                    }
                }
                ?>
                <div class="nav-group<?= $groupActive ? ' active open' : '' ?>">
                    <button
                        type="button"
                        class="nav-item nav-group-toggle<?= $groupActive ? ' active' : '' ?>"
                        data-submenu-toggle="<?= e($groupId) ?>"
                        aria-expanded="<?= $groupActive ? 'true' : 'false' ?>"
                        title="<?= e(__($group['label'])) ?>"
                    >
                        <span class="nav-icon"><?= faIcon($group['icon_class']) ?></span>
                        <span class="nav-label"><?= e(__($group['label'])) ?></span>
                        <span class="nav-caret"><?= faIcon('fa-solid fa-chevron-down', 'fa-nav-caret') ?></span>
                    </button>
                    <div class="nav-group-links" id="<?= e($groupId) ?>">
                        <?php foreach ($items as $child): ?>
                            <?php $isActive = $isItemActive($child); ?>
                            <a
                                href="<?= url($child['path']) ?>"
                                class="nav-subitem<?= $isActive ? ' active' : '' ?>"
                                title="<?= e(__($child['label'])) ?>"
                            >
                                <span class="nav-subicon"><?= faIcon($child['icon_class'], 'fa-sub-icon') ?></span>
                                <span class="nav-subtext"><?= e(__($child['label'])) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </nav>

    <div class="sidebar-footer">
        <?php foreach ($footerLinks as $link): ?>
            <a
                href="<?= e($link['href']) ?>"
                class="nav-item nav-link <?= e($link['class'] ?? '') ?>"
                title="<?= e(__($link['label'])) ?>"
                <?= !empty($link['target']) ? 'target="' . e($link['target']) . '" rel="noopener"' : '' ?>
            >
                <span class="nav-icon"><?= faIcon($link['icon_class']) ?></span>
                <span class="nav-label"><?= e(__($link['label'])) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>
