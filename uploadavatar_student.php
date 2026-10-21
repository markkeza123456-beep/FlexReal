<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

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

try {
    $body = json_decode(file_get_contents('php://input'), true);
    $dataUrl = is_array($body) ? (string) ($body['image'] ?? '') : '';
    if (!preg_match('/^data:image\/png;base64,([A-Za-z0-9+\/]+={0,2})$/', $dataUrl, $matches)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ข้อมูลรูปภาพไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $png = base64_decode($matches[1], true);
    if ($png === false || strlen($png) > 5 * 1024 * 1024 || !str_starts_with($png, "\x89PNG\r\n\x1a\n")) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ไฟล์รูปต้องเป็น PNG และมีขนาดไม่เกิน 5MB'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    require_once __DIR__ . '/config/db.php';
    require_once __DIR__ . '/avatar_storage.php';
    $userId = (string) $_SESSION['user_id'];
    $url = uploadPublicAvatar('student', $userId, $png);
    $stmt = $conn->prepare('UPDATE public.students SET avatar_url = :url WHERE user_id = :id');
    $stmt->execute([':url' => $url, ':id' => $userId]);
    if ($stmt->rowCount() === 0) {
        $check = $conn->prepare('SELECT 1 FROM public.students WHERE user_id = :id');
        $check->execute([':id' => $userId]);
        if (!$check->fetchColumn()) throw new RuntimeException('ไม่พบข้อมูลนักเรียน');
    }
    echo json_encode(['success' => true, 'url' => $url], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    error_log('Student avatar save failed: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'บันทึกรูปไม่สำเร็จ กรุณาลองใหม่'], JSON_UNESCAPED_UNICODE);
}
