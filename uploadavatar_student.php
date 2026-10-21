<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/avatar_storage.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'คำขอไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (empty($_SESSION['user_id']) || strtolower((string) ($_SESSION['role'] ?? '')) !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'กรุณาล็อกอินใหม่'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = (string) $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$dataUrl = is_array($data) ? (string) ($data['image'] ?? '') : '';
if (!preg_match('/^data:image\/png;base64,([A-Za-z0-9+\/]+={0,2})$/', $dataUrl, $matches)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ข้อมูลรูปภาพไม่ถูกต้อง กรุณาเลือกรูปใหม่'], JSON_UNESCAPED_UNICODE);
    exit;
}

$binary = base64_decode($matches[1], true);
if ($binary === false || strlen($binary) > 5 * 1024 * 1024 || !str_starts_with($binary, "\x89PNG\r\n\x1a\n")) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ไฟล์รูปต้องเป็น PNG และมีขนาดไม่เกิน 5MB'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $publicUrl = uploadPublicAvatar('student', $userId, $binary);
    $stmt = $conn->prepare(
        'UPDATE public.students SET avatar_url = :url, updated_at = NOW() WHERE user_id = :uid RETURNING avatar_url'
    );
    $stmt->execute([':url' => $publicUrl, ':uid' => $userId]);
    $savedUrl = $stmt->fetchColumn();
    if (!is_string($savedUrl) || $savedUrl === '') throw new RuntimeException('Student profile was not found or avatar URL was not saved.');
    $conn->prepare('UPDATE public.users SET updated_by = :updated_by, updated_at = NOW() WHERE user_id = :uid')
        ->execute([':updated_by' => $userId, ':uid' => $userId]);
    echo json_encode(['success' => true, 'url' => $savedUrl], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Student avatar save failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'อัปโหลดรูปแล้ว แต่บันทึกข้อมูลรูปไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
}
?>
