<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function transcriptJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$role = strtolower(trim((string) ($_SESSION['role'] ?? '')));
$reviewerId = (string) ($_SESSION['user_id'] ?? '');
$reviewerName = trim((string) ($_SESSION['name'] ?? $_SESSION['full_name'] ?? $_SESSION['user_name'] ?? ''));
if ($reviewerId === '' || !in_array($role, ['staff', 'teacher'], true)) {
    transcriptJson(['status' => 'unauthorized', 'message' => 'กรุณาเข้าสู่ระบบผู้ตรวจสอบ'], 401);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    transcriptJson(['status' => 'error', 'message' => 'รองรับเฉพาะคำขอ POST'], 405);
}

$expectedToken = (string) ($_SESSION['transcript_review_csrf'] ?? '');
if ($expectedToken === '' || !hash_equals($expectedToken, (string) ($_POST['csrf_token'] ?? ''))) {
    transcriptJson(['status' => 'error', 'message' => 'คำขอหมดอายุ กรุณาโหลดหน้าใหม่'], 419);
}
session_write_close();

$requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$rawLessonIds = $_POST['lesson_ids'] ?? [];
if (!$requestId || !is_array($rawLessonIds)) {
    transcriptJson(['status' => 'error', 'message' => 'ข้อมูลคำขอไม่ถูกต้อง'], 400);
}
$lessonIds = [];
foreach ($rawLessonIds as $rawId) {
    $lessonId = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$lessonId) transcriptJson(['status' => 'error', 'message' => 'พบรหัสบทเรียนไม่ถูกต้อง'], 400);
    $lessonIds[(string) $lessonId] = true;
}
if (!$lessonIds) {
    transcriptJson(['status' => 'error', 'message' => 'กรุณาเลือกอย่างน้อยหนึ่งบทเรียนที่ให้ผ่าน'], 400);
}
$reviewerNote = trim((string) ($_POST['reviewer_note'] ?? ''));
$reviewerNote = mb_substr($reviewerNote, 0, 2000, 'UTF-8');

