CREATE TABLE IF NOT EXISTS exchange_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rate_date DATE NOT NULL UNIQUE,
    usd_to_sar DECIMAL(12,6) NOT NULL,
    source VARCHAR(50) DEFAULT 'api',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS treasury_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference_number VARCHAR(40) NOT NULL UNIQUE,
    type ENUM('deposit', 'withdrawal') NOT NULL DEFAULT 'deposit',
    category VARCHAR(50) NOT NULL DEFAULT 'external_funding',
    currency ENUM('SAR', 'USD') NOT NULL DEFAULT 'SAR',
    amount DECIMAL(14,2) NOT NULL,
    amount_sar DECIMAL(14,2) NOT NULL,
    description TEXT,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS cash_accounts (
    id INT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    currency ENUM('SAR', 'USD') NOT NULL DEFAULT 'USD',
    balance DECIMAL(14,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

ALTER TABLE treasury_transactions
    ADD COLUMN cash_account_id INT NOT NULL DEFAULT 1 AFTER user_id;

ALTER TABLE treasury_transactions
    ADD COLUMN balance_after DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER cash_account_id;

INSERT INTO exchange_rates (rate_date, usd_to_sar, source) VALUES (CURDATE(), 3.750000, 'default')
ON DUPLICATE KEY UPDATE usd_to_sar = VALUES(usd_to_sar);
