<?php

require_once __DIR__ . '/erp/includes/functions.php';
require_once __DIR__ . '/erp/includes/auth.php';

requireOwner();
redirect(url('reset-data.php'));
