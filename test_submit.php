<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config/db.php';

function quizResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function submittedChoice(mixed $value): ?string
{
    if (is_int($value) || (is_string($value) && ctype_digit($value))) {
        return ['A', 'B', 'C', 'D'][(int) $value] ?? null;
    }
    $value = strtoupper(trim((string) $value));
    return in_array($value, ['A', 'B', 'C', 'D'], true) ? $value : null;
}

$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload)) quizResponse(['status' => 'error', 'message' => 'รูปแบบข้อมูลไม่ถูกต้อง'], 400);
if (($_SESSION['role'] ?? '') !== 'student' || empty($_SESSION['user_id'])) {
    quizResponse(['status' => 'unauthorized', 'message' => 'กรุณาเข้าสู่ระบบก่อนทำแบบทดสอบ'], 401);
}

$studentId = (string) $_SESSION['user_id'];
$courseId = trim((string) ($payload['subject_id'] ?? $payload['course_id'] ?? ''));
$lessonIndex = max(1, (int) ($payload['lesson_index'] ?? $payload['lesson_no'] ?? 1));
$answers = is_array($payload['answers'] ?? null) ? $payload['answers'] : [];
$action = strtolower(trim((string) ($payload['action'] ?? 'submit')));
$sessionId = filter_var($payload['session_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($courseId === '') quizResponse(['status' => 'error', 'message' => 'ไม่พบรหัสรายวิชา'], 400);
if (!in_array($action, ['submit', 'timeout', 'abandon'], true)) quizResponse(['status' => 'error', 'message' => 'ประเภทการส่งข้อสอบไม่ถูกต้อง'], 400);

try {
    $conn->beginTransaction();
    $enrollment = $conn->prepare("SELECT 1 FROM public.student_courses WHERE student_id = :student_id AND course_id = :course_id AND status = 'active'");
    $enrollment->execute([':student_id' => $studentId, ':course_id' => $courseId]);
    if (!$enrollment->fetchColumn()) throw new RuntimeException('กรุณาลงรายวิชาก่อนทำแบบทดสอบ');

    $lesson = $conn->prepare('SELECT lesson_id, title FROM public.lessons WHERE course_id = :course_id AND position = :position LIMIT 1');
    $lesson->execute([':course_id' => $courseId, ':position' => $lessonIndex]);
    $lessonRow = $lesson->fetch(PDO::FETCH_ASSOC);
    if (!$lessonRow) throw new RuntimeException('ไม่พบบทเรียนที่ต้องการทำแบบทดสอบ');
    $lessonId = (int) $lessonRow['lesson_id'];

    $passedAttemptStmt = $conn->prepare("SELECT EXISTS (SELECT 1 FROM public.quiz_attempts WHERE student_id = :student_id AND lesson_id = :lesson_id AND status = 'passed')");
    $passedAttemptStmt->execute([':student_id' => $studentId, ':lesson_id' => $lessonId]);
    if ($passedAttemptStmt->fetchColumn()) {
        $conn->rollBack();
        quizResponse(['status' => 'already_passed', 'message' => 'คุณผ่านแบบทดสอบบทนี้แล้ว ไม่สามารถทำซ้ำได้'], 409);
    }

    if ($sessionId === false) throw new RuntimeException('ไม่พบเซสชันข้อสอบ กรุณาโหลดข้อสอบใหม่');
    $sessionStmt = $conn->prepare("SELECT session_id, attempt_number, expires_at, status FROM public.quiz_attempt_sessions WHERE session_id = :session_id AND student_id = :student_id AND lesson_id = :lesson_id FOR UPDATE");
    $sessionStmt->execute([':session_id' => $sessionId, ':student_id' => $studentId, ':lesson_id' => $lessonId]);
    $quizSession = $sessionStmt->fetch(PDO::FETCH_ASSOC);
    if (!$quizSession || $quizSession['status'] !== 'in_progress') throw new RuntimeException('เซสชันข้อสอบหมดอายุหรือถูกปิดแล้ว');
    $expiresAt = strtotime((string) $quizSession['expires_at']);
    if ($action === 'timeout' && $expiresAt > time() + 2) throw new RuntimeException('ยังไม่ถึงเวลาหมดข้อสอบ');
    if ($action === 'submit' && $expiresAt <= time()) $action = 'timeout';

    $questionStmt = $conn->prepare('SELECT question_id, question_type, correct_choice, max_score FROM public.questions WHERE lesson_id = :lesson_id AND is_active = true ORDER BY question_id');
    $questionStmt->execute([':lesson_id' => $lessonId]);
    $questions = $questionStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$questions) throw new RuntimeException('บทเรียนนี้ยังไม่มีข้อสอบ');

    $attemptNo = (int) $quizSession['attempt_number'];
    $totalScore = array_sum(array_map(static fn(array $question): float => (float) $question['max_score'], $questions));
    if ($action === 'abandon') {
        $conn->prepare("INSERT INTO public.quiz_attempts (student_id, lesson_id, attempt_number, score, total_score, status) VALUES (:student_id, :lesson_id, :attempt_number, 0, :total_score, 'failed')")
            ->execute([':student_id' => $studentId, ':lesson_id' => $lessonId, ':attempt_number' => $attemptNo, ':total_score' => $totalScore]);
        $conn->prepare("UPDATE public.quiz_attempt_sessions SET status = 'failed', finished_at = now(), updated_at = now() WHERE session_id = :session_id")
            ->execute([':session_id' => $sessionId]);
        $conn->commit();
        quizResponse(['status' => 'success', 'quiz_status' => 'failed', 'score' => 0, 'total_score' => $totalScore, 'message' => 'ออกจากแบบทดสอบและบันทึกผลเป็นไม่ผ่านแล้ว']);
    }

    $score = 0.0;
    $hasEssay = false;
    $savedAnswers = [];
    foreach ($questions as $index => $question) {
        $maxScore = (float) $question['max_score'];
        if ($question['question_type'] === 'essay') {
            $answerKey = (string) $question['question_id'];
            $essayValue = array_key_exists($answerKey, $answers) ? $answers[$answerKey] : ($answers[$index] ?? '');
            $essayText = trim((string) $essayValue);
            if ($essayText === '' && $action !== 'timeout') {
                throw new RuntimeException('กรุณาตอบข้อเขียนให้ครบก่อนส่งข้อสอบ');
            }
            if ($essayText !== '') $hasEssay = true;
            $savedAnswers[] = [
                'question_id' => (int) $question['question_id'],
                'selected_choice' => null,
                'answer_text' => $essayText !== '' ? $essayText : null,
                'score' => null,
                'review_status' => $essayText !== '' && $action !== 'timeout' ? 'pending' : 'not_required',
            ];
            continue;
        }
        $answerKey = (string) $question['question_id'];
        $answerValue = array_key_exists($answerKey, $answers) ? $answers[$answerKey] : ($answers[$index] ?? null);
        $choice = submittedChoice($answerValue);
        $earned = $choice !== null && $choice === $question['correct_choice'] ? $maxScore : 0.0;
        $score += $earned;
        $savedAnswers[] = [
            'question_id' => (int) $question['question_id'],
            'selected_choice' => $choice,
            'answer_text' => null,
            'score' => $earned,
            'review_status' => 'not_required',
        ];
    }

    $requiredScore = $totalScore;
    // A timed-out attempt is always a failure, even if saved answers happen to meet the pass score.
    $status = $action === 'timeout' ? 'failed' : ($hasEssay ? 'pending_review' : ($score >= $requiredScore ? 'passed' : 'failed'));
    $attemptStmt = $conn->prepare('INSERT INTO public.quiz_attempts (student_id, lesson_id, attempt_number, score, total_score, status) VALUES (:student_id, :lesson_id, :attempt_number, :score, :total_score, :status) RETURNING attempt_id');
    $attemptStmt->execute([':student_id' => $studentId, ':lesson_id' => $lessonId, ':attempt_number' => $attemptNo, ':score' => $score, ':total_score' => $totalScore, ':status' => $status]);
    $attemptId = (int) $attemptStmt->fetchColumn();

    $answerStmt = $conn->prepare('INSERT INTO public.quiz_attempt_answers (attempt_id, question_id, selected_choice, answer_text, score, review_status) VALUES (:attempt_id, :question_id, :selected_choice, :answer_text, :score, :review_status)');
    foreach ($savedAnswers as $answer) {
        $answerStmt->execute([':attempt_id' => $attemptId] + $answer);
    }
    $bestScoreStmt = $conn->prepare('SELECT COALESCE(MAX(score), 0) FROM public.quiz_attempts WHERE student_id = :student_id AND lesson_id = :lesson_id');
    $bestScoreStmt->execute([':student_id' => $studentId, ':lesson_id' => $lessonId]);
    $bestScore = (float) $bestScoreStmt->fetchColumn();
    $sessionStatus = $action === 'timeout' ? 'timed_out' : 'submitted';
    $conn->prepare('UPDATE public.quiz_attempt_sessions SET status = :status, finished_at = now(), updated_at = now() WHERE session_id = :session_id')
        ->execute([':status' => $sessionStatus, ':session_id' => $sessionId]);
    $conn->commit();
    quizResponse(['status' => 'success', 'quiz_status' => $status, 'score' => $score, 'best_score' => $bestScore, 'total_score' => $totalScore, 'required_score' => $requiredScore, 'pending_review' => $hasEssay && $action !== 'timeout', 'message' => 'บันทึกผลสอบเรียบร้อย']);
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    error_log('Quiz submit failed: ' . $e->getMessage());
    quizResponse(['status' => 'error', 'message' => $e->getMessage()], 400);
}
