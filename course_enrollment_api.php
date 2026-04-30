<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db_connect.php';

function jsonResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function ensureEnrollmentTable(PDO $conn): void
{
    $conn->exec(
        'CREATE TABLE IF NOT EXISTS public.course_enrollments (
            student_id TEXT NOT NULL,
            course_name TEXT NOT NULL,
            enrolled_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            PRIMARY KEY (student_id, course_name)
        )'
    );
}

function currentStudentId(): ?string
{
    $role = strtolower((string) ($_SESSION['role'] ?? ''));
    if (!isset($_SESSION['user_id']) || $role !== 'student') {
        return null;
    }

    return (string) $_SESSION['user_id'];
}

$courseName = trim((string) ($_POST['course_name'] ?? $_GET['course_name'] ?? ''));
if ($courseName === '') {
    jsonResponse(['status' => 'error', 'message' => 'ไม่พบชื่อรายวิชา'], 400);
}

$studentId = currentStudentId();
if ($studentId === null) {
    $_SESSION['after_login_return'] = 'web.html?course=' . rawurlencode($courseName);

    jsonResponse([
        'status' => 'unauthorized',
        'message' => 'กรุณาเข้าสู่ระบบนักเรียนก่อนลงรายวิชา',
        'login_url' => 'login.php',
    ], 401);
}

try {
    ensureEnrollmentTable($conn);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $stmt = $conn->prepare(
            'INSERT INTO public.course_enrollments (student_id, course_name)
             VALUES (:student_id, :course_name)
             ON CONFLICT (student_id, course_name) DO NOTHING'
        );
        $stmt->execute([
            ':student_id' => $studentId,
            ':course_name' => $courseName,
        ]);

        jsonResponse([
            'status' => 'success',
            'enrolled' => true,
            'course_name' => $courseName,
        ]);
    }

    $stmt = $conn->prepare(
        'SELECT 1 FROM public.course_enrollments
         WHERE student_id = :student_id AND course_name = :course_name
         LIMIT 1'
    );
    $stmt->execute([
        ':student_id' => $studentId,
        ':course_name' => $courseName,
    ]);

    jsonResponse([
        'status' => 'success',
        'enrolled' => (bool) $stmt->fetchColumn(),
        'course_name' => $courseName,
    ]);
} catch (Throwable $e) {
    jsonResponse([
        'status' => 'error',
        'message' => 'ระบบลงรายวิชาขัดข้อง: ' . $e->getMessage(),
    ], 500);
}
