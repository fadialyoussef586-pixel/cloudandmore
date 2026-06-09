<?php

require_once __DIR__ . '/includes/functions.php';

if (isset($_GET['lang']) && in_array($_GET['lang'], ['ar', 'en'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
    setcookie('lang', $_GET['lang'], time() + 86400 * 365, '/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lang = $_POST['lang'] ?? (defined('APP_LANG_DEFAULT') ? APP_LANG_DEFAULT : 'en');
    if (in_array($lang, ['ar', 'en'], true)) {
        $_SESSION['lang'] = $lang;
        setcookie('lang', $lang, time() + 86400 * 365, '/');
    }
}

$redirect = url('index.php');
if (!empty($_SERVER['HTTP_REFERER'])) {
    $referer = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) ?: '';
    $base = BASE_URL === '' ? '' : BASE_URL;
    if ($referer !== '' && ($base === '' || str_starts_with($referer, $base))) {
        $redirect = $_SERVER['HTTP_REFERER'];
    }
}
header('Location: ' . $redirect);
exit;
