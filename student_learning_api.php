<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/learning_progress_lib.php';

function learningJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function currentLearningStudentId(): ?string
{
    $role = strtolower((string) ($_SESSION['role'] ?? ''));
    if (!isset($_SESSION['user_id']) || $role !== 'student') {
        return null;
    }

    return (string) $_SESSION['user_id'];
}

function fetchCourseSummary(PDO $conn, string $studentId, string $subjectId): array
{
    ensureLearningProgressTables($conn);

    $subjectStmt = $conn->prepare(
        'SELECT subjects_id, subjects_name
         FROM public.subjects
         WHERE subjects_id = :subject_id
         LIMIT 1'
    );
    $subjectStmt->execute([':subject_id' => $subjectId]);
    $subject = $subjectStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $lessonCountStmt = $conn->prepare(
        'SELECT COUNT(*) FROM public.lessons WHERE subjects_id = :subject_id'
    );
    $lessonCountStmt->execute([':subject_id' => $subjectId]);
    $lessonCount = (int) $lessonCountStmt->fetchColumn();
    if ($lessonCount <= 0) {
        $lessonCount = 5;
    }

    $summaryStmt = $conn->prepare(
        'SELECT
            COUNT(*) AS started_lessons,
            COALESCE(AVG(progress_percent), 0) AS average_progress,
            COALESCE(MAX(
                CASE
                    WHEN quiz_total_score > 0 THEN (best_quiz_score::numeric / quiz_total_score) * 100
                    ELSE 0
                END
            ), 0) AS best_score_percent,
            COALESCE(MAX(last_activity_at), NULL) AS last_activity_at
         FROM public.student_learning_progress
         WHERE student_id = :student_id
           AND subjects_id = :subject_id'
    );
    $summaryStmt->execute([
        ':student_id' => $studentId,
        ':subject_id' => $subjectId,
    ]);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $lessonProgressStmt = $conn->prepare(
        'SELECT lesson_index, lesson_title, opened_count, video_open_count, progress_percent, best_quiz_score, quiz_total_score, last_activity_at
         FROM public.student_learning_progress
         WHERE student_id = :student_id
           AND subjects_id = :subject_id
         ORDER BY lesson_index ASC'
    );
    $lessonProgressStmt->execute([
        ':student_id' => $studentId,
        ':subject_id' => $subjectId,
    ]);

    return [
        'subject_id' => $subjectId,
        'course_name' => (string) ($subject['subjects_name'] ?? ''),
        'lesson_count' => $lessonCount,
        'started_lessons' => (int) ($summary['started_lessons'] ?? 0),
        'progress_percent' => round((float) ($summary['average_progress'] ?? 0), 1),
        'best_score_percent' => round((float) ($summary['best_score_percent'] ?? 0), 1),
        'last_activity_at' => $summary['last_activity_at'] ?? null,
        'lessons' => $lessonProgressStmt->fetchAll(PDO::FETCH_ASSOC),
    ];
}

$studentId = currentLearningStudentId();
if ($studentId === null) {
    learningJson([
        'status' => 'unauthorized',
        'message' => 'กรุณาเข้าสู่ระบบนักเรียนก่อนใช้งาน',
    ], 401);
}

$action = strtolower((string) ($_GET['action'] ?? $_POST['action'] ?? 'summary'));

try {
    ensureLearningProgressTables($conn);

    if ($action === 'record') {
        $subjectId = trim((string) ($_POST['subject_id'] ?? ''));
        $lessonIndex = (int) ($_POST['lesson_index'] ?? 1);
        $lessonTitle = trim((string) ($_POST['lesson_title'] ?? ''));
        $activityType = trim((string) ($_POST['activity_type'] ?? 'lesson_open'));
        if ($subjectId === '') {
            learningJson(['status' => 'error', 'message' => 'ไม่พบรหัสรายวิชา'], 400);
        }
        if ($lessonIndex <= 0) {
            $lessonIndex = 1;
        }

        recordLearningActivity(
            $conn,
            $studentId,
            $subjectId,
            $lessonIndex,
            $activityType,
            $lessonTitle
        );

        learningJson([
            'status' => 'success',
            'summary' => fetchCourseSummary($conn, $studentId, $subjectId),
        ]);
    }

    $subjectId = trim((string) ($_GET['subject_id'] ?? ''));
    if ($subjectId === '') {
        learningJson(['status' => 'error', 'message' => 'ไม่พบรหัสรายวิชา'], 400);
    }

    learningJson([
        'status' => 'success',
        'summary' => fetchCourseSummary($conn, $studentId, $subjectId),
    ]);
} catch (Throwable $e) {
    learningJson([
        'status' => 'error',
        'message' => 'ระบบติดตามความคืบหน้าขัดข้อง: ' . $e->getMessage(),
    ], 500);
}
