<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function sendProgressApiResponse(array $payload, int $httpStatus = 200): never
{
    http_response_code($httpStatus);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    sendProgressApiResponse(['status' => 'error', 'ok' => false, 'message' => 'รองรับเฉพาะ POST'], 405);
}

session_start();
if (($_SESSION['role'] ?? '') !== 'student' || empty($_SESSION['user_id'])) {
    sendProgressApiResponse(['status' => 'error', 'ok' => false, 'message' => 'กรุณาเข้าสู่ระบบนักเรียนก่อนใช้งาน'], 401);
}
$studentId = (string) $_SESSION['user_id'];
session_write_close();

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody === false ? '' : $rawBody, true);
if (!is_array($payload)) {
    sendProgressApiResponse(['status' => 'error', 'ok' => false, 'message' => 'รูปแบบ JSON ไม่ถูกต้อง'], 400);
}

$lessonId = filter_var($payload['lesson_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$progressValue = filter_var($payload['progress_value'] ?? null, FILTER_VALIDATE_FLOAT);
$duration = filter_var($payload['duration_seconds'] ?? null, FILTER_VALIDATE_FLOAT);
$isCompleted = filter_var($payload['is_completed'] ?? false, FILTER_VALIDATE_BOOLEAN);
if ($lessonId === false || $progressValue === false || !is_finite((float) $progressValue) || (float) $progressValue < 0
    || $duration === false || !is_finite((float) $duration) || (float) $duration <= 0) {
    sendProgressApiResponse(['status' => 'error', 'ok' => false, 'message' => 'ข้อมูลความคืบหน้าวิดีโอไม่ถูกต้อง'], 400);
}

try {
    // Database connection only; this API intentionally has no page/layout includes.
    require_once __DIR__ . '/config/db.php';
    $lesson = $conn->prepare('SELECT l.lesson_id FROM public.lessons l JOIN public.student_courses sc ON sc.course_id = l.course_id AND sc.student_id = :student_id AND sc.status = \'active\' WHERE l.lesson_id = :lesson_id LIMIT 1');
    $lesson->execute([':student_id' => $studentId, ':lesson_id' => $lessonId]);
    if (!$lesson->fetch(PDO::FETCH_ASSOC)) {
        sendProgressApiResponse(['status' => 'error', 'ok' => false, 'message' => 'ไม่พบบทเรียนหรือไม่มีสิทธิ์เข้าถึง'], 403);
    }

    $durationSeconds = (float) $duration;
    $position = min((float) $progressValue, $durationSeconds);
    $percent = $isCompleted ? 100.0 : min(100.0, $position * 100 / $durationSeconds);
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
    $conn->prepare($sql)->execute([
        ':student_id' => $studentId,
        ':lesson_id' => $lessonId,
        ':progress_value' => $position,
        ':is_completed' => $isCompleted ? 'true' : 'false',
        ':video_position' => (int) floor($position),
        ':video_percent' => $percent,
    ]);

    sendProgressApiResponse(['status' => 'success', 'ok' => true, 'progress_value' => $position, 'is_completed' => $isCompleted]);
} catch (Throwable $error) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Video progress API failed: ' . $error->getMessage());
    sendProgressApiResponse(['status' => 'error', 'ok' => false, 'message' => 'บันทึกความคืบหน้าไม่สำเร็จ'], 500);
}
