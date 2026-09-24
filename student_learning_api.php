<?php
session_start(); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0'); header('Pragma: no-cache');
require_once __DIR__ . '/db_connect.php'; require_once __DIR__ . '/learning_progress_lib.php';
function learningJson(array $payload, int $statusCode = 200): void { http_response_code($statusCode); echo json_encode($payload, JSON_UNESCAPED_UNICODE); exit; }
function currentLearningStudentId(): ?string { return (($_SESSION['role'] ?? '') === 'student' && isset($_SESSION['user_id'])) ? (string) $_SESSION['user_id'] : null; }
function fetchCourseSummary(PDO $conn, string $studentId, string $courseId): array {
    ensureLearningProgressTables($conn);
    $course = $conn->prepare('SELECT course_id, name FROM public.courses WHERE course_id = :id'); $course->execute([':id' => $courseId]); $courseRow = $course->fetch(PDO::FETCH_ASSOC); if (!$courseRow) throw new RuntimeException('ไม่พบรายวิชา');
    $lessons = $conn->prepare("SELECT l.lesson_id, l.position AS lesson_index, l.title AS lesson_title, COALESCE(p.opened_count, 0) AS opened_count, COALESCE(p.video_open_count, 0) AS video_open_count, COALESCE(p.document_progress_percent, 0) AS document_progress_percent, COALESCE(p.document_page_index, 0) AS document_page_index, COALESCE(p.video_progress_percent, 0) AS video_progress_percent, COALESCE(p.video_position_seconds, 0) AS video_position_seconds, COALESCE(q.best_score, 0) AS best_quiz_score, COALESCE(q.total_score, 0) AS quiz_total_score, COALESCE(p.last_activity_at, q.last_activity_at) AS last_activity_at FROM public.lessons l LEFT JOIN public.lesson_progress p ON p.lesson_id = l.lesson_id AND p.student_id = :student_id LEFT JOIN LATERAL (SELECT MAX(a.score) AS best_score, MAX(a.total_score) AS total_score, MAX(a.submitted_at) AS last_activity_at FROM public.quiz_attempts a WHERE a.student_id = :student_id AND a.lesson_id = l.lesson_id) q ON true WHERE l.course_id = :course_id ORDER BY l.position");
    $lessons->execute([':student_id' => $studentId, ':course_id' => $courseId]); $rows = $lessons->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['document_progress'] = round((float) $row['document_progress_percent'] * 0.30, 1);
        $row['video_progress'] = round((float) $row['video_progress_percent'] * 0.30, 1);
        $row['quiz_progress'] = (int) $row['quiz_total_score'] > 0 ? round(min(40, ((float) $row['best_quiz_score'] / (float) $row['quiz_total_score']) * 40), 1) : 0;
        $row['progress_percent'] = lessonProgressFromQuiz((int) $row['best_quiz_score'], (int) $row['quiz_total_score'], (float) $row['document_progress_percent'], (float) $row['video_progress_percent']);
    }
    unset($row); $started = array_filter($rows, fn($r) => (int) $r['opened_count'] > 0 || (int) $r['video_open_count'] > 0 || $r['last_activity_at']);
    return ['subject_id' => $courseId, 'course_id' => $courseId, 'course_name' => $courseRow['name'], 'lesson_count' => count($rows), 'started_lessons' => count($started), 'progress_percent' => count($rows) ? round(array_sum(array_column($rows, 'progress_percent')) / count($rows), 1) : 0, 'best_score_percent' => max(array_column($rows, 'progress_percent') ?: [0]), 'last_activity_at' => max(array_filter(array_column($rows, 'last_activity_at')) ?: [null]), 'lessons' => $rows];
}
$studentId = currentLearningStudentId(); if ($studentId === null) learningJson(['status' => 'unauthorized', 'message' => 'กรุณาเข้าสู่ระบบนักเรียนก่อนใช้งาน'], 401); session_write_close();
try { $action = strtolower((string) ($_GET['action'] ?? $_POST['action'] ?? 'summary')); $courseId = trim((string) ($_POST['course_id'] ?? $_POST['subject_id'] ?? $_GET['course_id'] ?? $_GET['subject_id'] ?? '')); if ($courseId === '') learningJson(['status' => 'error', 'message' => 'ไม่พบรหัสรายวิชา'], 400);
    if ($action === 'record') {
        $position = max(1, (int) ($_POST['lesson_index'] ?? $_POST['position'] ?? 1));
        $durationSeconds = max(0, (int) (($_POST['duration_seconds'] ?? $_POST['duration'] ?? 0)));
        recordLearningActivity(
            $conn,
            $studentId,
            $courseId,
            $position,
            trim((string) ($_POST['activity_type'] ?? 'lesson_open')),
            trim((string) ($_POST['lesson_title'] ?? '')),
            (float) ($_POST['progress_percent'] ?? 0),
            (float) ($_POST['resume_position'] ?? 0),
            $durationSeconds
        );
        learningJson(['status' => 'success']);
    }
    learningJson(['status' => 'success', 'summary' => fetchCourseSummary($conn, $studentId, $courseId)]);
} catch (Throwable $e) { learningJson(['status' => 'error', 'message' => 'ระบบติดตามความคืบหน้าขัดข้อง: ' . $e->getMessage()], 500); }
