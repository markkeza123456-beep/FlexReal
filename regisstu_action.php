<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Location: regisstu.php');
    exit;
}

try {
    require_once __DIR__ . '/config/db.php';
    require_once __DIR__ . '/registration_lib.php';
    require_once __DIR__ . '/registration_mailer.php';

    error_log('[auth.register] request received');
    $registration = validateRegistrationPayload($conn, $_POST);
    error_log('[auth.register] validation passed');
    $otp = (string) random_int(100000, 999999);

    $pendingRegistration = [
        'data' => $registration,
        'otp_hash' => password_hash($otp, PASSWORD_DEFAULT),
        'expires_at' => time() + 180,
        'resend_at' => time() + 60,
        'attempts' => 0,
        'resends' => 0,
        'csrf_token' => bin2hex(random_bytes(32)),
    ];

    try {
        sendRegistrationOtp($registration['email'], $registration['full_name'], $otp);
        error_log('[auth.register] OTP delivery accepted');
    } catch (Throwable $mailError) {
        error_log('[auth.register] OTP delivery failed (' . get_class($mailError) . '): ' . $mailError->getMessage());
        throw new RuntimeException(flexibleMailFailureMessage($mailError), 502, $mailError);
    }

    session_regenerate_id(true);
    $_SESSION['pending_registration'] = $pendingRegistration;
    error_log('[auth.register] awaiting OTP verification');
    header('Location: registration_otp.php');
    exit;
} catch (Throwable $error) {
    $sqlState = $error instanceof PDOException ? (string) $error->getCode() : '';
    $status = $error instanceof PDOException ? ($sqlState === '23505' ? 409 : 500) : ((int) $error->getCode() >= 400 ? (int) $error->getCode() : 422);
    $messageText = $status === 500
        ? 'ระบบสมัครสมาชิกขัดข้อง กรุณาลองใหม่ภายหลัง'
        : ($status === 409 ? 'เลขบัตรประชาชนหรืออีเมลนี้ถูกใช้แล้ว' : $error->getMessage());
    error_log('[auth.register] failed with HTTP ' . $status . ' (' . get_class($error) . ')');
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    $message = json_encode($messageText, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    echo "<!doctype html><html lang='th'><meta charset='utf-8'><script>alert({$message});history.back();</script></html>";
}
