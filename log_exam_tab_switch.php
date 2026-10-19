<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function examLogJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (($_SESSION['role'] ?? '') !== 'student' || empty($_SESSION['user_id'])) {
    examLogJson(['status' => 'unauthorized', 'message' => 'กรุณาเข้าสู่ระบบก่อนทำแบบทดสอบ'], 401);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    examLogJson(['status' => 'error', 'message' => 'รองรับเฉพาะคำขอ POST'], 405);
}
$studentId = (string) $_SESSION['user_id'];
session_write_close();

$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload)) examLogJson(['status' => 'error', 'message' => 'รูปแบบข้อมูลไม่ถูกต้อง'], 400);
$courseId = filter_var($payload['course_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$lessonIndex = filter_var($payload['lesson_index'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$courseId || !$lessonIndex) examLogJson(['status' => 'error', 'message' => 'ข้อมูลข้อสอบไม่ถูกต้อง'], 400);

try {
    require_once __DIR__ . '/config/db.php';
    $conn->beginTransaction();

    $lessonStmt = $conn->prepare(
        "SELECT l.lesson_id
         FROM public.lessons l
         JOIN public.student_courses sc ON sc.course_id = l.course_id
         WHERE l.course_id = :course_id AND l.position = :position
           AND sc.student_id = :student_id AND sc.status = 'active'
           AND EXISTS (SELECT 1 FROM public.questions q WHERE q.lesson_id = l.lesson_id AND q.is_active = TRUE)
         LIMIT 1"
    );
    $lessonStmt->execute([
        ':course_id' => $courseId,
        ':position' => $lessonIndex,
        ':student_id' => $studentId,
    ]);
    $lessonId = $lessonStmt->fetchColumn();
    if (!$lessonId) {
        $conn->rollBack();
        examLogJson(['status' => 'error', 'message' => 'ไม่พบข้อสอบหรือไม่มีสิทธิ์ทำรายวิชานี้'], 403);
    }

    $attemptStmt = $conn->prepare(
        'SELECT COALESCE(MAX(attempt_number), 0) + 1
         FROM public.quiz_attempts WHERE student_id = :student_id AND lesson_id = :lesson_id'
    );
    $attemptStmt->execute([':student_id' => $studentId, ':lesson_id' => $lessonId]);
    $attemptNumber = (int) $attemptStmt->fetchColumn();

    $logStmt = $conn->prepare(
        'INSERT INTO public.exam_logs (student_id, exam_id, attempt_number, tab_switch_count, updated_at)
         VALUES (:student_id, :exam_id, :attempt_number, 1, now())
         ON CONFLICT (student_id, exam_id, attempt_number)
         DO UPDATE SET tab_switch_count = public.exam_logs.tab_switch_count + 1, updated_at = now()
         RETURNING tab_switch_count'
    );
    $logStmt->execute([
        ':student_id' => $studentId,
        ':exam_id' => $lessonId,
        ':attempt_number' => $attemptNumber,
    ]);
    $count = (int) $logStmt->fetchColumn();
    $conn->commit();

    examLogJson([
        'status' => 'success',
        'tab_switch_count' => $count,
        'attempt_number' => $attemptNumber,
        'auto_submit' => $count >= 3,
    ]);
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) $conn->rollBack();
    error_log('Exam tab switch logging failed: ' . $e->getMessage());
    examLogJson(['status' => 'error', 'message' => 'บันทึกการสลับแท็บไม่สำเร็จ กรุณาลองใหม่'], 500);
}
