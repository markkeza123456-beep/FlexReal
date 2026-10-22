<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('Content-Type: application/json; charset=utf-8');

function studentPinResetResponse(string $status, string $message, int $httpStatus = 200): never
{
    http_response_code($httpStatus);
    echo json_encode(['status' => $status, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || strtolower((string) ($_SESSION['role'] ?? '')) !== 'student' || empty($_SESSION['user_id'])) {
    studentPinResetResponse('error', 'กรุณาเข้าสู่ระบบนักเรียนใหม่อีกครั้ง', 401);
}
$csrf = (string) ($_POST['csrf_token'] ?? '');
if ($csrf === '' || !hash_equals((string) ($_SESSION['student_pin_reset_csrf'] ?? ''), $csrf)) {
    studentPinResetResponse('error', 'เซสชันหมดอายุ กรุณาโหลดหน้าใหม่', 403);
}
$action = (string) ($_POST['action'] ?? '');
$userId = (string) $_SESSION['user_id'];

try {
    require_once __DIR__ . '/config/db.php';
    if ($action === 'send') {
        $previousSentAt = (int) ($_SESSION['student_pin_reset']['sent_at'] ?? 0);
        if ($previousSentAt > 0 && time() - $previousSentAt < 60) {
            studentPinResetResponse('error', 'กรุณารอ 1 นาทีก่อนขอรหัสใหม่อีกครั้ง', 429);
        }
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) studentPinResetResponse('error', 'กรุณากรอกอีเมลที่ถูกต้อง', 422);
        $stmt = $conn->prepare("SELECT s.full_name, s.email FROM public.students s JOIN public.users u ON u.user_id = s.user_id WHERE s.user_id = :uid AND LOWER(s.email) = :email AND u.account_status = 'active'");
        $stmt->execute([':uid' => $userId, ':email' => $email]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$student || !filter_var((string) ($student['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
            studentPinResetResponse('error', 'อีเมลไม่ตรงกับบัญชีนักเรียนที่เข้าสู่ระบบ', 404);
        }
        $otp = (string) random_int(100000, 999999);
        require_once __DIR__ . '/registration_mailer.php';
        $failureStage = 'mail';
        sendStudentPinResetOtp((string) $student['email'], (string) $student['full_name'], $otp);
        $_SESSION['student_pin_reset'] = [
            'otp_hash' => password_hash($otp, PASSWORD_DEFAULT),
            'expires_at' => time() + 600,
            'sent_at' => time(),
            'attempts' => 0,
            'verified' => false,
        ];
        $failureStage = 'database';
        studentPinResetResponse('success', 'ส่งรหัสยืนยันไปยังอีเมลที่ลงทะเบียนไว้แล้ว');
    }

    $reset = $_SESSION['student_pin_reset'] ?? null;
    if (!is_array($reset) || (int) ($reset['expires_at'] ?? 0) < time()) {
        unset($_SESSION['student_pin_reset']);
        studentPinResetResponse('error', 'รหัสยืนยันหมดอายุ กรุณากดลืม PIN เพื่อขอรหัสใหม่', 410);
    }

    if ($action === 'reset') {
        if (empty($reset['verified'])) studentPinResetResponse('error', 'กรุณายืนยันรหัสจากอีเมลก่อน', 403);
        $pin = trim((string) ($_POST['pin'] ?? ''));
        $confirm = trim((string) ($_POST['pin_confirm'] ?? ''));
        if (!preg_match('/^[0-9]{6}$/', $pin) || $pin !== $confirm) {
            studentPinResetResponse('error', 'PIN ใหม่ต้องเป็นตัวเลข 6 หลักและกรอกยืนยันให้ตรงกัน', 422);
        }
        $conn->exec('ALTER TABLE public.students ADD COLUMN IF NOT EXISTS pin_hash TEXT');
        $stmt = $conn->prepare('UPDATE public.students SET pin_hash = :pin_hash WHERE user_id = :uid');
        $stmt->execute([':pin_hash' => password_hash($pin, PASSWORD_DEFAULT), ':uid' => $userId]);
        if ($stmt->rowCount() < 1) studentPinResetResponse('error', 'ไม่พบข้อมูลนักเรียน', 404);
        unset($_SESSION['student_pin_reset']);
        studentPinResetResponse('success', 'ตั้ง PIN ใหม่สำเร็จ');
    }

    if ($action === 'verify') {
        $otp = trim((string) ($_POST['otp'] ?? ''));
        if (!preg_match('/^[0-9]{6}$/', $otp)) studentPinResetResponse('error', 'กรุณากรอกรหัสยืนยัน 6 หลัก', 422);
        if ((int) ($reset['attempts'] ?? 0) >= 5) {
            unset($_SESSION['student_pin_reset']);
            studentPinResetResponse('error', 'กรอกรหัสผิดครบจำนวนครั้ง กรุณาขอรหัสใหม่', 429);
        }
        if (!password_verify($otp, (string) ($reset['otp_hash'] ?? ''))) {
            $_SESSION['student_pin_reset']['attempts'] = (int) $reset['attempts'] + 1;
            studentPinResetResponse('error', 'รหัสยืนยันไม่ถูกต้อง', 422);
        }
        $_SESSION['student_pin_reset']['verified'] = true;
        studentPinResetResponse('success', 'ยืนยันอีเมลสำเร็จ กรุณาตั้ง PIN ใหม่');
    }
    studentPinResetResponse('error', 'คำขอไม่ถูกต้อง', 400);
} catch (Throwable $error) {
    error_log('[student.pin-reset] ' . get_class($error) . ': ' . $error->getMessage());
    $failureStage = $failureStage ?? 'database';
    $message = $failureStage === 'mail'
        ? (function_exists('flexibleMailFailureMessage') ? flexibleMailFailureMessage($error) : $error->getMessage())
        : 'ดำเนินการไม่สำเร็จ กรุณาลองใหม่อีกครั้ง';
    studentPinResetResponse('error', $message, $failureStage === 'mail' ? 502 : 500);
}
