<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

$new_password = trim((string)($_POST['new_password'] ?? ''));
$confirm_password = trim((string)($_POST['confirm_password'] ?? ''));

// เช็คความถูกต้องของ Session จาก Step 2
if (!isset($_SESSION['reset_user_id'], $_SESSION['reset_pin'], $_SESSION['reset_pin_verified_at'])) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณายืนยัน PIN ก่อนเปลี่ยนรหัสผ่าน']);
    exit;
}

if ((time() - (int)$_SESSION['reset_pin_verified_at']) > 900) {
    unset($_SESSION['reset_user_id'], $_SESSION['reset_pin'], $_SESSION['reset_pin_verified_at']);
    echo json_encode(['status' => 'error', 'message' => 'เซสชันยืนยัน PIN หมดอายุ กรุณายืนยันใหม่']);
    exit;
}

if ($new_password === '' || $confirm_password === '') {
    echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกรหัสผ่านให้ครบถ้วน']);
    exit;
}

if (strlen($new_password) < 6) {
    echo json_encode(['status' => 'error', 'message' => 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร']);
    exit;
}

if ($new_password !== $confirm_password) {
    echo json_encode(['status' => 'error', 'message' => 'ยืนยันรหัสผ่านใหม่ไม่ตรงกัน']);
    exit;
}

try {
    $user_id = (string)$_SESSION['reset_user_id'];
    $pin = (string)$_SESSION['reset_pin'];

    // เช็ค PIN จากฐานข้อมูลอีกรอบเพื่อความชัวร์
    $stmtToken = $conn->prepare('SELECT user_id FROM public.password_reset_tokens WHERE token = :pin AND user_id = :uid AND expires_at > NOW() ORDER BY expires_at DESC LIMIT 1');
    $stmtToken->execute(['pin' => $pin, 'uid' => $user_id]);
    $tokenRow = $stmtToken->fetch(PDO::FETCH_ASSOC);

    if (!$tokenRow) {
        unset($_SESSION['reset_user_id'], $_SESSION['reset_pin'], $_SESSION['reset_pin_verified_at']);
        echo json_encode(['status' => 'error', 'message' => 'PIN ไม่ถูกต้อง หรือหมดอายุแล้ว']);
        exit;
    }

    $conn->beginTransaction();

    // 1. ดึง Role ของผู้ใช้จากตารางหลัก "User"
    $stmt_role = $conn->prepare('SELECT status FROM public."User" WHERE user_id = :uid');
    $stmt_role->execute(['uid' => $user_id]);
    $roleRow = $stmt_role->fetch(PDO::FETCH_ASSOC);
    $role = strtolower((string)($roleRow['status'] ?? ''));

    // 2. อัปเดตรหัสผ่านในตารางหลัก "User" (ทุกคนถูกอัปเดตตรงนี้จบเป็นมาตรฐาน)
    $stmt_user = $conn->prepare('UPDATE public."User" SET password = :pass WHERE user_id = :uid');
    $stmt_user->execute(['pass' => $new_password, 'uid' => $user_id]);

    // 3. อัปเดตตารางย่อย *เฉพาะตารางที่มีคอลัมน์ password* เท่านั้น! 
    // (นักเรียนและเจ้าหน้าที่จะถูกข้ามไป ทำให้ไม่เกิด Error: column does not exist)
    $stmt_sub = null;
    if ($role === 'teacher') {
        $stmt_sub = $conn->prepare('UPDATE public.teachers SET password = :pass WHERE teachers_id = :uid');
    } elseif ($role === 'parent') {
        $stmt_sub = $conn->prepare('UPDATE public.parents SET password = :pass WHERE parents_id = :uid');
    } elseif ($role === 'executive') {
        $stmt_sub = $conn->prepare('UPDATE public.executive SET password = :pass WHERE executive_id = :uid');
    } elseif ($role === 'officer') {
        $stmt_sub = $conn->prepare('UPDATE public.officer SET password = :pass WHERE officer_id = :uid');
    }

    // ถ้าระบุตารางย่อยไว้ ให้ทำการ Execute คำสั่ง
    if ($stmt_sub !== null) {
        $stmt_sub->execute(['pass' => $new_password, 'uid' => $user_id]);
    }

    // 4. ลบ PIN ทิ้งหลังใช้งานเสร็จ
    $stmt_del = $conn->prepare('DELETE FROM public.password_reset_tokens WHERE user_id = :uid');
    $stmt_del->execute(['uid' => $user_id]);

    $conn->commit();

    // เคลียร์ Session ป้องกันการแบคกลับมาเปลี่ยนรหัสซ้ำ
    unset($_SESSION['reset_user_id'], $_SESSION['reset_pin'], $_SESSION['reset_pin_verified_at']);

    echo json_encode(['status' => 'success', 'message' => 'เปลี่ยนรหัสผ่านสำเร็จ']);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => 'ระบบฐานข้อมูลผิดพลาด: ' . $e->getMessage()]);
}
?>