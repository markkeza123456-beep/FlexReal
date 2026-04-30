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

function findSubjectIdByCourseName(PDO $conn, string $courseName): ?string
{
    $stmt = $conn->prepare('SELECT subjects_id FROM public.subjects WHERE subjects_name = :name LIMIT 1');
    $stmt->execute([':name' => $courseName]);
    $id = $stmt->fetchColumn();
    if ($id !== false) {
        return (string) $id;
    }

    $stmt = $conn->prepare('SELECT subjects_id FROM public.subjects WHERE LOWER(subjects_name) = LOWER(:name) LIMIT 1');
    $stmt->execute([':name' => $courseName]);
    $id = $stmt->fetchColumn();

    return $id === false ? null : (string) $id;
}

$role = strtolower((string) ($_SESSION['role'] ?? ''));
if (!isset($_SESSION['user_id']) || $role !== 'student') {
    deny('กรุณาเข้าสู่ระบบนักเรียนก่อนดาวน์โหลดเอกสาร', 401);
}

$subjectId = trim((string) ($_GET['subject_id'] ?? ''));
$courseName = trim((string) ($_GET['course_name'] ?? ''));
if ($subjectId === '' && $courseName === '') {
    deny('ไม่พบรายวิชา', 400);
}

$lessonByCourse = [
    'คณิตศาสตร์' => 'lesson-math.pdf',
    'ภาษาไทย' => 'lesson-thai.pdf',
    'วิทยาศาสตร์' => 'lesson-science.pdf',
    'สังคมศึกษา' => 'lesson-social.pdf',
    'ภาษาอังกฤษ' => 'lesson-english.pdf',
];

try {
    if ($subjectId === '') {
        $subjectId = findSubjectIdByCourseName($conn, $courseName);
        if ($subjectId === null || $subjectId === '') {
            deny('ไม่พบรายวิชานี้ในตาราง Subjects', 404);
        }
    }

    $stmt = $conn->prepare(
        'SELECT 1 FROM public.student_subject
         WHERE student_id = :student_id AND subjects_id = :subjects_id
         LIMIT 1'
    );
    $stmt->execute([
        ':student_id' => (string) $_SESSION['user_id'],
        ':subjects_id' => $subjectId,
    ]);

    if (!$stmt->fetchColumn()) {
        deny('กรุณาลงรายวิชาก่อนดาวน์โหลดเอกสาร', 403);
    }
} catch (Throwable $e) {
    deny('ระบบตรวจสอบสิทธิ์ขัดข้อง: ' . $e->getMessage(), 500);
}

if ($courseName === '') {
    $stmtCourse = $conn->prepare('SELECT subjects_name FROM public.subjects WHERE subjects_id = :id LIMIT 1');
    $stmtCourse->execute([':id' => $subjectId]);
    $courseName = (string)($stmtCourse->fetchColumn() ?: '');
}

if (!isset($lessonByCourse[$courseName])) {
    deny('ไม่พบเอกสารของรายวิชานี้', 404);
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
