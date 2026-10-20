<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function parentAvatarResponse(bool $success, string $message, array $extra = [], int $status = 200): never
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') parentAvatarResponse(false, 'คำขอไม่ถูกต้อง', [], 405);
if (empty($_SESSION['user_id']) || strtolower((string) ($_SESSION['role'] ?? '')) !== 'parent') {
    parentAvatarResponse(false, 'กรุณาล็อกอินใหม่', [], 401);
}

$data = json_decode(file_get_contents('php://input'), true);
$dataUrl = is_array($data) ? (string) ($data['image'] ?? '') : '';
if (!preg_match('/^data:image\/png;base64,([A-Za-z0-9+\/]+={0,2})$/', $dataUrl, $matches)) {
    parentAvatarResponse(false, 'ข้อมูลรูปภาพไม่ถูกต้อง กรุณาเลือกรูปใหม่', [], 400);
}
$png = base64_decode($matches[1], true);
if ($png === false || strlen($png) > 5 * 1024 * 1024 || !str_starts_with($png, "\x89PNG\r\n\x1a\n")) {
    parentAvatarResponse(false, 'ไฟล์รูปต้องเป็น PNG และมีขนาดไม่เกิน 5MB', [], 400);
}

try {
    require_once __DIR__ . '/config/db.php';
    require_once __DIR__ . '/avatar_storage.php';
    $conn->exec('ALTER TABLE public.parents ADD COLUMN IF NOT EXISTS avatar_url TEXT');
    $userId = (string) $_SESSION['user_id'];
    $url = uploadPublicAvatar('parent', $userId, $png);
    $stmt = $conn->prepare('UPDATE public.parents SET avatar_url = :url WHERE user_id = :uid');
    $stmt->execute([':url' => $url, ':uid' => $userId]);
    if ($stmt->rowCount() === 0) {
        $check = $conn->prepare('SELECT 1 FROM public.parents WHERE user_id = :uid');
        $check->execute([':uid' => $userId]);
        if (!$check->fetchColumn()) throw new RuntimeException('Parent profile was not found.');
    }
    parentAvatarResponse(true, 'บันทึกรูปโปรไฟล์สำเร็จ', ['url' => $url]);
} catch (Throwable $error) {
    error_log('Parent avatar save failed: ' . $error->getMessage());
    parentAvatarResponse(false, 'บันทึกรูปไม่สำเร็จ กรุณาลองใหม่', [], 500);
}
