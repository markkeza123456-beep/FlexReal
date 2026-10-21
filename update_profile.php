<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config/db.php';


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'กรุณาล็อกอินใหม่']);
    exit;
}
if (isset($_FILES['avatar'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'แก้ไขรูปโปรไฟล์ได้เฉพาะเจ้าหน้าที่'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user_id = $_SESSION['user_id'];
$firstName = trim((string) ($_POST['first_name'] ?? ''));
$lastName = trim((string) ($_POST['last_name'] ?? ''));
$name = trim($firstName . ' ' . $lastName);
$pwd_current = $_POST['pwd_current'] ?? '';
$pwd_new = $_POST['pwd_new'] ?? '';

if (($firstName !== '' || $lastName !== '') && ($firstName === '' || $lastName === '' || mb_strlen($firstName) > 100 || mb_strlen($lastName) > 100)) {
    echo json_encode(['success' => false, 'message' => 'กรุณากรอกชื่อและนามสกุลให้ถูกต้อง']);
    exit;
}
if ($pwd_new !== '' && (mb_strlen($pwd_new) < 6 || $pwd_current === '')) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'กรุณาตรวจสอบรหัสผ่านปัจจุบันและรหัสผ่านใหม่ (อย่างน้อย 6 ตัวอักษร)'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $conn->beginTransaction();

    if ($pwd_new !== '') {
        $stmt = $conn->prepare('SELECT password_hash FROM public.users WHERE user_id = :uid FOR UPDATE');
        $stmt->execute(['uid' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !password_verify($pwd_current, (string) $user['password_hash'])) {
            $conn->rollBack();
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    if ($name !== '') {
        // Teachers may edit their name and password; contact and address values are intentionally ignored.
        $stmt = $conn->prepare("UPDATE public.teachers SET full_name = :name, updated_at = NOW() WHERE user_id = :uid");
        $stmt->execute(['name' => $name, 'uid' => $user_id]);
        $conn->prepare('UPDATE public.users SET updated_by = :updated_by, updated_at = NOW() WHERE user_id = :uid')
            ->execute(['updated_by' => $user_id, 'uid' => $user_id]);
        $_SESSION['name'] = $name;
    }


    if (!empty($pwd_new)) {
        $stmt_upd1 = $conn->prepare('UPDATE public.users SET password_hash = :pass, updated_by = :updated_by, updated_at = NOW() WHERE user_id = :uid');
        $stmt_upd1->execute(['pass' => password_hash($pwd_new, PASSWORD_DEFAULT), 'updated_by' => $user_id, 'uid' => $user_id]);
    }

    $conn->prepare('UPDATE public.users SET updated_by = :updated_by, updated_at = NOW() WHERE user_id = :uid')
        ->execute(['updated_by' => $user_id, 'uid' => $user_id]);

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'บันทึกข้อมูลสำเร็จ!', 'name' => (string) ($_SESSION['name'] ?? '')], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'ระบบฐานข้อมูลผิดพลาด: ' . $e->getMessage()]);
}
?>
