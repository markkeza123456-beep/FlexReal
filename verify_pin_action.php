<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

$pin = trim((string)($_POST['pin'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !preg_match('/^\d{6}$/', $pin)) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกรหัส PIN']);
    exit;
}

$userId = (string) ($_SESSION['password_reset_user_id'] ?? '');
$requestedAt = (int) ($_SESSION['password_reset_requested_at'] ?? 0);
if ($userId === '' || $requestedAt < time() - 900) {
    unset($_SESSION['password_reset_user_id'], $_SESSION['password_reset_requested_at']);
    echo json_encode(['status' => 'error', 'message' => 'คำขอรีเซ็ตรหัสผ่านหมดอายุ กรุณาขอ PIN ใหม่']);
    exit;
}

try {
    $stmt = $conn->prepare('SELECT token_id, token_hash FROM public.password_reset_tokens WHERE user_id = :uid AND used_at IS NULL AND expires_at > NOW() ORDER BY created_at DESC');
    $stmt->execute([':uid' => $userId]);
    $tokenRow = null;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $candidate) {
        if (password_verify($pin, (string) $candidate['token_hash'])) { $tokenRow = $candidate; break; }
    }

    if (!$tokenRow) {
        echo json_encode(['status' => 'error', 'message' => 'PIN ไม่ถูกต้อง หรือหมดอายุแล้ว']);
        exit;
    }

    $_SESSION['reset_user_id'] = $userId;
    $_SESSION['reset_token_id'] = (int) $tokenRow['token_id'];
    $_SESSION['reset_pin_verified_at'] = time();

    echo json_encode(['status' => 'success', 'message' => 'ยืนยัน PIN สำเร็จ']);
} catch (Throwable $e) {
    error_log('Password reset PIN verification failed: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถยืนยัน PIN ได้ กรุณาลองใหม่']);
}
?>
