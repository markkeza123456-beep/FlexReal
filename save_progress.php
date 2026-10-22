<?php
declare(strict_types=1);

// This endpoint must never leak PHP warnings, included markup, or whitespace
// into the JSON response consumed by fetch().
ini_set('display_errors', '0');
ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function progressJson(array $payload, int $status = 200): never
{
    http_response_code($status);
    $payload = ['status' => $status < 400 ? 'success' : 'error'] + $payload;
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

session_start();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    progressJson(['ok' => false, 'message' => 'รองรับเฉพาะ POST'], 405);
}
if (($_SESSION['role'] ?? '') !== 'student' || empty($_SESSION['user_id'])) {
    progressJson(['ok' => false, 'message' => 'กรุณาเข้าสู่ระบบนักเรียนก่อนใช้งาน'], 401);
}
$studentId = (string) $_SESSION['user_id'];
session_write_close();

$lessonId = filter_var($_POST['lesson_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$progressType = strtolower(trim((string) ($_POST['progress_type'] ?? '')));
$progressValue = filter_var($_POST['progress_value'] ?? null, FILTER_VALIDATE_FLOAT);
$isCompleted = filter_var($_POST['is_completed'] ?? false, FILTER_VALIDATE_BOOLEAN);
if ($lessonId === false || !in_array($progressType, ['video', 'document'], true) || $progressValue === false || !is_finite((float) $progressValue) || (float) $progressValue < 0) {
    progressJson(['ok' => false, 'message' => 'ข้อมูลความคืบหน้าไม่ถูกต้อง'], 400);
}

try {
    require_once __DIR__ . '/config/db.php';
    $lesson = $conn->prepare('SELECT l.lesson_id, l.course_id FROM public.lessons l JOIN public.student_courses sc ON sc.course_id = l.course_id AND sc.student_id = :student_id AND sc.status = \'active\' WHERE l.lesson_id = :lesson_id LIMIT 1');
    $lesson->execute([':student_id' => $studentId, ':lesson_id' => $lessonId]);
    if (!$lesson->fetch(PDO::FETCH_ASSOC)) {
        progressJson(['ok' => false, 'message' => 'ไม่พบบทเรียนหรือไม่มีสิทธิ์เข้าถึง'], 403);
    }

    $conn->beginTransaction();
    if ($progressType === 'video') {
        $duration = max(0.0, (float) ($_POST['duration_seconds'] ?? 0));
        $position = $duration > 0 ? min((float) $progressValue, $duration) : (float) $progressValue;
        $percent = $duration > 0 ? min(100.0, $position * 100 / $duration) : 0.0;
        if ($isCompleted) $percent = 100.0;
        $sql = 'INSERT INTO public.lesson_progress
                    (student_id, lesson_id, progress_value, progress_type, is_completed, video_position_seconds,
                     video_progress_percent, video_open_count, first_opened_at, last_opened_at, last_activity_at, updated_at)
                VALUES (:student_id, :lesson_id, :progress_value, \'video\', :is_completed, :video_position,
                        :video_percent, 1, now(), now(), now(), now())
                ON CONFLICT (student_id, lesson_id, progress_type) DO UPDATE SET
                    progress_value = EXCLUDED.progress_value,
                    is_completed = lesson_progress.is_completed OR EXCLUDED.is_completed,
                    video_position_seconds = CASE WHEN EXCLUDED.is_completed THEN 0 ELSE EXCLUDED.video_position_seconds END,
                    video_progress_percent = GREATEST(lesson_progress.video_progress_percent, EXCLUDED.video_progress_percent),
                    video_open_count = GREATEST(lesson_progress.video_open_count, 1),
                    last_opened_at = now(), last_activity_at = now(), updated_at = now()';
        $params = [':student_id' => $studentId, ':lesson_id' => $lessonId, ':progress_value' => $position, ':is_completed' => $isCompleted ? 'true' : 'false', ':video_position' => (int) floor($position), ':video_percent' => $percent];
    } else {
        $percent = min(100.0, max(0.0, (float) $progressValue));
        $currentPage = filter_var($_POST['current_page'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $totalPages = filter_var($_POST['total_pages'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($currentPage !== false && $totalPages !== false) {
            $currentPage = min($currentPage, $totalPages);
        }
        $page = $currentPage !== false ? (int) $currentPage : max(0, (int) ($_POST['resume_page'] ?? 0));
        $completed = $percent >= 100;
        $sql = 'INSERT INTO public.lesson_progress
                    (student_id, lesson_id, progress_value, progress_type, is_completed, document_progress_percent,
                     document_page_index, opened_count, first_opened_at, last_opened_at, last_activity_at, updated_at)
                VALUES (:student_id, :lesson_id, :progress_value, \'document\', :is_completed, :document_percent,
                        :document_page, 1, now(), now(), now(), now())
                ON CONFLICT (student_id, lesson_id, progress_type) DO UPDATE SET
                    progress_value = GREATEST(lesson_progress.progress_value, EXCLUDED.progress_value),
                    is_completed = lesson_progress.is_completed OR EXCLUDED.is_completed,
                    document_progress_percent = GREATEST(lesson_progress.document_progress_percent, EXCLUDED.document_progress_percent),
                    document_page_index = CASE WHEN EXCLUDED.document_page_index > 0 THEN EXCLUDED.document_page_index ELSE lesson_progress.document_page_index END,
                    opened_count = GREATEST(lesson_progress.opened_count, 1),
                    last_opened_at = now(), last_activity_at = now(), updated_at = now()';
        $params = [':student_id' => $studentId, ':lesson_id' => $lessonId, ':progress_value' => $percent, ':is_completed' => $completed ? 'true' : 'false', ':document_percent' => $percent, ':document_page' => $page];
    }
    $conn->prepare($sql)->execute($params);
    $conn->commit();
    $completedResponse = $progressType === 'video' ? $isCompleted : $percent >= 100;
    progressJson(['ok' => true, 'progress_value' => $progressType === 'video' ? $position : $percent, 'is_completed' => $completedResponse]);
} catch (Throwable $error) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) $conn->rollBack();
    error_log('Progress save failed: ' . $error->getMessage());
    progressJson(['ok' => false, 'message' => 'บันทึกความคืบหน้าไม่สำเร็จ'], 500);
}
