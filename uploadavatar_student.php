<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'กรุณาล็อกอินใหม่']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['image']) || !is_string($data['image']) || !preg_match('/^data:image\/png;base64,/', $data['image'])) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลรูปภาพ']);
    exit;
}

$base64 = substr($data['image'], strlen('data:image/png;base64,'));
$binary = base64_decode($base64, true);
if ($binary === false || $binary === '') {
    echo json_encode(['success' => false, 'message' => 'ข้อมูลรูปภาพไม่ถูกต้อง']);
    exit;
}

$SUPABASE_URL = $supabaseUrl;
$SUPABASE_KEY = $supabaseKey;
$BUCKET = 'avatars';
$filePath = "students/{$user_id}.png";

$uploadUrl = "{$SUPABASE_URL}/storage/v1/object/{$BUCKET}/{$filePath}";
$ch = curl_init($uploadUrl);
curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $binary,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer {$SUPABASE_KEY}",
        "apikey: {$SUPABASE_KEY}",
        'Content-Type: image/png',
        'x-upsert: true',
    ],
]);
$res = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($res === false) {
    echo json_encode(['success' => false, 'message' => 'เชื่อมต่อพื้นที่จัดเก็บรูปไม่สำเร็จ: ' . $curlError], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($status !== 200 && $status !== 201) {
    echo json_encode(['success' => false, 'message' => "อัปโหลดล้มเหลว: HTTP $status — $res"], JSON_UNESCAPED_UNICODE);
    exit;
}

$publicUrl = "{$SUPABASE_URL}/storage/v1/object/public/{$BUCKET}/{$filePath}?t=" . (string) round(microtime(true) * 1000);

try {
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
