<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    if (empty($_SESSION['user_id']) || strtolower((string) ($_SESSION['role'] ?? '')) !== 'staff') {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'กรุณาล็อกอินใหม่'], JSON_UNESCAPED_UNICODE);
        exit;
    }
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
    $conn->exec('ALTER TABLE public.staff ADD COLUMN IF NOT EXISTS avatar_url TEXT');
    $userId = (string) $_SESSION['user_id'];
    $url = uploadPublicAvatar('staff', $userId, $png);
    $stmt = $conn->prepare('UPDATE public.staff SET avatar_url = :url WHERE user_id = :id');
    $stmt->execute([':url' => $url, ':id' => $userId]);
    if ($stmt->rowCount() === 0) {
        $check = $conn->prepare('SELECT 1 FROM public.staff WHERE user_id = :id');
        $check->execute([':id' => $userId]);
        if (!$check->fetchColumn()) throw new RuntimeException('ไม่พบข้อมูลเจ้าหน้าที่');
    }
    echo json_encode(['success' => true, 'url' => $url], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    error_log('Staff avatar save failed: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'บันทึกรูปไม่สำเร็จ กรุณาลองใหม่'], JSON_UNESCAPED_UNICODE);
}
