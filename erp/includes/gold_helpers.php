<?php

function ensureGoldTables(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $path = BASE_PATH . '/database/migrate_gold_trading.sql';
    if (is_file($path)) {
        runSqlFile(db(), $path);
    }
}

function httpGetJson(string $url, int $timeout = 8): ?array
{
    $context = stream_context_create([
        'http' => [
            'timeout' => $timeout,
            'header' => "User-Agent: CloudAndMore-ERP/1.0\r\nAccept: application/json\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    if ($raw === false || $raw === '') {
        return null;
    }

    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : null;
}

function fetchGoldPriceFromYahoo(): ?array
{
    $url = 'https://query1.finance.yahoo.com/v8/finance/chart/GC=F?interval=1d&range=5d';
    $data = httpGetJson($url);
    if (!$data || empty($data['chart']['result'][0])) {
        return null;
    }

    $result = $data['chart']['result'][0];
    $meta = $result['meta'] ?? [];
    $quotes = $result['indicators']['quote'][0] ?? [];
    $closes = array_values(array_filter($quotes['close'] ?? [], static fn ($v) => $v !== null));

    if ($closes === []) {
        return null;
    }

    $current = (float) ($meta['regularMarketPrice'] ?? end($closes));
    $previous = count($closes) >= 2 ? (float) $closes[count($closes) - 2] : $current;
    $changePct = $previous > 0 ? (($current - $previous) / $previous) * 100 : 0.0;

    $highs = array_filter($quotes['high'] ?? [], static fn ($v) => $v !== null);
    $lows = array_filter($quotes['low'] ?? [], static fn ($v) => $v !== null);

    return [
        'price' => round($current, 4),
        'change_pct' => round($changePct, 4),
        'high_24h' => $highs !== [] ? round((float) max($highs), 4) : null,
        'low_24h' => $lows !== [] ? round((float) min($lows), 4) : null,
        'source' => 'yahoo',
    ];
}

function fetchGoldPriceFromMetalsLive(): ?array
{
    $data = httpGetJson('https://api.metals.live/v1/spot/gold');
    if (!is_array($data) || $data === []) {
        return null;
    }

    $latest = end($data);
    if (!is_array($latest)) {
        return null;
    }

    $price = (float) ($latest['price'] ?? $latest[1] ?? 0);
    if ($price <= 0) {
        return null;
    }

    return [
        'price' => round($price, 4),
        'change_pct' => 0.0,
        'high_24h' => null,
        'low_24h' => null,
        'source' => 'metals.live',
    ];
}

function fetchLiveGoldPrice(): ?array
{
    foreach ([fetchGoldPriceFromYahoo(...), fetchGoldPriceFromMetalsLive(...)] as $fetcher) {
        $result = $fetcher();
        if ($result !== null && ($result['price'] ?? 0) > 0) {
            return $result;
        }
    }

    return null;
}

function fetchGoldHistoryFromYahoo(int $days = 90): array
{
    $range = match (true) {
        $days <= 30 => '1mo',
        $days <= 90 => '3mo',
        $days <= 180 => '6mo',
        default => '1y',
    };

    $url = 'https://query1.finance.yahoo.com/v8/finance/chart/GC=F?interval=1d&range=' . $range;
    $data = httpGetJson($url, 12);
    if (!$data || empty($data['chart']['result'][0])) {
        return [];
    }

    $result = $data['chart']['result'][0];
    $timestamps = $result['timestamp'] ?? [];
    $closes = $result['indicators']['quote'][0]['close'] ?? [];
    $history = [];

    foreach ($timestamps as $i => $ts) {
        if (!isset($closes[$i]) || $closes[$i] === null) {
            continue;
        }
        $history[] = [
            'price' => round((float) $closes[$i], 4),
            'recorded_at' => date('Y-m-d H:i:s', (int) $ts),
        ];
    }

    return $history;
}

function seedGoldHistoryIfEmpty(PDO $pdo): void
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM gold_prices')->fetchColumn();
    if ($count >= 20) {
        return;
    }

    $history = fetchGoldHistoryFromYahoo(90);
    if ($history === []) {
        $live = fetchLiveGoldPrice();
        $base = (float) ($live['price'] ?? 2650.0);
        for ($i = 60; $i >= 0; $i--) {
            $noise = sin($i / 5) * 25 + (mt_rand(-100, 100) / 100) * 8;
            $history[] = [
                'price' => round($base + $noise - ($i * 0.15), 4),
                'recorded_at' => date('Y-m-d H:i:s', strtotime("-{$i} days")),
            ];
        }
    }

    $stmt = $pdo->prepare(
        'INSERT INTO gold_prices (price_usd, change_pct, source, recorded_at) VALUES (?, 0, ?, ?)'
    );

    foreach ($history as $row) {
        $stmt->execute([$row['price'], 'seed', $row['recorded_at']]);
    }
}

function recordGoldPrice(PDO $pdo, array $quote): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO gold_prices (price_usd, change_pct, high_24h, low_24h, source)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $quote['price'],
        $quote['change_pct'] ?? 0,
        $quote['high_24h'] ?? null,
        $quote['low_24h'] ?? null,
        $quote['source'] ?? 'api',
    ]);

    return (int) $pdo->lastInsertId();
}

