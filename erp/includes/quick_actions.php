<?php

function quickActionItems(): array
{
    $items = [
        ['icon_class' => 'fa-solid fa-plus', 'label' => 'add_product', 'path' => 'inventory/add.php', 'perm' => PERM_INVENTORY],
        ['icon_class' => 'fa-solid fa-file-circle-plus', 'label' => 'new_sale', 'path' => 'invoices/create.php', 'perm' => PERM_INVOICES],
        ['icon_class' => 'fa-solid fa-truck-fast', 'label' => 'create_delivery', 'path' => 'delivery/create.php', 'perm' => PERM_DELIVERY],
        ['icon_class' => 'fa-solid fa-cart-shopping', 'label' => 'sales_orders', 'path' => 'orders/sales.php', 'perm' => PERM_ORDERS],
        ['icon_class' => 'fa-solid fa-cart-plus', 'label' => 'new_purchase', 'path' => 'purchases/create.php', 'perm' => PERM_PURCHASES],
        ['icon_class' => 'fa-solid fa-wallet', 'label' => 'treasury', 'path' => 'treasury/index.php', 'perm' => PERM_TREASURY],
        ['icon_class' => 'fa-solid fa-store', 'label' => 'view_shop', 'path' => 'shop/index.php', 'external' => true],
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
