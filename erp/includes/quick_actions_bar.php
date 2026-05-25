<?php
$currentPath = $_SERVER['PHP_SELF'] ?? '';
$quickItems = quickActionItems();
?>
<nav class="bottom-action-bar" aria-label="<?= e(__('quick_actions')) ?>">
    <?php foreach ($quickItems as $item):
        $href = !empty($item['external']) ? shopUrl() : url($item['path']);
        $target = !empty($item['external']) ? ' target="_blank" rel="noopener"' : '';
        $isActive = !empty($item['external'])
            ? str_contains($currentPath, '/shop/')
            : str_contains($currentPath, '/' . $item['path']);
    ?>
        <a href="<?= e($href) ?>" class="bottom-action-item<?= $isActive ? ' active' : '' ?>"<?= $target ?>>
            <span class="bottom-action-icon"><?= appIcon($item['icon']) ?></span>
            <span class="bottom-action-label"><?= e(__($item['label'])) ?></span>
        </a>
    <?php endforeach; ?>
</nav>
