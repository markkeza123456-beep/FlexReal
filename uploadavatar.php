<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

function avatarResponse(bool $success, string $message, array $extra = [], int $status = 200): never
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    avatarResponse(false, 'คำขอไม่ถูกต้อง', [], 405);
}
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    avatarResponse(false, 'กรุณาล็อกอินใหม่', [], 401);
}

$data = json_decode(file_get_contents('php://input'), true);
$dataUrl = is_array($data) ? (string) ($data['image'] ?? '') : '';
if (!preg_match('/^data:image\/png;base64,([A-Za-z0-9+\/]+={0,2})$/', $dataUrl, $matches)) {
    avatarResponse(false, 'ข้อมูลรูปภาพไม่ถูกต้อง กรุณาเลือกรูปใหม่', [], 400);
}

$binary = base64_decode($matches[1], true);
if ($binary === false || strlen($binary) > 5 * 1024 * 1024 || !str_starts_with($binary, "\x89PNG\r\n\x1a\n")) {
    avatarResponse(false, 'ไฟล์รูปต้องเป็น PNG และมีขนาดไม่เกิน 5MB', [], 400);
}

try {
    require_once __DIR__ . '/db_connect.php';

    $userId = (string) $_SESSION['user_id'];
    $avatarDirectory = __DIR__ . '/uploads/avatars';
    if (!is_dir($avatarDirectory) && !mkdir($avatarDirectory, 0755, true) && !is_dir($avatarDirectory)) {
        throw new RuntimeException('Unable to create avatar upload directory.');
    }

    $fileName = 'teacher_' . hash('sha256', $userId) . '.png';
    if (file_put_contents($avatarDirectory . '/' . $fileName, $binary, LOCK_EX) === false) {
        throw new RuntimeException('Unable to write teacher avatar file.');
    }

    $publicUrl = 'uploads/avatars/' . $fileName . '?t=' . time();
    $stmt = $conn->prepare('UPDATE public.teachers SET avatar_url = :url WHERE user_id = :uid');
    $stmt->execute([':url' => $publicUrl, ':uid' => $userId]);
    if ($stmt->rowCount() === 0) {
        $check = $conn->prepare('SELECT 1 FROM public.teachers WHERE user_id = :uid');
        $check->execute([':uid' => $userId]);
        if (!$check->fetchColumn()) {
            throw new RuntimeException('Teacher profile was not found for the authenticated user.');
        }
    }

    avatarResponse(true, 'บันทึกรูปโปรไฟล์สำเร็จ', ['url' => $publicUrl]);
} catch (Throwable $e) {
    error_log('Teacher avatar save failed: ' . $e->getMessage());
    avatarResponse(false, 'บันทึกรูปไม่สำเร็จ กรุณาลองใหม่หรือติดต่อผู้ดูแลระบบ', [], 500);
}
