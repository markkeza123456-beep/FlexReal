<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/learning_progress_lib.php';

function jsonResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function ensureTestTable(PDO $conn): void
{
    $conn->exec(
        'CREATE TABLE IF NOT EXISTS public.test (
            test_id INTEGER PRIMARY KEY,
            score INTEGER,
            test_attempt INTEGER,
            status VARCHAR(50),
            student_id VARCHAR(50),
            course_name TEXT,
            total_score INTEGER,
            answers JSONB,
            subjects_id VARCHAR(50),
            lesson_no INTEGER,
            submitted_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )'
    );
    $conn->exec('ALTER TABLE public.test ADD COLUMN IF NOT EXISTS total_score INTEGER');
    $conn->exec('ALTER TABLE public.test ADD COLUMN IF NOT EXISTS answers JSONB');
    $conn->exec('ALTER TABLE public.test ADD COLUMN IF NOT EXISTS subjects_id VARCHAR(50)');
    $conn->exec('ALTER TABLE public.test ADD COLUMN IF NOT EXISTS lesson_no INTEGER');
    $conn->exec('ALTER TABLE public.test ADD COLUMN IF NOT EXISTS submitted_at TIMESTAMPTZ NOT NULL DEFAULT NOW()');
}

function nextTestId(PDO $conn): int
{
    $stmt = $conn->query('SELECT COALESCE(MAX(test_id), 0) + 1 FROM public.test');
    return (int) $stmt->fetchColumn();
}

function nextTestAttempt(PDO $conn, string $studentId, string $courseName): int
{
    $stmt = $conn->prepare(
        'SELECT COALESCE(MAX(test_attempt), 0) + 1
         FROM public.test
         WHERE student_id = :student_id AND course_name = :course_name'
    );
    $stmt->execute([
        ':student_id' => $studentId,
        ':course_name' => $courseName,
    ]);

    return (int) $stmt->fetchColumn();
}

function ensureStudentExists(PDO $conn, string $studentId): void
{
    $stmt = $conn->prepare('SELECT 1 FROM public.student WHERE student_id = :student_id LIMIT 1');
    $stmt->execute([':student_id' => $studentId]);

    if ($stmt->fetchColumn()) {
        return;
    }

    $stmt = $conn->prepare(
        'INSERT INTO public.student (student_id, student_name)
         VALUES (:student_id, :student_name)'
    );
    $stmt->execute([
        ':student_id' => $studentId,
        ':student_name' => $_SESSION['name'] ?? 'นักเรียน',
    ]);
}

$role = strtolower((string) ($_SESSION['role'] ?? ''));
if (!isset($_SESSION['user_id']) || $role !== 'student') {
    jsonResponse([
        'status' => 'unauthorized',
        'message' => 'กรุณาเข้าสู่ระบบนักเรียนก่อนส่งแบบทดสอบ',
        'login_url' => 'login.php',
    ], 401);
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input)) {
    jsonResponse(['status' => 'error', 'message' => 'ข้อมูลแบบทดสอบไม่ถูกต้อง'], 400);
}

$courseName = trim((string) ($input['course_name'] ?? ''));
$subjectId = trim((string) ($input['subject_id'] ?? ''));
$lessonIndex = (int) ($input['lesson_index'] ?? 1);
$score = (int) ($input['score'] ?? -1);
$totalScore = (int) ($input['total_score'] ?? 0);
$lessonNo = (int) ($input['lesson_no'] ?? ($lessonIndex > 0 ? $lessonIndex : 1));

if ($courseName === '' || $subjectId === '' || $score < 0 || $totalScore <= 0) {
    jsonResponse(['status' => 'error', 'message' => 'ข้อมูลแบบทดสอบไม่ครบถ้วน'], 400);
}

try {
    ensureTestTable($conn);
    $studentId = (string) $_SESSION['user_id'];
    ensureStudentExists($conn, $studentId);
    $testId = nextTestId($conn);
    $testAttempt = nextTestAttempt($conn, $studentId, $courseName);
    $requiredScore = max(1, (int) ceil($totalScore * 0.6));
    $status = $score >= $requiredScore ? 'pass' : 'fail';

    $stmt = $conn->prepare(
        'INSERT INTO public.test (
            test_id, score, test_attempt, status, student_id, course_name, total_score, answers, subjects_id, lesson_no, submitted_at
         )
         VALUES (
            :test_id, :score, :test_attempt, :status, :student_id, :course_name, :total_score, :answers, :subjects_id, :lesson_no, NOW()
         )
         RETURNING test_id'
    );
    $stmt->execute([
        ':test_id' => $testId,
        ':score' => $score,
        ':test_attempt' => $testAttempt,
        ':status' => $status,
        ':student_id' => $studentId,
        ':course_name' => $courseName,
        ':total_score' => $totalScore,
        ':answers' => json_encode($input['answers'] ?? [], JSON_UNESCAPED_UNICODE),
        ':subjects_id' => ($subjectId === '' ? null : $subjectId),
        ':lesson_no' => ($lessonNo > 0 ? $lessonNo : null),
    ]);

    recordLearningActivity(
        $conn,
        $studentId,
        $subjectId,
        $lessonIndex > 0 ? $lessonIndex : 1,
        'quiz_submit',
        $courseName . ' บทที่ ' . ($lessonIndex > 0 ? $lessonIndex : 1),
        $score,
        $totalScore
    );

    jsonResponse([
        'status' => 'success',
        'message' => 'บันทึกผลแบบทดสอบเรียบร้อย',
        'test_id' => $stmt->fetchColumn(),
        'quiz_status' => $status,
        'required_score' => $requiredScore,
    ]);
} catch (Throwable $e) {
    jsonResponse([
        'status' => 'error',
        'message' => 'บันทึกผลแบบทดสอบไม่สำเร็จ',
    ], 500);
}
