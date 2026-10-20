<?php
session_start();
$role = strtolower(trim((string) ($_SESSION['role'] ?? '')));
$user_role = $role;
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
$requestListFilter = $user_role === 'teacher' ? "WHERE r.status = 'pending'" : '';
$requestsStmt = $conn->query(
    "SELECT r.request_id, r.student_id, r.original_file_name, r.submitted_at,
            r.created_at, r.status, r.approved_at, r.approved_by, r.approved_courses,
            r.reviewed_by, r.reviewed_at,
            r.reviewer_note,
            COALESCE(reviewer_teacher.full_name, NULLIF(TRIM(reviewer_staff.first_name || ' ' || reviewer_staff.last_name), '')) AS approver_name,
            COALESCE(reviewer.user_id, r.reviewed_by) AS approver_id,
            COALESCE(s.full_name, r.student_id) AS student_name
     FROM public.transcript_requests r
     LEFT JOIN public.students s ON s.user_id = r.student_id
     LEFT JOIN public.users reviewer ON reviewer.user_id = r.reviewed_by
     LEFT JOIN public.teachers reviewer_teacher ON reviewer_teacher.user_id = reviewer.user_id
     LEFT JOIN public.staff reviewer_staff ON reviewer_staff.user_id = reviewer.user_id
     $requestListFilter
     ORDER BY CASE WHEN r.status = 'pending' THEN 0 ELSE 1 END,
              r.submitted_at DESC, r.request_id DESC"
);
$requests = $requestsStmt->fetchAll(PDO::FETCH_ASSOC);
$selectedRequestId = trim((string) ($_GET['id'] ?? $_GET['request_id'] ?? ''));
$selectedRequest = null;
foreach ($requests as $request) {
    if ((string) $request['request_id'] === $selectedRequestId) {
        $selectedRequest = $request;
        break;
    }
}
// Teachers may open a previously approved request by its direct URL, while it
// remains excluded from their searchable request list.
if (!$selectedRequest && $user_role === 'teacher' && $selectedRequestId !== '') {
    $selectedStmt = $conn->prepare(
        "SELECT r.request_id, r.student_id, r.original_file_name, r.submitted_at,
                r.created_at, r.status, r.approved_at, r.approved_by, r.approved_courses,
                r.reviewed_by, r.reviewed_at, r.reviewer_note,
                COALESCE(reviewer_teacher.full_name, NULLIF(TRIM(reviewer_staff.first_name || ' ' || reviewer_staff.last_name), '')) AS approver_name,
                COALESCE(reviewer.user_id, r.reviewed_by) AS approver_id,
                COALESCE(s.full_name, r.student_id) AS student_name
         FROM public.transcript_requests r
         LEFT JOIN public.students s ON s.user_id = r.student_id
         LEFT JOIN public.users reviewer ON reviewer.user_id = r.reviewed_by
         LEFT JOIN public.teachers reviewer_teacher ON reviewer_teacher.user_id = reviewer.user_id
         LEFT JOIN public.staff reviewer_staff ON reviewer_staff.user_id = reviewer.user_id
         WHERE r.request_id = :request_id AND r.status = 'approved'"
    );
    $selectedStmt->execute([':request_id' => $selectedRequestId]);
    $selectedRequest = $selectedStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
if (!$selectedRequest && $requests) {
    $pendingRequests = array_values(array_filter($requests, static fn(array $request): bool => $request['status'] === 'pending'));
    $selectedRequest = $pendingRequests[0] ?? $requests[0];
    $selectedRequestId = (string) $selectedRequest['request_id'];
}

if ($selectedRequest) {
    $documentStmt = $conn->prepare(
        "SELECT COALESCE(NULLIF(document_url, ''), file_path) AS document_data, mime_type
         FROM public.transcript_requests
         WHERE request_id = :request_id"
    );
    $documentStmt->execute([':request_id' => $selectedRequestId]);
    $document = $documentStmt->fetch(PDO::FETCH_ASSOC);
    if ($document) {
        $selectedRequest['document_data'] = $document['document_data'];
        $selectedRequest['mime_type'] = $document['mime_type'];
    }

    $approvedCourses = json_decode((string) ($selectedRequest['approved_courses'] ?? ''), true);
    if (!is_array($approvedCourses) || !$approvedCourses) {
        $approvedCourses = [];
        $approvedLessonsStmt = $conn->prepare(
            'SELECT c.course_id, c.code AS course_code, c.name AS course_name,
                    l.lesson_id, l.title AS lesson_title
             FROM public.transcript_request_lessons trl
             JOIN public.lessons l ON l.lesson_id = trl.lesson_id
             JOIN public.courses c ON c.course_id = l.course_id
             WHERE trl.request_id = :request_id AND trl.is_approved = TRUE
             ORDER BY c.name, l.position'
        );
        $approvedLessonsStmt->execute([':request_id' => $selectedRequestId]);
        foreach ($approvedLessonsStmt->fetchAll(PDO::FETCH_ASSOC) as $lesson) {
            $courseId = (string) $lesson['course_id'];
            if (!isset($approvedCourses[$courseId])) {
                $approvedCourses[$courseId] = [
                    'course_id' => $courseId,
                    'course_code' => (string) ($lesson['course_code'] ?? ''),
                    'course_name' => (string) $lesson['course_name'],
                    'lessons' => [],
                ];
            }
            $approvedCourses[$courseId]['lessons'][] = [
                'lesson_id' => (string) $lesson['lesson_id'],
                'lesson_title' => (string) $lesson['lesson_title'],
            ];
        }
        $approvedCourses = array_values($approvedCourses);
    }
    $selectedRequest['approved_courses_list'] = $approvedCourses;
    $approverId = trim((string) ($selectedRequest['approver_id'] ?? $selectedRequest['reviewed_by'] ?? ''));
    $approverName = trim((string) ($selectedRequest['approver_name'] ?? ''));
    $storedApprover = trim((string) ($selectedRequest['approved_by'] ?? ''));
    if ($approverName !== '') {
        $selectedRequest['approver_display'] = $approverId !== ''
            ? $approverId . ' · ' . $approverName
            : $approverName;
    } else {
        $selectedRequest['approver_display'] = $storedApprover !== '' ? $storedApprover : ($approverId !== '' ? $approverId : '-');
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

function formatReviewDate(?string $value): string
{
    if ($value === null || trim($value) === '') return '-';
    try {
        $date = (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('Asia/Bangkok'));
        return $date->format('d/m/') . ((int) $date->format('Y') + 543) . $date->format(' H:i') . ' น.';
    } catch (Throwable $error) {
        return '-';
    }
}
