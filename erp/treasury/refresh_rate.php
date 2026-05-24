<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requirePermission(PERM_TREASURY);

flash('error', 'Exchange rate is disabled. All amounts are in USD.');
redirect(url('treasury/index.php'));
