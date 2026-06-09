<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requirePermission(PERM_GOLD);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

ensureGoldTables();
$pdo = db();

try {
    $market = refreshGoldMarketData($pdo);
    $prediction = $market['prediction'] ?? null;

    echo json_encode([
        'ok' => true,
        'price' => (float) $market['price'],
        'price_display' => formatGoldPrice((float) $market['price']),
        'change_pct' => (float) ($market['change_pct'] ?? 0),
        'high_24h' => $market['high_24h'],
        'low_24h' => $market['low_24h'],
        'source' => $market['source'] ?? 'api',
        'updated_at' => $market['updated_at'] ?? null,
        'prediction' => $prediction ? [
            'signal' => $prediction['signal'],
            'signal_label' => $prediction['signal_label'] ?? goldSignalLabel($prediction['signal']),
            'confidence' => (int) ($prediction['confidence'] ?? 0),
            'target_price' => (float) ($prediction['target_price'] ?? 0),
            'stop_loss' => (float) ($prediction['stop_loss'] ?? 0),
            'rsi' => $prediction['rsi'],
            'analysis' => $prediction['analysis'] ?? '',
        ] : null,
        'history_count' => count($market['history'] ?? []),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'gold_data_failed',
    ], JSON_UNESCAPED_UNICODE);
}
