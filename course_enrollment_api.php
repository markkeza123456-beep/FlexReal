<?php
session_start(); header('Content-Type: application/json; charset=utf-8'); require_once __DIR__ . '/db_connect.php';
function jsonResponse(array $payload, int $statusCode = 200): void { http_response_code($statusCode); echo json_encode($payload, JSON_UNESCAPED_UNICODE); exit; }
function currentStudentId(): ?string { return (($_SESSION['role'] ?? '') === 'student' && isset($_SESSION['user_id'])) ? (string) $_SESSION['user_id'] : null; }
function findCourseId(PDO $conn, string $id, string $name): ?string {
    if ($id !== '') { $s = $conn->prepare('SELECT course_id FROM public.courses WHERE course_id = :id'); $s->execute([':id' => $id]); if ($s->fetchColumn() !== false) return $id; }
    $s = $conn->prepare('SELECT course_id FROM public.courses WHERE LOWER(name) = LOWER(:name) LIMIT 1'); $s->execute([':name' => $name]); $result = $s->fetchColumn(); return $result === false ? null : (string) $result;
}
$studentId = currentStudentId(); if ($studentId === null) { $_SESSION['after_login_return'] = 'index.html'; jsonResponse(['status' => 'unauthorized', 'message' => 'กรุณาเข้าสู่ระบบนักเรียนก่อนลงรายวิชา', 'login_url' => 'login.php'], 401); }
try {
    $courseId = findCourseId($conn, trim((string) ($_POST['course_id'] ?? $_POST['subject_id'] ?? $_GET['course_id'] ?? $_GET['subject_id'] ?? '')), trim((string) ($_POST['course_name'] ?? $_GET['course_name'] ?? '')));
    if ($courseId === null) jsonResponse(['status' => 'error', 'message' => 'ไม่พบรายวิชา'], 404);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') $conn->prepare('INSERT INTO public.student_courses (student_id, course_id) VALUES (:student_id, :course_id) ON CONFLICT (student_id, course_id) DO NOTHING')->execute([':student_id' => $studentId, ':course_id' => $courseId]);
    $s = $conn->prepare('SELECT 1 FROM public.student_courses WHERE student_id = :student_id AND course_id = :course_id'); $s->execute([':student_id' => $studentId, ':course_id' => $courseId]);
    jsonResponse(['status' => 'success', 'enrolled' => (bool) $s->fetchColumn(), 'course_id' => $courseId, 'subject_id' => $courseId]);
} catch (Throwable $e) { jsonResponse(['status' => 'error', 'message' => 'ระบบลงรายวิชาขัดข้อง: ' . $e->getMessage()], 500); }
