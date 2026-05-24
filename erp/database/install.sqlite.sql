PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'staff' CHECK(role IN ('admin', 'manager', 'sales', 'driver', 'staff')),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sku TEXT NOT NULL UNIQUE,
    name_ar TEXT NOT NULL,
    name_en TEXT NOT NULL,
    description_ar TEXT,
    description_en TEXT,
    category TEXT,
    unit TEXT DEFAULT 'piece',
    quantity INTEGER DEFAULT 0,
    min_stock INTEGER DEFAULT 5,
    cost_price REAL DEFAULT 0,
    sell_price REAL DEFAULT 0,
    image TEXT,
    is_published INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS stock_movements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    product_id INTEGER NOT NULL,
    type TEXT NOT NULL CHECK(type IN ('in', 'out', 'adjustment')),
    quantity INTEGER NOT NULL,
    reference TEXT,
    notes TEXT,
    user_id INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS customers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT,
    phone TEXT,
    address TEXT,
    tax_number TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS invoices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_number TEXT NOT NULL UNIQUE,
    customer_id INTEGER NOT NULL,
    subtotal REAL DEFAULT 0,
    tax_rate REAL DEFAULT 15,
    tax_amount REAL DEFAULT 0,
    discount REAL DEFAULT 0,
    total REAL DEFAULT 0,
    status TEXT DEFAULT 'draft' CHECK(status IN ('draft', 'sent', 'paid', 'cancelled')),
    due_date TEXT,
    notes TEXT,
    user_id INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS invoice_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_id INTEGER NOT NULL,
    product_id INTEGER,
    description TEXT NOT NULL,
    quantity INTEGER NOT NULL DEFAULT 1,
    unit_price REAL NOT NULL,
    total REAL NOT NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS employees (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_code TEXT NOT NULL UNIQUE,
    name_ar TEXT NOT NULL,
    name_en TEXT NOT NULL,
    email TEXT,
    phone TEXT,
    department TEXT,
    job_title TEXT,
    salary REAL DEFAULT 0,
    hire_date TEXT,
    status TEXT DEFAULT 'active' CHECK(status IN ('active', 'inactive', 'terminated')),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS payroll (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER NOT NULL,
    month INTEGER NOT NULL,
    year INTEGER NOT NULL,
    base_salary REAL NOT NULL,
    bonus REAL DEFAULT 0,
    deductions REAL DEFAULT 0,
    net_salary REAL NOT NULL,
    status TEXT DEFAULT 'pending' CHECK(status IN ('pending', 'paid')),
    paid_at TEXT,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE(employee_id, month, year)
);

CREATE TABLE IF NOT EXISTS orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_number TEXT NOT NULL UNIQUE,
    customer_id INTEGER,
    customer_name TEXT NOT NULL,
    customer_email TEXT,
    customer_phone TEXT NOT NULL,
    delivery_address TEXT NOT NULL,
    subtotal REAL DEFAULT 0,
    total REAL DEFAULT 0,
    status TEXT DEFAULT 'new' CHECK(status IN ('new', 'confirmed', 'ready_for_delivery', 'out_for_delivery', 'delivered', 'cancelled')),
    source TEXT DEFAULT 'website' CHECK(source IN ('website', 'manual')),
    sales_user_id INTEGER,
    delivery_user_id INTEGER,
    notes TEXT,
    confirmed_at DATETIME,
    handed_at DATETIME,
    delivered_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (sales_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (delivery_user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS order_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL,
    product_id INTEGER,
    description TEXT NOT NULL,
    quantity INTEGER NOT NULL DEFAULT 1,
    unit_price REAL NOT NULL,
    total REAL NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS deliveries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    delivery_number TEXT NOT NULL UNIQUE,
    customer_id INTEGER,
    invoice_id INTEGER,
    order_id INTEGER,
    driver_name TEXT,
    vehicle_number TEXT,
    delivery_address TEXT NOT NULL,
    status TEXT DEFAULT 'pending' CHECK(status IN ('pending', 'in_transit', 'delivered', 'cancelled')),
    scheduled_date TEXT,
    delivered_at DATETIME,
    notes TEXT,
    user_id INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS delivery_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    delivery_id INTEGER NOT NULL,
    product_id INTEGER,
    description TEXT NOT NULL,
    quantity INTEGER NOT NULL DEFAULT 1,
    FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

INSERT OR IGNORE INTO products (sku, name_ar, name_en, category, quantity, min_stock, cost_price, sell_price) VALUES
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

INSERT OR IGNORE INTO customers (name, email, phone, address) VALUES
('متجر الريادة', 'sales@riyada-store.com', '+966501112233', 'الرياض، حي النخيل'),
('Cloud Solutions Co.', 'orders@cloudsolutions.com', '+966504445566', 'Jeddah, Al Andalus');

INSERT OR IGNORE INTO employees (employee_code, name_ar, name_en, email, department, job_title, salary, hire_date) VALUES
('EMP-001', 'أحمد محمد', 'Ahmed Mohammed', 'ahmed@ikos.com', 'Sales', 'IKOS Sales Manager', 12000.00, '2023-01-15'),
('EMP-002', 'سارة علي', 'Sara Ali', 'sara@ikos.com', 'Support', 'Asma Cloud Specialist', 9500.00, '2023-06-01'),
('EMP-003', 'خالد عبدالله', 'Khalid Abdullah', 'khalid@ikos.com', 'Logistics', 'Delivery Driver', 6000.00, '2024-02-10');
