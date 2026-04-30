<?php
session_start();

function json_out(array $data): void {
    if (ob_get_length() !== false) ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    json_out(['success' => false, 'message' => 'ไม่มีสิทธิ์เข้าถึง']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['success' => false, 'message' => 'Method ไม่ถูกต้อง']);
}

$teacherId  = $_SESSION['user_id'];
$name       = trim($_POST['name']       ?? '');
$pwdCurrent = trim($_POST['pwd_current'] ?? '');
$pwdNew     = trim($_POST['pwd_new']     ?? '');

if ($name === '') {
    json_out(['success' => false, 'message' => 'กรุณาใส่ชื่อ-นามสกุล']);
}

require_once 'db_connect.php';
$pdo = $conn;

$stmt = $pdo->prepare('SELECT "Password", "Teachers_Name" FROM "Teachers" WHERE "Teachers_ID" = ?');
$stmt->execute([$teacherId]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    json_out(['success' => false, 'message' => 'ไม่พบข้อมูลอาจารย์']);
}

$changePwd = ($pwdNew !== '');

if ($changePwd) {
    if (strlen($pwdNew) < 8) {
        json_out(['success' => false, 'message' => 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร']);
    }
    if (!preg_match('/[A-Z]/', $pwdNew)) {
        json_out(['success' => false, 'message' => 'รหัสผ่านต้องมีตัวพิมพ์ใหญ่อย่างน้อย 1 ตัว']);
    }
    if (!preg_match('/[0-9]/', $pwdNew)) {
        json_out(['success' => false, 'message' => 'รหัสผ่านต้องมีตัวเลขอย่างน้อย 1 ตัว']);
    }

    $storedPwd = $teacher['Password'];
    $isHashed  = strlen($storedPwd) >= 60 && str_starts_with($storedPwd, '$2');
    $currentOk = $isHashed
        ? password_verify($pwdCurrent, $storedPwd)
        : ($pwdCurrent === $storedPwd);

    if (!$currentOk) {
        json_out(['success' => false, 'message' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง']);
    }

    $hashedNew = password_hash($pwdNew, PASSWORD_BCRYPT, ['cost' => 12]);
    $update = $pdo->prepare('UPDATE "Teachers" SET "Teachers_Name" = ?, "Password" = ? WHERE "Teachers_ID" = ?');
    $update->execute([$name, $hashedNew, $teacherId]);

} else {
    $update = $pdo->prepare('UPDATE "Teachers" SET "Teachers_Name" = ? WHERE "Teachers_ID" = ?');
    $update->execute([$name, $teacherId]);
}

$_SESSION['name'] = $name;
$msg = $changePwd ? 'บันทึกข้อมูลและเปลี่ยนรหัสผ่านสำเร็จ' : 'บันทึกข้อมูลสำเร็จ';
json_out(['success' => true, 'message' => $msg]);