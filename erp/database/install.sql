CREATE DATABASE IF NOT EXISTS erp_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE erp_system;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'manager', 'sales', 'driver', 'staff') DEFAULT 'staff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(50) NOT NULL UNIQUE,
    name_ar VARCHAR(200) NOT NULL,
    name_en VARCHAR(200) NOT NULL,
    description_ar TEXT,
    description_en TEXT,
    category VARCHAR(100),
    unit VARCHAR(30) DEFAULT 'piece',
    quantity INT DEFAULT 0,
    min_stock INT DEFAULT 5,
    cost_price DECIMAL(12,2) DEFAULT 0,
    sell_price DECIMAL(12,2) DEFAULT 0,
    image VARCHAR(255) DEFAULT NULL,
    is_published TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    type ENUM('in', 'out', 'adjustment') NOT NULL,
    quantity INT NOT NULL,
    reference VARCHAR(100),
    notes TEXT,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150),
    phone VARCHAR(30),
    address TEXT,
    tax_number VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(30) NOT NULL UNIQUE,
    customer_id INT NOT NULL,
    subtotal DECIMAL(12,2) DEFAULT 0,
    tax_rate DECIMAL(5,2) DEFAULT 15,
    tax_amount DECIMAL(12,2) DEFAULT 0,
    discount DECIMAL(12,2) DEFAULT 0,
    total DECIMAL(12,2) DEFAULT 0,
    status ENUM('draft', 'sent', 'paid', 'cancelled') DEFAULT 'draft',
    due_date DATE,
    notes TEXT,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    product_id INT,
    description VARCHAR(255) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL,
    total DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_code VARCHAR(20) NOT NULL UNIQUE,
    name_ar VARCHAR(150) NOT NULL,
    name_en VARCHAR(150) NOT NULL,
    email VARCHAR(150),
    phone VARCHAR(30),
    department VARCHAR(100),
    job_title VARCHAR(100),
    salary DECIMAL(12,2) DEFAULT 0,
    hire_date DATE,
    status ENUM('active', 'inactive', 'terminated') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS payroll (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    month TINYINT NOT NULL,
    year SMALLINT NOT NULL,
    base_salary DECIMAL(12,2) NOT NULL,
    bonus DECIMAL(12,2) DEFAULT 0,
    deductions DECIMAL(12,2) DEFAULT 0,
    net_salary DECIMAL(12,2) NOT NULL,
    status ENUM('pending', 'paid') DEFAULT 'pending',
    paid_at DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE KEY unique_payroll (employee_id, month, year)
);

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

CREATE TABLE IF NOT EXISTS deliveries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    delivery_number VARCHAR(30) NOT NULL UNIQUE,
    customer_id INT,
    invoice_id INT,
    order_id INT,
    driver_name VARCHAR(100),
    vehicle_number VARCHAR(30),
    delivery_address TEXT NOT NULL,
    status ENUM('pending', 'in_transit', 'delivered', 'cancelled') DEFAULT 'pending',
    scheduled_date DATE,
    delivered_at DATETIME,
    notes TEXT,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS delivery_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    delivery_id INT NOT NULL,
    product_id INT,
    description VARCHAR(255) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

-- Sample data (admin user is created by setup.php)
INSERT INTO products (sku, name_ar, name_en, category, quantity, min_stock, cost_price, sell_price) VALUES
('IKOS-D001', 'جهاز IKOS Pro', 'IKOS Pro Device', 'IKOS Devices', 40, 8, 450.00, 699.00),
('IKOS-D002', 'جهاز IKOS Lite', 'IKOS Lite Device', 'IKOS Devices', 35, 8, 320.00, 499.00),
('IKOS-D003', 'جهاز IKOS Max', 'IKOS Max Device', 'IKOS Devices', 20, 5, 580.00, 899.00),
('IKOS-A001', 'كابل شحن IKOS USB-C', 'IKOS USB-C Charging Cable', 'Accessories', 120, 25, 25.00, 49.00),
('IKOS-A002', 'غطاء حماية IKOS', 'IKOS Protective Case', 'Accessories', 80, 15, 18.00, 39.00),
('IKOS-A003', 'حامل سيارة IKOS', 'IKOS Car Mount', 'Accessories', 45, 10, 35.00, 69.00),
('IKOS-A004', 'سماعات IKOS Bluetooth', 'IKOS Bluetooth Earbuds', 'Accessories', 60, 12, 85.00, 149.00),
('ACLM-001', 'اشتراك Asma Cloud - سنة', 'Asma Cloud Subscription - 1 Year', 'Asma Cloud & More', 999, 0, 120.00, 199.00),
('ACLM-002', 'باقة Asma Cloud & More - شهر', 'Asma Cloud & More - Monthly', 'Asma Cloud & More', 999, 0, 15.00, 29.00),
('IKOS-C001', 'Pods IKOS - علبة', 'IKOS Pods - Pack', 'Consumables', 200, 40, 45.00, 79.00),
('IKOS-C002', 'فلتر IKOS - 5 قطع', 'IKOS Filter - 5 Pack', 'Consumables', 150, 30, 22.00, 45.00);

INSERT INTO customers (name, email, phone, address) VALUES
('متجر الريادة', 'sales@riyada-store.com', '+966501112233', 'الرياض، حي النخيل'),
('Cloud Solutions Co.', 'orders@cloudsolutions.com', '+966504445566', 'Jeddah, Al Andalus');

INSERT INTO employees (employee_code, name_ar, name_en, email, department, job_title, salary, hire_date) VALUES
('EMP-001', 'أحمد محمد', 'Ahmed Mohammed', 'ahmed@ikos.com', 'Sales', 'IKOS Sales Manager', 12000.00, '2023-01-15'),
('EMP-002', 'سارة علي', 'Sara Ali', 'sara@ikos.com', 'Support', 'Asma Cloud Specialist', 9500.00, '2023-06-01'),
('EMP-003', 'خالد عبدالله', 'Khalid Abdullah', 'khalid@ikos.com', 'Logistics', 'Delivery Driver', 6000.00, '2024-02-10');
