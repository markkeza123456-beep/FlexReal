<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

function passwordResetResponse(string $status, string $message, int $httpStatus = 200, array $extra = []): never
{
    http_response_code($httpStatus);
    echo json_encode(['status' => $status, 'message' => $message] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') passwordResetResponse('error', 'คำขอไม่ถูกต้อง', 405);
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) passwordResetResponse('error', 'กรุณากรอกอีเมลที่ถูกต้อง', 422);

$failureStage = 'database';
try {
    require_once __DIR__ . '/config/db.php';
    require_once __DIR__ . '/registration_mailer.php';

    $sql = "
        SELECT p.user_id, p.full_name AS user_name
        FROM public.students p INNER JOIN public.users u ON u.user_id = p.user_id
        WHERE LOWER(p.email) = :email AND u.account_status = 'active'
        UNION
        SELECT p.user_id, p.full_name AS user_name
        FROM public.teachers p INNER JOIN public.users u ON u.user_id = p.user_id
        WHERE LOWER(p.email) = :email AND u.account_status = 'active'
        UNION
        SELECT p.user_id, p.full_name AS user_name
        FROM public.parents p INNER JOIN public.users u ON u.user_id = p.user_id
        WHERE LOWER(p.email) = :email AND u.account_status = 'active'
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) passwordResetResponse('error', 'ไม่พบอีเมลนี้ในระบบ', 404);

    $token = (string) random_int(100000, 999999);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    $failureStage = 'database';
    $conn->beginTransaction();
    try {
        $conn->prepare('UPDATE public.password_reset_tokens SET used_at = NOW() WHERE user_id = :uid AND used_at IS NULL')
            ->execute([':uid' => $user['user_id']]);
        $stmtToken = $conn->prepare('INSERT INTO public.password_reset_tokens (user_id, token_hash, expires_at) VALUES (:uid, :token_hash, :exp)');
        $stmtToken->execute([
            ':uid' => $user['user_id'],
            ':token_hash' => password_hash($token, PASSWORD_DEFAULT),
            ':exp' => $expiresAt,
        ]);

        // Keep token changes uncommitted until SMTP accepts the PIN message.
        $failureStage = 'mail';
        sendPasswordResetOtp($email, (string) ($user['user_name'] ?? ''), $token);
        $failureStage = 'database';
        $conn->commit();
    } catch (Throwable $error) {
        if ($conn->inTransaction()) $conn->rollBack();
        throw $error;
    }

    $_SESSION['password_reset_user_id'] = (string) $user['user_id'];
    $_SESSION['password_reset_requested_at'] = time();
    // Persist the reset context before returning the JSON response. The browser
    // continues this flow with a second same-origin fetch immediately after.
    session_write_close();
    passwordResetResponse('success', 'ส่งรหัส PIN ไปยังอีเมลของคุณแล้ว');
} catch (Throwable $error) {
    $errorId = bin2hex(random_bytes(6));
    error_log('[auth.password-reset] request failed [ref=' . $errorId . '] stage=' . $failureStage . ' type=' . get_class($error) . ' message=' . $error->getMessage());
    $message = $failureStage === 'mail'
        ? flexibleMailFailureMessage($error)
        : 'บันทึกคำขอรีเซ็ตรหัสผ่านไม่สำเร็จ กรุณาลองใหม่อีกครั้ง';
    if ($failureStage !== 'mail' && isFlexibleMailDebugEnabled()) $message .= ' (รายละเอียด: ' . substr(trim($error->getMessage()), 0, 400) . ')';
    passwordResetResponse('error', $message, $failureStage === 'mail' ? 502 : 500, ['error_id' => $errorId]);
}
