ALTER TABLE orders ADD COLUMN payment_method ENUM('cod', 'transfer', 'pickup') NOT NULL DEFAULT 'cod' AFTER total;
ALTER TABLE orders ADD COLUMN delivery_fee DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER payment_method;
ALTER TABLE orders ADD COLUMN invoice_id INT NULL AFTER delivery_fee;
