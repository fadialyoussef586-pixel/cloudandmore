<?php

function quickActionItems(): array
{
    $items = [
        ['icon' => 'add_product', 'label' => 'add_product', 'path' => 'inventory/add.php', 'perm' => PERM_INVENTORY],
        ['icon' => 'new_sale', 'label' => 'new_sale', 'path' => 'invoices/create.php', 'perm' => PERM_INVOICES],
        ['icon' => 'create_delivery', 'label' => 'create_delivery', 'path' => 'delivery/create.php', 'perm' => PERM_DELIVERY],
        ['icon' => 'sales_orders', 'label' => 'sales_orders', 'path' => 'orders/sales.php', 'perm' => PERM_ORDERS],
        ['icon' => 'new_purchase', 'label' => 'new_purchase', 'path' => 'purchases/create.php', 'perm' => PERM_PURCHASES],
        ['icon' => 'treasury', 'label' => 'treasury', 'path' => 'treasury/index.php', 'perm' => PERM_TREASURY],
        ['icon' => 'shop', 'label' => 'view_shop', 'path' => 'shop/index.php', 'external' => true],
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
