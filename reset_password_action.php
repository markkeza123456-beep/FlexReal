<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

$pin = $_POST['pin'] ?? '';
$new_pass = $_POST['new_password'] ?? '';

try {
    $stmt = $conn->prepare("SELECT user_id FROM public.password_reset_tokens WHERE token = :tk AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
    $stmt->execute(['tk' => $pin]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($res) {
        $stmt_up = $conn->prepare("UPDATE public.student SET password = :pw WHERE student_id = :uid");
        $stmt_up->execute(['pw' => $new_pass, 'uid' => $res['user_id']]);

        $stmt_del = $conn->prepare("DELETE FROM public.password_reset_tokens WHERE user_id = :uid");
        $stmt_del->execute(['uid' => $res['user_id']]);

        echo json_encode(['status' => 'success', 'message' => 'เปลี่ยนรหัสผ่านสำเร็จ!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'PIN ไม่ถูกต้องหรือหมดอายุ']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'ระบบขัดข้อง']);
}