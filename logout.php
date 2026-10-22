<?php
session_start();

if (!empty($_SESSION['site_visit_id'])) {
    try {
        require_once __DIR__ . '/config/db.php';
        $visit = $conn->prepare('UPDATE public.site_visits SET exited_at = NOW() WHERE visit_id = :visit_id AND user_id = :user_id AND exited_at IS NULL');
        $visit->execute([
            ':visit_id' => (int) $_SESSION['site_visit_id'],
            ':user_id' => (string) ($_SESSION['user_id'] ?? ''),
        ]);
    } catch (Throwable $trackingError) {
        error_log('[site_visits] logout tracking failed: ' . get_class($trackingError));
    }
}

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
$allowed = ['index.php', 'index.html', 'login.php'];
if (!in_array($return, $allowed, true)) {
    $return = 'login.php';
}

header('Location: ' . $return);
exit();
?>
