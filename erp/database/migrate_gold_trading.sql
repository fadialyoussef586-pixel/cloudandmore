CREATE TABLE IF NOT EXISTS gold_prices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    price_usd DECIMAL(12,4) NOT NULL,
    change_pct DECIMAL(8,4) DEFAULT 0,
    high_24h DECIMAL(12,4) DEFAULT NULL,
    low_24h DECIMAL(12,4) DEFAULT NULL,
    source VARCHAR(50) DEFAULT 'api',
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_gold_prices_recorded (recorded_at)
);

CREATE TABLE IF NOT EXISTS gold_predictions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    price_at_prediction DECIMAL(12,4) NOT NULL,
    signal ENUM('strong_buy', 'buy', 'hold', 'sell', 'strong_sell') NOT NULL DEFAULT 'hold',
    confidence TINYINT NOT NULL DEFAULT 50,
    target_price DECIMAL(12,4) DEFAULT NULL,
    stop_loss DECIMAL(12,4) DEFAULT NULL,
    rsi DECIMAL(8,4) DEFAULT NULL,
    sma_20 DECIMAL(12,4) DEFAULT NULL,
    sma_50 DECIMAL(12,4) DEFAULT NULL,
    macd DECIMAL(12,6) DEFAULT NULL,
    macd_signal DECIMAL(12,6) DEFAULT NULL,
    analysis_ar TEXT,
    analysis_en TEXT,
    timeframe ENUM('1h', '4h', '1d') NOT NULL DEFAULT '1d',
    user_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_gold_predictions_created (created_at)
);

CREATE TABLE IF NOT EXISTS gold_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alert_type ENUM('price_above', 'price_below', 'signal_change') NOT NULL,
    threshold DECIMAL(12,4) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    triggered_at TIMESTAMP NULL DEFAULT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
