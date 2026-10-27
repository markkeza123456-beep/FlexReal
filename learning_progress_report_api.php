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
    $params = [':days' => $days, ':search' => '%' . $search . '%'];
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
        WITH lesson_activity AS (
            SELECT lp.student_id::text AS student_id, lp.lesson_id,
                   MAX(COALESCE(lp.last_activity_at, lp.last_opened_at, lp.updated_at)) AS activity_at,
                   BOOL_OR(lp.is_completed) AS is_completed
            FROM public.lesson_progress lp
            WHERE lp.progress_type IN ('document', 'video')
              AND COALESCE(lp.last_activity_at, lp.last_opened_at, lp.updated_at) >= now() - make_interval(days => CAST(:days AS integer))
            GROUP BY lp.student_id, lp.lesson_id
        ), quiz_activity AS (
            SELECT qa.student_id::text AS student_id, qa.lesson_id, qa.attempt_id,
                   qa.attempt_number, qa.score, qa.total_score, qa.status, qa.submitted_at
            FROM public.quiz_attempts qa
            WHERE qa.submitted_at >= now() - make_interval(days => CAST(:days AS integer))
        ), lesson_summary AS (
            SELECT student_id, COUNT(DISTINCT lesson_id) AS lessons_active,
                   COUNT(DISTINCT lesson_id) FILTER (WHERE is_completed) AS lessons_completed,
                   MAX(activity_at) AS last_lesson_activity
            FROM lesson_activity GROUP BY student_id
        ), quiz_summary AS (
            SELECT student_id, COUNT(DISTINCT attempt_id) AS quizzes_submitted,
                   COUNT(DISTINCT attempt_id) FILTER (WHERE status = 'passed') AS quizzes_passed,
                   COUNT(DISTINCT attempt_id) FILTER (WHERE status <> 'passed') AS quizzes_failed,
                   MAX(submitted_at) AS last_quiz_activity
            FROM quiz_activity GROUP BY student_id
        ), per_student AS (
            SELECT s.user_id::text AS student_id,
                   COALESCE(ls.lessons_active, 0) AS lessons_active,
                   COALESCE(ls.lessons_completed, 0) AS lessons_completed,
                   COALESCE(qs.quizzes_submitted, 0) AS quizzes_submitted,
                   COALESCE(qs.quizzes_passed, 0) AS quizzes_passed,
                   COALESCE(qs.quizzes_failed, 0) AS quizzes_failed,
                   GREATEST(ls.last_lesson_activity, qs.last_quiz_activity) AS last_activity_at
            FROM public.students s
            LEFT JOIN lesson_summary ls ON ls.student_id = s.user_id::text
            LEFT JOIN quiz_summary qs ON qs.student_id = s.user_id::text
        ), student_targets AS (
            SELECT sc.student_id::text AS student_id,
                   COUNT(DISTINCT l.lesson_id) AS total_lessons
            FROM public.student_courses sc
            JOIN public.lessons l ON l.course_id = sc.course_id
            WHERE sc.status = 'active'
            GROUP BY sc.student_id
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
        COALESCE(ps.lessons_active, 0) AS lessons_active,
        COALESCE(ps.lessons_completed, 0) AS lessons_completed,
        COALESCE(ps.quizzes_submitted, 0) AS quizzes_submitted,
        COALESCE(ps.quizzes_passed, 0) AS quizzes_passed,
        COALESCE(ps.quizzes_failed, 0) AS quizzes_failed,
        COALESCE(LEAST(100, ROUND(100.0 * COALESCE(ps.lessons_completed, 0) / NULLIF(st.total_lessons, 0), 1)), 0) AS progress_percent,
        ps.last_activity_at
        FROM public.students s
        LEFT JOIN per_student ps ON ps.student_id = s.user_id::text
        LEFT JOIN student_targets st ON st.student_id = s.user_id::text
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
        foreach (['lessons_active', 'lessons_completed', 'quizzes_submitted', 'quizzes_passed', 'quizzes_failed'] as $field) $row[$field] = (int) $row[$field];
        $row['progress_percent'] = min(100, max(0, round((float) $row['progress_percent'], 1)));
        $row['lessons'] = [];
        $row['quizzes'] = [];
    }
    unset($row);

    if ($rows) {
        $studentIds = array_column($rows, 'student_id');
        $placeholders = [];
        $detailParams = [':detail_days' => $days];
        foreach ($studentIds as $index => $studentId) {
            $key = ':student_' . $index;
            $placeholders[] = $key;
            $detailParams[$key] = $studentId;
        }
        $inSql = implode(', ', $placeholders);
        $lessonStmt = $conn->prepare("SELECT lp.student_id::text AS student_id, lp.lesson_id,
                COALESCE(c.name || ' · ' || l.title, l.title, 'บทเรียน') AS lesson_title,
                BOOL_OR(lp.is_completed) AS is_completed,
                MAX(COALESCE(lp.last_activity_at, lp.last_opened_at, lp.updated_at)) AS activity_at
            FROM public.lesson_progress lp
            JOIN public.lessons l ON l.lesson_id = lp.lesson_id
            LEFT JOIN public.courses c ON c.course_id = l.course_id
            WHERE lp.progress_type IN ('document', 'video')
              AND COALESCE(lp.last_activity_at, lp.last_opened_at, lp.updated_at) >= now() - make_interval(days => CAST(:detail_days AS integer))
              AND lp.student_id::text IN ({$inSql})
            GROUP BY lp.student_id, lp.lesson_id, c.name, l.title
            ORDER BY activity_at DESC, lp.lesson_id DESC");
        $lessonStmt->execute($detailParams);
        $rowIndex = array_column($rows, null, 'student_id');
        foreach ($lessonStmt->fetchAll(PDO::FETCH_ASSOC) as $lesson) {
            $studentId = $lesson['student_id'];
            if (isset($rowIndex[$studentId])) {
                $rowIndex[$studentId]['lessons'][] = [
                    'lesson_id' => (string) $lesson['lesson_id'],
                    'title' => $lesson['lesson_title'],
                    'is_completed' => in_array($lesson['is_completed'], [true, 1, '1', 't', 'true'], true),
                    'activity_at' => $lesson['activity_at'],
                ];
            }
        }

        $quizStmt = $conn->prepare("SELECT qa.student_id::text AS student_id, qa.attempt_id,
                qa.lesson_id, qa.attempt_number, qa.score, qa.total_score, qa.status,
                qa.submitted_at, COALESCE(c.name || ' · ' || l.title, l.title, 'แบบทดสอบ') AS quiz_title,
                COUNT(*) OVER (PARTITION BY qa.student_id, qa.lesson_id) AS attempts_in_range,
                ROW_NUMBER() OVER (PARTITION BY qa.student_id, qa.lesson_id ORDER BY qa.submitted_at DESC, qa.attempt_number DESC, qa.attempt_id DESC) AS recent_rank
            FROM public.quiz_attempts qa
            JOIN public.lessons l ON l.lesson_id = qa.lesson_id
            LEFT JOIN public.courses c ON c.course_id = l.course_id
            WHERE qa.submitted_at >= now() - make_interval(days => CAST(:detail_days AS integer))
              AND qa.student_id::text IN ({$inSql})
            ORDER BY qa.submitted_at DESC, qa.attempt_number DESC");
        $quizStmt->execute($detailParams);
        foreach ($quizStmt->fetchAll(PDO::FETCH_ASSOC) as $quiz) {
            $studentId = $quiz['student_id'];
            if (isset($rowIndex[$studentId])) {
                $quiz['has_retry'] = (int) $quiz['attempts_in_range'] > 1;
                $quiz['has_failed'] = (int) $quiz['recent_rank'] === 1 && $quiz['status'] !== 'passed';
                $quiz['score'] = (float) $quiz['score'];
                $quiz['total_score'] = (float) $quiz['total_score'];
                $quiz['attempt_number'] = (int) $quiz['attempt_number'];
                unset($quiz['student_id'], $quiz['attempts_in_range'], $quiz['recent_rank']);
                $rowIndex[$studentId]['quizzes'][] = $quiz;
            }
        }
        $rows = array_values($rowIndex);
        foreach ($rows as &$row) {
            $row['has_retry'] = count(array_filter($row['quizzes'], static fn(array $quiz): bool => $quiz['has_retry'])) > 0;
            $row['has_failed'] = count(array_filter($row['quizzes'], static fn(array $quiz): bool => $quiz['has_failed'])) > 0;
        }
        unset($row);
    }

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