function getGoldPriceHistory(PDO $pdo, int $limit = 90): array
{
    $stmt = $pdo->prepare(
        'SELECT price_usd AS price, change_pct, recorded_at
         FROM gold_prices
         ORDER BY recorded_at ASC
         LIMIT ?'
    );
    $stmt->bindValue(1, max(10, $limit), PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getLatestGoldPrice(PDO $pdo): ?array
{
    $row = $pdo->query(
        'SELECT * FROM gold_prices ORDER BY recorded_at DESC, id DESC LIMIT 1'
    )->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function extractClosePrices(array $history): array
{
    return array_map(static fn ($row) => (float) $row['price'], $history);
}

function calcSma(array $prices, int $period): ?float
{
    if (count($prices) < $period) {
        return null;
    }

    $slice = array_slice($prices, -$period);

    return round(array_sum($slice) / $period, 4);
}

function calcEma(array $prices, int $period): ?float
{
    if (count($prices) < $period) {
        return null;
    }

    $k = 2 / ($period + 1);
    $ema = array_sum(array_slice($prices, 0, $period)) / $period;

    for ($i = $period; $i < count($prices); $i++) {
        $ema = ($prices[$i] * $k) + ($ema * (1 - $k));
    }

    return round($ema, 4);
}

function calcRsi(array $prices, int $period = 14): ?float
{
    if (count($prices) <= $period) {
        return null;
    }

    $gains = 0.0;
    $losses = 0.0;

    for ($i = count($prices) - $period; $i < count($prices); $i++) {
        $change = $prices[$i] - $prices[$i - 1];
        if ($change >= 0) {
            $gains += $change;
        } else {
            $losses += abs($change);
        }
    }

    if ($losses == 0.0) {
        return 100.0;
    }

    $rs = ($gains / $period) / ($losses / $period);

    return round(100 - (100 / (1 + $rs)), 2);
}

function calcMacd(array $prices): array
{
    $ema12 = calcEma($prices, 12);
    $ema26 = calcEma($prices, 26);

    if ($ema12 === null || $ema26 === null) {
        return ['macd' => null, 'signal' => null, 'histogram' => null];
    }

    $macd = round($ema12 - $ema26, 6);

    $macdSeries = [];
    for ($i = 26; $i <= count($prices); $i++) {
        $slice = array_slice($prices, 0, $i);
        $e12 = calcEma($slice, 12);
        $e26 = calcEma($slice, 26);
        if ($e12 !== null && $e26 !== null) {
            $macdSeries[] = $e12 - $e26;
        }
    }

    $signal = count($macdSeries) >= 9 ? calcEma($macdSeries, 9) : null;
    $histogram = ($signal !== null) ? round($macd - $signal, 6) : null;

    return [
        'macd' => $macd,
        'signal' => $signal,
        'histogram' => $histogram,
    ];
}

function calcAtr(array $history, int $period = 14): ?float
{
    if (count($history) <= $period) {
        return null;
    }

    $trs = [];
    for ($i = 1; $i < count($history); $i++) {
        $high = (float) ($history[$i]['price'] ?? 0);
        $low = (float) ($history[$i]['price'] ?? 0);
        $prev = (float) ($history[$i - 1]['price'] ?? 0);
        $trs[] = max(abs($high - $low), abs($high - $prev), abs($low - $prev));
    }

    if (count($trs) < $period) {
        return null;
    }

    return round(array_sum(array_slice($trs, -$period)) / $period, 4);
}

function goldSignalLabel(string $signal): string
{
    return match ($signal) {
        'strong_buy' => __('gold_signal_strong_buy'),
        'buy' => __('gold_signal_buy'),
        'sell' => __('gold_signal_sell'),
        'strong_sell' => __('gold_signal_strong_sell'),
        default => __('gold_signal_hold'),
    };
}

function goldSignalBadge(string $signal): string
{
    $class = match ($signal) {
        'strong_buy', 'buy' => 'badge-green',
        'strong_sell', 'sell' => 'badge-red',
        default => 'badge-yellow',
    };

    return '<span class="badge ' . $class . ' gold-signal-badge">' . e(goldSignalLabel($signal)) . '</span>';
}

function generateGoldPrediction(PDO $pdo, float $currentPrice, ?int $userId = null): array
{
    $history = getGoldPriceHistory($pdo, 120);
    $prices = extractClosePrices($history);

    if ($prices === []) {
        $prices = [$currentPrice];
    } elseif (abs(end($prices) - $currentPrice) > 0.01) {
        $prices[] = $currentPrice;
    }

    $sma20 = calcSma($prices, 20);
    $sma50 = calcSma($prices, 50);
    $rsi = calcRsi($prices, 14);
    $macdData = calcMacd($prices);
    $atr = calcAtr($history, 14) ?? ($currentPrice * 0.008);

    $score = 0;

    if ($rsi !== null) {
        if ($rsi < 30) {
            $score += 25;
        } elseif ($rsi < 45) {
            $score += 10;
        } elseif ($rsi > 70) {
            $score -= 25;
        } elseif ($rsi > 55) {
            $score -= 10;
        }
    }

    if ($sma20 !== null && $sma50 !== null) {
        if ($currentPrice > $sma20 && $sma20 > $sma50) {
            $score += 30;
        } elseif ($currentPrice < $sma20 && $sma20 < $sma50) {
            $score -= 30;
        } elseif ($currentPrice > $sma20) {
            $score += 10;
        } else {
            $score -= 10;
        }
    }

    if ($macdData['histogram'] !== null) {
        $score += $macdData['histogram'] > 0 ? 20 : -20;
    }

    $changePct = (float) ($history[count($history) - 1]['change_pct'] ?? 0);
    if ($changePct > 0.5) {
        $score += 5;
    } elseif ($changePct < -0.5) {
        $score -= 5;
    }

    $score = max(-100, min(100, $score));
    $confidence = (int) min(95, max(35, abs($score) + 40));

    $signal = match (true) {
        $score >= 45 => 'strong_buy',
        $score >= 15 => 'buy',
        $score <= -45 => 'strong_sell',
        $score <= -15 => 'sell',
        default => 'hold',
    };

    $multiplier = match ($signal) {
        'strong_buy' => 2.5,
        'buy' => 1.5,
        'strong_sell' => -2.5,
        'sell' => -1.5,
        default => 0.5,
    };

    $targetPrice = round($currentPrice + ($atr * $multiplier), 2);
    $stopLoss = round($currentPrice - ($atr * 1.8 * ($score >= 0 ? 1 : -1)), 2);

    if ($signal === 'hold') {
        $targetPrice = round($currentPrice + ($atr * 0.8), 2);
        $stopLoss = round($currentPrice - ($atr * 0.8), 2);
    }

    $analysisAr = buildGoldAnalysisText('ar', $signal, $currentPrice, $rsi, $sma20, $sma50, $macdData, $targetPrice, $stopLoss);
    $analysisEn = buildGoldAnalysisText('en', $signal, $currentPrice, $rsi, $sma20, $sma50, $macdData, $targetPrice, $stopLoss);

    $stmt = $pdo->prepare(
        'INSERT INTO gold_predictions
         (price_at_prediction, signal, confidence, target_price, stop_loss, rsi, sma_20, sma_50, macd, macd_signal, analysis_ar, analysis_en, user_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $currentPrice,
        $signal,
        $confidence,
        $targetPrice,
        $stopLoss,
        $rsi,
        $sma20,
        $sma50,
        $macdData['macd'],
        $macdData['signal'],
        $analysisAr,
        $analysisEn,
        $userId,
    ]);

    return [
        'id' => (int) $pdo->lastInsertId(),
        'price' => $currentPrice,
        'signal' => $signal,
        'signal_label' => goldSignalLabel($signal),
        'confidence' => $confidence,
        'score' => $score,
        'target_price' => $targetPrice,
        'stop_loss' => $stopLoss,
        'rsi' => $rsi,
        'sma_20' => $sma20,
        'sma_50' => $sma50,
        'macd' => $macdData['macd'],
        'macd_signal' => $macdData['signal'],
        'macd_histogram' => $macdData['histogram'],
        'atr' => $atr,
        'analysis' => lang() === 'ar' ? $analysisAr : $analysisEn,
        'analysis_ar' => $analysisAr,
        'analysis_en' => $analysisEn,
    ];
}

function buildGoldAnalysisText(
    string $locale,
    string $signal,
    float $price,
    ?float $rsi,
    ?float $sma20,
    ?float $sma50,
    array $macdData,
    float $target,
    float $stopLoss
): string {
    $rsiText = $rsi !== null ? number_format($rsi, 1) : '-';
    $sma20Text = $sma20 !== null ? number_format($sma20, 2) : '-';
    $sma50Text = $sma50 !== null ? number_format($sma50, 2) : '-';
    $macdHist = $macdData['histogram'] ?? null;
    $macdTrend = $macdHist === null ? '-' : ($macdHist > 0 ? ($locale === 'ar' ? 'إيجابي' : 'positive') : ($locale === 'ar' ? 'سلبي' : 'negative'));

    $signalLabels = [
        'ar' => [
            'strong_buy' => 'شراء قوي',
            'buy' => 'شراء',
            'hold' => 'انتظار',
            'sell' => 'بيع',
            'strong_sell' => 'بيع قوي',
        ],
        'en' => [
            'strong_buy' => 'Strong Buy',
            'buy' => 'Buy',
            'hold' => 'Hold',
            'sell' => 'Sell',
            'strong_sell' => 'Strong Sell',
        ],
    ];

    $signalText = $signalLabels[$locale][$signal] ?? $signal;

    if ($locale === 'ar') {
        return "السعر الحالي: \${$price}. الإشارة: {$signalText}. "
            . "RSI = {$rsiText}، SMA20 = \${$sma20Text}، SMA50 = \${$sma50Text}. "
            . "اتجاه MACD: {$macdTrend}. الهدف: \${$target}، وقف الخسارة: \${$stopLoss}.";
    }

    return "Current price: \${$price}. Signal: {$signalText}. "
        . "RSI = {$rsiText}, SMA20 = \${$sma20Text}, SMA50 = \${$sma50Text}. "
        . "MACD trend: {$macdTrend}. Target: \${$target}, stop loss: \${$stopLoss}.";
}

function getRecentGoldPredictions(PDO $pdo, int $limit = 10): array
{
    $stmt = $pdo->prepare(
        'SELECT p.*, u.name AS user_name
         FROM gold_predictions p
         LEFT JOIN users u ON u.id = p.user_id
         ORDER BY p.created_at DESC
         LIMIT ?'
    );
    $stmt->bindValue(1, max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function refreshGoldMarketData(PDO $pdo): array
{
    ensureGoldTables();
    seedGoldHistoryIfEmpty($pdo);

    $live = fetchLiveGoldPrice();
    if ($live !== null) {
        recordGoldPrice($pdo, $live);
    } else {
        $latest = getLatestGoldPrice($pdo);
        if (!$latest) {
            recordGoldPrice($pdo, [
                'price' => 2650.0,
                'change_pct' => 0,
                'source' => 'fallback',
            ]);
        }
    }

    $latest = getLatestGoldPrice($pdo);
    $currentPrice = (float) ($latest['price_usd'] ?? 2650.0);

    $lastPrediction = $pdo->query(
        'SELECT created_at FROM gold_predictions ORDER BY created_at DESC LIMIT 1'
    )->fetchColumn();

    $shouldPredict = !$lastPrediction || (time() - strtotime((string) $lastPrediction)) > 3600;
    $prediction = null;

    if ($shouldPredict) {
        $prediction = generateGoldPrediction($pdo, $currentPrice, $_SESSION['user_id'] ?? null);
    } else {
        $row = $pdo->query('SELECT * FROM gold_predictions ORDER BY created_at DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $prediction = [
                'id' => (int) $row['id'],
                'price' => (float) $row['price_at_prediction'],
                'signal' => $row['signal'],
                'signal_label' => goldSignalLabel($row['signal']),
                'confidence' => (int) $row['confidence'],
                'target_price' => (float) $row['target_price'],
                'stop_loss' => (float) $row['stop_loss'],
                'rsi' => $row['rsi'] !== null ? (float) $row['rsi'] : null,
                'sma_20' => $row['sma_20'] !== null ? (float) $row['sma_20'] : null,
                'sma_50' => $row['sma_50'] !== null ? (float) $row['sma_50'] : null,
                'macd' => $row['macd'] !== null ? (float) $row['macd'] : null,
                'macd_signal' => $row['macd_signal'] !== null ? (float) $row['macd_signal'] : null,
                'analysis' => lang() === 'ar' ? ($row['analysis_ar'] ?? '') : ($row['analysis_en'] ?? ''),
            ];
        }
    }

    $history = getGoldPriceHistory($pdo, 90);

    return [
        'price' => $currentPrice,
        'change_pct' => (float) ($latest['change_pct'] ?? 0),
        'high_24h' => isset($latest['high_24h']) ? (float) $latest['high_24h'] : null,
        'low_24h' => isset($latest['low_24h']) ? (float) $latest['low_24h'] : null,
        'source' => $latest['source'] ?? 'db',
        'updated_at' => $latest['recorded_at'] ?? date('Y-m-d H:i:s'),
        'prediction' => $prediction,
        'history' => $history,
    ];
}

function formatGoldPrice(float $price): string
{
    return '$' . number_format($price, 2);
}
