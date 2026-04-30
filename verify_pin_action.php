<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

$pin = trim((string)($_POST['pin'] ?? ''));

if ($pin === '') {
    echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกรหัส PIN']);
    exit;
}

try {
    $stmt = $conn->prepare('SELECT user_id FROM public.password_reset_tokens WHERE token = :pin AND expires_at > NOW() ORDER BY expires_at DESC LIMIT 1');
    $stmt->execute(['pin' => $pin]);
    $tokenRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tokenRow) {
        echo json_encode(['status' => 'error', 'message' => 'PIN ไม่ถูกต้อง หรือหมดอายุแล้ว']);
        exit;
    }

    $_SESSION['reset_user_id'] = $tokenRow['user_id'];
    $_SESSION['reset_pin'] = $pin;
    $_SESSION['reset_pin_verified_at'] = time();

    echo json_encode(['status' => 'success', 'message' => 'ยืนยัน PIN สำเร็จ']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการยืนยัน PIN: ' . $e->getMessage()]);
}
?>
