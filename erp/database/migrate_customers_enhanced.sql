ALTER TABLE customers
    ADD COLUMN notes TEXT DEFAULT NULL,
    ADD COLUMN rating_avg DECIMAL(3,2) NOT NULL DEFAULT 0,
    ADD COLUMN rating_count INT NOT NULL DEFAULT 0,
    ADD COLUMN is_blocked TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

CREATE TABLE IF NOT EXISTS customer_ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    score TINYINT NOT NULL,
    comment TEXT,
    source ENUM('manual', 'invoice', 'maintenance', 'order') NOT NULL DEFAULT 'manual',
    reference_id INT DEFAULT NULL,
    user_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_customer_ratings_customer (customer_id),
    INDEX idx_customer_ratings_created (created_at)
);
