<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
error_reporting(0);

try {
    require_once __DIR__ . '/db_connect.php';

    $role = trim((string) ($_POST['role'] ?? ''));
    $rawLogin = trim((string) ($_POST['email'] ?? ''));
    $loginId = preg_replace('/\D+/', '', $rawLogin);
    $password = (string) ($_POST['password'] ?? '');

    if ($role === '' || $loginId === '' || $password === '') {
        echo json_encode([
            'status' => 'error',
            'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $redirectUrl = 'student_dashboard.php';
    $userData = null;

    switch ($role) {
        case 'student':
            $stmt = $conn->prepare(
                "SELECT u.user_id AS user_id, u.password, s.student_name AS name
                 FROM public.\"User\" u
                 LEFT JOIN public.student s ON s.student_id = u.user_id
                 WHERE u.user_id = :id
                   AND LOWER(u.status) = 'student'"
            );
            $stmt->execute([':id' => $loginId]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            break;

        case 'parent':
            $redirectUrl = 'parent_dashboard.php';
            $stmt = $conn->prepare(
                "SELECT p.parents_id AS user_id, p.password, p.parents_name AS name, s.student_id
                 FROM public.parents p
                 LEFT JOIN public.student s ON p.parents_id = s.parent_id
                 WHERE p.parents_id = :id"
            );
            $stmt->execute([':id' => $loginId]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            break;

        
            case 'teacher':
            $redirectUrl = 'teacher_dashboard.php'; // ← เปลี่ยนเป็น
            $redirectUrl = 'teacherdash.php';        // ← ชื่อไฟล์ที่ทำไว้
            $stmt = $conn->prepare(
                "SELECT teachers_id AS user_id, password, teachers_name AS name
                 FROM public.teachers
                 WHERE teachers_id = :id"
            );
            $stmt->execute([':id' => $loginId]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            break;

        case 'staff':
            $redirectUrl = 'staffdash.php';
            $stmt = $conn->prepare(
                "SELECT u.user_id AS user_id,
                        u.password,
                        COALESCE(NULLIF(TRIM(CONCAT(s.firstname, ' ', s.lastname)), ''), u.user_id) AS name
                 FROM public.\"User\" u
                 LEFT JOIN public.staff s ON s.user_id = u.user_id
                 WHERE u.user_id = :id
                   AND LOWER(u.status) = 'staff'"
            );
            $stmt->execute([':id' => $loginId]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            break;

        default:
            echo json_encode([
                'status' => 'error',
                'message' => 'ไม่พบบทบาทผู้ใช้งาน',
            ], JSON_UNESCAPED_UNICODE);
            exit;
    }

    if (!$userData) {
        echo json_encode([
            'status' => 'error',
            'message' => 'ข้อมูลไม่ถูกต้อง',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($password !== (string) ($userData['password'] ?? '')) {
        echo json_encode([
            'status' => 'error',
            'message' => 'ข้อมูลไม่ถูกต้อง',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $_SESSION['user_id'] = $userData['user_id'];
    $_SESSION['role'] = $role;
    $_SESSION['name'] = $userData['name'] ?? '';

    if ($role === 'parent') {
        $_SESSION['current_student_id'] = $userData['student_id'] ?? null;
    }

    if ($role === 'student' && !empty($_SESSION['after_login_return'])) {
        $returnUrl = (string) $_SESSION['after_login_return'];
        unset($_SESSION['after_login_return']);

        if (preg_match('/^web\.html\?course=/', $returnUrl)) {
            $redirectUrl = $returnUrl;
        }
    }

    echo json_encode([
        'status' => 'success',
        'redirect_url' => $redirectUrl,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'ระบบขัดข้อง: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
