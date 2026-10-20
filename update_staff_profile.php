<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function staffProfileResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['user_id']) || strtolower((string) ($_SESSION['role'] ?? '')) !== 'staff') {
    staffProfileResponse(['success' => false, 'message' => 'กรุณาล็อกอินใหม่'], 401);
}

$userId = (string) $_SESSION['user_id'];
$firstName = trim((string) ($_POST['first_name'] ?? ''));
$lastName = trim((string) ($_POST['last_name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$currentPassword = (string) ($_POST['pwd_current'] ?? '');
$newPassword = (string) ($_POST['pwd_new'] ?? '');
$confirmPassword = (string) ($_POST['pwd_confirm'] ?? '');

if ($firstName === '' || $lastName === '' || mb_strlen($firstName) > 100 || mb_strlen($lastName) > 100) {
    staffProfileResponse(['success' => false, 'message' => 'กรุณากรอกชื่อและนามสกุลให้ถูกต้อง'], 422);
}
if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 254)) {
    staffProfileResponse(['success' => false, 'message' => 'รูปแบบอีเมลไม่ถูกต้อง'], 422);
}
if (mb_strlen($phone) > 30) staffProfileResponse(['success' => false, 'message' => 'เบอร์โทรศัพท์ยาวเกินไป'], 422);
if ($newPassword !== '' && (mb_strlen($newPassword) < 6 || $currentPassword === '' || $newPassword !== $confirmPassword)) {
    staffProfileResponse(['success' => false, 'message' => 'กรุณาตรวจสอบรหัสผ่านปัจจุบัน รหัสผ่านใหม่ และการยืนยันรหัสผ่าน'], 422);
}

try {
    require_once __DIR__ . '/config/db.php';
    $conn->beginTransaction();
    if ($newPassword !== '') {
        $stmt = $conn->prepare('SELECT password_hash FROM public.users WHERE user_id = :id FOR UPDATE');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !password_verify($currentPassword, (string) $user['password_hash'])) {
            $conn->rollBack();
            staffProfileResponse(['success' => false, 'message' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง'], 422);
        }
        $stmt = $conn->prepare('UPDATE public.users SET password_hash = :hash, updated_at = NOW() WHERE user_id = :id');
        $stmt->execute([':hash' => password_hash($newPassword, PASSWORD_DEFAULT), ':id' => $userId]);
    }

    $stmt = $conn->prepare('UPDATE public.staff SET first_name = :first_name, last_name = :last_name, email = :email, phone = :phone, updated_at = NOW() WHERE user_id = :id');
    $stmt->execute([
        ':first_name' => $firstName,
        ':last_name' => $lastName,
        ':email' => $email !== '' ? $email : null,
        ':phone' => $phone !== '' ? $phone : null,
        ':id' => $userId,
    ]);
    if ($stmt->rowCount() === 0) {
        $check = $conn->prepare('SELECT 1 FROM public.staff WHERE user_id = :id');
        $check->execute([':id' => $userId]);
        if (!$check->fetchColumn()) throw new RuntimeException('ไม่พบข้อมูลเจ้าหน้าที่');
    }
    $conn->commit();

    $fullName = $firstName . ' ' . $lastName;
    $_SESSION['name'] = $fullName;
    staffProfileResponse(['success' => true, 'message' => 'บันทึกข้อมูลโปรไฟล์แล้ว', 'name' => $fullName]);
} catch (Throwable $error) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) $conn->rollBack();
    error_log('Staff profile update failed: ' . $error->getMessage());
    staffProfileResponse(['success' => false, 'message' => 'บันทึกข้อมูลไม่สำเร็จ กรุณาลองอีกครั้ง'], 500);
}
