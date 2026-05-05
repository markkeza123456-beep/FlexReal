<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'กรุณาล็อกอินใหม่']);
    exit;
}

$user_id = $_SESSION['user_id'];

// รับไฟล์ base64 จาก JS
$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['image'])) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลรูปภาพ']);
    exit;
}

// แปลง base64 → binary
$base64 = preg_replace('/^data:image\/\w+;base64,/', '', $data['image']);
$binary = base64_decode($base64);
if (!$binary) {
    echo json_encode(['success' => false, 'message' => 'ข้อมูลรูปภาพไม่ถูกต้อง']);
    exit;
}

// ชื่อไฟล์ = teachers/{user_id}.png (เขียนทับได้เลย ไม่สะสม)
$SUPABASE_URL = 'https://gwunrmptlmfpvidrxwdf.supabase.co';
$SUPABASE_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imd3dW5ybXB0bG1mcHZpZHJ4d2RmIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzY2NDY3ODUsImV4cCI6MjA5MjIyMjc4NX0.TvvgwaVxPIRzCguAH7x58vUEi2od31QeTXypRxaFMxA';
$BUCKET      = 'avatars';
$filePath    = "teachers/{$user_id}.png";

// อัปโหลดไปยัง Supabase Storage (upsert=true เพื่อเขียนทับ)
$uploadUrl = "{$SUPABASE_URL}/storage/v1/object/{$BUCKET}/{$filePath}";
$ch = curl_init($uploadUrl);
curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST  => 'POST',
    CURLOPT_POSTFIELDS     => $binary,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        "Authorization: Bearer {$SUPABASE_KEY}",
        "Content-Type: image/png",
        "x-upsert: true",
    ],
]);
$res    = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($status !== 200 && $status !== 201) {
    echo json_encode(['success' => false, 'message' => "อัปโหลดล้มเหลว: HTTP $status — $res"]);
    exit;
}

// URL สาธารณะของรูป (บวก cache-bust ด้วย timestamp)
$publicUrl = "{$SUPABASE_URL}/storage/v1/object/public/{$BUCKET}/{$filePath}?t=" . time();

// บันทึก URL ลงในตาราง teachers
try {
    $stmt = $conn->prepare("UPDATE public.teachers SET avatar_url = :url WHERE teachers_id = :uid");
    $stmt->execute(['url' => $publicUrl, 'uid' => $user_id]);
    echo json_encode(['success' => true, 'url' => $publicUrl]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'บันทึก URL ล้มเหลว: ' . $e->getMessage()]);
}
?>