<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'กรุณาล็อกอินใหม่']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['image'])) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลรูปภาพ']);
    exit;
}

$base64 = preg_replace('/^data:image\/\w+;base64,/', '', $data['image']);
$binary = base64_decode($base64);
if (!$binary) {
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
        'Content-Type: image/png',
        'x-upsert: true',
    ],
]);
$res = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($status !== 200 && $status !== 201) {
    echo json_encode(['success' => false, 'message' => "อัปโหลดล้มเหลว: HTTP $status — $res"]);
    exit;
}

$publicUrl = "{$SUPABASE_URL}/storage/v1/object/public/{$BUCKET}/{$filePath}?t=" . time();

try {
    $stmt = $conn->prepare(
        'UPDATE public.students SET avatar_url = :url, updated_at = NOW() WHERE user_id = :uid'
    );
    $stmt->execute(['url' => $publicUrl, 'uid' => $user_id]);
    echo json_encode(['success' => true, 'url' => $publicUrl]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'บันทึก URL ล้มเหลว: ' . $e->getMessage()]);
}
?>
