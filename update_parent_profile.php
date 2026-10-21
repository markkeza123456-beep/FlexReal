<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

function parentProfileJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['user_id']) || strtolower((string) ($_SESSION['role'] ?? '')) !== 'parent') {
    parentProfileJson(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบใหม่'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    parentProfileJson(['success' => false, 'message' => 'ไม่รองรับคำขอนี้'], 405);
}

$userId = (string) $_SESSION['user_id'];
$firstName = trim((string) ($_POST['first_name'] ?? ''));
$lastName = trim((string) ($_POST['last_name'] ?? ''));
$name = trim($firstName . ' ' . $lastName);
$currentPassword = (string) ($_POST['pwd_current'] ?? '');
$newPassword = (string) ($_POST['pwd_new'] ?? '');
$confirmPassword = (string) ($_POST['pwd_confirm'] ?? '');

if ($firstName === '' || $lastName === '' || mb_strlen($firstName) > 100 || mb_strlen($lastName) > 100) {
    parentProfileJson(['success' => false, 'message' => 'กรุณากรอกชื่อและนามสกุลให้ถูกต้อง'], 422);
}
if ($newPassword !== '' && (mb_strlen($newPassword) < 6 || $currentPassword === '' || $newPassword !== $confirmPassword)) {
    parentProfileJson(['success' => false, 'message' => 'กรุณาตรวจสอบรหัสผ่านปัจจุบัน รหัสผ่านใหม่ และการยืนยันรหัสผ่าน'], 422);
}

require_once __DIR__ . '/config/db.php';
try {
    $conn->beginTransaction();
    if ($newPassword !== '') {
        $stmt = $conn->prepare('SELECT password_hash FROM public.users WHERE user_id = :id FOR UPDATE');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !password_verify($currentPassword, (string) $user['password_hash'])) {
            $conn->rollBack();
            parentProfileJson(['success' => false, 'message' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง'], 422);
        }
        $stmt = $conn->prepare('UPDATE public.users SET password_hash = :hash, updated_at = NOW() WHERE user_id = :id');
        $stmt->execute([':hash' => password_hash($newPassword, PASSWORD_DEFAULT), ':id' => $userId]);
    }

    // Non-staff users may update name and password only; contact and address changes are ignored.
    $stmt = $conn->prepare('UPDATE public.parents SET full_name = :name, updated_at = NOW() WHERE user_id = :id');
    $stmt->execute([':name' => $name, ':id' => $userId]);
    if ($stmt->rowCount() === 0) {
        $check = $conn->prepare('SELECT 1 FROM public.parents WHERE user_id = :id');
        $check->execute([':id' => $userId]);
        if (!$check->fetchColumn()) throw new RuntimeException('ไม่พบข้อมูลผู้ปกครอง');
    }
    $conn->prepare('UPDATE public.users SET updated_by = :updated_by, updated_at = NOW() WHERE user_id = :id')
        ->execute([':updated_by' => $userId, ':id' => $userId]);
    $conn->commit();
    $_SESSION['name'] = $name;
    parentProfileJson(['success' => true, 'message' => $newPassword !== '' ? 'เปลี่ยนรหัสผ่านสำเร็จ' : 'บันทึกข้อมูลสำเร็จ']);
} catch (Throwable $error) {
    if ($conn->inTransaction()) $conn->rollBack();
    error_log('Parent profile update failed: ' . $error->getMessage());
    parentProfileJson(['success' => false, 'message' => 'บันทึกข้อมูลไม่สำเร็จ กรุณาลองอีกครั้ง'], 500);
}
