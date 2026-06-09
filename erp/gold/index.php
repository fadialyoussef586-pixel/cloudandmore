<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();
requirePermission(PERM_GOLD);

ensureGoldTables();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refresh'])) {
    refreshGoldMarketData($pdo);
    flash('success', __('gold_refreshed'));
    redirect(url('gold/index.php'));
}

$market = refreshGoldMarketData($pdo);
$prediction = $market['prediction'] ?? null;
$history = $market['history'] ?? [];
$recentPredictions = getRecentGoldPredictions($pdo, 8);

$pageTitle = __('gold_trading');

$chartLabels = [];
$chartPrices = [];
foreach ($history as $row) {
    $chartLabels[] = date('m/d', strtotime($row['recorded_at']));
    $chartPrices[] = round((float) $row['price'], 2);
}

require __DIR__ . '/../includes/header.php';
?>

<div class="gold-hero">
    <div class="gold-hero-main">
        <span class="gold-hero-kicker"><?= e(__('gold_xau_usd')) ?></span>
        <div class="gold-hero-price" id="gold-live-price"><?= formatGoldPrice((float) $market['price']) ?></div>
        <div class="gold-hero-change <?= ($market['change_pct'] ?? 0) >= 0 ? 'up' : 'down' ?>" id="gold-live-change">
            <?= ($market['change_pct'] ?? 0) >= 0 ? '+' : '' ?><?= number_format((float) ($market['change_pct'] ?? 0), 2) ?>%
        </div>
        <p class="text-muted gold-updated-at">
            <?= e(__('gold_last_update')) ?>: <?= e(formatDate($market['updated_at'] ?? date('Y-m-d H:i:s'))) ?>
            · <?= e(__('gold_source')) ?>: <?= e($market['source'] ?? 'api') ?>
        </p>
    </div>
    <?php if ($prediction): ?>
    <div class="gold-hero-signal gold-signal-<?= e($prediction['signal']) ?>">
        <span class="gold-signal-label"><?= e(__('gold_prediction')) ?></span>
        <strong class="gold-signal-value"><?= goldSignalBadge($prediction['signal']) ?></strong>
        <div class="gold-confidence">
            <span><?= e(__('gold_confidence')) ?></span>
            <div class="gold-confidence-bar">
                <div class="gold-confidence-fill" style="width:<?= (int) $prediction['confidence'] ?>%"></div>
            </div>
            <span><?= (int) $prediction['confidence'] ?>%</span>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="stats-grid gold-stats-grid">
    <?php if ($prediction): ?>
    <div class="stat-card success">
        <div class="label"><?= e(__('gold_target_price')) ?></div>
        <div class="value"><?= formatGoldPrice((float) $prediction['target_price']) ?></div>
    </div>
    <div class="stat-card warning">
        <div class="label"><?= e(__('gold_stop_loss')) ?></div>
        <div class="value"><?= formatGoldPrice((float) $prediction['stop_loss']) ?></div>
    </div>
    <div class="stat-card">
        <div class="label">RSI (14)</div>
        <div class="value"><?= $prediction['rsi'] !== null ? number_format((float) $prediction['rsi'], 1) : '-' ?></div>
    </div>
    <div class="stat-card">
        <div class="label">SMA 20 / 50</div>
        <div class="value" style="font-size:1rem">
            <?= $prediction['sma_20'] !== null ? formatGoldPrice((float) $prediction['sma_20']) : '-' ?>
            /
            <?= $prediction['sma_50'] !== null ? formatGoldPrice((float) $prediction['sma_50']) : '-' ?>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($market['high_24h']): ?>
    <div class="stat-card primary">
        <div class="label"><?= e(__('gold_high_24h')) ?></div>
        <div class="value"><?= formatGoldPrice((float) $market['high_24h']) ?></div>
    </div>
    <?php endif; ?>
    <?php if ($market['low_24h']): ?>
    <div class="stat-card warning">
        <div class="label"><?= e(__('gold_low_24h')) ?></div>
        <div class="value"><?= formatGoldPrice((float) $market['low_24h']) ?></div>
    </div>
    <?php endif; ?>
</div>

