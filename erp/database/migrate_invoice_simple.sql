ALTER TABLE invoices ADD COLUMN payment_method ENUM('cash', 'transfer') NOT NULL DEFAULT 'cash' AFTER status;
ALTER TABLE invoice_items ADD COLUMN serial_number VARCHAR(100) NULL AFTER description;
