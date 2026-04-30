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