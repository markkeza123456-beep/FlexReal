<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'กรุณาล็อกอินใหม่']);
    exit;
}

$user_id = $_SESSION['user_id'];
$name = $_POST['name'] ?? '';
$pwd_current = $_POST['pwd_current'] ?? '';
$pwd_new = $_POST['pwd_new'] ?? '';

try {

    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file     = $_FILES['avatar'];
        $allowed  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $mimeType = mime_content_type($file['tmp_name']);

        if ($file['size'] > 2 * 1024 * 1024) throw new Exception('ไฟล์รูปต้องมีขนาดไม่เกิน 2MB');
        if (!in_array($mimeType, $allowed)) throw new Exception('รองรับเฉพาะไฟล์ JPG, PNG, GIF, WEBP');

        $uploadDir = __DIR__ . '/uploads/avatars/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);


        $stmtOld = $conn->prepare("SELECT avatar_url FROM public.teachers WHERE user_id = :id");
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
        $stmt = $conn->prepare("UPDATE public.teachers SET avatar_url = :url, updated_at = NOW() WHERE user_id = :id");
        $stmt->execute(['url' => $avatarUrl, 'id' => $user_id]);

        echo json_encode(['success' => true, 'avatar_url' => $avatarUrl]);
        exit;
    }


    if (!empty($name)) {
        $stmt = $conn->prepare("UPDATE public.teachers SET full_name = :name, updated_at = NOW() WHERE user_id = :uid");
        $stmt->execute(['name' => $name, 'uid' => $user_id]);
        $_SESSION['name'] = $name;
    }


    if (!empty($pwd_new)) {
        $stmt = $conn->prepare('SELECT password_hash FROM public.users WHERE user_id = :uid');
        $stmt->execute(['uid' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($pwd_current, (string) $user['password_hash'])) {
            $stmt_upd1 = $conn->prepare('UPDATE public.users SET password_hash = :pass, updated_at = NOW() WHERE user_id = :uid');
            $stmt_upd1->execute(['pass' => password_hash($pwd_new, PASSWORD_DEFAULT), 'uid' => $user_id]);
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
