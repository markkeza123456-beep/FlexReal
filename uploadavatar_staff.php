<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
error_reporting(0);

try {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $body = json_decode(file_get_contents('php://input'), true);
    $dataURL = $body['image'] ?? '';

    if (!$dataURL || !str_starts_with($dataURL, 'data:image/')) {
        echo json_encode(['success' => false, 'message' => 'ข้อมูลรูปไม่ถูกต้อง']);
        exit;
    }

    // แปลง base64 → binary
    $base64 = preg_replace('/^data:image\/\w+;base64,/', '', $dataURL);
    $imageData = base64_decode($base64);
    if (!$imageData) {
        echo json_encode(['success' => false, 'message' => 'แปลงรูปไม่สำเร็จ']);
        exit;
    }

    require_once __DIR__ . '/db_connect.php';

    // อ่าน Supabase config จาก db_connect.php (ต้องมี $supabaseUrl และ $supabaseKey)
    // หรือกำหนดตรงนี้:
    // $supabaseUrl = 'https://xxxx.supabase.co';
    // $supabaseKey = 'your-service-role-key';

    $userId   = $_SESSION['user_id'];
    $fileName = 'staff_' . $userId . '_' . time() . '.png';
    $bucket   = 'avatars'; // ชื่อ bucket ใน Supabase Storage

    // อัปโหลดไป Supabase Storage
    $uploadUrl = rtrim($supabaseUrl, '/') . '/storage/v1/object/' . $bucket . '/' . $fileName;
    $ch = curl_init($uploadUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => $imageData,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $supabaseKey,
            'Content-Type: image/png',
            'x-upsert: true',
        ],
    ]);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        echo json_encode(['success' => false, 'message' => 'Supabase upload failed: ' . $result]);
        exit;
    }

    // Public URL ของรูป
    $publicUrl = rtrim($supabaseUrl, '/') . '/storage/v1/object/public/' . $bucket . '/' . $fileName;

    // บันทึก URL ลงตาราง staff
    $stmt = $conn->prepare('UPDATE public.staff SET avatar_url = :url WHERE user_id = :id');
    $stmt->execute([':url' => $publicUrl, ':id' => $userId]);

    echo json_encode(['success' => true, 'url' => $publicUrl]);

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
