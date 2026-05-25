<?php

require_once __DIR__ . '/erp/config/database.php';

$pdo = db();

// مصفوفة بأسماء كل الجداول بالسيستم عشان نمسحها كلياً
$tables = ['purchases', 'sales', 'treasury', 'cash_boxes', 'products', 'employees', 'invoices', 'orders', 'users'];

header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0;');
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `$table`;");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1;');
    echo 'تم مسح جميع الجداول من الجذور بنجاح! قاعدة البيانات الآن فاضية 100%.';
} catch (PDOException $e) {
    echo 'خطأ: ' . $e->getMessage();
}
