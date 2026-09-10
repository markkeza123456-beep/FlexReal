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
    $stmtToken = $conn->prepare('SELECT token_id, token_hash FROM public.password_reset_tokens WHERE user_id = :uid AND used_at IS NULL AND expires_at > NOW() ORDER BY created_at DESC');
    $stmtToken->execute(['uid' => $user_id]);
    $tokenRow = null;
    foreach ($stmtToken->fetchAll(PDO::FETCH_ASSOC) as $candidate) {
        if (password_verify($pin, (string) $candidate['token_hash'])) { $tokenRow = $candidate; break; }
    }

    if (!$tokenRow) {
        unset($_SESSION['reset_user_id'], $_SESSION['reset_pin'], $_SESSION['reset_pin_verified_at']);
        echo json_encode(['status' => 'error', 'message' => 'PIN ไม่ถูกต้อง หรือหมดอายุแล้ว']);
        exit;
    }

    $conn->beginTransaction();

    $stmt_user = $conn->prepare('UPDATE public.users SET password_hash = :password_hash, updated_at = NOW() WHERE user_id = :uid');
    $stmt_user->execute(['password_hash' => password_hash($new_password, PASSWORD_DEFAULT), 'uid' => $user_id]);

    // 4. ลบ PIN ทิ้งหลังใช้งานเสร็จ
    $stmt_del = $conn->prepare('UPDATE public.password_reset_tokens SET used_at = NOW() WHERE token_id = :id');
    $stmt_del->execute(['id' => $tokenRow['token_id']]);

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
