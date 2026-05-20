<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php'; // ไฟล์เชื่อมต่อฐานข้อมูล

// เช็คว่าล็อกอินเป็นอาจารย์จริงไหม
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'กรุณาล็อกอินใหม่']);
    exit;
}

$user_id = $_SESSION['user_id'];
$name = $_POST['name'] ?? '';
$pwd_current = $_POST['pwd_current'] ?? '';
$pwd_new = $_POST['pwd_new'] ?? '';

try {
    // 0. อัปโหลดรูปโปรไฟล์ (ถ้ามีการส่งไฟล์มา)
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file     = $_FILES['avatar'];
        $allowed  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $mimeType = mime_content_type($file['tmp_name']);

        if ($file['size'] > 2 * 1024 * 1024) throw new Exception('ไฟล์รูปต้องมีขนาดไม่เกิน 2MB');
        if (!in_array($mimeType, $allowed)) throw new Exception('รองรับเฉพาะไฟล์ JPG, PNG, GIF, WEBP');

        $uploadDir = __DIR__ . '/uploads/avatars/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        // ลบรูปเก่า
        $stmtOld = $conn->prepare("SELECT avatar_url FROM public.teachers WHERE teachers_id = :id");
        $stmtOld->execute(['id' => $user_id]);
        $oldRow = $stmtOld->fetch(PDO::FETCH_ASSOC);
        if ($oldRow && !empty($oldRow['avatar_url'])) {
            $oldPath = __DIR__ . '/' . $oldRow['avatar_url'];
            if (file_exists($oldPath) && strpos($oldPath, '/uploads/avatars/') !== false) unlink($oldPath);
        }

        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
        $filename = 'teacher_' . $user_id . '_' . time() . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) throw new Exception('บันทึกไฟล์ไม่สำเร็จ');

        $avatarUrl = 'uploads/avatars/' . $filename;
        $stmt = $conn->prepare("UPDATE public.teachers SET avatar_url = :url WHERE teachers_id = :id");
        $stmt->execute(['url' => $avatarUrl, 'id' => $user_id]);

        echo json_encode(['success' => true, 'avatar_url' => $avatarUrl]);
        exit;
    }

    // 1. อัปเดตชื่อ-นามสกุล (ถ้ามีการพิมพ์มา)
    if (!empty($name)) {
        $stmt = $conn->prepare("UPDATE public.teachers SET teachers_name = :name WHERE teachers_id = :uid");
        $stmt->execute(['name' => $name, 'uid' => $user_id]);
        $_SESSION['name'] = $name; // อัปเดต session เพื่อให้ชื่อมุมขวาบนเปลี่ยนด้วย
    }

    // 2. อัปเดตรหัสผ่าน (ถ้ามีการกรอกรหัสใหม่มา)
    if (!empty($pwd_new)) {
        // ดึงรหัสผ่านเก่าจากตาราง User มาเช็คก่อนว่าตรงกับที่พิมพ์มาไหม
        $stmt = $conn->prepare('SELECT password FROM public."User" WHERE user_id = :uid');
        $stmt->execute(['uid' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $user['password'] === $pwd_current) {
            // อัปเดตรหัสผ่านใหม่ลงตารางหลัก "User"
            $stmt_upd1 = $conn->prepare('UPDATE public."User" SET password = :pass WHERE user_id = :uid');
            $stmt_upd1->execute(['pass' => $pwd_new, 'uid' => $user_id]);
            
            // อัปเดตรหัสผ่านใหม่ลงตารางย่อย "teachers" ด้วย
            $stmt_upd2 = $conn->prepare('UPDATE public.teachers SET password = :pass WHERE teachers_id = :uid');
            $stmt_upd2->execute(['pass' => $pwd_new, 'uid' => $user_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง']);
            exit;
        }
    }

    echo json_encode(['success' => true, 'message' => 'บันทึกข้อมูลสำเร็จ!']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'ระบบฐานข้อมูลผิดพลาด: ' . $e->getMessage()]);
}
?>