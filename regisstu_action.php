<?php
session_start(); require_once __DIR__ . '/db_connect.php'; require_once __DIR__ . '/curriculum_subjects_lib.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: regisstu.php'); exit; }
try {
    $role = strtolower(trim((string) ($_POST['role'] ?? ''))); $userId = preg_replace('/\D+/', '', (string) ($_POST['userid'] ?? '')); $phone = preg_replace('/\D+/', '', (string) ($_POST['phone'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $givenName = trim((string) ($_POST['fullname'] ?? $_POST['firstname'] ?? ''));
    $fullName = trim($givenName . ' ' . trim((string) ($_POST['lastname'] ?? '')));
    $email = trim((string) ($_POST['email'] ?? '')) ?: null;
    if (!in_array($role, ['student', 'teacher', 'parent'], true) || $userId === '' || $fullName === '' || !preg_match('/^(?=.*[A-Za-z])(?=.*[0-9])[A-Za-z0-9]{8,15}$/', $password) || $password !== (string) ($_POST['confirm'] ?? '')) throw new RuntimeException('ข้อมูลสมัครสมาชิกไม่ถูกต้อง');
    $conn->beginTransaction();
    $conn->prepare('INSERT INTO public.users (user_id, password_hash, role) VALUES (:id, :password_hash, :role)')->execute([':id' => $userId, ':password_hash' => password_hash($password, PASSWORD_DEFAULT), ':role' => $role]);
    if ($role === 'student') {
        $level = trim((string) ($_POST['level'] ?? ''));
        $conn->prepare('INSERT INTO public.students (user_id, full_name, email, phone, student_level) VALUES (:id, :name, :email, :phone, :level)')->execute([':id' => $userId, ':name' => $fullName, ':email' => $email, ':phone' => $phone ?: null, ':level' => $level ?: null]);
        if ($level !== '') assignCurriculumAndEnrollRequiredSubjects($conn, $userId, $level);
    } elseif ($role === 'teacher') {
        $conn->prepare('INSERT INTO public.teachers (user_id, full_name, email, phone) VALUES (:id, :name, :email, :phone)')->execute([':id' => $userId, ':name' => $fullName, ':email' => $email, ':phone' => $phone ?: null]);
    } else {
        // Parent accounts may be created before a student account is available.
        // If an ID is supplied, validate it and link the accounts in this transaction.
        $studentId = preg_replace('/\D+/', '', (string) ($_POST['link_student_id'] ?? ''));
        if ($studentId !== '') {
            if (strlen($studentId) !== 13) throw new RuntimeException('เลขบัตรประชาชนนักเรียนต้องมี 13 หลัก');
            $check = $conn->prepare('SELECT 1 FROM public.students WHERE user_id = :id');
            $check->execute([':id' => $studentId]);
            if (!$check->fetchColumn()) throw new RuntimeException('ไม่พบนักเรียนที่ต้องการเชื่อมโยง');
        }
        $conn->prepare('INSERT INTO public.parents (user_id, full_name, email, phone) VALUES (:id, :name, :email, :phone)')->execute([':id' => $userId, ':name' => $fullName, ':email' => $email, ':phone' => $phone ?: null]);
        if ($studentId !== '') {
            $conn->prepare('UPDATE public.students SET parent_user_id = :parent, updated_at = now() WHERE user_id = :student')->execute([':parent' => $userId, ':student' => $studentId]);
        }
    }
    $conn->prepare('INSERT INTO public.user_addresses (user_id, address_line, subdistrict, district, province, postal_code) VALUES (:id, :line, :subdistrict, :district, :province, :postal_code)')->execute([':id' => $userId, ':line' => trim((string) ($_POST['house'] ?? '')), ':subdistrict' => trim((string) ($_POST['tambon'] ?? '')) ?: null, ':district' => trim((string) ($_POST['amphoe'] ?? '')) ?: null, ':province' => trim((string) ($_POST['province'] ?? '')) ?: null, ':postal_code' => trim((string) ($_POST['zipcode'] ?? '')) ?: null]);
    $conn->commit(); echo "<script>alert('ลงทะเบียนสำเร็จ! กรุณาเข้าสู่ระบบ'); location.href='login.php';</script>";
} catch (Throwable $e) { if ($conn->inTransaction()) $conn->rollBack(); $message = json_encode($e->getMessage(), JSON_UNESCAPED_UNICODE); echo "<script>alert({$message}); history.back();</script>"; }
