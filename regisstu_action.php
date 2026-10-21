<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/registration_lib.php';
require_once __DIR__ . '/registration_mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: regisstu.php');
    exit;
}

try {
    $registration = validateRegistrationPayload($conn, $_POST);
    $otp = (string) random_int(100000, 999999);

    session_regenerate_id(true);
    $_SESSION['pending_registration'] = [
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
    } catch (Throwable $mailError) {
        unset($_SESSION['pending_registration']);
        error_log('Registration OTP email failed: ' . $mailError->getMessage());
        throw new RuntimeException('ส่งอีเมล OTP ไม่สำเร็จ กรุณาตรวจสอบอีเมลและลองใหม่');
    }

    header('Location: registration_otp.php');
    exit;
} catch (Throwable $error) {
    $message = json_encode($error->getMessage(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    echo "<!doctype html><html lang='th'><meta charset='utf-8'><script>alert({$message});history.back();</script></html>";
}
