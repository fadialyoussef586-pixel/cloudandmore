<?php

require_once __DIR__ . '/includes/functions.php';

if (isset($_GET['lang']) && in_array($_GET['lang'], ['ar', 'en'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
    setcookie('lang', $_GET['lang'], time() + 86400 * 365, '/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lang = $_POST['lang'] ?? 'en';
    if (in_array($lang, ['ar', 'en'], true)) {
        $_SESSION['lang'] = $lang;
        setcookie('lang', $lang, time() + 86400 * 365, '/');
    }
}

$redirect = $_SERVER['HTTP_REFERER'] ?? url('index.php');
header('Location: ' . $redirect);
exit;
