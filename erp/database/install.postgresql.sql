-- PostgreSQL schema for IKOS ERP (Render)

CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'staff' CHECK (role IN ('admin', 'manager', 'sales', 'driver', 'staff')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS products (
    id SERIAL PRIMARY KEY,
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
    is_published BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS stock_movements (
    id SERIAL PRIMARY KEY,
    product_id INT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    type VARCHAR(20) NOT NULL CHECK (type IN ('in', 'out', 'adjustment')),
    quantity INT NOT NULL,
    reference VARCHAR(100),
    notes TEXT,
    user_id INT REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS customers (
    id SERIAL PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150),
    phone VARCHAR(30),
    address TEXT,
    tax_number VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS invoices (
    id SERIAL PRIMARY KEY,
    invoice_number VARCHAR(30) NOT NULL UNIQUE,
    customer_id INT NOT NULL REFERENCES customers(id),
    subtotal DECIMAL(12,2) DEFAULT 0,
    tax_rate DECIMAL(5,2) DEFAULT 15,
    tax_amount DECIMAL(12,2) DEFAULT 0,
    discount DECIMAL(12,2) DEFAULT 0,
    total DECIMAL(12,2) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'draft' CHECK (status IN ('draft', 'sent', 'paid', 'cancelled')),
    due_date DATE,
    notes TEXT,
    user_id INT REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS invoice_items (
    id SERIAL PRIMARY KEY,
    invoice_id INT NOT NULL REFERENCES invoices(id) ON DELETE CASCADE,
    product_id INT REFERENCES products(id) ON DELETE SET NULL,
    description VARCHAR(255) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL,
    total DECIMAL(12,2) NOT NULL
);

CREATE TABLE IF NOT EXISTS employees (
    id SERIAL PRIMARY KEY,
    employee_code VARCHAR(20) NOT NULL UNIQUE,
    name_ar VARCHAR(150) NOT NULL,
    name_en VARCHAR(150) NOT NULL,
    email VARCHAR(150),
    phone VARCHAR(30),
    department VARCHAR(100),
    job_title VARCHAR(100),
    salary DECIMAL(12,2) DEFAULT 0,
    hire_date DATE,
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'inactive', 'terminated')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS payroll (
    id SERIAL PRIMARY KEY,
    employee_id INT NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    month SMALLINT NOT NULL,
    year SMALLINT NOT NULL,
    base_salary DECIMAL(12,2) NOT NULL,
    bonus DECIMAL(12,2) DEFAULT 0,
    deductions DECIMAL(12,2) DEFAULT 0,
    net_salary DECIMAL(12,2) NOT NULL,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'paid')),
    paid_at DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (employee_id, month, year)
);

CREATE TABLE IF NOT EXISTS orders (
    id SERIAL PRIMARY KEY,
    order_number VARCHAR(30) NOT NULL UNIQUE,
    customer_id INT REFERENCES customers(id) ON DELETE SET NULL,
    customer_name VARCHAR(150) NOT NULL,
    customer_email VARCHAR(150),
    customer_phone VARCHAR(30) NOT NULL,
    delivery_address TEXT NOT NULL,
    subtotal DECIMAL(12,2) DEFAULT 0,
    total DECIMAL(12,2) DEFAULT 0,
    status VARCHAR(30) DEFAULT 'new' CHECK (status IN ('new', 'confirmed', 'ready_for_delivery', 'out_for_delivery', 'delivered', 'cancelled')),
    source VARCHAR(20) DEFAULT 'website' CHECK (source IN ('website', 'manual')),
    sales_user_id INT REFERENCES users(id) ON DELETE SET NULL,
    delivery_user_id INT REFERENCES users(id) ON DELETE SET NULL,
    notes TEXT,
    confirmed_at TIMESTAMP,
    handed_at TIMESTAMP,
    delivered_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS order_items (
    id SERIAL PRIMARY KEY,
    order_id INT NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    product_id INT REFERENCES products(id) ON DELETE SET NULL,
    description VARCHAR(255) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL,
    total DECIMAL(12,2) NOT NULL
);

CREATE TABLE IF NOT EXISTS deliveries (
    id SERIAL PRIMARY KEY,
    delivery_number VARCHAR(30) NOT NULL UNIQUE,
    customer_id INT REFERENCES customers(id) ON DELETE SET NULL,
    invoice_id INT REFERENCES invoices(id) ON DELETE SET NULL,
    order_id INT REFERENCES orders(id) ON DELETE SET NULL,
    driver_name VARCHAR(100),
    vehicle_number VARCHAR(30),
    delivery_address TEXT NOT NULL,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'in_transit', 'delivered', 'cancelled')),
    scheduled_date DATE,
    delivered_at TIMESTAMP,
    notes TEXT,
    user_id INT REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS delivery_items (
    id SERIAL PRIMARY KEY,
    delivery_id INT NOT NULL REFERENCES deliveries(id) ON DELETE CASCADE,
    product_id INT REFERENCES products(id) ON DELETE SET NULL,
    description VARCHAR(255) NOT NULL,
    quantity INT NOT NULL DEFAULT 1
);

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
('IKOS-C002', 'فلتر IKOS - 5 قطع', 'IKOS Filter - 5 Pack', 'Consumables', 150, 30, 22.00, 45.00)
ON CONFLICT (sku) DO NOTHING;

INSERT INTO customers (name, email, phone, address) VALUES
('متجر الريادة', 'sales@riyada-store.com', '+966501112233', 'الرياض، حي النخيل'),
('Cloud Solutions Co.', 'orders@cloudsolutions.com', '+966504445566', 'Jeddah, Al Andalus');

INSERT INTO employees (employee_code, name_ar, name_en, email, department, job_title, salary, hire_date) VALUES
('EMP-001', 'أحمد محمد', 'Ahmed Mohammed', 'ahmed@ikos.com', 'Sales', 'IKOS Sales Manager', 12000.00, '2023-01-15'),
('EMP-002', 'سارة علي', 'Sara Ali', 'sara@ikos.com', 'Support', 'Asma Cloud Specialist', 9500.00, '2023-06-01'),
('EMP-003', 'خالد عبدالله', 'Khalid Abdullah', 'khalid@ikos.com', 'Logistics', 'Delivery Driver', 6000.00, '2024-02-10')
ON CONFLICT (employee_code) DO NOTHING;
