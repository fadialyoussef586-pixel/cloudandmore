<?php
$currentPath = $_SERVER['PHP_SELF'] ?? '';
$quickItems = quickActionItems();
?>
<?php if ($quickItems !== []): ?>
<div class="floating-action-menu no-print" id="quickActionMenu">
    <button
        type="button"
        class="floating-action-toggle"
        id="quickActionToggle"
        aria-expanded="false"
        aria-controls="quickActionPanel"
    >
        <span class="floating-action-toggle-icon"><?= faIcon('fa-solid fa-bolt') ?></span>
        <span class="floating-action-toggle-text"><?= e(__('quick_actions')) ?></span>
        <span class="floating-action-toggle-caret"><?= faIcon('fa-solid fa-chevron-up', 'fa-nav-caret') ?></span>
    </button>

    <div class="floating-action-panel" id="quickActionPanel" aria-label="<?= e(__('quick_actions')) ?>">
        <?php foreach ($quickItems as $item):
            $href = !empty($item['external']) ? shopUrl() : url($item['path']);
            $target = !empty($item['external']) ? ' target="_blank" rel="noopener"' : '';
            $isActive = !empty($item['external'])
                ? str_contains($currentPath, '/shop/')
                : str_contains($currentPath, '/' . $item['path']);
        ?>
            <a href="<?= e($href) ?>" class="floating-action-item<?= $isActive ? ' active' : '' ?>"<?= $target ?>>
                <span class="floating-action-item-icon"><?= faIcon($item['icon_class']) ?></span>
                <span class="floating-action-item-label"><?= e(__($item['label'])) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
