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
$newUserId = trim((string) ($_POST['user_id'] ?? $userId));
$firstName = trim((string) ($_POST['first_name'] ?? ''));
$lastName = trim((string) ($_POST['last_name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$addressLine = trim((string) ($_POST['address_line'] ?? ''));
$subdistrict = trim((string) ($_POST['subdistrict'] ?? ''));
$district = trim((string) ($_POST['district'] ?? ''));
$province = trim((string) ($_POST['province'] ?? ''));
$postalCode = preg_replace('/\D+/', '', (string) ($_POST['postal_code'] ?? ''));
$currentPassword = (string) ($_POST['pwd_current'] ?? '');
$newPassword = (string) ($_POST['pwd_new'] ?? '');
$confirmPassword = (string) ($_POST['pwd_confirm'] ?? '');

if ($firstName === '' || $lastName === '' || mb_strlen($firstName) > 100 || mb_strlen($lastName) > 100) {
    staffProfileResponse(['success' => false, 'message' => 'กรุณากรอกชื่อและนามสกุลให้ถูกต้อง'], 422);
}
if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 254)) {
    staffProfileResponse(['success' => false, 'message' => 'รูปแบบอีเมลไม่ถูกต้อง'], 422);
}
if ($newUserId !== $userId && !preg_match('/^[0-9]{13}$/', $newUserId)) staffProfileResponse(['success' => false, 'message' => 'เลขบัตรประจำตัวประชาชนใหม่ต้องมี 13 หลัก'], 422);
if (mb_strlen($addressLine) > 255 || mb_strlen($subdistrict) > 150 || mb_strlen($district) > 150 || mb_strlen($province) > 150 || ($postalCode !== '' && strlen($postalCode) !== 5)) {
    staffProfileResponse(['success' => false, 'message' => 'กรุณาตรวจสอบข้อมูลที่อยู่และรหัสไปรษณีย์'], 422);
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
        $stmt = $conn->prepare('UPDATE public.users SET password_hash = :hash, updated_by = :updated_by, updated_at = NOW() WHERE user_id = :id');
        $stmt->execute([':hash' => password_hash($newPassword, PASSWORD_DEFAULT), ':updated_by' => $userId, ':id' => $userId]);
    }

    if ($newUserId !== $userId) {
        $exists = $conn->prepare('SELECT 1 FROM public.users WHERE user_id = :id');
        $exists->execute([':id' => $newUserId]);
        if ($exists->fetchColumn()) throw new RuntimeException('เลขบัตรประชาชนนี้มีบัญชีผู้ใช้งานแล้ว');

        $conn->prepare('INSERT INTO public.users (user_id, password_hash, role, account_status, created_at, updated_at, created_by, updated_by)
            SELECT :new_id, password_hash, role, account_status, created_at, updated_at, created_by, updated_by FROM public.users WHERE user_id = :old_id')
            ->execute([':new_id' => $newUserId, ':old_id' => $userId]);

        // Repoint every public foreign key to users.user_id before removing the old key.
        $references = $conn->query("SELECT DISTINCT kcu.table_name, kcu.column_name
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu ON kcu.constraint_name = tc.constraint_name AND kcu.constraint_schema = tc.constraint_schema
            JOIN information_schema.constraint_column_usage ccu ON ccu.constraint_name = tc.constraint_name AND ccu.constraint_schema = tc.constraint_schema
            WHERE tc.constraint_type = 'FOREIGN KEY' AND tc.table_schema = 'public'
              AND ccu.table_schema = 'public' AND ccu.table_name = 'users' AND ccu.column_name = 'user_id'")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($references as $reference) {
            $table = '"' . str_replace('"', '""', (string) $reference['table_name']) . '"';
            $column = '"' . str_replace('"', '""', (string) $reference['column_name']) . '"';
            $conn->prepare('UPDATE public.' . $table . ' SET ' . $column . ' = :new_id WHERE ' . $column . ' = :old_id')
                ->execute([':new_id' => $newUserId, ':old_id' => $userId]);
        }
        $conn->prepare('DELETE FROM public.users WHERE user_id = :id')->execute([':id' => $userId]);
        $userId = $newUserId;
    }

    $stmt = $conn->prepare('UPDATE public.staff SET first_name = :first_name, last_name = :last_name, email = :email, phone = :phone, updated_by = :updated_by, updated_at = NOW() WHERE user_id = :id');
    $stmt->execute([
        ':first_name' => $firstName,
        ':last_name' => $lastName,
        ':email' => $email !== '' ? $email : null,
        ':phone' => $phone !== '' ? $phone : null,
        ':updated_by' => $userId,
        ':id' => $userId,
    ]);
    if ($stmt->rowCount() === 0) {
        $check = $conn->prepare('SELECT 1 FROM public.staff WHERE user_id = :id');
        $check->execute([':id' => $userId]);
        if (!$check->fetchColumn()) throw new RuntimeException('ไม่พบข้อมูลเจ้าหน้าที่');
    }
    $addressCheck = $conn->prepare('SELECT address_id FROM public.user_addresses WHERE user_id = :id ORDER BY is_primary DESC, address_id ASC LIMIT 1 FOR UPDATE');
    $addressCheck->execute([':id' => $userId]);
    $addressId = $addressCheck->fetchColumn();
    $addressParams = [':line' => $addressLine !== '' ? $addressLine : null, ':subdistrict' => $subdistrict !== '' ? $subdistrict : null,
        ':district' => $district !== '' ? $district : null, ':province' => $province !== '' ? $province : null,
        ':postal_code' => $postalCode !== '' ? $postalCode : null];
    if ($addressId) {
        $addressParams[':id'] = $addressId;
        $conn->prepare('UPDATE public.user_addresses SET address_line = :line, subdistrict = :subdistrict, district = :district, province = :province, postal_code = :postal_code, updated_at = NOW() WHERE address_id = :id')->execute($addressParams);
    } else {
        $addressParams[':user_id'] = $userId;
        $conn->prepare('INSERT INTO public.user_addresses (user_id, address_line, subdistrict, district, province, postal_code, is_primary) VALUES (:user_id, :line, :subdistrict, :district, :province, :postal_code, TRUE)')->execute($addressParams);
    }
    $conn->prepare('UPDATE public.users SET updated_by = :updated_by, updated_at = NOW() WHERE user_id = :id')
        ->execute([':updated_by' => $userId, ':id' => $userId]);
    $conn->commit();

    $fullName = $firstName . ' ' . $lastName;
    $_SESSION['user_id'] = $userId;
    $_SESSION['name'] = $fullName;
    staffProfileResponse([
        'success' => true,
        'message' => $newPassword !== '' ? 'เปลี่ยนรหัสผ่านสำเร็จ' : 'บันทึกข้อมูลโปรไฟล์แล้ว',
        'name' => $fullName,
        'user_id' => $userId,
    ]);
} catch (Throwable $error) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) $conn->rollBack();
    error_log('Staff profile update failed: ' . $error->getMessage());
    staffProfileResponse(['success' => false, 'message' => 'บันทึกข้อมูลไม่สำเร็จ กรุณาลองอีกครั้ง'], 500);
}
