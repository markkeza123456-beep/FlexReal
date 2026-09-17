<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

$new_password = trim((string)($_POST['new_password'] ?? ''));
$confirm_password = trim((string)($_POST['confirm_password'] ?? ''));


if (!isset($_SESSION['reset_user_id'], $_SESSION['reset_token_id'], $_SESSION['reset_pin_verified_at'])) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณายืนยัน PIN ก่อนเปลี่ยนรหัสผ่าน']);
    exit;
}

if ((time() - (int)$_SESSION['reset_pin_verified_at']) > 900) {
    unset($_SESSION['password_reset_user_id'], $_SESSION['password_reset_requested_at'], $_SESSION['reset_user_id'], $_SESSION['reset_token_id'], $_SESSION['reset_pin_verified_at']);
    echo json_encode(['status' => 'error', 'message' => 'เซสชันยืนยัน PIN หมดอายุ กรุณายืนยันใหม่']);
    exit;
}

if ($new_password === '' || $confirm_password === '') {
    echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกรหัสผ่านให้ครบถ้วน']);
    exit;
}

if (!preg_match('/^(?=.*[A-Za-z])(?=.*[0-9])[A-Za-z0-9]{8,15}$/', $new_password)) {
    echo json_encode(['status' => 'error', 'message' => 'รหัสผ่านต้องเป็นภาษาอังกฤษและตัวเลข 8-15 ตัว']);
    exit;
}

if ($new_password !== $confirm_password) {
    echo json_encode(['status' => 'error', 'message' => 'ยืนยันรหัสผ่านใหม่ไม่ตรงกัน']);
    exit;
}

try {
    $user_id = (string)$_SESSION['reset_user_id'];
    $tokenId = (int) $_SESSION['reset_token_id'];


    $stmtToken = $conn->prepare('SELECT token_id FROM public.password_reset_tokens WHERE token_id = :token_id AND user_id = :uid AND used_at IS NULL AND expires_at > NOW()');
    $stmtToken->execute([':token_id' => $tokenId, ':uid' => $user_id]);
    $tokenRow = $stmtToken->fetch(PDO::FETCH_ASSOC);

    if (!$tokenRow) {
        unset($_SESSION['password_reset_user_id'], $_SESSION['password_reset_requested_at'], $_SESSION['reset_user_id'], $_SESSION['reset_token_id'], $_SESSION['reset_pin_verified_at']);
        echo json_encode(['status' => 'error', 'message' => 'PIN ไม่ถูกต้อง หรือหมดอายุแล้ว']);
        exit;
    }

    $conn->beginTransaction();

    $stmt_user = $conn->prepare('UPDATE public.users SET password_hash = :password_hash, updated_at = NOW() WHERE user_id = :uid');
    $stmt_user->execute(['password_hash' => password_hash($new_password, PASSWORD_DEFAULT), 'uid' => $user_id]);


    $stmt_del = $conn->prepare('UPDATE public.password_reset_tokens SET used_at = NOW() WHERE token_id = :id');
    $stmt_del->execute(['id' => $tokenRow['token_id']]);

    $conn->commit();


    unset($_SESSION['password_reset_user_id'], $_SESSION['password_reset_requested_at'], $_SESSION['reset_user_id'], $_SESSION['reset_token_id'], $_SESSION['reset_pin_verified_at']);

    echo json_encode(['status' => 'success', 'message' => 'เปลี่ยนรหัสผ่านสำเร็จ']);
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Password reset failed: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถเปลี่ยนรหัสผ่านได้ กรุณาลองใหม่']);
}
?>
