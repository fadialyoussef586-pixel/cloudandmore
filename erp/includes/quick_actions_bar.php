<?php
$currentPath = $_SERVER['PHP_SELF'] ?? '';
$quickItems = quickActionItems();
?>
<?php if ($quickItems !== []): ?>
<div class="topbar-quick-actions no-print" id="quickActionMenu">
    <button
        type="button"
        class="topbar-quick-toggle"
        id="quickActionToggle"
        aria-expanded="false"
        aria-controls="quickActionPanel"
        aria-label="<?= e(__('quick_actions')) ?>"
    >
        <span class="topbar-quick-toggle-icon"><?= faIcon('fa-solid fa-bolt') ?></span>
        <span class="topbar-quick-toggle-text"><?= e(__('quick_actions')) ?></span>
        <span class="topbar-quick-toggle-caret"><?= faIcon('fa-solid fa-chevron-down', 'fa-nav-caret') ?></span>
    </button>

    <div class="topbar-quick-panel" id="quickActionPanel" aria-label="<?= e(__('quick_actions')) ?>" hidden>
        <?php foreach ($quickItems as $item):
            $href = !empty($item['external']) ? shopUrl() : url($item['path']);
            $target = !empty($item['external']) ? ' target="_blank" rel="noopener"' : '';
            $isActive = !empty($item['external'])
                ? str_contains($currentPath, '/shop/')
                : str_contains($currentPath, '/' . $item['path']);
        ?>
            <a href="<?= e($href) ?>" class="topbar-quick-item<?= $isActive ? ' active' : '' ?>"<?= $target ?>>
                <span class="topbar-quick-item-icon"><?= faIcon($item['icon_class']) ?></span>
                <span class="topbar-quick-item-label"><?= e(__($item['label'])) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
