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

function currentStudentId(): ?string
{
    $role = strtolower((string) ($_SESSION['role'] ?? ''));
    if (!isset($_SESSION['user_id']) || $role !== 'student') {
        return null;
    }

    return (string) $_SESSION['user_id'];
}

function findSubjectIdByCourseName(PDO $conn, string $courseName): ?string
{
    $stmt = $conn->prepare('SELECT subjects_id FROM public.subjects WHERE subjects_name = :name LIMIT 1');
    $stmt->execute([':name' => $courseName]);
    $id = $stmt->fetchColumn();
    if ($id !== false) {
        return (string) $id;
    }

    $stmt = $conn->prepare('SELECT subjects_id FROM public.subjects WHERE LOWER(subjects_name) = LOWER(:name) LIMIT 1');
    $stmt->execute([':name' => $courseName]);
    $id = $stmt->fetchColumn();

    return $id === false ? null : (string) $id;
}

function ensureEnrollmentLogTable(PDO $conn): void
{
    $conn->exec(
        'CREATE TABLE IF NOT EXISTS public.student_subject_enrollment_logs (
            log_id BIGSERIAL PRIMARY KEY,
            student_id VARCHAR(50) NOT NULL,
            subjects_id VARCHAR(50) NOT NULL,
            enrolled_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )'
    );
}

$subjectIdInput = trim((string) ($_POST['subject_id'] ?? $_GET['subject_id'] ?? ''));
$courseName = trim((string) ($_POST['course_name'] ?? $_GET['course_name'] ?? ''));
if ($subjectIdInput === '' && $courseName === '') {
    jsonResponse(['status' => 'error', 'message' => 'ไม่พบรายวิชา'], 400);
}

$studentId = currentStudentId();
if ($studentId === null) {
    $_SESSION['after_login_return'] = 'web.html';

    jsonResponse([
        'status' => 'unauthorized',
        'message' => 'กรุณาเข้าสู่ระบบนักเรียนก่อนลงรายวิชา',
        'login_url' => 'login.php',
    ], 401);
}

try {
    ensureEnrollmentLogTable($conn);

    $subjectId = $subjectIdInput;
    if ($subjectId === '') {
        $subjectId = findSubjectIdByCourseName($conn, $courseName);
    }
    if ($subjectId === null || $subjectId === '') {
        jsonResponse([
            'status' => 'error',
            'message' => 'ไม่พบรายวิชานี้ในตาราง Subjects',
        ], 404);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $stmt = $conn->prepare(
            'INSERT INTO public.student_subject (student_id, subjects_id)
             VALUES (:student_id, :subjects_id)
             ON CONFLICT (student_id, subjects_id) DO NOTHING'
        );
        $stmt->execute([
            ':student_id' => $studentId,
            ':subjects_id' => $subjectId,
        ]);
        if ($stmt->rowCount() > 0) {
            $stmtLog = $conn->prepare(
                'INSERT INTO public.student_subject_enrollment_logs (student_id, subjects_id, enrolled_at)
                 VALUES (:student_id, :subjects_id, NOW())'
            );
            $stmtLog->execute([
                ':student_id' => $studentId,
                ':subjects_id' => $subjectId,
            ]);
        }

        jsonResponse([
            'status' => 'success',
            'enrolled' => true,
            'course_name' => $courseName,
            'subject_id' => $subjectId,
        ]);
    }

    $stmt = $conn->prepare(
        'SELECT 1 FROM public.student_subject
         WHERE student_id = :student_id AND subjects_id = :subjects_id
         LIMIT 1'
    );
    $stmt->execute([
        ':student_id' => $studentId,
        ':subjects_id' => $subjectId,
    ]);

    jsonResponse([
        'status' => 'success',
        'enrolled' => (bool) $stmt->fetchColumn(),
        'course_name' => $courseName,
        'subject_id' => $subjectId,
    ]);
} catch (Throwable $e) {
    jsonResponse([
        'status' => 'error',
        'message' => 'ระบบลงรายวิชาขัดข้อง: ' . $e->getMessage(),
    ], 500);
}
