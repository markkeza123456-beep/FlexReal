<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (($_SESSION['role'] ?? '') !== 'staff' || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'ไม่มีสิทธิ์เข้าถึง'], JSON_UNESCAPED_UNICODE);
    exit;
}

$days = filter_var($_GET['days'] ?? 1, FILTER_VALIDATE_INT);
if (!in_array($days, [1, 3, 7], true)) $days = 1;

try {
    require_once __DIR__ . '/config/db.php';
    $sql = <<<'SQL'
        WITH activity AS (
            SELECT lp.student_id, lp.lesson_id,
                   COALESCE(lp.last_activity_at, lp.last_opened_at, lp.updated_at) AS activity_at,
                   lp.is_completed,
                   COALESCE(lp.document_progress_percent, 0) AS document_percent,
                   COALESCE(lp.video_progress_percent, 0) AS video_percent,
                   'progress'::text AS activity_type
            FROM public.lesson_progress lp
            WHERE lp.progress_type IN ('document', 'video')
              AND COALESCE(lp.last_activity_at, lp.last_opened_at, lp.updated_at) >= now() - make_interval(days => CAST(:progress_days AS integer))
            UNION ALL
            SELECT qa.student_id, qa.lesson_id, qa.submitted_at AS activity_at,
                   (qa.status = 'passed') AS is_completed,
                   0::numeric AS document_percent, 0::numeric AS video_percent,
                   'quiz'::text AS activity_type
            FROM public.quiz_attempts qa
            WHERE qa.submitted_at >= now() - make_interval(days => CAST(:quiz_days AS integer))
        ),
        per_student AS (
            SELECT a.student_id,
                   COUNT(DISTINCT a.lesson_id) FILTER (WHERE a.activity_type = 'progress') AS lessons_active,
                   COUNT(DISTINCT a.lesson_id) FILTER (WHERE a.is_completed) AS lessons_completed,
                   COUNT(DISTINCT a.lesson_id) FILTER (WHERE a.activity_type = 'quiz') AS quizzes_submitted,
                   SUM(GREATEST(a.document_percent, a.video_percent)) AS progress_percent_points,
                   MAX(a.activity_at) AS last_activity_at
            FROM activity a
            GROUP BY a.student_id
        )
        SELECT s.user_id::text AS student_id, s.full_name AS student_name,
               COALESCE(c.name || ' · ' || l.title, '-') AS latest_lesson,
               COALESCE(ps.lessons_active, 0) AS lessons_active,
               COALESCE(ps.lessons_completed, 0) AS lessons_completed,
               COALESCE(ps.quizzes_submitted, 0) AS quizzes_submitted,
               COALESCE(ps.progress_percent_points, 0) AS progress_percent_points,
               ps.last_activity_at
        FROM public.students s
        LEFT JOIN per_student ps ON ps.student_id = s.user_id::text
        LEFT JOIN LATERAL (
            SELECT a.lesson_id
            FROM activity a
            WHERE a.student_id = s.user_id::text
            ORDER BY a.activity_at DESC NULLS LAST, a.lesson_id DESC
            LIMIT 1
        ) latest ON TRUE
        LEFT JOIN public.lessons l ON l.lesson_id = latest.lesson_id
        LEFT JOIN public.courses c ON c.course_id = l.course_id
        ORDER BY ps.last_activity_at DESC NULLS LAST, s.full_name ASC, s.user_id ASC
    SQL;
    $stmt = $conn->prepare($sql);
    $stmt->execute([':progress_days' => $days, ':quiz_days' => $days]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$row) {
        $row['lessons_active'] = (int) $row['lessons_active'];
        $row['lessons_completed'] = (int) $row['lessons_completed'];
        $row['quizzes_submitted'] = (int) $row['quizzes_submitted'];
        $row['progress_percent_points'] = round((float) $row['progress_percent_points'], 1);
    }
    unset($row);
    echo json_encode(['ok' => true, 'days' => $days, 'rows' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    error_log('Staff learning progress report failed: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'โหลดรายงานความคืบหน้าไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
}
