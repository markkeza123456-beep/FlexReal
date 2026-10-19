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
            SELECT c.curriculum_id, c.total_credits
            FROM public.student_curricula sc
            INNER JOIN public.curricula c ON c.curriculum_id = sc.curriculum_id
            WHERE sc.student_id = :student_curriculum_id AND sc.status = 'active'
            ORDER BY sc.enrolled_at DESC
            LIMIT 1
        ), course_results AS (
            SELECT cc.course_id,
                   COALESCE(c.credits, 0)::numeric AS credits,
                   COUNT(l.lesson_id)::integer AS lesson_count,
                   COUNT(l.lesson_id) FILTER (
                       WHERE COALESCE(lp.is_transferred, FALSE)
                          OR qa.status = 'passed'
                          OR (qa.status <> 'pending_review'
                              AND COALESCE(qa.total_score, 0) > 0
                              AND qa.score >= CEIL(qa.total_score))
                   )::integer AS passed_lessons
            FROM active_curriculum ac
            INNER JOIN public.curriculum_courses cc ON cc.curriculum_id = ac.curriculum_id
            INNER JOIN public.courses c ON c.course_id = cc.course_id
            LEFT JOIN public.lessons l ON l.course_id = c.course_id
            LEFT JOIN public.lesson_progress lp
                   ON lp.student_id = :student_progress_id AND lp.lesson_id = l.lesson_id
            LEFT JOIN LATERAL (
                SELECT a.status, a.score, a.total_score
                FROM public.quiz_attempts a
                WHERE a.student_id = :student_attempt_id AND a.lesson_id = l.lesson_id
                ORDER BY a.submitted_at DESC, a.attempt_number DESC
                LIMIT 1
            ) qa ON TRUE
            GROUP BY cc.course_id, c.credits
        )
        SELECT ac.total_credits AS required_credits,
               COALESCE(SUM(cr.credits) FILTER (
                   WHERE cr.lesson_count > 0 AND cr.passed_lessons = cr.lesson_count
               ), 0)::numeric AS earned_credits
        FROM active_curriculum ac
        LEFT JOIN course_results cr ON TRUE
        GROUP BY ac.total_credits"
    );
    $stmt->execute([
        ':student_curriculum_id' => $studentId,
        ':student_progress_id' => $studentId,
        ':student_attempt_id' => $studentId,
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return [
            'has_curriculum' => false,
            'required_credits' => 0.0,
            'earned_credits' => 0.0,
            'progress_percent' => 0.0,
            'eligible' => false,
        ];
    }

    $required = max(0.0, (float) $row['required_credits']);
    $earned = max(0.0, (float) $row['earned_credits']);
    $percent = $required > 0 ? min(100.0, round(($earned / $required) * 100, 1)) : 0.0;

    return [
        'has_curriculum' => true,
        'required_credits' => $required,
        'earned_credits' => $earned,
        'progress_percent' => $percent,
        'eligible' => $required > 0 && $earned >= $required,
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
    require_once __DIR__ . '/db_connect.php';

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
        $summary['csrf_token'] = $csrfToken;
        graduationJson(['status' => 'success', 'graduation' => $summary]);
    }

    $conn->beginTransaction();
    $summary = graduationSummary($conn, $studentId);
    if (!$summary['eligible']) {
        $conn->rollBack();
        graduationJson([
            'status' => 'error',
            'message' => $summary['has_curriculum']
                ? 'หน่วยกิตยังไม่ครบตามหลักสูตร'
                : 'ยังไม่พบหลักสูตรที่กำลังศึกษา กรุณาติดต่อเจ้าหน้าที่',
        ], 409);
    }

    $insertStmt = $conn->prepare(
        "INSERT INTO public.graduation_requests (student_id, total_credits, status)
         VALUES (:student_id, :total_credits, 'pending')
         RETURNING request_id"
    );
    $insertStmt->execute([
        ':student_id' => $studentId,
        ':total_credits' => $summary['earned_credits'],
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
