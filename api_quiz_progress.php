<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config/db.php';

const QUIZ_PASS_RATIO = 1.0;

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
$studentId = (string) $_SESSION['user_id'];
session_write_close();

$subjectId = trim((string) ($_GET['subject_id'] ?? ''));
if ($subjectId === '') {
    jsonResponse(['status' => 'error', 'message' => 'subject_id is required'], 400);
}

try {
    $stmtResults = $conn->prepare(
        "SELECT l.position AS lesson_no, a.score, a.total_score, a.status,
                CASE WHEN COALESCE(lp.is_transferred, FALSE) THEN 1 ELSE 0 END AS is_transferred
         FROM public.lessons l
         LEFT JOIN public.lesson_progress lp
           ON lp.lesson_id = l.lesson_id AND lp.student_id = :progress_student_id
         LEFT JOIN LATERAL (
             SELECT score, total_score, status
             FROM public.quiz_attempts
             WHERE student_id = :student_id AND lesson_id = l.lesson_id
             ORDER BY submitted_at DESC LIMIT 1
         ) a ON true
         WHERE l.course_id = :course_id
         ORDER BY l.position ASC"
    );
    $stmtResults->execute([
        ':progress_student_id' => $studentId,
        ':student_id' => $studentId,
        ':course_id' => $subjectId,
    ]);
    $lessonResults = $stmtResults->fetchAll(PDO::FETCH_ASSOC);
    $passedLessons = array_map(
        static fn(array $row): int => (int) $row['lesson_no'],
        array_filter($lessonResults, static fn(array $row): bool =>
            (int) $row['is_transferred'] === 1
            || $row['status'] === 'passed'
            || ($row['status'] !== 'pending_review' && (float) $row['total_score'] > 0 && (float) $row['score'] >= ceil((float) $row['total_score'] * QUIZ_PASS_RATIO))
        )
    );

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
