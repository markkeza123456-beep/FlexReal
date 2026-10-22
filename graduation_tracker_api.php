<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

function graduationJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function graduationSummary(PDO $conn, string $studentId): array
{
    $stmt = $conn->prepare(
        "WITH active_curriculum AS (
            SELECT curriculum_id FROM public.student_curricula
            WHERE student_id = :student_curriculum_id AND status = 'active'
            ORDER BY enrolled_at DESC LIMIT 1
        ), course_progress AS (
            SELECT c.course_id,
                   COALESCE(c.credits, 0)::numeric AS credits,
                   CASE WHEN cc.requirement_type = 'required' THEN 'compulsory' WHEN cc.requirement_type = 'elective' THEN 'elective' ELSE NULL END AS subject_type,
                   sc.status AS enrollment_status,
                   COUNT(l.lesson_id)::integer AS lesson_count,
                   COUNT(l.lesson_id) FILTER (
                       WHERE COALESCE(lp.is_transferred, FALSE)
                          OR UPPER(COALESCE(lp.grade, '')) = 'TR'
                          OR qa.status = 'passed'
                          OR (qa.status <> 'pending_review'
                              AND COALESCE(qa.total_score, 0) > 0
                              AND qa.score >= CEIL(qa.total_score))
                   )::integer AS passed_lessons,
                   COUNT(l.lesson_id) FILTER (
                       WHERE COALESCE(lp.is_transferred, FALSE)
                          OR UPPER(COALESCE(lp.grade, '')) = 'TR'
                   )::integer AS transferred_lessons
            FROM public.courses c
            LEFT JOIN active_curriculum ac ON TRUE
            LEFT JOIN public.curriculum_courses cc ON cc.curriculum_id = ac.curriculum_id AND cc.course_id = c.course_id
            LEFT JOIN public.student_courses sc ON sc.course_id = c.course_id AND sc.student_id = :student_enrollment_id
            LEFT JOIN public.lessons l ON l.course_id = c.course_id
            LEFT JOIN public.lesson_progress_summary lp
                   ON lp.student_id = :student_progress_id AND lp.lesson_id = l.lesson_id
            LEFT JOIN LATERAL (
                SELECT a.status, a.score, a.total_score
                FROM public.quiz_attempts a
                WHERE a.student_id = :student_attempt_id AND a.lesson_id = l.lesson_id
                ORDER BY a.submitted_at DESC, a.attempt_number DESC
                LIMIT 1
            ) qa ON TRUE
            WHERE cc.course_id IS NOT NULL OR sc.course_id IS NOT NULL
            GROUP BY c.course_id, c.credits, c.subject_type, cc.requirement_type, sc.status
        ), course_results AS (
            SELECT course_id, credits, subject_type, enrollment_status, lesson_count,
                   passed_lessons, transferred_lessons,
                   (LOWER(COALESCE(enrollment_status, '')) IN ('passed', 'completed', 'tr')
                    OR (lesson_count > 0 AND passed_lessons = lesson_count)) AS is_completed,
                   (UPPER(COALESCE(enrollment_status, '')) = 'TR'
                    OR (lesson_count > 0 AND transferred_lessons = lesson_count)) AS is_transferred
            FROM course_progress
        )
        SELECT COUNT(*)::integer AS total_courses,
               COUNT(*) FILTER (WHERE is_completed)::integer AS completed_courses,
               COUNT(*) FILTER (WHERE subject_type = 'compulsory')::integer AS compulsory_courses,
               COUNT(*) FILTER (WHERE subject_type = 'compulsory' AND is_completed)::integer AS completed_compulsory_courses,
               COUNT(*) FILTER (WHERE subject_type = 'elective')::integer AS elective_courses,
               COUNT(*) FILTER (WHERE subject_type = 'elective' AND is_completed)::integer AS completed_elective_courses,
               COUNT(*) FILTER (WHERE is_transferred)::integer AS transferred_courses,
               COALESCE(SUM(credits) FILTER (WHERE is_completed), 0)::numeric AS completed_credits
        FROM course_results"
    );
    $stmt->execute([
        ':student_progress_id' => $studentId,
        ':student_attempt_id' => $studentId,
        ':student_enrollment_id' => $studentId,
        ':student_curriculum_id' => $studentId,
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $total = max(0, (int) ($row['total_courses'] ?? 0));
    $completed = max(0, (int) ($row['completed_courses'] ?? 0));
    $transferred = max(0, (int) ($row['transferred_courses'] ?? 0));
    $compulsory = max(0, (int) ($row['compulsory_courses'] ?? 0));
    $completedCompulsory = max(0, (int) ($row['completed_compulsory_courses'] ?? 0));
    $electives = max(0, (int) ($row['elective_courses'] ?? 0));
    $completedElectives = max(0, (int) ($row['completed_elective_courses'] ?? 0));
    $percent = $total > 0 ? min(100.0, round(($completed / $total) * 100, 1)) : 0.0;

    return [
        'has_courses' => $total > 0,
        'total_courses' => $total,
        'completed_courses' => $completed,
        'transferred_courses' => $transferred,
        'compulsory_courses' => $compulsory,
        'completed_compulsory_courses' => $completedCompulsory,
        'elective_courses' => $electives,
        'completed_elective_courses' => $completedElectives,
        'completed_credits' => max(0.0, (float) ($row['completed_credits'] ?? 0)),
        'progress_percent' => $percent,
        'eligible' => $compulsory > 0 && $completedCompulsory >= $compulsory,
    ];
}

$role = strtolower(trim((string) ($_SESSION['role'] ?? '')));
$studentId = (string) ($_SESSION['user_id'] ?? '');
if ($studentId === '' || $role !== 'student') {
    graduationJson(['status' => 'unauthorized', 'message' => 'กรุณาเข้าสู่ระบบนักเรียน'], 401);
}

if (empty($_SESSION['graduation_csrf'])) {
    $_SESSION['graduation_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = (string) $_SESSION['graduation_csrf'];
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = strtolower(trim((string) ($_GET['action'] ?? 'summary')));

if ($method === 'POST') {
    $action = 'submit';
    $providedToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!hash_equals($csrfToken, $providedToken)) {
        graduationJson(['status' => 'error', 'message' => 'คำขอหมดอายุ กรุณาโหลดหน้าใหม่'], 419);
    }
}
session_write_close();

if (!in_array($method, ['GET', 'POST'], true) || ($method === 'GET' && $action !== 'summary') || ($method === 'POST' && $action !== 'submit')) {
    header('Allow: GET, POST');
    graduationJson(['status' => 'error', 'message' => 'รูปแบบคำขอไม่ถูกต้อง'], 405);
}

try {
    require_once __DIR__ . '/config/db.php';

    if ($method === 'GET') {
        $summary = graduationSummary($conn, $studentId);
        $pendingStmt = $conn->prepare(
            "SELECT request_id, status, submitted_at
             FROM public.graduation_requests
             WHERE student_id = :student_id AND status = 'pending'
             ORDER BY submitted_at DESC LIMIT 1"
        );
        $pendingStmt->execute([':student_id' => $studentId]);
        $summary['pending_request'] = $pendingStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $approvedStmt = $conn->prepare(
            "SELECT request_id, status, total_credits, reviewed_at,
                    document_bucket, document_object_path
             FROM public.graduation_requests
             WHERE student_id = :student_id AND status = 'approved'
             ORDER BY reviewed_at DESC NULLS LAST, request_id DESC LIMIT 1"
        );
        $approvedStmt->execute([':student_id' => $studentId]);
        $approvedRequest = $approvedStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $summary['approved_request'] = $approvedRequest;
        $summary['approved_document'] = $approvedRequest
            && !empty($approvedRequest['document_bucket'])
            && !empty($approvedRequest['document_object_path'])
                ? $approvedRequest
                : null;
        $summary['csrf_token'] = $csrfToken;
        graduationJson(['status' => 'success', 'graduation' => $summary]);
    }

    $conn->beginTransaction();
    $summary = graduationSummary($conn, $studentId);
    if (!$summary['eligible']) {
        $conn->rollBack();
        graduationJson([
            'status' => 'error',
            'message' => $summary['has_courses']
                ? 'ต้องเรียนหรือเทียบโอนวิชาบังคับให้ครบทุกวิชาก่อน จึงจะสามารถกดส่งคำขอจบได้'
                : 'ยังไม่พบรายวิชาที่กำหนดให้เรียน กรุณาติดต่อเจ้าหน้าที่',
        ], 409);
    }

    $insertStmt = $conn->prepare(
        "INSERT INTO public.graduation_requests (student_id, total_credits, status)
         VALUES (:student_id, :total_credits, 'pending')
         RETURNING request_id"
    );
    $insertStmt->execute([
        ':student_id' => $studentId,
        ':total_credits' => $summary['completed_credits'],
    ]);
    $requestId = (int) $insertStmt->fetchColumn();
    $conn->commit();

    graduationJson([
        'status' => 'success',
        'message' => 'ส่งคำขอจบการศึกษาแล้ว',
        'request_id' => $requestId,
    ]);
} catch (PDOException $e) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    if ($e->getCode() === '23505') {
        graduationJson(['status' => 'error', 'message' => 'คุณมีคำขอจบการศึกษาที่รอพิจารณาอยู่แล้ว'], 409);
    }
    error_log('Graduation tracker database error: ' . $e->getMessage());
    graduationJson(['status' => 'error', 'message' => 'ไม่สามารถบันทึกคำขอจบการศึกษาได้'], 500);
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Graduation tracker failed: ' . $e->getMessage());
    graduationJson(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด กรุณาลองอีกครั้ง'], 500);
}
