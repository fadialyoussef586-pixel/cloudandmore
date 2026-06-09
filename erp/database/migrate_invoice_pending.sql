ALTER TABLE invoices
    MODIFY COLUMN payment_method ENUM('cash', 'transfer', 'deferred', 'pending') NOT NULL DEFAULT 'cash';
