<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/learning_progress_lib.php';

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
if ($courseId === '') quizResponse(['status' => 'error', 'message' => 'ไม่พบรหัสรายวิชา'], 400);

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

    $questionStmt = $conn->prepare('SELECT question_id, question_type, correct_choice, max_score FROM public.questions WHERE lesson_id = :lesson_id AND is_active = true ORDER BY question_id');
    $questionStmt->execute([':lesson_id' => $lessonId]);
    $questions = $questionStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$questions) throw new RuntimeException('บทเรียนนี้ยังไม่มีข้อสอบ');

    $totalScore = 0.0;
    $score = 0.0;
    $hasEssay = false;
    $savedAnswers = [];
    foreach ($questions as $index => $question) {
        $maxScore = (float) $question['max_score'];
        $totalScore += $maxScore;
        if ($question['question_type'] === 'essay') {
            $hasEssay = true;
            $savedAnswers[] = [
                'question_id' => (int) $question['question_id'],
                'selected_choice' => null,
                'answer_text' => trim((string) ($answers[$index] ?? '')) ?: null,
                'score' => null,
                'review_status' => 'pending',
            ];
            continue;
        }
        $choice = submittedChoice($answers[$index] ?? null);
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

    $attemptNoStmt = $conn->prepare('SELECT COALESCE(MAX(attempt_number), 0) + 1 FROM public.quiz_attempts WHERE student_id = :student_id AND lesson_id = :lesson_id');
    $attemptNoStmt->execute([':student_id' => $studentId, ':lesson_id' => $lessonId]);
    $attemptNo = (int) $attemptNoStmt->fetchColumn();
    $requiredScore = $totalScore;
    $status = $hasEssay ? 'pending_review' : ($score >= $requiredScore ? 'passed' : 'failed');
    $attemptStmt = $conn->prepare('INSERT INTO public.quiz_attempts (student_id, lesson_id, attempt_number, score, total_score, status) VALUES (:student_id, :lesson_id, :attempt_number, :score, :total_score, :status) RETURNING attempt_id');
    $attemptStmt->execute([':student_id' => $studentId, ':lesson_id' => $lessonId, ':attempt_number' => $attemptNo, ':score' => $score, ':total_score' => $totalScore, ':status' => $status]);
    $attemptId = (int) $attemptStmt->fetchColumn();

    $answerStmt = $conn->prepare('INSERT INTO public.quiz_attempt_answers (attempt_id, question_id, selected_choice, answer_text, score, review_status) VALUES (:attempt_id, :question_id, :selected_choice, :answer_text, :score, :review_status)');
    foreach ($savedAnswers as $answer) {
        $answerStmt->execute([':attempt_id' => $attemptId] + $answer);
    }
    recordLearningActivity($conn, $studentId, $courseId, $lessonIndex, 'quiz_submit', (string) $lessonRow['title'], (int) $score, (int) $totalScore);
    $conn->commit();
    quizResponse(['status' => 'success', 'quiz_status' => $status, 'score' => $score, 'total_score' => $totalScore, 'required_score' => $requiredScore, 'pending_review' => $hasEssay, 'message' => 'บันทึกผลสอบเรียบร้อย']);
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    error_log('Quiz submit failed: ' . $e->getMessage());
    quizResponse(['status' => 'error', 'message' => $e->getMessage()], 400);
}
