<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (isset($_GET['lang']) && in_array(strtolower((string) $_GET['lang']), ['th', 'en'], true)) {
    $_SESSION['lang'] = strtolower((string) $_GET['lang']);
}
$langCode = $_SESSION['lang'] ?? 'th';
$lang = require __DIR__ . ($langCode === 'en' ? '/lang_en.php' : '/lang_th.php');