try {
    require_once __DIR__ . '/config/db.php';

    if ($role === 'staff') {
        $staffCheck = $conn->prepare("SELECT 1 FROM public.users WHERE user_id = :user_id AND role = 'staff'");
        $staffCheck->execute([':user_id' => $reviewerId]);
        if (!$staffCheck->fetchColumn()) transcriptJson(['status' => 'unauthorized', 'message' => 'ไม่พบสิทธิ์เจ้าหน้าที่'], 403);
    } else {
        $teacherCheck = $conn->prepare(
            "SELECT EXISTS (
                SELECT 1 FROM public.transfer_permissions p
                JOIN public.users u ON u.user_id = p.instructor_id
                WHERE p.instructor_id = :user_id AND p.is_active = TRUE AND u.role = 'teacher'
            )"
        );
        $teacherCheck->execute([':user_id' => $reviewerId]);
        if (!$teacherCheck->fetchColumn()) transcriptJson(['status' => 'unauthorized', 'message' => 'คุณไม่มีสิทธิ์ตรวจสอบคำขอเทียบโอน'], 403);
    }

    $conn->beginTransaction();
    $requestStmt = $conn->prepare(
        "SELECT student_id, status FROM public.transcript_requests WHERE request_id = :request_id FOR UPDATE"
    );
    $requestStmt->execute([':request_id' => $requestId]);
    $request = $requestStmt->fetch(PDO::FETCH_ASSOC);
    if (!$request) {
        $conn->rollBack();
        transcriptJson(['status' => 'error', 'message' => 'ไม่พบคำขอเทียบโอน'], 404);
    }
    if ($request['status'] !== 'pending') {
        $conn->rollBack();
        transcriptJson(['status' => 'error', 'message' => 'คำขอนี้ได้รับการตรวจสอบไปแล้ว'], 409);
    }

    $allLessons = $conn->query(
        'SELECT l.lesson_id, l.course_id, l.title AS lesson_title,
                c.code AS course_code, c.name AS course_name
         FROM public.lessons l
         JOIN public.courses c ON c.course_id = l.course_id
         ORDER BY l.lesson_id'
    )->fetchAll(PDO::FETCH_ASSOC);
    $lessonMap = [];
    foreach ($allLessons as $lesson) $lessonMap[(string) $lesson['lesson_id']] = (string) $lesson['course_id'];
    foreach ($lessonIds as $lessonId => $_) {
        if (!isset($lessonMap[$lessonId])) {
            $conn->rollBack();
            transcriptJson(['status' => 'error', 'message' => 'มีบทเรียนที่ไม่อยู่ในระบบ กรุณาโหลดหน้าใหม่'], 400);
        }
    }

    $studentId = (string) $request['student_id'];
    $decisionStmt = $conn->prepare(
        'INSERT INTO public.transcript_request_lessons (request_id, lesson_id, is_approved, reviewed_by, reviewed_at)
         VALUES (:request_id, :lesson_id, :is_approved, :reviewed_by, now())'
    );
    $enrollStmt = $conn->prepare(
        "INSERT INTO public.student_courses (student_id, course_id, status)
         VALUES (:student_id, :course_id, 'active')
         ON CONFLICT (student_id, course_id) DO NOTHING"
    );
    $progressStmt = $conn->prepare(
        'INSERT INTO public.lesson_progress
            (student_id, lesson_id, progress_type, is_transferred, grade, transfer_request_id, transferred_by, transferred_at, last_activity_at)
         VALUES (:student_id, :lesson_id, \'transfer\', TRUE, \'TR\', :request_id, :reviewer_id, now(), now())
         ON CONFLICT (student_id, lesson_id, progress_type) DO UPDATE SET
            is_transferred = TRUE,
            grade = \'TR\',
            transfer_request_id = EXCLUDED.transfer_request_id,
            transferred_by = EXCLUDED.transferred_by,
            transferred_at = EXCLUDED.transferred_at,
            last_activity_at = now()'
    );

    $enrolledCourses = [];
    $approvedCourses = [];
    foreach ($allLessons as $lesson) {
        $lessonId = (string) $lesson['lesson_id'];
        $isApproved = isset($lessonIds[$lessonId]);
        $decisionStmt->execute([
            ':request_id' => $requestId,
            ':lesson_id' => $lessonId,
            ':is_approved' => $isApproved ? 'true' : 'false',
            ':reviewed_by' => $reviewerId,
        ]);
        if ($isApproved) {
            $courseId = (string) $lesson['course_id'];
            $enrolledCourses[$courseId] = true;
            if (!isset($approvedCourses[$courseId])) {
                $approvedCourses[$courseId] = [
                    'course_id' => $courseId,
                    'course_code' => (string) ($lesson['course_code'] ?? ''),
                    'course_name' => (string) $lesson['course_name'],
                    'lessons' => [],
                ];
            }
            $approvedCourses[$courseId]['lessons'][] = [
                'lesson_id' => $lessonId,
                'lesson_title' => (string) $lesson['lesson_title'],
            ];
            $progressStmt->execute([
                ':student_id' => $studentId,
                ':lesson_id' => $lessonId,
                ':request_id' => $requestId,
                ':reviewer_id' => $reviewerId,
            ]);
        }
    }
    foreach (array_keys($enrolledCourses) as $courseId) {
        $enrollStmt->execute([':student_id' => $studentId, ':course_id' => $courseId]);
    }

    $approvedBy = $reviewerName !== '' ? $reviewerName . ' (' . $reviewerId . ')' : $reviewerId;
    $approvedCoursesJson = json_encode(array_values($approvedCourses), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $finishStmt = $conn->prepare(
        "UPDATE public.transcript_requests
         SET status = 'approved', reviewed_by = :reviewer_id, reviewed_at = CURRENT_TIMESTAMP,
             approved_by = :approved_by, approved_at = CURRENT_TIMESTAMP,
             approved_courses = :approved_courses, reviewer_note = :reviewer_note
         WHERE request_id = :request_id AND status = 'pending'"
    );
    $finishStmt->execute([
        ':reviewer_id' => $reviewerId,
        ':approved_by' => $approvedBy,
        ':approved_courses' => $approvedCoursesJson,
        ':reviewer_note' => $reviewerNote !== '' ? $reviewerNote : null,
        ':request_id' => $requestId,
    ]);
    if ($finishStmt->rowCount() !== 1) throw new RuntimeException('ไม่สามารถยืนยันผลตรวจสอบได้');

    $conn->commit();
    transcriptJson([
        'status' => 'success',
        'message' => 'อนุมัติผลเทียบโอนเรียบร้อยแล้ว',
        'approved_lessons' => count($lessonIds),
    ]);
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) $conn->rollBack();
    error_log('Transcript review processing failed: ' . $e->getMessage());
    transcriptJson(['status' => 'error', 'message' => 'บันทึกผลเทียบโอนไม่สำเร็จ กรุณาตรวจสอบการตั้งค่าฐานข้อมูล'], 500);
}
