<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/avatar_storage.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    echo json_encode(['success' => false, 'message' => 'กรุณาล็อกอินใหม่']);
    exit;
}

$user_id = (string) $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['image']) || !is_string($data['image']) || !preg_match('/^data:image\/png;base64,/', $data['image'])) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลรูปภาพ']);
    exit;
}

$base64 = substr($data['image'], strlen('data:image/png;base64,'));
$binary = base64_decode($base64, true);
if ($binary === false || strlen($binary) > 5 * 1024 * 1024 || !str_starts_with($binary, "\x89PNG\r\n\x1a\n")) {
    echo json_encode(['success' => false, 'message' => 'ไฟล์รูปต้องเป็น PNG และมีขนาดไม่เกิน 5MB'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $publicUrl = uploadPublicAvatar('student', $user_id, $binary);
    $stmt = $conn->prepare(
        'UPDATE public.students SET avatar_url = :url, updated_at = NOW() WHERE user_id = :uid'
    );
    $stmt->execute(['url' => $publicUrl, 'uid' => $user_id]);
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'อัปโหลดรูปแล้ว แต่ไม่พบข้อมูลนักเรียนสำหรับบันทึกรูป'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['success' => true, 'url' => $publicUrl]);
} catch (Throwable $e) {
    error_log('Student avatar save failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'อัปโหลดรูปแล้ว แต่บันทึกข้อมูลรูปไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
}
?>
