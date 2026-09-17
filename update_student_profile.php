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

    if (!empty($name) || !empty($email) || !empty($phone)) {
        $stmt = $conn->prepare("
            UPDATE public.students
            SET full_name = :name,
                email        = :email,
                phone        = :tel,
                updated_at   = NOW()
            WHERE user_id = :uid
        ");
        $stmt->execute([
            'name'  => $name,
            'email' => $email,
            'tel'   => $phone,
            'uid'   => $user_id,
        ]);
        $_SESSION['name'] = $name;
    }


    if (!empty($pwd_new)) {
        $stmt = $conn->prepare('SELECT password_hash FROM public.users WHERE user_id = :uid');
        $stmt->execute(['uid' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($pwd_current, (string) $user['password_hash'])) {
            $stmt_upd = $conn->prepare('UPDATE public.users SET password_hash = :pass, updated_at = NOW() WHERE user_id = :uid');
            $stmt_upd->execute(['pass' => password_hash($pwd_new, PASSWORD_DEFAULT), 'uid' => $user_id]);
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
