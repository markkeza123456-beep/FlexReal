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

$role = strtolower((string) ($_SESSION['role'] ?? ''));
if (!isset($_SESSION['user_id']) || $role !== 'student') {
    jsonResponse([
        'status' => 'unauthorized',
        'passed_lessons' => [],
    ], 401);
}

$subjectId = trim((string) ($_GET['subject_id'] ?? ''));
if ($subjectId === '') {
    jsonResponse(['status' => 'error', 'message' => 'subject_id is required'], 400);
}

try {
    $studentId = (string) $_SESSION['user_id'];
    $stmtSubject = $conn->prepare(
        'SELECT subjects_name
         FROM public.subjects
         WHERE subjects_id = :subjects_id
         LIMIT 1'
    );
    $stmtSubject->execute([':subjects_id' => $subjectId]);
    $subjectName = (string) ($stmtSubject->fetchColumn() ?: '');

    $stmt = $conn->prepare(
        'SELECT DISTINCT lesson_no
         FROM public.test
         WHERE student_id = :student_id
           AND (
                subjects_id = :subjects_id
                OR (:subject_name <> \'\' AND course_name = :subject_name)
           )
           AND status = :status
           AND lesson_no IS NOT NULL
         ORDER BY lesson_no ASC'
    );
    $stmt->execute([
        ':student_id' => $studentId,
        ':subjects_id' => $subjectId,
        ':subject_name' => $subjectName,
        ':status' => 'pass',
    ]);

    $passedLessons = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    $stmtResults = $conn->prepare(
        'SELECT lesson_no, score, total_score, status
         FROM (
             SELECT
                 lesson_no,
                 score,
                 total_score,
                 status,
                 ROW_NUMBER() OVER (PARTITION BY lesson_no ORDER BY COALESCE(test_attempt, 0) DESC, test_id DESC) AS rn
             FROM public.test
             WHERE student_id = :student_id
               AND (
                    subjects_id = :subjects_id
                    OR (:subject_name <> \'\' AND course_name = :subject_name)
               )
               AND lesson_no IS NOT NULL
         ) ranked
         WHERE rn = 1
         ORDER BY lesson_no ASC'
    );
    $stmtResults->execute([
        ':student_id' => $studentId,
        ':subjects_id' => $subjectId,
        ':subject_name' => $subjectName,
    ]);
    $lessonResults = $stmtResults->fetchAll(PDO::FETCH_ASSOC);

    jsonResponse([
        'status' => 'success',
        'passed_lessons' => $passedLessons,
        'lesson_results' => $lessonResults,
    ]);
} catch (Throwable $e) {
    jsonResponse([
        'status' => 'error',
        'message' => 'ไม่สามารถโหลดความคืบหน้าแบบทดสอบได้',
    ], 500);
}
