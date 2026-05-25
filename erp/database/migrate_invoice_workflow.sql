ALTER TABLE invoices
    MODIFY COLUMN payment_method ENUM('cash', 'transfer', 'deferred') NOT NULL DEFAULT 'cash';

ALTER TABLE invoices
    ADD COLUMN invoice_type ENUM('sale', 'gift') NOT NULL DEFAULT 'sale' AFTER payment_method;
