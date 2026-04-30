<?php
session_start();

$_SESSION = array();

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

$return = (string)($_GET['return'] ?? '');
$allowed = ['web.html', 'login.php'];
if (!in_array($return, $allowed, true)) {
    $return = 'login.php';
}

header('Location: ' . $return);
exit();
?>
