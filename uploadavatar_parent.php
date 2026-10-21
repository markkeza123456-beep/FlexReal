<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function parentAvatarResponse(bool $success, string $message, int $status): never
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    parentAvatarResponse(false, 'คำขอไม่ถูกต้อง', 405);
}
if (empty($_SESSION['user_id']) || strtolower((string) ($_SESSION['role'] ?? '')) !== 'parent') {
    parentAvatarResponse(false, 'กรุณาล็อกอินใหม่', 401);
}

try {
    $body = json_decode(file_get_contents('php://input'), true);
    $dataUrl = is_array($body) ? (string) ($body['image'] ?? '') : '';
    if (!preg_match('/^data:image\/png;base64,([A-Za-z0-9+\/]+={0,2})$/', $dataUrl, $matches)) {
        parentAvatarResponse(false, 'ข้อมูลรูปภาพไม่ถูกต้อง', 400);
    }
    $png = base64_decode($matches[1], true);
    if ($png === false || strlen($png) > 5 * 1024 * 1024 || !str_starts_with($png, "\x89PNG\r\n\x1a\n")) {
        parentAvatarResponse(false, 'ไฟล์รูปต้องเป็น PNG และมีขนาดไม่เกิน 5MB', 400);
    }

    require_once __DIR__ . '/config/db.php';
    require_once __DIR__ . '/avatar_storage.php';
    $userId = (string) $_SESSION['user_id'];
    $url = uploadPublicAvatar('parent', $userId, $png);
    $conn->exec('ALTER TABLE public.parents ADD COLUMN IF NOT EXISTS avatar_url TEXT');
    $stmt = $conn->prepare('UPDATE public.parents SET avatar_url = :url WHERE user_id = :id');
    $stmt->execute([':url' => $url, ':id' => $userId]);
    if ($stmt->rowCount() === 0) {
        $check = $conn->prepare('SELECT 1 FROM public.parents WHERE user_id = :id');
        $check->execute([':id' => $userId]);
        if (!$check->fetchColumn()) throw new RuntimeException('ไม่พบข้อมูลผู้ปกครอง');
    }
    echo json_encode(['success' => true, 'url' => $url], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    error_log('Parent avatar save failed: ' . $error->getMessage());
    parentAvatarResponse(false, 'บันทึกรูปไม่สำเร็จ กรุณาลองใหม่', 500);
}
