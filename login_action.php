<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function loginResponse(string $status, int $httpStatus, array $extra = []): never
{
    http_response_code($httpStatus);
    echo json_encode(['status' => $status] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    loginResponse('error', 405, ['message' => 'คำขอไม่ถูกต้อง']);
}

$role = strtolower(trim((string) ($_POST['role'] ?? '')));
$rawId = trim((string) ($_POST['email'] ?? ''));
$id = str_replace(['-', ' ', "\t", "\r", "\n"], '', $rawId);
$password = (string) ($_POST['password'] ?? '');

if (!in_array($role, ['student', 'parent', 'teacher', 'staff'], true)
    || !preg_match('/^[0-9]{13}$/', $id)
    || $password === '') {
    error_log('[auth.login] rejected invalid request fields');
    loginResponse('error', 422, ['message' => 'กรุณากรอกเลขบัตรประชาชน 13 หลักและรหัสผ่านให้ครบถ้วน']);
}

try {
    require_once __DIR__ . '/config/db.php';
    require_once __DIR__ . '/curriculum_subjects_lib.php';

    $profile = match ($role) {
        'student' => 'SELECT full_name AS name, student_level, NULL::varchar AS student_id FROM public.students WHERE user_id = u.user_id',
        'parent' => 'SELECT p.full_name AS name, NULL::varchar AS student_level, (SELECT s.user_id FROM public.students s WHERE s.parent_user_id = u.user_id LIMIT 1) AS student_id FROM public.parents p WHERE p.user_id = u.user_id',
        'teacher' => 'SELECT full_name AS name, NULL::varchar AS student_level, NULL::varchar AS student_id FROM public.teachers WHERE user_id = u.user_id',
        'staff' => "SELECT CONCAT(first_name, ' ', last_name) AS name, NULL::varchar AS student_level, NULL::varchar AS student_id FROM public.staff WHERE user_id = u.user_id",
    };
    $stmt = $conn->prepare("SELECT u.user_id, u.password_hash, u.account_status, p.name, p.student_level, p.student_id FROM public.users u CROSS JOIN LATERAL ({$profile}) p WHERE u.user_id = :id AND u.role = :role");
    $stmt->execute([':id' => $id, ':role' => $role]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, (string) $user['password_hash'])) {
        error_log('[auth.login] authentication failed');
        loginResponse('error', 401, ['message' => 'ข้อมูลไม่ถูกต้อง']);
    }
    if (($user['account_status'] ?? 'active') !== 'active') {
        error_log('[auth.login] inactive account rejected');
        loginResponse('error', 403, ['message' => 'บัญชีนี้ไม่พร้อมใช้งาน']);
    }

    if ($role === 'student' && !empty($user['student_level'])) {
        assignCurriculumAndEnrollRequiredSubjects($conn, (string) $user['user_id'], (string) $user['student_level']);
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['role'] = $role;
    $_SESSION['name'] = $user['name'] ?? '';
    if ($role === 'parent') {
        $_SESSION['current_student_id'] = $user['student_id'] ?? null;
    }

    $redirect = match ($role) {
        'parent' => 'parent_dashboard.php',
        'teacher' => 'teacherdash.php',
        'staff' => 'staffdash.php',
        default => 'index.php',
    };
    error_log('[auth.login] success');
    loginResponse('success', 200, ['redirect_url' => $redirect, 'user_id' => (string) $user['user_id']]);
} catch (Throwable $error) {
    error_log('[auth.login] backend failure: ' . get_class($error));
    loginResponse('error', 500, ['message' => 'ระบบเข้าสู่ระบบขัดข้อง กรุณาลองใหม่ภายหลัง']);
}
