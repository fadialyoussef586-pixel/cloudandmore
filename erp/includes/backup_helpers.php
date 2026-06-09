<?php

function backupTableNames(): array
{
    return [
        'users',
        'products',
        'customers',
        'invoices',
        'invoice_items',
        'invoice_returns',
        'stock_movements',
        'orders',
        'order_items',
        'deliveries',
        'delivery_items',
        'employees',
        'payroll',
        'suppliers',
        'purchases',
        'supplier_debt_payments',
        'treasury_transactions',
        'cash_accounts',
        'exchange_rates',
        'gold_prices',
        'gold_predictions',
        'gold_alerts',
    ];
}

function exportBusinessBackup(PDO $pdo): array
{
    $payload = [
        'meta' => [
            'app' => defined('APP_NAME') ? APP_NAME : 'ERP',
            'version' => defined('APP_VERSION') ? APP_VERSION : '1.0.0',
            'exported_at' => date('c'),
            'database' => appEnv('DB_NAME', 'erp'),
        ],
        'tables' => [],
    ];

    foreach (backupTableNames() as $table) {
        if (!databaseTableExists($pdo, $table)) {
            continue;
        }

        try {
            $rows = $pdo->query('SELECT * FROM `' . str_replace('`', '``', $table) . '`')->fetchAll(PDO::FETCH_ASSOC);
            $payload['tables'][$table] = $rows;
        } catch (Throwable) {
            $payload['tables'][$table] = [];
        }
    }

    return $payload;
}

function backupDownloadFilename(): string
{
    return 'cloudandmore-backup-' . date('Y-m-d-His') . '.json';
}

function sendBackupDownload(PDO $pdo): void
{
    $payload = exportBusinessBackup($pdo);
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        throw new RuntimeException('backup_encode_failed');
    }

    $filename = backupDownloadFilename();

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Content-Length: ' . strlen($json));

    echo $json;
    exit;
}
