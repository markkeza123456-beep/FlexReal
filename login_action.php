<?php
session_start(); header('Content-Type: application/json; charset=utf-8');
try {
    require_once __DIR__ . '/db_connect.php'; require_once __DIR__ . '/curriculum_subjects_lib.php';
    $role = strtolower(trim((string) ($_POST['role'] ?? ''))); $id = preg_replace('/\D+/', '', trim((string) ($_POST['email'] ?? ''))); $password = (string) ($_POST['password'] ?? '');
    if (!in_array($role, ['student', 'parent', 'teacher', 'staff'], true) || $id === '' || $password === '') throw new RuntimeException('กรุณากรอกข้อมูลให้ครบถ้วน');
    $profile = match ($role) {
        'student' => 'SELECT full_name AS name, student_level, NULL::varchar AS student_id FROM public.students WHERE user_id = u.user_id',
        'parent' => 'SELECT p.full_name AS name, NULL::varchar AS student_level, (SELECT s.user_id FROM public.students s WHERE s.parent_user_id = u.user_id LIMIT 1) AS student_id FROM public.parents p WHERE p.user_id = u.user_id',
        'teacher' => 'SELECT full_name AS name, NULL::varchar AS student_level, NULL::varchar AS student_id FROM public.teachers WHERE user_id = u.user_id',
        'staff' => "SELECT CONCAT(first_name, ' ', last_name) AS name, NULL::varchar AS student_level, NULL::varchar AS student_id FROM public.staff WHERE user_id = u.user_id",
    };
    $stmt = $conn->prepare("SELECT u.user_id, u.password_hash, u.account_status, p.name, p.student_level, p.student_id FROM public.users u CROSS JOIN LATERAL ({$profile}) p WHERE u.user_id = :id AND u.role = :role");
    $stmt->execute([':id' => $id, ':role' => $role]); $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || !password_verify($password, (string) $user['password_hash'])) throw new RuntimeException('ข้อมูลไม่ถูกต้อง');
    if (($user['account_status'] ?? 'active') !== 'active') throw new RuntimeException('บัญชีนี้ไม่พร้อมใช้งาน');
    $_SESSION['user_id'] = $user['user_id']; $_SESSION['role'] = $role; $_SESSION['name'] = $user['name'] ?? '';
    if ($role === 'student' && !empty($user['student_level'])) assignCurriculumAndEnrollRequiredSubjects($conn, (string) $user['user_id'], (string) $user['student_level']);
    if ($role === 'parent') $_SESSION['current_student_id'] = $user['student_id'] ?? null;
    $redirect = match ($role) { 'parent' => 'parent_dashboard.php', 'teacher' => 'teacherdash.php', 'staff' => 'staffdash.php', default => 'web.html' };
    echo json_encode(['status' => 'success', 'redirect_url' => $redirect, 'user_id' => (string) $user['user_id']], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE); }
