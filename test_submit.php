<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db_connect.php';

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
            course_name TEXT
        )'
    );
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
$score = (int) ($input['score'] ?? -1);
$totalScore = (int) ($input['total_score'] ?? 0);

if ($courseName === '' || $score < 0 || $totalScore <= 0) {
    jsonResponse(['status' => 'error', 'message' => 'ข้อมูลแบบทดสอบไม่ครบถ้วน'], 400);
}

try {
    ensureTestTable($conn);
    $studentId = (string) $_SESSION['user_id'];
    ensureStudentExists($conn, $studentId);
    $testId = nextTestId($conn);
    $testAttempt = nextTestAttempt($conn, $studentId, $courseName);
    $status = $score >= 3 ? 'pass' : 'fail';

    $stmt = $conn->prepare(
        'INSERT INTO public.test (test_id, score, test_attempt, status, student_id, course_name)
         VALUES (:test_id, :score, :test_attempt, :status, :student_id, :course_name)
         RETURNING test_id'
    );
    $stmt->execute([
        ':test_id' => $testId,
        ':score' => $score,
        ':test_attempt' => $testAttempt,
        ':status' => $status,
        ':student_id' => $studentId,
        ':course_name' => $courseName,
    ]);

    jsonResponse([
        'status' => 'success',
        'message' => 'บันทึกผลแบบทดสอบเรียบร้อย',
        'test_id' => $stmt->fetchColumn(),
    ]);
} catch (Throwable $e) {
    jsonResponse([
        'status' => 'error',
        'message' => 'บันทึกผลแบบทดสอบไม่สำเร็จ',
    ], 500);
}
