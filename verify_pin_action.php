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
    $stmt = $conn->query('SELECT user_id, token_hash FROM public.password_reset_tokens WHERE used_at IS NULL AND expires_at > NOW() ORDER BY created_at DESC');
    $tokenRow = null;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $candidate) {
        if (password_verify($pin, (string) $candidate['token_hash'])) { $tokenRow = $candidate; break; }
    }

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
