<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

function otpResponse(string $status, string $message, int $httpStatus = 200, array $extra = []): never
{
    http_response_code($httpStatus);
    echo json_encode(['status' => $status, 'message' => $message] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') otpResponse('error', 'คำขอไม่ถูกต้อง', 405);
$pending = $_SESSION['pending_registration'] ?? null;
if (!is_array($pending) || !is_array($pending['data'] ?? null)) otpResponse('error', 'ไม่พบข้อมูลสมัครที่รอยืนยัน กรุณาเริ่มสมัครใหม่', 401);

$csrf = (string) ($_POST['csrf_token'] ?? '');
if ($csrf === '' || !hash_equals((string) ($pending['csrf_token'] ?? ''), $csrf)) otpResponse('error', 'เซสชันหมดอายุ กรุณาเริ่มสมัครใหม่', 403);

$action = (string) ($_POST['action'] ?? 'verify');
if ($action === 'resend') {
    $now = time();
    if ($now < (int) ($pending['resend_at'] ?? 0)) otpResponse('error', 'กรุณารอให้ครบ 60 วินาทีก่อนขอรหัสใหม่', 429, ['retry_after' => (int) $pending['resend_at'] - $now]);
    if ((int) ($pending['resends'] ?? 0) >= 5) otpResponse('error', 'ขอรหัสใหม่ครบจำนวนครั้งแล้ว กรุณาเริ่มสมัครใหม่', 429);
    require_once __DIR__ . '/registration_mailer.php';
    $otp = (string) random_int(100000, 999999);
    try {
        sendRegistrationOtp((string) $pending['data']['email'], (string) $pending['data']['full_name'], $otp);
    } catch (Throwable $error) {
        error_log('Registration OTP resend failed: ' . $error->getMessage());
        otpResponse('error', flexibleMailFailureMessage($error), 502);
    }
    $pending['otp_hash'] = password_hash($otp, PASSWORD_DEFAULT);
    $pending['expires_at'] = $now + 180;
    $pending['resend_at'] = $now + 60;
    $pending['attempts'] = 0;
    $pending['resends'] = (int) ($pending['resends'] ?? 0) + 1;
    $_SESSION['pending_registration'] = $pending;
    otpResponse('success', 'ส่งรหัส OTP ใหม่แล้ว', 200, ['retry_after' => 60]);
}

if ($action !== 'verify') otpResponse('error', 'คำขอไม่ถูกต้อง', 400);
$now = time();
if ($now > (int) ($pending['expires_at'] ?? 0)) {
    otpResponse('error', 'รหัส OTP หมดอายุแล้ว กรุณากดส่งรหัส OTP อีกครั้ง', 410);
}
if ((int) ($pending['attempts'] ?? 0) >= 5) {
    unset($_SESSION['pending_registration']);
    otpResponse('error', 'กรอกรหัสผิดเกินจำนวนครั้ง กรุณาสมัครใหม่', 429, ['restart' => true]);
}
$otp = trim((string) ($_POST['otp'] ?? ''));
if (!preg_match('/^[0-9]{6}$/', $otp)) otpResponse('error', 'กรุณากรอกรหัส OTP 6 หลัก', 422);
$pending['attempts'] = (int) ($pending['attempts'] ?? 0) + 1;
if (!password_verify($otp, (string) ($pending['otp_hash'] ?? ''))) {
    if ($pending['attempts'] >= 5) {
        unset($_SESSION['pending_registration']);
        otpResponse('error', 'รหัส OTP ไม่ถูกต้องครบจำนวนครั้ง กรุณาสมัครใหม่', 429, ['restart' => true]);
    }
    $_SESSION['pending_registration'] = $pending;
    otpResponse('error', 'รหัส OTP ไม่ถูกต้อง ลองใหม่อีกครั้ง', 422, ['attempts_left' => 5 - $pending['attempts']]);
}

try {
    require_once __DIR__ . '/config/db.php';
    require_once __DIR__ . '/registration_lib.php';
    createRegisteredUser($conn, $pending['data']);
    unset($_SESSION['pending_registration']);
    session_regenerate_id(true);
    otpResponse('success', 'ยืนยันอีเมลและสมัครสมาชิกสำเร็จ', 200, ['redirect_url' => 'login.php']);
} catch (Throwable $error) {
    error_log('Registration OTP verification failed: ' . $error->getMessage());
    otpResponse('error', 'บันทึกข้อมูลสมัครไม่สำเร็จ กรุณาเริ่มสมัครใหม่', 500);
}
