<?php

function quickActionItems(): array
{
    $items = [
        ['icon' => '📦', 'label' => 'add_product', 'path' => 'inventory/add.php', 'perm' => PERM_INVENTORY],
        ['icon' => '💵', 'label' => 'new_sale', 'path' => 'invoices/create.php', 'perm' => PERM_INVOICES],
        ['icon' => '🚚', 'label' => 'create_delivery', 'path' => 'delivery/create.php', 'perm' => PERM_DELIVERY],
        ['icon' => '🛒', 'label' => 'sales_orders', 'path' => 'orders/sales.php', 'perm' => PERM_ORDERS],
        ['icon' => '🛍️', 'label' => 'new_purchase', 'path' => 'purchases/create.php', 'perm' => PERM_PURCHASES],
        ['icon' => '🏦', 'label' => 'treasury', 'path' => 'treasury/index.php', 'perm' => PERM_TREASURY],
        ['icon' => '🌐', 'label' => 'view_shop', 'path' => 'shop/index.php', 'external' => true],
    ];

    $visible = [];
    foreach ($items as $item) {
        if (isset($item['perm']) && !can($item['perm'])) {
            continue;
        }
        $visible[] = $item;
    }

    return $visible;
}
