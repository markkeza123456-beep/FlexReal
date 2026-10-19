<?php
session_start();
$role = strtolower(trim((string) ($_SESSION['role'] ?? '')));
$userId = (string) ($_SESSION['user_id'] ?? '');
if ($userId === '' || !in_array($role, ['staff', 'teacher'], true)) {
    http_response_code(401);
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';
if ($role === 'teacher') {
    $access = $conn->prepare(
        "SELECT EXISTS (
            SELECT 1 FROM public.transfer_permissions p
            JOIN public.users u ON u.user_id = p.instructor_id
            WHERE p.instructor_id = :user_id AND p.is_active = TRUE AND u.role = 'teacher'
        )"
    );
    $access->execute([':user_id' => $userId]);
    if (!$access->fetchColumn()) {
        http_response_code(403);
        exit('คุณไม่มีสิทธิ์ตรวจสอบคำขอเทียบโอน');
    }
}

if (empty($_SESSION['transcript_review_csrf'])) {
    $_SESSION['transcript_review_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = (string) $_SESSION['transcript_review_csrf'];
$requestsStmt = $conn->query(
    "SELECT r.request_id, r.student_id, r.original_file_name, r.submitted_at,
            COALESCE(s.full_name, r.student_id) AS student_name
     FROM public.transcript_requests r
     LEFT JOIN public.students s ON s.user_id = r.student_id
     WHERE r.status = 'pending'
     ORDER BY r.submitted_at ASC"
);
$requests = $requestsStmt->fetchAll(PDO::FETCH_ASSOC);
$selectedRequestId = trim((string) ($_GET['request_id'] ?? ''));
$selectedRequest = null;
foreach ($requests as $request) {
    if ((string) $request['request_id'] === $selectedRequestId) {
        $selectedRequest = $request;
        break;
    }
}
if (!$selectedRequest && $requests) {
    $selectedRequest = $requests[0];
    $selectedRequestId = (string) $selectedRequest['request_id'];
}

if ($selectedRequest) {
    $documentStmt = $conn->prepare(
        "SELECT COALESCE(NULLIF(document_url, ''), file_path) AS document_data, mime_type
         FROM public.transcript_requests
         WHERE request_id = :request_id AND status = 'pending'"
    );
    $documentStmt->execute([':request_id' => $selectedRequestId]);
    $document = $documentStmt->fetch(PDO::FETCH_ASSOC);
    if ($document) {
        $selectedRequest['document_data'] = $document['document_data'];
        $selectedRequest['mime_type'] = $document['mime_type'];
    }
}

$lessonsByCourse = [];
if ($selectedRequest) {
    $lessonsStmt = $conn->query(
        'SELECT c.course_id, c.code AS course_code, c.name AS course_name,
                l.lesson_id, l.position, l.title, l.content
         FROM public.courses c
         INNER JOIN public.lessons l ON l.course_id = c.course_id
         ORDER BY c.name ASC, l.position ASC'
    );
    foreach ($lessonsStmt->fetchAll(PDO::FETCH_ASSOC) as $lesson) {
        $courseId = (string) $lesson['course_id'];
        if (!isset($lessonsByCourse[$courseId])) {
            $lessonsByCourse[$courseId] = [
                'code' => (string) ($lesson['course_code'] ?? ''),
                'name' => (string) $lesson['course_name'],
                'lessons' => [],
            ];
        }
        $lessonsByCourse[$courseId]['lessons'][] = $lesson;
    }
}

function hReview(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
