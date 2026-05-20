<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/curriculum_subjects_lib.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'logged_in' => false,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$role = strtolower(trim((string) ($_SESSION['role'] ?? '')));
$userId = trim((string) ($_SESSION['user_id'] ?? ''));
$name = trim((string) ($_SESSION['name'] ?? 'ผู้ใช้งาน'));
if ($name === '') {
    $name = 'ผู้ใช้งาน';
}

$firstChar = mb_substr($name, 0, 1, 'UTF-8');
if ($firstChar === '') {
    $firstChar = 'U';
}

$dashboardUrlMap = [
    'student' => 'student_dashboard.php',
    'teacher' => 'teacherdash.php',
    'staff' => 'staffdash.php',
    'parent' => 'parent_dashboard.php',
];

$payload = [
    'logged_in' => true,
    'name' => $name,
    'role' => $role,
    'avatar_text' => $firstChar,
    'dashboard_url' => $dashboardUrlMap[$role] ?? 'web.html',
];

if ($role === 'student' && $userId !== '') {
    try {
        $studentStmt = $conn->prepare(
            "SELECT student_level, studcurriculums_id
             FROM public.student
             WHERE student_id = :student_id
             LIMIT 1"
        );
        $studentStmt->execute([':student_id' => $userId]);
        $student = $studentStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $studentLevel = trim((string) ($student['student_level'] ?? ''));
        if ($studentLevel !== '') {
            $curriculumId = assignCurriculumAndEnrollRequiredSubjects($conn, $userId, $studentLevel);
            if ($curriculumId !== null) {
                $student['studcurriculums_id'] = $curriculumId;
            }
        }

        $payload['student_level'] = $studentLevel;
        $payload['curriculum_id'] = (string) ($student['studcurriculums_id'] ?? '');
    } catch (Throwable $e) {
        $payload['student_level'] = '';
        $payload['curriculum_id'] = '';
    }
}

echo json_encode($payload, JSON_UNESCAPED_UNICODE);
?>
