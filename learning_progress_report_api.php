<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$role = strtolower(trim((string) ($_SESSION['role'] ?? '')));
$userId = trim((string) ($_SESSION['user_id'] ?? ''));
$roleScope = ['student' => 'student', 'teacher' => 'teacher', 'director' => 'director', 'staff' => 'director', 'admin' => 'director', 'executive' => 'director'];
if ($userId === '' || !isset($roleScope[$role])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'ไม่มีสิทธิ์เข้าถึง'], JSON_UNESCAPED_UNICODE);
    exit;
}

$days = filter_var($_GET['days'] ?? 1, FILTER_VALIDATE_INT);
if (!in_array($days, [1, 3, 7], true)) $days = 1;
$search = trim((string) ($_GET['q'] ?? ''));
$search = mb_substr($search, 0, 100, 'UTF-8');
$page = max(1, (int) ($_GET['page'] ?? 1));
$pageSize = 25;

try {
    require_once __DIR__ . '/config/db.php';
    $scope = $roleScope[$role];
    $params = [':progress_days' => $days, ':quiz_days' => $days, ':search' => '%' . $search . '%'];
    $scopeSql = '';
    if ($scope === 'student') {
        $scopeSql = 'AND s.user_id::text = :scope_user';
        $params[':scope_user'] = $userId;
    } elseif ($scope === 'teacher') {
        $scopeSql = 'AND EXISTS (
            SELECT 1 FROM public.student_courses enrolled
            JOIN public.course_teachers assigned ON assigned.course_id = enrolled.course_id
            WHERE enrolled.student_id = s.user_id AND enrolled.status = \'active\'
              AND assigned.teacher_id::text = :scope_teacher
        )';
        $params[':scope_teacher'] = $userId;
    }
    $base = <<<'SQL'
        WITH activity AS (
            SELECT lp.student_id::text AS student_id, lp.lesson_id,
                   COALESCE(lp.last_activity_at, lp.last_opened_at, lp.updated_at) AS activity_at,
                   lp.is_completed,
                   GREATEST(COALESCE(lp.document_progress_percent, 0), COALESCE(lp.video_progress_percent, 0)) AS progress_percent,
                   'progress'::text AS activity_type
            FROM public.lesson_progress lp
            WHERE lp.progress_type IN ('document', 'video')
              AND COALESCE(lp.last_activity_at, lp.last_opened_at, lp.updated_at) >= now() - make_interval(days => CAST(:progress_days AS integer))
            UNION ALL
            SELECT qa.student_id::text, qa.lesson_id, qa.submitted_at,
                   (qa.status = 'passed'), 0::numeric, 'quiz'::text
            FROM public.quiz_attempts qa
            WHERE qa.submitted_at >= now() - make_interval(days => CAST(:quiz_days AS integer))
        ), per_student AS (
            SELECT student_id,
                   COUNT(DISTINCT lesson_id) FILTER (WHERE activity_type = 'progress') AS lessons_active,
                   COUNT(DISTINCT lesson_id) FILTER (WHERE is_completed) AS lessons_completed,
                   COUNT(*) FILTER (WHERE activity_type = 'quiz') AS quizzes_submitted,
                   SUM(progress_percent) AS progress_percent_points,
                   MAX(activity_at) AS last_activity_at
            FROM activity GROUP BY student_id
        )
SQL;

    $countSql = $base . " SELECT COUNT(*) FROM public.students s
        WHERE s.full_name ILIKE :search {$scopeSql}";
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();
    $pages = max(1, (int) ceil($total / $pageSize));
    $page = min($page, $pages);
    $offset = ($page - 1) * $pageSize;

    $sql = $base . " SELECT s.user_id::text AS student_id, s.full_name AS student_name,
        COALESCE(c.name || ' · ' || l.title, '-') AS latest_lesson,
        COALESCE(ps.lessons_active, 0) AS lessons_active,
        COALESCE(ps.lessons_completed, 0) AS lessons_completed,
        COALESCE(ps.quizzes_submitted, 0) AS quizzes_submitted,
        COALESCE(ps.progress_percent_points, 0) AS progress_percent_points,
        ps.last_activity_at
        FROM public.students s
        LEFT JOIN per_student ps ON ps.student_id = s.user_id::text
        LEFT JOIN LATERAL (
            SELECT a.lesson_id FROM activity a WHERE a.student_id = s.user_id::text
            ORDER BY a.activity_at DESC, a.lesson_id DESC LIMIT 1
        ) latest ON TRUE
        LEFT JOIN public.lessons l ON l.lesson_id = latest.lesson_id
        LEFT JOIN public.courses c ON c.course_id = l.course_id
        WHERE s.full_name ILIKE :search {$scopeSql}
        ORDER BY (ps.last_activity_at IS NOT NULL) DESC, ps.last_activity_at DESC NULLS LAST, s.full_name ASC
        LIMIT :page_size OFFSET :page_offset";
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) $stmt->bindValue($key, $value);
    $stmt->bindValue(':page_size', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':page_offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$row) {
        foreach (['lessons_active', 'lessons_completed', 'quizzes_submitted'] as $field) $row[$field] = (int) $row[$field];
        $row['progress_percent_points'] = round((float) $row['progress_percent_points'], 1);
    }
    unset($row);

    $activeScopeSql = '';
    $activeParams = [':days' => $days];
    if ($scope === 'student') {
        $activeScopeSql = 'AND active.student_id = :scope_user';
        $activeParams[':scope_user'] = $userId;
    } elseif ($scope === 'teacher') {
        $activeScopeSql = "AND EXISTS (
            SELECT 1 FROM public.student_courses enrolled
            JOIN public.course_teachers assigned ON assigned.course_id = enrolled.course_id
            WHERE enrolled.student_id::text = active.student_id AND enrolled.status = 'active'
              AND assigned.teacher_id::text = :scope_teacher
        )";
        $activeParams[':scope_teacher'] = $userId;
    }
    $activeStmt = $conn->prepare("SELECT COUNT(DISTINCT active.student_id)
        FROM (
            SELECT student_id::text AS student_id FROM public.lesson_progress
            WHERE progress_type IN ('document', 'video')
              AND COALESCE(last_activity_at, last_opened_at, updated_at) >= now() - make_interval(days => CAST(:days AS integer))
            UNION SELECT student_id::text FROM public.quiz_attempts
            WHERE submitted_at >= now() - make_interval(days => CAST(:days AS integer))
        ) active WHERE TRUE {$activeScopeSql}");
    $activeStmt->execute($activeParams);
    $activeCount = (int) $activeStmt->fetchColumn();

    echo json_encode(['ok' => true, 'role' => $role, 'days' => $days, 'active_count' => $activeCount,
        'pagination' => ['page' => $page, 'page_size' => $pageSize, 'total' => $total, 'pages' => $pages], 'rows' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    error_log('Learning progress report failed: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'โหลดรายงานความคืบหน้าไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
}
