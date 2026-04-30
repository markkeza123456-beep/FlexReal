<?php
session_start();

require_once __DIR__ . '/db_connect.php';

function deny(string $message, int $statusCode = 403): void
{
    http_response_code($statusCode);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

function ensureEnrollmentTable(PDO $conn): void
{
    $conn->exec(
        'CREATE TABLE IF NOT EXISTS public.course_enrollments (
            student_id TEXT NOT NULL,
            course_name TEXT NOT NULL,
            enrolled_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            PRIMARY KEY (student_id, course_name)
        )'
    );
}

$role = strtolower((string) ($_SESSION['role'] ?? ''));
if (!isset($_SESSION['user_id']) || $role !== 'student') {
    deny('กรุณาเข้าสู่ระบบนักเรียนก่อนดาวน์โหลดเอกสาร', 401);
}

$courseName = trim((string) ($_GET['course_name'] ?? ''));
if ($courseName === '') {
    deny('ไม่พบชื่อรายวิชา', 400);
}

$lessonByCourse = [
    'คณิตศาสตร์' => 'lesson-math.pdf',
    'ภาษาไทย' => 'lesson-thai.pdf',
    'วิทยาศาสตร์' => 'lesson-science.pdf',
    'สังคมศึกษา' => 'lesson-social.pdf',
    'ภาษาอังกฤษ' => 'lesson-english.pdf',
];

if (!isset($lessonByCourse[$courseName])) {
    deny('ไม่พบเอกสารของรายวิชานี้', 404);
}

try {
    ensureEnrollmentTable($conn);

    $stmt = $conn->prepare(
        'SELECT 1 FROM public.course_enrollments
         WHERE student_id = :student_id AND course_name = :course_name
         LIMIT 1'
    );
    $stmt->execute([
        ':student_id' => (string) $_SESSION['user_id'],
        ':course_name' => $courseName,
    ]);

    if (!$stmt->fetchColumn()) {
        deny('กรุณาลงรายวิชาก่อนดาวน์โหลดเอกสาร', 403);
    }
} catch (Throwable $e) {
    deny('ระบบตรวจสอบสิทธิ์ขัดข้อง: ' . $e->getMessage(), 500);
}

$filePath = __DIR__ . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . $lessonByCourse[$courseName];
if (!is_file($filePath)) {
    deny('ไม่พบไฟล์เอกสาร', 404);
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
