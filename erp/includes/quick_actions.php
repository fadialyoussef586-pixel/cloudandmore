<?php

function quickActionItems(): array
{
    $role = $_SESSION['user_role'] ?? 'staff';
    $items = [
        ['icon' => '📦', 'label' => 'add_product', 'path' => 'inventory/add.php'],
        ['icon' => '💵', 'label' => 'new_sale', 'path' => 'invoices/create.php'],
        ['icon' => '🚚', 'label' => 'create_delivery', 'path' => 'delivery/create.php'],
        ['icon' => '🛒', 'label' => 'sales_orders', 'path' => 'orders/sales.php', 'roles' => ['admin', 'manager', 'sales']],
        ['icon' => '🛍️', 'label' => 'new_purchase', 'path' => 'purchases/create.php', 'roles' => ['admin', 'manager']],
        ['icon' => '🏦', 'label' => 'treasury', 'path' => 'treasury/index.php', 'roles' => ['admin', 'manager']],
        ['icon' => '🌐', 'label' => 'view_shop', 'path' => 'shop/index.php', 'external' => true],
    ];

    $visible = [];
    foreach ($items as $item) {
        if (isset($item['roles']) && !in_array($role, $item['roles'], true) && $role !== 'admin') {
            continue;
        }
        $visible[] = $item;
    }

    return $visible;
}
