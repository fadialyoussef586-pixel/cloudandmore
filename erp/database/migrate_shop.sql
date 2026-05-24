ALTER TABLE products ADD COLUMN image VARCHAR(255) DEFAULT NULL;
ALTER TABLE products ADD COLUMN is_published TINYINT(1) DEFAULT 1;
ALTER TABLE deliveries ADD COLUMN order_id INT NULL;

CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(30) NOT NULL UNIQUE,
    customer_id INT,
    customer_name VARCHAR(150) NOT NULL,
    customer_email VARCHAR(150),
    customer_phone VARCHAR(30) NOT NULL,
    delivery_address TEXT NOT NULL,
    subtotal DECIMAL(12,2) DEFAULT 0,
    total DECIMAL(12,2) DEFAULT 0,
    status ENUM('new', 'confirmed', 'ready_for_delivery', 'out_for_delivery', 'delivered', 'cancelled') DEFAULT 'new',
    source ENUM('website', 'manual') DEFAULT 'website',
    sales_user_id INT,
    delivery_user_id INT,
    notes TEXT,
    confirmed_at DATETIME,
    handed_at DATETIME,
    delivered_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (sales_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (delivery_user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT,
    description VARCHAR(255) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL,
    total DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);
