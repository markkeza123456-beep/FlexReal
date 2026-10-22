<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function courseProgressJson(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SESSION['role'] ?? '') !== 'student' || empty($_SESSION['user_id'])) {
    courseProgressJson(['ok' => false, 'status' => 'unauthorized', 'message' => 'กรุณาเข้าสู่ระบบนักเรียนก่อนใช้งาน'], 401);
}
$studentId = (string) $_SESSION['user_id'];
session_write_close();
$courseId = trim((string) ($_GET['course_id'] ?? $_GET['subject_id'] ?? ''));
if ($courseId === '') courseProgressJson(['ok' => false, 'status' => 'error', 'message' => 'ไม่พบรหัสรายวิชา'], 400);

try {
    require_once __DIR__ . '/config/db.php';
    require_once __DIR__ . '/learning_progress_lib.php';
    $course = $conn->prepare('SELECT course_id, name FROM public.courses WHERE course_id = :id');
    $course->execute([':id' => $courseId]);
    $courseRow = $course->fetch(PDO::FETCH_ASSOC);
    if (!$courseRow) courseProgressJson(['ok' => false, 'status' => 'error', 'message' => 'ไม่พบรายวิชา'], 404);

    $lessons = $conn->prepare("SELECT l.lesson_id, l.position AS lesson_index, l.title AS lesson_title,
            COALESCE(p.opened_count, 0) AS opened_count, COALESCE(p.video_open_count, 0) AS video_open_count,
            COALESCE(p.document_progress_percent, 0) AS document_progress_percent,
            COALESCE(p.document_page_index, 0) AS document_page_index,
            COALESCE(p.video_progress_percent, 0) AS video_progress_percent,
            COALESCE(p.video_position_seconds, 0) AS video_position_seconds,
            COALESCE(p.progress_value, 0) AS progress_value, COALESCE(p.progress_type, 'document') AS progress_type,
            COALESCE(p.is_completed, FALSE) AS is_completed, p.updated_at,
            CASE WHEN COALESCE(p.is_transferred, FALSE) THEN 1 ELSE 0 END AS is_transferred,
            COALESCE(q.best_score, 0) AS best_quiz_score, COALESCE(q.total_score, 0) AS quiz_total_score,
            COALESCE(p.last_activity_at, q.last_activity_at) AS last_activity_at
        FROM public.lessons l
        LEFT JOIN public.lesson_progress_summary p ON p.lesson_id = l.lesson_id AND p.student_id = :student_id
        LEFT JOIN LATERAL (
            SELECT MAX(a.score) AS best_score, MAX(a.total_score) AS total_score, MAX(a.submitted_at) AS last_activity_at
            FROM public.quiz_attempts a WHERE a.student_id = :student_id AND a.lesson_id = l.lesson_id
        ) q ON true
        WHERE l.course_id = :course_id ORDER BY l.position");
    $lessons->execute([':student_id' => $studentId, ':course_id' => $courseId]);
    $rows = $lessons->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$row) {
        $row['document_progress'] = round((float) $row['document_progress_percent'] * 0.30, 1);
        $row['video_progress'] = round((float) $row['video_progress_percent'] * 0.30, 1);
        $row['quiz_progress'] = (int) $row['quiz_total_score'] > 0 ? round(min(40, ((float) $row['best_quiz_score'] / (float) $row['quiz_total_score']) * 40), 1) : 0;
        $row['is_transferred'] = (int) $row['is_transferred'] === 1;
        $row['progress_percent'] = $row['is_transferred'] ? 100 : lessonProgressFromQuiz((float) $row['best_quiz_score'], (float) $row['quiz_total_score'], (float) $row['document_progress_percent'], (float) $row['video_progress_percent']);
    }
    unset($row);
    $started = array_filter($rows, static fn(array $row): bool => (int) $row['opened_count'] > 0 || (int) $row['video_open_count'] > 0 || $row['last_activity_at'] !== null);
    courseProgressJson(['ok' => true, 'status' => 'success', 'summary' => [
        'subject_id' => $courseId, 'course_id' => $courseId, 'course_name' => $courseRow['name'],
        'lesson_count' => count($rows), 'started_lessons' => count($started),
        'progress_percent' => count($rows) ? round(array_sum(array_column($rows, 'progress_percent')) / count($rows), 1) : 0,
        'best_score_percent' => max(array_column($rows, 'progress_percent') ?: [0]),
        'last_activity_at' => max(array_filter(array_column($rows, 'last_activity_at')) ?: [null]), 'lessons' => $rows,
    ]]);
} catch (Throwable $error) {
    error_log('Course progress request failed: ' . $error->getMessage());
    courseProgressJson(['ok' => false, 'status' => 'error', 'message' => 'โหลดความคืบหน้ารายวิชาไม่สำเร็จ'], 500);
}
