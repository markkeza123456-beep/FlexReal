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
    require_once __DIR__ . '/config/db.php';

    $userId = (string) $_SESSION['user_id'];
    $storageUrl = rtrim((string) ($env['SUPABASE_URL'] ?? $supabaseUrl ?? ''), '/');
    $storageKey = trim((string) ($env['SUPABASE_SERVICE_ROLE_KEY'] ?? ''));
    $bucket = trim((string) ($env['SUPABASE_LESSON_BUCKET'] ?? 'lesson-media'));
    if ($storageUrl === '' || $storageKey === '' || $bucket === '') {
        throw new RuntimeException('Shared Supabase Storage is not configured.');
    }

    $headers = ['apikey: ' . $storageKey, 'Content-Type: application/json'];
    if (!str_starts_with($storageKey, 'sb_secret_')) $headers[] = 'Authorization: Bearer ' . $storageKey;
    $bucketUrl = $storageUrl . '/storage/v1/bucket/' . rawurlencode($bucket);
    $checkBucket = curl_init($bucketUrl);
    curl_setopt_array($checkBucket, [CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 15]);
    $bucketBody = curl_exec($checkBucket);
    $bucketStatus = (int) curl_getinfo($checkBucket, CURLINFO_RESPONSE_CODE);
    $bucketError = curl_error($checkBucket);
    curl_close($checkBucket);
    if ($bucketBody === false) throw new RuntimeException('Unable to reach Supabase Storage: ' . $bucketError);
    if ($bucketStatus === 404 || stripos((string) $bucketBody, 'bucket not found') !== false) {
        $createBucket = curl_init($storageUrl . '/storage/v1/bucket');
        curl_setopt_array($createBucket, [CURLOPT_POST => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => json_encode(['id' => $bucket, 'name' => $bucket, 'public' => true]), CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 15]);
        $createBody = curl_exec($createBucket);
        $createStatus = (int) curl_getinfo($createBucket, CURLINFO_RESPONSE_CODE);
        $createError = curl_error($createBucket);
        curl_close($createBucket);
        if ($createBody === false || $createStatus < 200 || $createStatus >= 300) throw new RuntimeException('Unable to initialize avatar storage: ' . ($createError ?: 'HTTP ' . $createStatus));
    } elseif ($bucketStatus < 200 || $bucketStatus >= 300) {
        throw new RuntimeException('Unable to check avatar storage bucket: HTTP ' . $bucketStatus);
    }

    $objectPath = 'avatars/teachers/' . hash('sha256', $userId) . '.png';
    $objectUrl = $storageUrl . '/storage/v1/object/' . rawurlencode($bucket) . '/' . implode('/', array_map('rawurlencode', explode('/', $objectPath)));
    $uploadHeaders = ['apikey: ' . $storageKey, 'Content-Type: image/png', 'x-upsert: true'];
    if (!str_starts_with($storageKey, 'sb_secret_')) $uploadHeaders[] = 'Authorization: Bearer ' . $storageKey;
    $upload = curl_init($objectUrl);
    curl_setopt_array($upload, [CURLOPT_POST => true, CURLOPT_HTTPHEADER => $uploadHeaders, CURLOPT_POSTFIELDS => $binary, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 30]);
    $uploadBody = curl_exec($upload);
    $uploadStatus = (int) curl_getinfo($upload, CURLINFO_RESPONSE_CODE);
    $uploadError = curl_error($upload);
    curl_close($upload);
    if ($uploadBody === false || $uploadStatus < 200 || $uploadStatus >= 300) {
        throw new RuntimeException('Unable to upload avatar to Supabase Storage: ' . ($uploadError ?: 'HTTP ' . $uploadStatus));
    }

    $publicUrl = $storageUrl . '/storage/v1/object/public/' . rawurlencode($bucket) . '/' . implode('/', array_map('rawurlencode', explode('/', $objectPath))) . '?t=' . time();
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
