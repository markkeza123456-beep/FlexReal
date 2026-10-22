<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function quizSessionResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    while (ob_get_level() > 0) ob_end_clean();
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    quizSessionResponse(['status' => 'error', 'message' => 'รองรับเฉพาะ POST'], 405);
}

session_start();
if (($_SESSION['role'] ?? '') !== 'student' || empty($_SESSION['user_id'])) {
    quizSessionResponse(['status' => 'unauthorized', 'message' => 'กรุณาเข้าสู่ระบบก่อนทำแบบทดสอบ'], 401);
}
$studentId = (string) $_SESSION['user_id'];
session_write_close();

$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload)) quizSessionResponse(['status' => 'error', 'message' => 'รูปแบบข้อมูลไม่ถูกต้อง'], 400);
$action = strtolower(trim((string) ($payload['action'] ?? 'start')));
$courseId = filter_var($payload['course_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$lessonIndex = filter_var($payload['lesson_index'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($courseId === false || $lessonIndex === false || !in_array($action, ['start', 'tab_switch', 'abandon'], true)) {
    quizSessionResponse(['status' => 'error', 'message' => 'ข้อมูลข้อสอบไม่ถูกต้อง'], 400);
}

try {
    require_once __DIR__ . '/config/db.php';
    $conn->beginTransaction();

    $lessonStmt = $conn->prepare(
        "SELECT l.lesson_id, l.quiz_duration_minutes
         FROM public.lessons l
         JOIN public.student_courses sc ON sc.course_id = l.course_id
         WHERE l.course_id = :course_id AND l.position = :position
           AND sc.student_id = :student_id AND sc.status = 'active'
         LIMIT 1"
    );
    $lessonStmt->execute([':course_id' => $courseId, ':position' => $lessonIndex, ':student_id' => $studentId]);
    $lesson = $lessonStmt->fetch(PDO::FETCH_ASSOC);
    if (!$lesson) {
        $conn->rollBack();
        quizSessionResponse(['status' => 'error', 'message' => 'ไม่พบบทเรียนหรือไม่มีสิทธิ์ทำข้อสอบ'], 403);
    }
    $lessonId = (int) $lesson['lesson_id'];

    $passedStmt = $conn->prepare("SELECT EXISTS (SELECT 1 FROM public.quiz_attempts WHERE student_id = :student_id AND lesson_id = :lesson_id AND status = 'passed')");
    $passedStmt->execute([':student_id' => $studentId, ':lesson_id' => $lessonId]);
    if ($passedStmt->fetchColumn()) {
        $conn->rollBack();
        quizSessionResponse(['status' => 'already_passed', 'message' => 'คุณผ่านแบบทดสอบบทนี้แล้ว ไม่สามารถทำซ้ำได้'], 409);
    }

    $activeStmt = $conn->prepare(
        "SELECT session_id, attempt_number, expires_at, tab_switch_count,
                EXTRACT(EPOCH FROM expires_at) * 1000 AS expires_at_ms,
                EXTRACT(EPOCH FROM (expires_at - now())) AS remaining_seconds
         FROM public.quiz_attempt_sessions
         WHERE student_id = :student_id AND lesson_id = :lesson_id AND status = 'in_progress'
         ORDER BY attempt_number DESC LIMIT 1 FOR UPDATE"
    );
    $activeStmt->execute([':student_id' => $studentId, ':lesson_id' => $lessonId]);
    $activeSession = $activeStmt->fetch(PDO::FETCH_ASSOC);
    $previousTimedOut = false;

    if ($activeSession && strtotime((string) $activeSession['expires_at']) <= time()) {
        $scoreStmt = $conn->prepare('SELECT COALESCE(SUM(max_score), 0) FROM public.questions WHERE lesson_id = :lesson_id AND is_active = TRUE');
        $scoreStmt->execute([':lesson_id' => $lessonId]);
        $totalScore = (float) $scoreStmt->fetchColumn();
        $conn->prepare("INSERT INTO public.quiz_attempts (student_id, lesson_id, attempt_number, score, total_score, status) VALUES (:student_id, :lesson_id, :attempt_number, 0, :total_score, 'failed')")
            ->execute([':student_id' => $studentId, ':lesson_id' => $lessonId, ':attempt_number' => (int) $activeSession['attempt_number'], ':total_score' => $totalScore]);
        $conn->prepare("UPDATE public.quiz_attempt_sessions SET status = 'timed_out', finished_at = now(), updated_at = now() WHERE session_id = :session_id")
            ->execute([':session_id' => $activeSession['session_id']]);
        $expiredSessionId = (int) $activeSession['session_id'];
        $activeSession = null;
        $previousTimedOut = true;
        if ($action !== 'start') {
            $conn->commit();
            quizSessionResponse([
                'status' => 'success',
                'auto_failed' => true,
                'previous_timed_out' => true,
                'message' => 'เวลาสอบหมดแล้ว ระบบบันทึกผลเป็นไม่ผ่าน',
                'session_id' => $expiredSessionId,
            ]);
        }
    }

    if ($action === 'start') {
        if (!$activeSession) {
            $attemptStmt = $conn->prepare(
                'SELECT GREATEST(
                    COALESCE((SELECT MAX(attempt_number) FROM public.quiz_attempts WHERE student_id = :student_id AND lesson_id = :lesson_id), 0),
                    COALESCE((SELECT MAX(attempt_number) FROM public.quiz_attempt_sessions WHERE student_id = :student_id AND lesson_id = :lesson_id), 0)
                ) + 1'
            );
            $attemptStmt->execute([':student_id' => $studentId, ':lesson_id' => $lessonId]);
            $attemptNumber = (int) $attemptStmt->fetchColumn();
            $durationMinutes = max(1, min(1440, (int) ($lesson['quiz_duration_minutes'] ?? 60)));
            $insert = $conn->prepare(
                "INSERT INTO public.quiz_attempt_sessions (student_id, lesson_id, attempt_number, expires_at)
                 VALUES (:student_id, :lesson_id, :attempt_number, now() + (:duration * INTERVAL '1 minute'))
                 RETURNING session_id, attempt_number, EXTRACT(EPOCH FROM expires_at) * 1000 AS expires_at_ms,
                           EXTRACT(EPOCH FROM (expires_at - now())) AS remaining_seconds, tab_switch_count"
            );
            $insert->execute([':student_id' => $studentId, ':lesson_id' => $lessonId, ':attempt_number' => $attemptNumber, ':duration' => $durationMinutes]);
            $activeSession = $insert->fetch(PDO::FETCH_ASSOC);
        }
        $conn->commit();
        quizSessionResponse([
            'status' => 'success',
            'session_id' => (int) $activeSession['session_id'],
            'attempt_number' => (int) $activeSession['attempt_number'],
            'expires_at_ms' => (int) round((float) $activeSession['expires_at_ms']),
            'remaining_seconds' => max(0, (int) ceil((float) $activeSession['remaining_seconds'])),
            'duration_minutes' => (int) ($lesson['quiz_duration_minutes'] ?? 60),
            'tab_switch_count' => (int) ($activeSession['tab_switch_count'] ?? 0),
            'previous_timed_out' => $previousTimedOut,
        ]);
    }

    $sessionId = filter_var($payload['session_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($sessionId === false || !$activeSession || (int) $activeSession['session_id'] !== $sessionId) {
        $conn->rollBack();
        quizSessionResponse(['status' => 'session_expired', 'message' => 'เวลาสอบหมดแล้วหรือเซสชันนี้ไม่ทำงาน'], 409);
    }

    if ($action === 'tab_switch') {
        $switchStmt = $conn->prepare('UPDATE public.quiz_attempt_sessions SET tab_switch_count = tab_switch_count + 1, updated_at = now() WHERE session_id = :session_id RETURNING tab_switch_count, attempt_number');
        $switchStmt->execute([':session_id' => $sessionId]);
        $switch = $switchStmt->fetch(PDO::FETCH_ASSOC);
        $switchCount = (int) $switch['tab_switch_count'];
        // Warn on switches 1–3; fail immediately on the next switch.
        $autoFailed = $switchCount > 3;
        if ($autoFailed) {
            $scoreStmt = $conn->prepare('SELECT COALESCE(SUM(max_score), 0) FROM public.questions WHERE lesson_id = :lesson_id AND is_active = TRUE');
            $scoreStmt->execute([':lesson_id' => $lessonId]);
            $totalScore = (float) $scoreStmt->fetchColumn();
            $conn->prepare("INSERT INTO public.quiz_attempts (student_id, lesson_id, attempt_number, score, total_score, status) VALUES (:student_id, :lesson_id, :attempt_number, 0, :total_score, 'failed')")
                ->execute([':student_id' => $studentId, ':lesson_id' => $lessonId, ':attempt_number' => (int) $switch['attempt_number'], ':total_score' => $totalScore]);
            $conn->prepare("UPDATE public.quiz_attempt_sessions SET status = 'failed', finished_at = now(), updated_at = now() WHERE session_id = :session_id")
                ->execute([':session_id' => $sessionId]);
        }
        $conn->commit();
        quizSessionResponse(['status' => 'success', 'tab_switch_count' => $switchCount, 'auto_failed' => $autoFailed]);
    }

    $scoreStmt = $conn->prepare('SELECT COALESCE(SUM(max_score), 0) FROM public.questions WHERE lesson_id = :lesson_id AND is_active = TRUE');
    $scoreStmt->execute([':lesson_id' => $lessonId]);
    $totalScore = (float) $scoreStmt->fetchColumn();
    $conn->prepare("INSERT INTO public.quiz_attempts (student_id, lesson_id, attempt_number, score, total_score, status) VALUES (:student_id, :lesson_id, :attempt_number, 0, :total_score, 'failed')")
        ->execute([':student_id' => $studentId, ':lesson_id' => $lessonId, ':attempt_number' => (int) $activeSession['attempt_number'], ':total_score' => $totalScore]);
    $conn->prepare("UPDATE public.quiz_attempt_sessions SET status = 'failed', finished_at = now(), updated_at = now() WHERE session_id = :session_id")
        ->execute([':session_id' => $sessionId]);
    $conn->commit();
    quizSessionResponse(['status' => 'success', 'quiz_status' => 'failed', 'message' => 'ออกจากแบบทดสอบ ระบบบันทึกผลเป็นไม่ผ่านแล้ว']);
} catch (Throwable $error) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) $conn->rollBack();
    error_log('Quiz session failed: ' . $error->getMessage());
    quizSessionResponse(['status' => 'error', 'message' => 'ไม่สามารถจัดการเซสชันข้อสอบได้'], 500);
}
