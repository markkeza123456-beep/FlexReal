<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$selected = strtolower((string) ($_GET['lang'] ?? ''));
if (!in_array($selected, ['th', 'en'], true)) {
    http_response_code(400);
    exit('Unsupported language');
}

$_SESSION['lang'] = $selected;
setcookie('flh_lang', $selected, [
    'expires' => time() + 60 * 60 * 24 * 365,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => false,
    'samesite' => 'Lax',
]);

$referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
$refererHost = parse_url($referer, PHP_URL_HOST);
$requestHost = preg_replace('/:\\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
if ($referer !== '' && $refererHost !== null && strcasecmp((string) $refererHost, $requestHost) === 0) {
    header('Location: ' . $referer);
} else {
    header('Location: index.html');
}
exit;
