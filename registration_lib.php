<?php

function validateRegistrationPayload(PDO $conn, array $post): array
{
    $conn->exec('ALTER TABLE public.students ADD COLUMN IF NOT EXISTS pin_hash TEXT');
    $role = strtolower(trim((string) ($post['role'] ?? '')));
    $userId = str_replace(['-', ' ', "\t", "\r", "\n"], '', trim((string) ($post['userid'] ?? '')));
    $password = (string) ($post['password'] ?? '');
    $firstName = trim((string) ($post['firstname'] ?? $post['fullname'] ?? ''));
    $lastName = trim((string) ($post['lastname'] ?? ''));
    $fullName = trim($firstName . ' ' . $lastName);
    $email = strtolower(trim((string) ($post['email'] ?? '')));
    $phone = preg_replace('/\D+/', '', (string) ($post['phone'] ?? ''));
    $level = trim((string) ($post['level'] ?? ''));
    $studentId = str_replace(['-', ' ', "\t", "\r", "\n"], '', trim((string) ($post['link_student_id'] ?? '')));
    $address = trim((string) ($post['house'] ?? ''));
    $subdistrict = trim((string) ($post['tambon'] ?? ''));
    $district = trim((string) ($post['amphoe'] ?? ''));
    $province = trim((string) ($post['province'] ?? ''));
    $postalCode = preg_replace('/\D+/', '', (string) ($post['zipcode'] ?? ''));
    $studentPin = trim((string) ($post['student_pin'] ?? ''));
    $linkedStudentPin = trim((string) ($post['link_student_pin'] ?? ''));

    if (!in_array($role, ['student', 'teacher', 'parent'], true)) throw new RuntimeException('กรุณาเลือกบทบาทสมาชิกให้ถูกต้อง');
    if (!preg_match('/^[0-9]{13}$/', $userId)) throw new RuntimeException('เลขบัตรประชาชนต้องมีตัวเลข 13 หลัก');
    if ($fullName === '') throw new RuntimeException('กรุณากรอกชื่อและนามสกุล');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('กรุณากรอกอีเมลที่ถูกต้องเพื่อรับ OTP');
    if (strlen($phone) !== 10) throw new RuntimeException('เบอร์โทรศัพท์ต้องมี 10 หลัก');
    if ($address === '' || $subdistrict === '' || $district === '' || $province === '' || strlen($postalCode) !== 5) throw new RuntimeException('กรุณากรอกที่อยู่และรหัสไปรษณีย์ให้ครบถ้วน');
    if (!preg_match('/^(?=.*[A-Za-z])(?=.*[0-9])[A-Za-z0-9]{8,15}$/', $password)) throw new RuntimeException('รหัสผ่านต้องมีตัวอักษรภาษาอังกฤษและตัวเลข 8-15 ตัว');
    if ($password !== (string) ($post['confirm'] ?? '')) throw new RuntimeException('รหัสผ่านไม่ตรงกัน');
    if ($role === 'student' && $level === '') throw new RuntimeException('กรุณาเลือกระดับชั้น');
    if ($role === 'student' && !preg_match('/^[0-9]{6}$/', $studentPin)) throw new RuntimeException('กรุณาตั้ง PIN นักเรียน 6 หลัก');
    if ($role === 'parent' && $studentId !== '' && !preg_match('/^[0-9]{13}$/', $studentId)) throw new RuntimeException('เลขประจำตัวนักเรียนต้องมี 13 หลัก');
    if ($role === 'parent' && $studentId !== '') {
        if (!preg_match('/^[0-9]{6}$/', $linkedStudentPin)) throw new RuntimeException('กรุณากรอก PIN นักเรียน 6 หลัก');
        $studentCheck = $conn->prepare('SELECT pin_hash, parent_user_id FROM public.students WHERE user_id = :id');
        $studentCheck->execute([':id' => $studentId]);
        $linkedStudent = $studentCheck->fetch(PDO::FETCH_ASSOC);
        if (!$linkedStudent) throw new RuntimeException('ไม่พบนักเรียนที่ต้องการเชื่อมโยง');
        if (empty($linkedStudent['pin_hash']) || !password_verify($linkedStudentPin, (string) $linkedStudent['pin_hash'])) throw new RuntimeException('PIN นักเรียนไม่ถูกต้อง หรือยังไม่ได้ตั้ง PIN');
        if (!empty($linkedStudent['parent_user_id'])) throw new RuntimeException('นักเรียนคนนี้เชื่อมโยงกับผู้ปกครองแล้ว');
    }

    $idCheck = $conn->prepare('SELECT 1 FROM public.users WHERE user_id = :id');
    $idCheck->execute([':id' => $userId]);
    if ($idCheck->fetchColumn()) throw new RuntimeException('เลขบัตรประชาชนนี้มีบัญชีผู้ใช้งานแล้ว');

    // A profile only owns an email while its parent account is active. Older
    // installations may not have email columns on users, so join via user_id.
    $emailCheck = $conn->prepare("SELECT EXISTS (
        SELECT 1 FROM public.students s JOIN public.users u ON u.user_id = s.user_id
        WHERE LOWER(s.email) = :student_email AND u.account_status = 'active'
    ) OR EXISTS (
        SELECT 1 FROM public.teachers t JOIN public.users u ON u.user_id = t.user_id
        WHERE LOWER(t.email) = :teacher_email AND u.account_status = 'active'
    ) OR EXISTS (
        SELECT 1 FROM public.parents p JOIN public.users u ON u.user_id = p.user_id
        WHERE LOWER(p.email) = :parent_email AND u.account_status = 'active'
    )");
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
        'student_pin_hash' => $role === 'student' ? password_hash($studentPin, PASSWORD_DEFAULT) : null,
        'link_student_id' => $studentId,
        'link_student_pin' => $linkedStudentPin,
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
            $conn->prepare('INSERT INTO public.students (user_id, full_name, email, phone, student_level, pin_hash) VALUES (:id, :name, :email, :phone, :level, :pin_hash)')
                ->execute([':id' => $data['user_id'], ':name' => $data['full_name'], ':email' => $data['email'], ':phone' => $data['phone'] ?: null, ':level' => $data['level'], ':pin_hash' => $data['student_pin_hash']]);
            if ($data['level'] !== '') assignCurriculumAndEnrollRequiredSubjects($conn, $data['user_id'], $data['level']);
        } elseif ($data['role'] === 'teacher') {
            $conn->prepare('INSERT INTO public.teachers (user_id, full_name, email, phone) VALUES (:id, :name, :email, :phone)')
                ->execute([':id' => $data['user_id'], ':name' => $data['full_name'], ':email' => $data['email'], ':phone' => $data['phone'] ?: null]);
        } else {
            if ($data['link_student_id'] !== '') {
                $studentCheck = $conn->prepare('SELECT pin_hash, parent_user_id FROM public.students WHERE user_id = :id FOR UPDATE');
                $studentCheck->execute([':id' => $data['link_student_id']]);
                $linkedStudent = $studentCheck->fetch(PDO::FETCH_ASSOC);
                if (!$linkedStudent) throw new RuntimeException('ไม่พบนักเรียนที่ต้องการเชื่อมโยง');
                if (empty($linkedStudent['pin_hash']) || !password_verify((string) $data['link_student_pin'], (string) $linkedStudent['pin_hash'])) throw new RuntimeException('PIN นักเรียนไม่ถูกต้อง หรือยังไม่ได้ตั้ง PIN');
                if (!empty($linkedStudent['parent_user_id'])) throw new RuntimeException('นักเรียนคนนี้เชื่อมโยงกับผู้ปกครองแล้ว');
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
