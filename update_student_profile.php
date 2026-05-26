<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'กรุณาล็อกอินใหม่']);
    exit;
}

$user_id       = $_SESSION['user_id'];
$name          = $_POST['name']          ?? '';
$email         = $_POST['email']         ?? '';
$phone         = $_POST['phone']         ?? '';
$pwd_current   = $_POST['pwd_current']   ?? '';
$pwd_new       = $_POST['pwd_new']       ?? '';
$avatar_base64 = $_POST['avatar_base64'] ?? '';

try {
    // 1. บันทึกรูป avatar ไปยัง Supabase Storage (ถ้ามี)
    $avatar_url = null;
    if (!empty($avatar_base64)) {
        if (!preg_match('/^data:image\/(png|jpeg|jpg|webp);base64,/', $avatar_base64)) {
            echo json_encode(['success' => false, 'message' => 'รูปภาพไม่ถูกต้อง']);
            exit;
        }
        $imgData = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $avatar_base64));

        if ($imgData === false || strlen($imgData) < 100) {
            echo json_encode(['success' => false, 'message' => 'ไม่สามารถถอดรหัสรูปภาพได้']);
            exit;
        }
        if (strlen($imgData) > 2 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'รูปภาพต้องไม่เกิน 2MB']);
            exit;
        }

        $SUPABASE_URL = 'https://gwunrmptlmfpvidrxwdf.supabase.co';
        $SUPABASE_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imd3dW5ybXB0bG1mcHZpZHJ4d2RmIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzY2NDY3ODUsImV4cCI6MjA5MjIyMjc4NX0.TvvgwaVxPIRzCguAH7x58vUEi2od31QeTXypRxaFMxA';
        $BUCKET       = 'avatars';
        $filePath     = "students/{$user_id}.png";

        $ch = curl_init("{$SUPABASE_URL}/storage/v1/object/{$BUCKET}/{$filePath}");
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => $imgData,
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
            echo json_encode(['success' => false, 'message' => "อัปโหลดรูปล้มเหลว: HTTP $status"]);
            exit;
        }

        $avatar_url = "{$SUPABASE_URL}/storage/v1/object/public/{$BUCKET}/{$filePath}?t=" . time();

        // ✅ บันทึก avatar_url ลงตาราง student
        $stmtAvatar = $conn->prepare("UPDATE public.student SET avatar_url = :url WHERE student_id = :uid");
        $stmtAvatar->execute(['url' => $avatar_url, 'uid' => $user_id]);
    }

    // 2. อัปเดตชื่อ, อีเมล, เบอร์โทร
    if (!empty($name) || !empty($email) || !empty($phone)) {
        $stmt = $conn->prepare("
            UPDATE public.student 
            SET student_name = :name,
                email        = :email,
                tel          = :tel
            WHERE student_id = :uid
        ");
        $stmt->execute([
            'name'  => $name,
            'email' => $email,
            'tel'   => $phone,
            'uid'   => $user_id,
        ]);
        $_SESSION['name'] = $name;
    }

    // 3. อัปเดตรหัสผ่าน
    if (!empty($pwd_new)) {
        $stmt = $conn->prepare('SELECT password FROM public."User" WHERE user_id = :uid');
        $stmt->execute(['uid' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $user['password'] === $pwd_current) {
            $stmt_upd = $conn->prepare('UPDATE public."User" SET password = :pass WHERE user_id = :uid');
            $stmt_upd->execute(['pass' => $pwd_new, 'uid' => $user_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง']);
            exit;
        }
    }

    $response = ['success' => true, 'message' => 'บันทึกข้อมูลสำเร็จ!'];
    if ($avatar_url) {
        $response['avatar_url'] = $avatar_url;
    }
    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'ระบบฐานข้อมูลผิดพลาด: ' . $e->getMessage()]);
}
?>