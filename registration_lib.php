<?php

function validateRegistrationPayload(PDO $conn, array $post): array
{
    $role = strtolower(trim((string) ($post['role'] ?? '')));
    $userId = preg_replace('/\D+/', '', trim((string) ($post['userid'] ?? '')));
    $password = (string) ($post['password'] ?? '');
    $firstName = trim((string) ($post['firstname'] ?? $post['fullname'] ?? ''));
    $lastName = trim((string) ($post['lastname'] ?? ''));
    $fullName = trim($firstName . ' ' . $lastName);
    $email = strtolower(trim((string) ($post['email'] ?? '')));
    $phone = preg_replace('/\D+/', '', (string) ($post['phone'] ?? ''));
    $level = trim((string) ($post['level'] ?? ''));
    $studentId = preg_replace('/\D+/', '', trim((string) ($post['link_student_id'] ?? '')));
    $address = trim((string) ($post['house'] ?? ''));
    $subdistrict = trim((string) ($post['tambon'] ?? ''));
    $district = trim((string) ($post['amphoe'] ?? ''));
    $province = trim((string) ($post['province'] ?? ''));
    $postalCode = preg_replace('/\D+/', '', (string) ($post['zipcode'] ?? ''));

    if (!in_array($role, ['student', 'teacher', 'parent'], true)) throw new RuntimeException('กรุณาเลือกบทบาทสมาชิกให้ถูกต้อง');
    if (strlen($userId) !== 13 || !preg_match('/^[0-9]{13}$/', $userId)) throw new RuntimeException('เลขบัตรประชาชนต้องมีตัวเลข 13 หลัก');
    if ($fullName === '') throw new RuntimeException('กรุณากรอกชื่อและนามสกุล');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('กรุณากรอกอีเมลที่ถูกต้องเพื่อรับ OTP');
    if (strlen($phone) !== 10) throw new RuntimeException('เบอร์โทรศัพท์ต้องมี 10 หลัก');
    if ($address === '' || $subdistrict === '' || $district === '' || $province === '' || strlen($postalCode) !== 5) throw new RuntimeException('กรุณากรอกที่อยู่และรหัสไปรษณีย์ให้ครบถ้วน');
    if (!preg_match('/^(?=.*[A-Za-z])(?=.*[0-9])[A-Za-z0-9]{8,15}$/', $password)) throw new RuntimeException('รหัสผ่านต้องมีตัวอักษรภาษาอังกฤษและตัวเลข 8-15 ตัว');
    if ($password !== (string) ($post['confirm'] ?? '')) throw new RuntimeException('รหัสผ่านไม่ตรงกัน');
    if ($role === 'student' && $level === '') throw new RuntimeException('กรุณาเลือกระดับชั้น');
    if ($role === 'parent' && $studentId !== '' && strlen($studentId) !== 13) throw new RuntimeException('เลขประจำตัวนักเรียนต้องมี 13 หลัก');
    if ($role === 'parent' && $studentId !== '') {
        $studentCheck = $conn->prepare('SELECT 1 FROM public.students WHERE user_id = :id');
        $studentCheck->execute([':id' => $studentId]);
        if (!$studentCheck->fetchColumn()) throw new RuntimeException('ไม่พบนักเรียนที่ต้องการเชื่อมโยง');
    }

    $idCheck = $conn->prepare('SELECT 1 FROM public.users WHERE user_id = :id');
    $idCheck->execute([':id' => $userId]);
    if ($idCheck->fetchColumn()) throw new RuntimeException('เลขบัตรประชาชนนี้มีบัญชีผู้ใช้งานแล้ว');

    $emailCheck = $conn->prepare("SELECT EXISTS (SELECT 1 FROM public.students WHERE LOWER(email) = :student_email) OR EXISTS (SELECT 1 FROM public.teachers WHERE LOWER(email) = :teacher_email) OR EXISTS (SELECT 1 FROM public.parents WHERE LOWER(email) = :parent_email)");
    $emailCheck->execute([':student_email' => $email, ':teacher_email' => $email, ':parent_email' => $email]);
    if ($emailCheck->fetchColumn()) throw new RuntimeException('อีเมลนี้มีบัญชีผู้ใช้งานแล้ว');

    return [
        'role' => $role,
        'user_id' => $userId,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'full_name' => $fullName,
        'email' => $email,
        'phone' => $phone,
        'level' => $level,
        'link_student_id' => $studentId,
        'house' => $address,
        'tambon' => $subdistrict,
        'amphoe' => $district,
        'province' => $province,
        'zipcode' => $postalCode,
    ];
}

function createRegisteredUser(PDO $conn, array $data): void
{
    require_once __DIR__ . '/curriculum_subjects_lib.php';
    $conn->beginTransaction();
    try {
        $conn->prepare('INSERT INTO public.users (user_id, password_hash, role) VALUES (:id, :password_hash, :role)')
            ->execute([':id' => $data['user_id'], ':password_hash' => $data['password_hash'], ':role' => $data['role']]);

        if ($data['role'] === 'student') {
            $conn->prepare('INSERT INTO public.students (user_id, full_name, email, phone, student_level) VALUES (:id, :name, :email, :phone, :level)')
                ->execute([':id' => $data['user_id'], ':name' => $data['full_name'], ':email' => $data['email'], ':phone' => $data['phone'] ?: null, ':level' => $data['level']]);
            if ($data['level'] !== '') assignCurriculumAndEnrollRequiredSubjects($conn, $data['user_id'], $data['level']);
        } elseif ($data['role'] === 'teacher') {
            $conn->prepare('INSERT INTO public.teachers (user_id, full_name, email, phone) VALUES (:id, :name, :email, :phone)')
                ->execute([':id' => $data['user_id'], ':name' => $data['full_name'], ':email' => $data['email'], ':phone' => $data['phone'] ?: null]);
        } else {
            if ($data['link_student_id'] !== '') {
                $studentCheck = $conn->prepare('SELECT 1 FROM public.students WHERE user_id = :id FOR UPDATE');
                $studentCheck->execute([':id' => $data['link_student_id']]);
                if (!$studentCheck->fetchColumn()) throw new RuntimeException('ไม่พบนักเรียนที่ต้องการเชื่อมโยง');
            }
            $conn->prepare('INSERT INTO public.parents (user_id, full_name, email, phone) VALUES (:id, :name, :email, :phone)')
                ->execute([':id' => $data['user_id'], ':name' => $data['full_name'], ':email' => $data['email'], ':phone' => $data['phone'] ?: null]);
            if ($data['link_student_id'] !== '') {
                $conn->prepare('UPDATE public.students SET parent_user_id = :parent, updated_at = now() WHERE user_id = :student')
                    ->execute([':parent' => $data['user_id'], ':student' => $data['link_student_id']]);
            }
        }

        $conn->prepare('INSERT INTO public.user_addresses (user_id, address_line, subdistrict, district, province, postal_code) VALUES (:id, :line, :subdistrict, :district, :province, :postal_code)')
            ->execute([':id' => $data['user_id'], ':line' => $data['house'], ':subdistrict' => $data['tambon'], ':district' => $data['amphoe'], ':province' => $data['province'], ':postal_code' => $data['zipcode']]);
        $conn->commit();
    } catch (Throwable $error) {
        if ($conn->inTransaction()) $conn->rollBack();
        throw $error;
    }
}
