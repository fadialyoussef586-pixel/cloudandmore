<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin', 'manager']);

flash('error', 'Exchange rate is disabled. All amounts are in USD.');
redirect(url('treasury/index.php'));
