<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'กรุณาล็อกอินใหม่']);
    exit;
}

$user_id     = $_SESSION['user_id'];
$name        = $_POST['name']        ?? '';
$email       = $_POST['email']       ?? '';
$phone       = $_POST['phone']       ?? '';
$pwd_current = $_POST['pwd_current'] ?? '';
$pwd_new     = $_POST['pwd_new']     ?? '';

try {
    // 1. อัปเดตชื่อ, อีเมล, เบอร์โทร ในตาราง student
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

    // 2. อัปเดตรหัสผ่านในตาราง User (ตาราง student ไม่มี column password)
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

    echo json_encode(['success' => true, 'message' => 'บันทึกข้อมูลสำเร็จ!']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'ระบบฐานข้อมูลผิดพลาด: ' . $e->getMessage()]);
}
?>