<div class="grid-2 gold-grid">
    <div class="card gold-chart-card">
        <div class="card-header">
            <h2><?= e(__('gold_price_chart')) ?></h2>
            <form method="post" class="gold-refresh-form">
                <input type="hidden" name="refresh" value="1">
                <button type="submit" class="btn btn-sm btn-secondary">
                    <?= faIcon('fa-solid fa-rotate', 'fa-btn-icon') ?>
                    <?= e(__('gold_refresh')) ?>
                </button>
            </form>
        </div>
        <div class="card-body">
            <canvas id="goldPriceChart" height="120"></canvas>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2><?= e(__('gold_technical_analysis')) ?></h2></div>
        <div class="card-body">
            <?php if ($prediction): ?>
                <p class="gold-analysis-text"><?= e($prediction['analysis'] ?? '') ?></p>
                <div class="gold-indicators-grid">
                    <div class="gold-indicator">
                        <span class="gold-indicator-label">MACD</span>
                        <span class="gold-indicator-value"><?= $prediction['macd'] !== null ? number_format((float) $prediction['macd'], 4) : '-' ?></span>
                    </div>
                    <div class="gold-indicator">
                        <span class="gold-indicator-label"><?= e(__('gold_macd_signal')) ?></span>
                        <span class="gold-indicator-value"><?= $prediction['macd_signal'] !== null ? number_format((float) $prediction['macd_signal'], 4) : '-' ?></span>
                    </div>
                    <div class="gold-indicator">
                        <span class="gold-indicator-label"><?= e(__('gold_score')) ?></span>
                        <span class="gold-indicator-value"><?= isset($prediction['score']) ? (int) $prediction['score'] : '-' ?></span>
                    </div>
                </div>
                <p class="text-muted gold-disclaimer"><?= e(__('gold_disclaimer')) ?></p>
            <?php else: ?>
                <p class="text-muted"><?= e(__('no_data')) ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2><?= e(__('gold_prediction_history')) ?></h2></div>
    <div class="card-body table-wrap">
        <?php if ($recentPredictions === []): ?>
            <p class="text-muted"><?= e(__('no_data')) ?></p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th><?= e(__('date')) ?></th>
                        <th><?= e(__('price')) ?></th>
                        <th><?= e(__('gold_signal')) ?></th>
                        <th><?= e(__('gold_confidence')) ?></th>
                        <th><?= e(__('gold_target_price')) ?></th>
                        <th><?= e(__('gold_stop_loss')) ?></th>
                        <th>RSI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentPredictions as $row): ?>
                        <tr>
                            <td><?= formatDate($row['created_at']) ?></td>
                            <td><?= formatGoldPrice((float) $row['price_at_prediction']) ?></td>
                            <td><?= goldSignalBadge($row['signal']) ?></td>
                            <td><?= (int) $row['confidence'] ?>%</td>
                            <td><?= formatGoldPrice((float) $row['target_price']) ?></td>
                            <td><?= formatGoldPrice((float) $row['stop_loss']) ?></td>
                            <td><?= $row['rsi'] !== null ? number_format((float) $row['rsi'], 1) : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
  var labels = <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>;
  var prices = <?= json_encode($chartPrices, JSON_UNESCAPED_UNICODE) ?>;
  var ctx = document.getElementById('goldPriceChart');
  if (!ctx || !window.Chart) return;

  new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [{
        label: <?= json_encode(__('gold_xau_usd'), JSON_UNESCAPED_UNICODE) ?>,
        data: prices,
        borderColor: '#d4af37',
        backgroundColor: 'rgba(212, 175, 55, 0.12)',
        fill: true,
        tension: 0.35,
        pointRadius: 0,
        pointHitRadius: 12,
        borderWidth: 2
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      plugins: {
        legend: { display: false }
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: { maxTicksLimit: 8 }
        },
        y: {
          grid: { color: 'rgba(15, 23, 42, 0.06)' },
          ticks: {
            callback: function (v) { return '$' + Number(v).toLocaleString(); }
          }
        }
      }
    }
  });

  fetch('<?= url('api/gold-data.php') ?>?_=' + Date.now(), { credentials: 'same-origin', cache: 'no-store' })
    .then(function (r) { return r.ok ? r.json() : null; })
    .then(function (d) {
      if (!d || !d.price) return;
      var priceEl = document.getElementById('gold-live-price');
      var changeEl = document.getElementById('gold-live-change');
      if (priceEl) priceEl.textContent = d.price_display;
      if (changeEl && d.change_pct !== undefined) {
        var pct = Number(d.change_pct);
        changeEl.textContent = (pct >= 0 ? '+' : '') + pct.toFixed(2) + '%';
        changeEl.classList.toggle('up', pct >= 0);
        changeEl.classList.toggle('down', pct < 0);
      }
    })
    .catch(function () {});
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
