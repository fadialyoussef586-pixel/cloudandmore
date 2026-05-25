<?php

function appIcon(string $name, string $class = 'app-icon'): string
{
    $icons = [
        'dashboard' => '<path d="M4 13.5h6.5V4H4v9.5Zm9.5 6.5H20V10.5h-6.5V20ZM4 20h6.5v-3.5H4V20Zm9.5-12.5H20V4h-6.5v3.5Z"/>',
        'orders' => '<path d="M3.5 6.5h17"/><path d="M7 3.5v6"/><path d="M17 3.5v6"/><rect x="4" y="6.5" width="16" height="14" rx="2"/><path d="M8 11.5h8"/><path d="M8 15.5h5"/>',
        'inventory' => '<path d="m12 3.75 7.5 4.25v8.5L12 20.75l-7.5-4.25V8l7.5-4.25Z"/><path d="M12 3.75V12"/><path d="m19.5 8-7.5 4-7.5-4"/>',
        'purchases' => '<circle cx="9" cy="19" r="1.5"/><circle cx="17" cy="19" r="1.5"/><path d="M3.5 4.5h2l2.3 10.2a1 1 0 0 0 1 .8h8.9a1 1 0 0 0 1-.76L20.25 7.5H7"/>',
        'invoices' => '<rect x="5" y="3.5" width="14" height="17" rx="2"/><path d="M8 7.5h8"/><path d="M8 11.5h8"/><path d="M8 15.5h5"/><path d="M14.5 18.5h1.5"/>',
        'hr' => '<path d="M16.5 20v-1.5a3.5 3.5 0 0 0-3.5-3.5H8.5A3.5 3.5 0 0 0 5 18.5V20"/><circle cx="10.75" cy="8" r="3"/><path d="M20 20v-1a3 3 0 0 0-2.25-2.9"/><path d="M15.5 5.4a3 3 0 0 1 0 5.2"/>',
        'delivery' => '<path d="M3.5 6.5h11v8h-11z"/><path d="M14.5 9.5h3l2 2.25v2.75h-5"/><circle cx="7.5" cy="17.5" r="1.5"/><circle cx="16.5" cy="17.5" r="1.5"/><path d="M9 17.5h6"/>',
        'treasury' => '<path d="M3.5 9.5 12 4l8.5 5.5"/><path d="M5.5 9.5V20h13V9.5"/><path d="M9 20v-6h6v6"/><path d="M8.5 11h7"/>',
        'reports' => '<path d="M4.5 19.5h15"/><path d="M7.5 16V10"/><path d="M12 16V6"/><path d="M16.5 16v-3.5"/>',
        'shop' => '<path d="M5 8.5h14l-1 10.5H6L5 8.5Z"/><path d="M8 8.5V7a4 4 0 1 1 8 0v1.5"/>',
        'logout' => '<path d="M9.5 20H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h3.5"/><path d="M14 16.5 18.5 12 14 7.5"/><path d="M9 12h9.5"/>',
        'add_product' => '<path d="m12 3.75 7.5 4.25v8.5L12 20.75l-7.5-4.25V8l7.5-4.25Z"/><path d="M12 8v8"/><path d="M8 12h8"/>',
        'new_sale' => '<path d="M5.5 4.5h11l2 2v13.5h-13z"/><path d="M8 9.5h8"/><path d="M8 13.5h4"/><path d="M15.5 16.5h-2.25a1.75 1.75 0 1 0 0 3.5h2a1.75 1.75 0 1 0 0-3.5Zm0 0v-1m0 4.5v1"/>',
        'create_delivery' => '<path d="M3.5 7.5h10v8h-10z"/><path d="M13.5 10.5h3l2 2.5v2.5h-5"/><circle cx="7.5" cy="17.5" r="1.5"/><circle cx="16.5" cy="17.5" r="1.5"/><path d="M10 7.5V4.5"/><path d="M8.5 6h3"/>',
        'sales_orders' => '<path d="M4.5 5.5h15"/><path d="M7.5 3.5v4"/><path d="M16.5 3.5v4"/><rect x="4.5" y="5.5" width="15" height="14" rx="2"/><path d="M8 11h8"/><path d="M8 15h5"/><path d="M15.5 12.5 17 14l2.5-2.5"/>',
        'new_purchase' => '<circle cx="9" cy="19" r="1.5"/><circle cx="17" cy="19" r="1.5"/><path d="M3.5 4.5h2l2.3 10.2a1 1 0 0 0 1 .8h8.9a1 1 0 0 0 1-.76L20.25 7.5H7"/><path d="M14 9.5v4"/><path d="M12 11.5h4"/>',
    ];

    $body = $icons[$name] ?? '<circle cx="12" cy="12" r="8"/>';

    return '<svg class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $body . '</svg>';
}
