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
        'SELECT lesson_no, status, score, total_score, COALESCE(test_id, 0) AS attempt_order
         FROM public.test
         WHERE student_id = :student_id
           AND (
                subjects_id = :subjects_id
                OR (:subject_name <> \'\' AND course_name = :subject_name)
           )
           AND lesson_no IS NOT NULL
         ORDER BY lesson_no ASC, COALESCE(test_id, 0) DESC'
    );
    $stmt->execute([
        ':student_id' => $studentId,
        ':subjects_id' => $subjectId,
        ':subject_name' => $subjectName,
    ]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $lessonMap = [];
    foreach ($rows as $row) {
        $lessonNo = (int) ($row['lesson_no'] ?? 0);
        if ($lessonNo <= 0 || isset($lessonMap[$lessonNo])) {
            continue;
        }
        $lessonMap[$lessonNo] = [
            'lesson_no' => $lessonNo,
            'status' => (string) ($row['status'] ?? 'fail'),
            'score' => (int) ($row['score'] ?? 0),
            'total_score' => (int) ($row['total_score'] ?? 0),
        ];
    }

    ksort($lessonMap);
    $lessonResults = array_values($lessonMap);
    $passedLessons = [];
    foreach ($lessonResults as $lesson) {
        if (($lesson['status'] ?? '') === 'pass') {
            $passedLessons[] = (int) $lesson['lesson_no'];
        }
    }

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
