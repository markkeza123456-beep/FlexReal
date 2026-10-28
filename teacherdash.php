<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php');
    exit;
}
$profileRole = strtolower((string) ($_SESSION['role'] ?? ''));
$profileContactReadonly = $profileRole === 'staff' ? '' : 'readonly';

require_once __DIR__ . '/config/db.php';

$conn->exec('ALTER TABLE public.questions ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ');
$conn->exec('ALTER TABLE public.lessons ADD COLUMN IF NOT EXISTS updated_by VARCHAR(50)');
$conn->exec('ALTER TABLE public.lesson_resources ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ');
$conn->exec('ALTER TABLE public.lesson_resources ADD COLUMN IF NOT EXISTS updated_by VARCHAR(50)');
$conn->exec('CREATE OR REPLACE FUNCTION public.set_content_updated_at() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN NEW.updated_at = clock_timestamp(); RETURN NEW; END; $$');
foreach ([['lessons', 'lessons_set_updated_at'], ['questions', 'questions_set_updated_at'], ['lesson_resources', 'lesson_resources_set_updated_at']] as [$table, $trigger]) {
    $conn->exec("DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_trigger WHERE tgrelid = 'public.{$table}'::regclass AND tgname = '{$trigger}') THEN CREATE TRIGGER {$trigger} BEFORE UPDATE ON public.{$table} FOR EACH ROW EXECUTE FUNCTION public.set_content_updated_at(); END IF; END $$");
}

function h(?string $value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function normalizeLessonSlots(array $lessons): array {
    $normalized = [];
    foreach (array_values($lessons) as $lessonRow) {
        $lessonRow['is_placeholder'] = false;
        $normalized[] = $lessonRow;
    }

    return $normalized;
}

function formatTeacherEditDate(?string $value): string {
    if (!$value) return 'ยังไม่มีข้อมูลวันที่';
    try {
        $date = (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('Asia/Bangkok'));
        return $date->format('d/m/') . ((int) $date->format('Y') + 543) . $date->format(' H:i');
    } catch (Throwable $error) {
        return 'ยังไม่มีข้อมูลวันที่';
    }
}

$teacherId = (string) $_SESSION['user_id'];
$requestedSubjectId = trim((string) ($_GET['subject_id'] ?? ''));

$canAccessTransferApproval = false;
try {
    $transferPermissionStmt = $conn->prepare(
        'SELECT EXISTS (
            SELECT 1 FROM public.transfer_permissions p
            WHERE p.instructor_id = :teacher_id AND p.is_active = TRUE
        )'
    );
    $transferPermissionStmt->execute([':teacher_id' => $teacherId]);
    $canAccessTransferApproval = (bool) $transferPermissionStmt->fetchColumn();
} catch (Throwable $e) {
    error_log('Teacher transfer permission lookup failed: ' . $e->getMessage());
}


$avatar_url = '';
try {
    $stmtAv = $conn->prepare("SELECT avatar_url FROM public.teachers WHERE user_id = :uid");
    $stmtAv->execute(['uid' => $teacherId]);
    $rowAv = $stmtAv->fetch(PDO::FETCH_ASSOC);
    $avatar_url = $rowAv['avatar_url'] ?? '';
} catch (Exception $e) { $avatar_url = ''; }


$teacherStmt = $conn->prepare("SELECT t.user_id, t.full_name, t.email, t.phone,
    COALESCE(a.address_line, '') AS address_line, COALESCE(a.subdistrict, '') AS subdistrict,
    COALESCE(a.district, '') AS district, COALESCE(a.province, '') AS province, COALESCE(a.postal_code, '') AS postal_code
    FROM public.teachers t LEFT JOIN LATERAL (SELECT address_line, subdistrict, district, province, postal_code FROM public.user_addresses WHERE user_id = t.user_id ORDER BY is_primary DESC, address_id ASC LIMIT 1) a ON TRUE
    WHERE t.user_id = :teacher_id LIMIT 1");
$teacherStmt->execute([':teacher_id' => $teacherId]);
$teacherRow = $teacherStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$teacherName = trim((string) ($teacherRow['full_name'] ?? $_SESSION['name'] ?? $teacherId));
if ($teacherName === '') $teacherName = $teacherId;
$teacherNameParts = preg_split('/\s+/u', $teacherName, 2) ?: [];
$teacherProfileFirstName = $teacherNameParts[0] ?? '';
$teacherProfileLastName = $teacherNameParts[1] ?? '';


$subjectStmt = $conn->prepare("
    SELECT c.course_id AS subjects_id, c.name AS subjects_name, c.description AS subjects_description,
        (SELECT COUNT(*) FROM public.lessons l WHERE l.course_id = c.course_id) AS lesson_count,
        (SELECT COUNT(DISTINCT sc.student_id) FROM public.student_courses sc WHERE sc.course_id = c.course_id AND sc.status = 'active') AS student_count
    FROM public.courses c
    INNER JOIN public.course_teachers ct ON ct.course_id = c.course_id
    WHERE ct.teacher_id = :teacher_id
    ORDER BY c.name ASC
");
$subjectStmt->execute([':teacher_id' => $teacherId]);
$subjectRows = $subjectStmt->fetchAll(PDO::FETCH_ASSOC);

$subjects = [];
$subjectIds = [];
$subjectNames = [];
$totalLessonCount = 0;

foreach ($subjectRows as $row) {
    $subjectId = (string) $row['subjects_id'];
    $subjectName = trim((string) $row['subjects_name']);
    $lessonCount = (int) $row['lesson_count'];
    $studentCount = (int) $row['student_count'];

    $subjects[] = [
        'id' => $subjectId,
        'title' => $subjectName !== '' ? $subjectName : $subjectId,
        'subject' => trim((string) $row['subjects_description']) ?: 'ยังไม่มีคำอธิบายรายวิชา',
        'students' => $studentCount,
        'progress' => 0,
        'status' => $lessonCount > 0 ? 'active' : 'draft',
        'lesson_count' => $lessonCount,
        'avg_score' => 0,
    ];

    if ($subjectId) $subjectIds[] = $subjectId;
    if ($subjectName) $subjectNames[] = $subjectName;
    $totalLessonCount += $lessonCount;
}

$subjectMap = [];
foreach ($subjects as $subjectRow) {
    $subjectMap[(string) $subjectRow['id']] = $subjectRow;
}

$selectedSubjectId = $requestedSubjectId !== '' && isset($subjectMap[$requestedSubjectId])
    ? $requestedSubjectId
    : (!empty($subjects) ? (string) $subjects[0]['id'] : '');
$selectedSubject = $selectedSubjectId !== '' ? ($subjectMap[$selectedSubjectId] ?? null) : null;


$subLessonsBySubject = [];
if (!empty($subjectIds)) {
    $lessonStmt = $conn->prepare('
        SELECT l.lesson_id AS lessons_id, l.title AS lessons_name, l.study_hours, l.quiz_duration_minutes, l.course_id AS subjects_id,
            COALESCE(l.updated_at, l.created_at) AS last_modified_at,
            COALESCE(NULLIF(TRIM(lesson_teacher_editor.full_name), \'\'), NULLIF(TRIM(CONCAT_WS(\' \', lesson_staff_editor.first_name, lesson_staff_editor.last_name)), \'\'), lesson_editor_user.user_id, l.updated_by, l.created_by, \'ยังไม่มีข้อมูล\') AS lesson_editor_name,
            video_edit.last_modified_at AS video_last_modified_at,
            COALESCE(video_edit.editor_name, \'ยังไม่มีข้อมูล\') AS video_editor_name
        FROM public.lessons l
        INNER JOIN public.course_teachers ct ON ct.course_id = l.course_id
        LEFT JOIN public.teachers lesson_teacher_editor ON lesson_teacher_editor.user_id = COALESCE(l.updated_by, l.created_by)
        LEFT JOIN public.staff lesson_staff_editor ON lesson_staff_editor.user_id = COALESCE(l.updated_by, l.created_by)
        LEFT JOIN public.users lesson_editor_user ON lesson_editor_user.user_id = COALESCE(l.updated_by, l.created_by)
        LEFT JOIN LATERAL (
            SELECT COALESCE(r.updated_at, r.created_at) AS last_modified_at,
                   COALESCE(NULLIF(TRIM(video_teacher_editor.full_name), \'\'), NULLIF(TRIM(CONCAT_WS(\' \', video_staff_editor.first_name, video_staff_editor.last_name)), \'\'), video_editor_user.user_id, r.updated_by, \'ยังไม่มีข้อมูล\') AS editor_name
            FROM public.lesson_resources r
            LEFT JOIN public.teachers video_teacher_editor ON video_teacher_editor.user_id = r.updated_by
            LEFT JOIN public.staff video_staff_editor ON video_staff_editor.user_id = r.updated_by
            LEFT JOIN public.users video_editor_user ON video_editor_user.user_id = r.updated_by
            WHERE r.lesson_id = l.lesson_id AND r.resource_type = \'video\'
            ORDER BY COALESCE(r.updated_at, r.created_at) DESC, r.resource_id DESC
            LIMIT 1
        ) video_edit ON TRUE
        WHERE ct.teacher_id = :teacher_id
        ORDER BY l.course_id ASC, l.position ASC
    ');
    $lessonStmt->execute([':teacher_id' => $teacherId]);
    foreach ($lessonStmt->fetchAll(PDO::FETCH_ASSOC) as $lessonRow) {
        $subLessonsBySubject[$lessonRow['subjects_id']][] = [
            'id' => $lessonRow['lessons_id'],
            'title' => $lessonRow['lessons_name'],
            'duration' => $lessonRow['study_hours'] > 0 ? $lessonRow['study_hours'] . ' ชั่วโมง' : 'ไม่ระบุเวลา',
            'quiz_duration_minutes' => max(1, (int) ($lessonRow['quiz_duration_minutes'] ?? 60)),
            'last_modified_at' => $lessonRow['last_modified_at'],
            'lesson_editor_name' => $lessonRow['lesson_editor_name'],
            'video_last_modified_at' => $lessonRow['video_last_modified_at'],
            'video_editor_name' => $lessonRow['video_editor_name'],
            'status' => 'active',
        ];
    }
}

foreach ($subjectIds as $subjectId) {
    $subLessonsBySubject[$subjectId] = normalizeLessonSlots($subLessonsBySubject[$subjectId] ?? []);
}


$studentStmt = $conn->prepare("
    SELECT st.user_id AS student_id, st.full_name AS student_name, st.student_level AS class_name,
           STRING_AGG(DISTINCT c.course_id::text, ',') AS subject_ids,
           COALESCE(AVG(qa.score) FILTER (WHERE qa.attempt_id IS NOT NULL), 0) AS real_score,
           COALESCE(SUM(qa.score) FILTER (WHERE qa.attempt_id IS NOT NULL), 0) AS raw_score_sum,
           COUNT(DISTINCT l.lesson_id) FILTER (WHERE qa.attempt_id IS NOT NULL) AS attempted_lessons,
           COUNT(DISTINCT l.lesson_id) AS total_lessons,
           COUNT(DISTINCT l.lesson_id) FILTER (WHERE
               qa.attempt_id IS NOT NULL
               OR qa.score IS NOT NULL
               OR COALESCE(lp.is_transferred, FALSE)
               OR UPPER(COALESCE(lp.grade, '')) = 'TR'
               OR EXISTS (
                   SELECT 1
                   FROM public.transcript_request_lessons trl
                   INNER JOIN public.transcript_requests tr ON tr.request_id = trl.request_id
                   WHERE trl.lesson_id = l.lesson_id
                     AND trl.is_approved = TRUE
                     AND tr.student_id = st.user_id
                     AND tr.status = 'approved'
               )
           ) AS completed_lessons,
           STRING_AGG(DISTINCT l.title, '||') FILTER (WHERE
               qa.attempt_id IS NOT NULL
               OR qa.score IS NOT NULL
               OR COALESCE(lp.is_transferred, FALSE)
               OR UPPER(COALESCE(lp.grade, '')) = 'TR'
               OR EXISTS (
                   SELECT 1
                   FROM public.transcript_request_lessons trl
                   INNER JOIN public.transcript_requests tr ON tr.request_id = trl.request_id
                   WHERE trl.lesson_id = l.lesson_id
                     AND trl.is_approved = TRUE
                     AND tr.student_id = st.user_id
                     AND tr.status = 'approved'
               )
           ) AS completed_lesson_names
    FROM public.students st
    INNER JOIN public.student_courses sc ON sc.student_id = st.user_id AND sc.status = 'active'
    INNER JOIN public.courses c ON c.course_id = sc.course_id
    INNER JOIN public.course_teachers ct ON ct.course_id = c.course_id AND ct.teacher_id = :teacher_id
    LEFT JOIN public.lessons l ON l.course_id = c.course_id
    LEFT JOIN public.lesson_progress_summary lp ON lp.student_id = st.user_id AND lp.lesson_id = l.lesson_id
    LEFT JOIN LATERAL (SELECT attempt_id, score, total_score, status FROM public.quiz_attempts a WHERE a.student_id = st.user_id AND a.lesson_id = l.lesson_id ORDER BY a.submitted_at DESC LIMIT 1) qa ON true
    WHERE c.course_id = :selected_subject_id
    GROUP BY st.user_id, st.full_name, st.student_level
    ORDER BY st.full_name ASC
");
$studentStmt->execute([':teacher_id' => $teacherId, ':selected_subject_id' => $selectedSubjectId]);

// Load each selected-course student's latest result per lesson.
$lessonScoreStmt = $conn->prepare("
    SELECT st.user_id AS student_id, c.course_id, l.lesson_id, l.position, l.title AS lesson_title,
           qa.score, qa.total_score, qa.attempt_id,
           CASE WHEN COALESCE(lp.is_transferred, FALSE) OR UPPER(COALESCE(lp.grade, '')) = 'TR'
                     OR EXISTS (
                         SELECT 1 FROM public.transcript_request_lessons trl
                         INNER JOIN public.transcript_requests tr ON tr.request_id = trl.request_id
                         WHERE trl.lesson_id = l.lesson_id AND trl.is_approved = TRUE
                           AND tr.student_id = st.user_id AND tr.status = 'approved'
                     )
                THEN TRUE ELSE FALSE END AS is_transferred
    FROM public.students st
    INNER JOIN public.student_courses sc ON sc.student_id = st.user_id AND sc.status = 'active'
    INNER JOIN public.courses c ON c.course_id = sc.course_id
    INNER JOIN public.course_teachers ct ON ct.course_id = c.course_id AND ct.teacher_id = :teacher_id
    INNER JOIN public.lessons l ON l.course_id = c.course_id
    LEFT JOIN public.lesson_progress_summary lp ON lp.student_id = st.user_id AND lp.lesson_id = l.lesson_id
    LEFT JOIN LATERAL (
        SELECT attempt_id, score, total_score
        FROM public.quiz_attempts a
        WHERE a.student_id = st.user_id AND a.lesson_id = l.lesson_id
        ORDER BY a.submitted_at DESC, a.attempt_number DESC
        LIMIT 1
    ) qa ON TRUE
    WHERE c.course_id = :selected_subject_id
    ORDER BY st.user_id, c.name, l.position, l.lesson_id
");
$lessonScoreStmt->execute([':teacher_id' => $teacherId, ':selected_subject_id' => $selectedSubjectId]);
$lessonScoresByStudent = [];
foreach ($lessonScoreStmt->fetchAll(PDO::FETCH_ASSOC) as $lessonScore) {
    $lessonScoresByStudent[(string) $lessonScore['student_id']][] = [
        'course_id' => (string) $lessonScore['course_id'],
        'lesson_id' => (string) $lessonScore['lesson_id'],
        'title' => (string) $lessonScore['lesson_title'],
        'position' => (int) $lessonScore['position'],
        'score' => $lessonScore['score'],
        'total_score' => $lessonScore['total_score'],
        'attempted' => $lessonScore['attempt_id'] !== null,
        'transferred' => filter_var($lessonScore['is_transferred'], FILTER_VALIDATE_BOOLEAN),
    ];
}

$students = [];
$sumRawScores = 0;
$studentsWithAttempts = 0;
foreach ($studentStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $realScore = (int) round((float) $row['real_score']);
    if ((int) $row['attempted_lessons'] > 0) {
        $sumRawScores += (float) $row['raw_score_sum'];
        $studentsWithAttempts++;
    }

    $totalLessons = (int) $row['total_lessons'];
    $completedLessons = min((int) $row['completed_lessons'], $totalLessons);
    $progressPct = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100) : 0;

    $completedNames = $row['completed_lesson_names'] ? explode('||', $row['completed_lesson_names']) : [];

    $students[] = [
        'id' => $row['student_id'],
        'name' => $row['student_name'] ?: 'ไม่ระบุชื่อ',
        'class' => $row['class_name'] ?: '-',
        'score' => $realScore,
        'subject_ids' => $row['subject_ids'],
        'progress_pct' => $progressPct,
        'completed_lessons' => $completedLessons,
        'total_lessons' => $totalLessons,
        'completed_names_json' => htmlspecialchars(json_encode($completedNames), ENT_QUOTES, 'UTF-8'),
        'lesson_scores' => $lessonScoresByStudent[(string) $row['student_id']] ?? []
    ];
}

$overallAvgScore = $studentsWithAttempts > 0 ? (int) round($sumRawScores / $studentsWithAttempts) : 0;


$quizzes = [];
$defaultSubjectId = $selectedSubjectId;
if ($defaultSubjectId) {
    $quizStmt = $conn->prepare("
        SELECT q.question_id AS questions_id, q.question_text AS questions_text,
               COALESCE(q.updated_at, q.created_at) AS last_modified_at,
               COALESCE(q.options->>0, '') AS choice_a, COALESCE(q.options->>1, '') AS choice_b,
               COALESCE(q.options->>2, '') AS choice_c, COALESCE(q.options->>3, '') AS choice_d,
               COALESCE(q.correct_choice, '-') AS correct_answer, q.max_score AS question_max_score,
               COALESCE(q.teacher_explanation, '') AS teacher_explanation,
               q.lesson_id AS lessons_id, l.title AS lessons_name
        FROM public.questions q
        INNER JOIN public.lessons l ON q.lesson_id = l.lesson_id
        WHERE l.course_id = :subject_id
        ORDER BY q.question_id ASC
    ");
    $quizStmt->execute([':subject_id' => $defaultSubjectId]);
    $quizzes = $quizStmt->fetchAll(PDO::FETCH_ASSOC);
}

$teacherSubjectText = !empty($subjectNames)
    ? implode(', ', array_slice($subjectNames, 0, 3)) . (count($subjectNames) > 3 ? ' +' . (count($subjectNames) - 3) : '')
    : 'ยังไม่มีบทเรียนที่ดูแล';

$firstNameParts = preg_split('/\s+/', $teacherName);
$teacherFirstName = $firstNameParts[0] ?? $teacherName;
$teacherAvatar = mb_substr($teacherName, 0, 1, 'UTF-8') ?: 'T';

$teacher = [
    'name'       => $teacherName,
    'subject'    => $teacherSubjectText,
    'avatar'     => $teacherAvatar,
    'avatar_url' => $avatar_url,
    'id'         => $teacherId,
];

$stats = [
    'students' => count($students),
    'lessons' => $selectedSubject ? (int) $selectedSubject['lesson_count'] : 0,
    'subjects' => $selectedSubject ? 1 : 0,
    'avg_score' => $overallAvgScore,
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แดชบอร์ดอาจารย์ - Flexible Learning Hub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&family=Kanit:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/teacherdash.css?v=20261028-learning-pagination">
    <style>
        .action-icon-btn {
            background: none; border: none; cursor: pointer; padding: 6px;
            border-radius: 6px; font-size: 14px; transition: background 0.2s;
        }
        .action-icon-btn:hover { background: rgba(255,255,255,0.1); }
        .btn-open-add-quiz {
            background: rgba(92, 107, 192, 0.1); color: var(--text-primary); border: 1px solid rgba(92, 107, 192, 0.3);
            padding: 6px 12px; border-radius: 6px; font-size: 12px; cursor: pointer; transition: all 0.2s; font-family: 'Kanit', sans-serif;
            display: flex; align-items: center; gap: 4px;
        }
        .btn-open-add-quiz:hover { background: rgba(92, 107, 192, 0.2); transform: translateY(-1px); }

        .choice-row {
            display: flex; align-items: center; gap: 10px; margin-bottom: 8px;
        }
        input[type="radio"] {
            accent-color: var(--text-primary);
            width: 18px; height: 18px; cursor: pointer;
        }
        .choice-label { font-weight: 600; color: var(--text-dim); font-size: 14px; width: 16px; text-align: center; }
        @media (max-width: 760px) { .course-content-editor { grid-template-columns: 1fr !important; } }
    </style>
    <link rel="stylesheet" href="assets/css/theme.css?v=20261022-mobile-layout-v2">
    <link rel="stylesheet" href="assets/css/responsive.css?v=20261022-mobile-logout-bottom">
    <link rel="stylesheet" href="assets/css/profile-layout.css?v=20261021-centered-profile-layout">
    <link rel="stylesheet" href="assets/css/learning-progress-report.css?v=20261026-blue-buttons">
</head>
<body>
<aside class="sidebar">
    <a class="sidebar-logo sidebar-brand-link" href="index.html" title="กลับหน้าหลักและรายวิชา">
        <img class="sidebar-brand-logo" src="images/flexible-learning-hub-logo-transparent.png" alt="Flexible Learning Hub">
    </a>

    <script>
      window.activateTeacherView = function (viewName) {
        document.querySelectorAll('.page-view').forEach(function (view) { view.style.display = 'none'; });
        var target = document.getElementById('view-' + viewName);
        if (target) target.style.display = 'flex';
        document.querySelectorAll('.nav-item').forEach(function (item) { item.classList.remove('active'); });
        var activeItem = document.querySelector('.nav-item[data-view="' + viewName + '"]');
        if (activeItem) activeItem.classList.add('active');
        var url = new URL(window.location.href);
        if (viewName === 'dashboard') url.searchParams.delete('view');
        else url.searchParams.set('view', viewName);
        window.history.replaceState({}, '', url);
        if (viewName === 'reports') {
          var reportFrame = document.getElementById('teacherReportFrame');
          if (reportFrame && reportFrame.contentWindow) {
            requestAnimationFrame(function () {
              reportFrame.style.height = (reportFrame.contentWindow.document.documentElement.scrollHeight + 20) + 'px';
            });
          }
        }
        if (viewName === 'graduation') window.loadTeacherGraduationRequests?.();
        return false;
      };
    </script>
    <nav class="sidebar-nav">
        <a href="#" onclick="return window.activateTeacherView('dashboard')" class="nav-item active" data-view="dashboard"><span class="nav-icon"></span><span>แดชบอร์ด</span></a>
        <div class="sidebar-subject-filter">
            <label for="teacherSubjectSelect">เลือกรายวิชา</label>
            <select id="teacherSubjectSelect" aria-label="เลือกรายวิชา">
                <?php if ($subjects === []): ?>
                    <option value="">ยังไม่มีรายวิชา</option>
                <?php else: ?>
                    <?php foreach ($subjects as $subjectOption): ?>
                        <option value="<?= h($subjectOption['id']) ?>" <?= $subjectOption['id'] === $selectedSubjectId ? 'selected' : '' ?>><?= h($subjectOption['title']) ?></option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>
        <a href="#" onclick="return window.activateTeacherView('lessons')" class="nav-item" data-view="lessons"><span class="nav-icon"></span><span>บทเรียน</span></a>
        <a href="#" onclick="return window.activateTeacherView('reports')" class="nav-item" data-view="reports"><span class="nav-icon"></span><span>ออกรายงาน</span></a>
        <?php if ($canAccessTransferApproval): ?>
        <a href="review_transcript.php" class="nav-item"><span class="nav-icon"></span><span>ตรวจทรานสคริปต์</span></a>
        <a href="#" onclick="return window.activateTeacherView('graduation')" class="nav-item" data-view="graduation"><span class="nav-icon"></span><span>อนุมัติจบการศึกษา</span></a>
        <?php endif; ?>
    </nav>

    <a href="logout.php" class="sidebar-logout">
        <span></span><span>ออกจากระบบ</span>
    </a>

    <a href="#" onclick="return window.activateTeacherView('settings')" class="sidebar-profile nav-item" data-view="settings" style="text-decoration:none">
        <div class="profile-avatar" id="sidebarAvatarWrap" style="overflow:hidden;padding:0">
            <?php if (!empty($teacher['avatar_url'])): ?>
            <img src="<?= h($teacher['avatar_url']) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%" id="sidebarAvatarImg">
            <?php else: ?>
            <span id="sidebarAvatarInitial"><?= h($teacher['avatar']) ?></span>
            <?php endif; ?>
        </div>
        <div class="profile-info">
            <div class="profile-name"><?= h($teacher['name']) ?></div>
            <div class="profile-role">อาจารย์ • <?= h($teacher['subject']) ?></div>
        </div>
    </a>
</aside>

<main class="main">
    <div id="view-dashboard" class="page-view">
        <header class="topbar">
            <div class="topbar-left">
                <h1 class="page-title">แดชบอร์ด</h1>
                <span class="page-sub">ยินดีต้อนรับกลับมา, <?= h($teacherFirstName) ?> </span>
            </div>
        </header>

        <section class="stats-grid">
            <div class="stat-card" style="--accent:#5C6BC0">
                <div class="stat-icon"></div>
                <div class="stat-value"><?= $stats['students'] ?></div>
                <div class="stat-label">นักเรียนที่ดูแล</div>
                <div class="stat-trend">เฉพาะที่ผูกกับวิชาของอาจารย์</div>
            </div>
            <div class="stat-card" style="--accent:#5C6BC0">
                <div class="stat-icon"></div>
                <div class="stat-value"><?= $stats['lessons'] ?></div>
                <div class="stat-label">บทเรียนทั้งหมด</div>
                <div class="stat-trend">รวมบทเรียนในรายวิชาที่สอน</div>
            </div>
            <div class="stat-card" style="--accent:#10b981">
                <div class="stat-icon"></div>
                <div class="stat-value"><?= $stats['subjects'] ?></div>
                <div class="stat-label">รายวิชาที่ดูแล</div>
                <div class="stat-trend">แสดงเฉพาะของอาจารย์ท่านนี้</div>
            </div>
            <div class="stat-card" style="--accent:#a855f7">
                <div class="stat-icon"></div>
                <div class="stat-value"><?= number_format($stats['avg_score'], 0) ?></div>
                <div class="stat-label">คะแนนเฉลี่ยของข้อสอบ</div>
                <div class="stat-trend">คะแนนดิบเฉลี่ยของนักเรียนที่ทำข้อสอบ</div>
            </div>
        </section>

        <section class="card students-card learning-report-card" aria-labelledby="learningReportTitle">
            <div class="card-header" style="flex-wrap:wrap;gap:12px">
                <div><h2 class="card-title" id="learningReportTitle">รายงานความคืบหน้าการเรียนรู้</h2><p id="learningProgressSummary" class="page-sub" aria-live="polite">กำลังโหลดข้อมูล...</p></div>
                <div class="learning-range-controls" role="group" aria-label="เลือกช่วงเวลารายงาน">
                    <button type="button" class="learning-range-btn is-active" data-learning-days="1" aria-pressed="true">วันนี้</button>
                    <button type="button" class="learning-range-btn" data-learning-days="3" aria-pressed="false">ย้อนหลัง 3 วัน</button>
                    <button type="button" class="learning-range-btn" data-learning-days="7" aria-pressed="false">ย้อนหลัง 7 วัน</button>
                </div>
            </div>
            <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;margin:0 0 12px;flex-wrap:wrap">
                <input type="search" id="learningProgressSearch" class="search-input" placeholder="ค้นหาชื่อนักเรียน..." aria-label="ค้นหาชื่อนักเรียนในรายงาน">
                <span id="learningProgressPageInfo" class="page-sub"></span>
            </div>
            <div class="table-wrap"><table class="lessons-table learning-timeline-table" style="min-width:760px"><thead><tr><th>นักเรียน — เวลา — กิจกรรม</th></tr></thead><tbody id="learningProgressBody"><tr><td style="text-align:center">กำลังโหลด...</td></tr></tbody></table></div>
            <nav class="learning-pagination" aria-label="เปลี่ยนหน้ารายงาน">
                <button type="button" class="learning-pagination-btn" id="learningProgressPrev" aria-label="ไปหน้าก่อนหน้า"><span aria-hidden="true">‹</span> ก่อนหน้า</button>
                <button type="button" class="learning-pagination-btn is-next" id="learningProgressNext" aria-label="ไปหน้าถัดไป">ถัดไป <span aria-hidden="true">›</span></button>
            </nav>
        </section>

        <section class="card students-card">
            <div class="card-header">
                <h2 class="card-title">นักเรียนในการดูแล</h2>
                <input class="search-input" type="text" id="studentSearch" placeholder=" ค้นหานักเรียน...">
            </div>
            <div class="table-wrap">
                <table class="lessons-table" id="studentTable">
                    <thead>
                        <tr>
                            <th> </th>
                            <th>ชื่อ-นามสกุล</th>
                            <th>ระดับชั้น</th>
                            <th style="width: 25%">ความคืบหน้า</th>
                            <th>คะแนนแยกตามบท</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($students === []): ?>
                        <tr class="lesson-row">
                            <td colspan="5" style="text-align:center;color:var(--text-muted)">ยังไม่มีนักเรียนในการดูแล</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($students as $index => $student): ?>
                        <tr class="lesson-row dashboard-student-row">
                            <td class="mono"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></td>
                            <td>
                                <a href="#" class="student-progress-link"
                                   data-id="<?= h($student['id']) ?>"
                                   data-name="<?= h($student['name']) ?>"
                                   data-class="<?= h($student['class']) ?>"
                                   data-score="<?= h($student['score']) ?>"
                                   data-completed="<?= h($student['completed_lessons']) ?>"
                                   data-total="<?= h($student['total_lessons']) ?>"
                                   data-pct="<?= h($student['progress_pct']) ?>"
                                   data-json="<?= $student['completed_names_json'] ?>"
                                   onclick="openStudentModal(this); return false;"
                                   style="color:#60a5fa; font-weight:500; text-decoration:none; display:flex; align-items:center; gap:6px;" title="คลิกเพื่อดูข้อมูลนักเรียน">
                                    <?= h($student['name']) ?> <span style="font-size:12px; color:var(--text-dim);">ℹ️ </span>
                                </a>
                            </td>
                            <td><?= h($student['class']) ?></td>
                            <td>
                                <div class="progress-wrap">
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="--pct:<?= $student['progress_pct'] ?>%"></div>
                                    </div>
                                    <span class="progress-num"><?= $student['progress_pct'] ?>%</span>
                                </div>
                                <div style="font-size:10.5px; color:var(--text-muted); margin-top:4px;">
                                    สำเร็จ <?= $student['completed_lessons'] ?> / <?= $student['total_lessons'] ?> บทเรียน
                                </div>
                            </td>
                            <td>
                                <div style="display:flex; flex-wrap:wrap; gap:6px;">
                                    <?php foreach ($student['lesson_scores'] as $lessonScore): ?>
                                        <?php if ($lessonScore['transferred']): ?>
                                            <span title="<?= h($lessonScore['title']) ?>" style="background:#9FA8DA;color:#303F9F;padding:4px 8px;border-radius:6px;font-size:.85rem;">บท <?= (int) $lessonScore['position'] ?> : TR</span>
                                        <?php elseif ($lessonScore['attempted']): ?>
                                            <span title="<?= h($lessonScore['title']) ?>" style="background:#e6f4ea;color:#1e8e3e;padding:4px 8px;border-radius:6px;font-size:.85rem;">บท <?= (int) $lessonScore['position'] ?> : <?= h((string) $lessonScore['score']) ?>/<?= h((string) $lessonScore['total_score']) ?></span>
                                        <?php else: ?>
                                            <span title="<?= h($lessonScore['title']) ?>" style="background:#f1f3f4;color:#5f6368;padding:4px 8px;border-radius:6px;font-size:.85rem;">บท <?= (int) $lessonScore['position'] ?> : ยังไม่สอบ</span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr id="studentSearchEmpty" style="display:none">
                            <td colspan="5" style="text-align:center;color:var(--text-muted);padding:24px">ไม่พบนักเรียนที่ค้นหา</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div id="view-lessons" class="page-view" style="display:none">
        <header class="topbar">
            <div class="topbar-left">
                <h1 class="page-title">บทเรียน</h1>
                <span class="page-sub">จัดการเนื้อหาและแบบทดสอบในวิชาของคุณ</span>
            </div>
        </header>

        <?php $defaultSubject = $selectedSubject; ?>

        <div id="lessonDetailSection" style="display:flex;flex-direction:column;gap:20px">
            <div class="card" id="lessonDetailHeader" style="padding: 24px;">
                <?php if ($defaultSubject): ?>
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px">
                        <div>
                            <div style="font-size:24px;font-weight:700;color:var(--text);margin-bottom:6px">▶ <?= h($defaultSubject['title']) ?></div>
                            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;font-size:13px;color:var(--text-dim)">
                                <span> <?= h($defaultSubject['subject']) ?></span>
                                <span> <?= (int)$defaultSubject['students'] ?> คน</span>
                                <span class="badge badge-<?= h($defaultSubject['status']) ?>"><?= $defaultSubject['status'] === 'active' ? 'เผยแพร่' : 'ฉบับร่าง' ?></span>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="color:var(--text-muted);">ยังไม่มีรายวิชาที่คุณดูแล</div>
                <?php endif; ?>
            </div>

            <div class="lesson-tabs" style="display:flex;gap:0;border:1px solid var(--border);border-radius:var(--radius-sm);overflow:hidden;background:var(--bg2)">
                <button class="lesson-tab-btn active" data-tab="overview" style="flex:1;padding:14px;background:var(--orange-dim);border:none;border-right:1px solid var(--border);color:var(--text);font-family:'Kanit',sans-serif;font-size:14px;font-weight:600;cursor:pointer;transition:all .15s;"> ภาพรวมบทเรียน</button>
                <button class="lesson-tab-btn" data-tab="students" style="flex:1;padding:14px;background:none;border:none;border-right:1px solid var(--border);color:var(--text);font-family:'Kanit',sans-serif;font-size:14px;font-weight:500;cursor:pointer;transition:all .15s"> รายชื่อนักเรียน</button>
                <button class="lesson-tab-btn" data-tab="quiz" style="flex:1;padding:14px;background:none;border:none;color:var(--text);font-family:'Kanit',sans-serif;font-size:14px;font-weight:500;cursor:pointer;transition:all .15s"> คลังแบบทดสอบ</button>
                <button class="lesson-tab-btn" data-tab="essay" style="flex:1;padding:14px;background:none;border:none;color:var(--text);font-family:'Kanit',sans-serif;font-size:14px;font-weight:500;cursor:pointer;transition:all .15s">️ ตรวจข้อเขียน</button>
            </div>

            <div class="lesson-tab-content card" id="lessonTab-overview">
                <div id="lessonOverviewBody">
                    <?php if ($defaultSubject): ?>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px; margin-bottom: 30px;">
                            <div>
                                <div style="font-size:11.5px;color:var(--text-muted);margin-bottom:6px;font-weight:500">ข้อมูลรายวิชา</div>
                                <div style="font-size:13.5px;color:var(--text);line-height:1.7">
                                    <div><span style="color:var(--text-dim)">ชื่อ:</span> <?= h($defaultSubject['title']) ?></div>
                                    <div><span style="color:var(--text-dim)">คำอธิบาย:</span> <?= h($defaultSubject['subject']) ?></div>
                                </div>
                            </div>
                            <div class="teacher-subject-stats">
                                <div class="teacher-subject-stats-title">สถิติเบื้องต้น</div>
                                <span class="teacher-subject-stats-label">นักเรียนลงทะเบียน</span>
                                <span class="teacher-subject-stats-count mono"><?= (int)$defaultSubject['students'] ?> คน</span>
                            </div>
                        </div>

                        <div style="border-top:1px solid var(--border);padding-top:24px;">
                            <div class="lesson-list-heading">
                                <h3 style="font-size:16px;color:var(--text);font-weight:600;">รายการบทเรียนย่อย</h3>
                                <button type="button" class="btn-add-lesson" id="openContentManagerBtn" style="font-size:13px;padding:8px 14px" <?= $defaultSubjectId === '' ? 'disabled' : '' ?>>＋ เพิ่มบทเรียน</button>
                            </div>
                            <div id="teacherLessonList" style="display:flex;flex-direction:column;gap:12px;">
                                <?php
                                if ($defaultSubjectId && !empty($subLessonsBySubject[$defaultSubjectId])):
                                    foreach ($subLessonsBySubject[$defaultSubjectId] as $i => $subLesson):
                                        $isPlaceholderLesson = !empty($subLesson['is_placeholder']);
                                ?>
                                <div class="teacher-lesson-row" data-lesson-id="<?= h($subLesson['id']) ?>" style="background:<?= $isPlaceholderLesson ? 'rgba(255,255,255,0.65)' : 'var(--bg3)' ?>;padding:16px 20px;border-radius:var(--radius-sm);border:1px solid var(--border);display:flex;align-items:center;justify-content:space-between; flex-wrap:wrap; gap:12px;<?= $isPlaceholderLesson ? 'opacity:0.85;' : '' ?>">
                                    <div style="display:flex;align-items:center;gap:16px;">
                                        <div class="teacher-lesson-number" style="width:36px;height:36px;background:var(--bg2);border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--text-primary);font-weight:600;font-size:14px;border:1px solid rgba(92, 107, 192,0.2);">
                                            <?= $i+1 ?>
                                        </div>
                                        <div>
                                            <div style="color:var(--text);font-size:15px;font-weight:500;"><?= h($subLesson['title']) ?></div>
                                            <div style="color:var(--text-dim);font-size:12px;margin-top:2px;">⏳ <?= h($subLesson['duration']) ?></div>
                                            <div style="color:var(--text-muted);font-size:11px;margin-top:3px;">บทเรียนแก้ไขล่าสุด <?= h(formatTeacherEditDate($subLesson['last_modified_at'] ?? null)) ?> · โดย <?= h($subLesson['lesson_editor_name'] ?? 'ยังไม่มีข้อมูล') ?></div>
                                            <div id="lessonVideoModified-<?= h($subLesson['id']) ?>" style="color:var(--text-muted);font-size:11px;margin-top:3px;">วิดีโอแก้ไขล่าสุด <?= h(formatTeacherEditDate($subLesson['video_last_modified_at'] ?? null)) ?> · โดย <?= h($subLesson['video_editor_name'] ?? 'ยังไม่มีข้อมูล') ?></div>
                                        </div>
                                    </div>
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <span class="badge badge-<?= h($subLesson['status']) ?>" style="margin-right:8px;"><?= $isPlaceholderLesson ? 'ยังไม่มีเนื้อหา' : 'เผยแพร่' ?></span>
                                        <?php if (!$isPlaceholderLesson): ?>
                                            <button class="btn-open-add-quiz" data-id="<?= h($subLesson['id']) ?>" data-name="<?= h($subLesson['title']) ?>">
                                                <span style="font-size:16px;line-height:1;">+</span> เพิ่มแบบทดสอบ
                                            </button>

                                            <button class="action-icon-btn btn-edit-lsn" data-id="<?= h($subLesson['id']) ?>" data-name="<?= h($subLesson['title']) ?>" title="แก้ไขบทเรียน">แก้ไข</button>
                                            <button class="action-icon-btn btn-del-lsn" data-id="<?= h($subLesson['id']) ?>" title="ลบบทเรียน" style="color:#ef4444;">ลบ</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php
                                    endforeach;
                                else:
                                ?>
                                <div style="text-align:center;padding:40px;color:var(--text-muted);font-size:14px;background:var(--bg3);border-radius:var(--radius-sm);border:1px dashed var(--border);">
                                    <div style="font-size:32px;margin-bottom:10px;"></div>
                                    ยังไม่มีบทเรียนย่อย คลิกปุ่ม "+ เพิ่มบทเรียนย่อย" ด้านขวาบนเพื่อเริ่มต้น
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="modal-overlay content-manager-overlay" id="contentManagerModal">
                          <div class="modal content-manager-modal">
                            <div class="modal-header content-manager-header"><h3 class="modal-title">จัดการเนื้อหาวิชา</h3><button type="button" class="modal-close" id="closeContentManagerBtn">ปิด</button></div>
                            <div class="course-content-editor modal-body">
                            <section style="padding:20px;border:1px solid var(--border);border-radius:12px;background:var(--bg3);">
                                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:16px;"><div><p style="font-size:11px;color:var(--text-primary);font-weight:700;letter-spacing:.08em;margin:0 0 4px;">LESSON CONTENT</p><h3 style="font-size:17px;margin:0;" id="inlineLessonHeading">เพิ่มบทเรียนย่อย</h3></div><span id="inlineLessonCount" style="font-size:12px;color:var(--text-muted);"><?= count($subLessonsBySubject[$defaultSubjectId] ?? []) ?> บทเรียน</span></div>
                                <form id="inlineLessonForm" enctype="multipart/form-data">
                                    <div class="form-group"><label>ชื่อบทเรียน *</label><input type="text" class="form-input" id="inlineLessonName" required placeholder="เช่น 1.1 ตรรกศาสตร์เบื้องต้น"></div>
                                    <div class="form-group"><label>เอกสารประกอบ (PDF)</label><div id="inlineExistingDocuments" style="display:none;margin:0 0 8px;font-size:13px;color:var(--text-muted);"></div><input type="file" class="form-input" id="inlineLessonDocument" accept="application/pdf,.pdf"><small id="inlineLessonDocumentHint" style="color:var(--text-muted);">เลือกไฟล์ PDF ใหม่เมื่อต้องการเพิ่มเอกสาร</small></div>
                                    <button type="button" class="btn-save" id="saveInlineLessonBtn">บันทึกบทเรียน</button>
                                    <p class="save-feedback" id="inlineLessonSaveStatus" role="status" aria-live="polite"></p>
                                </form>
                            </section>

                            <section style="padding:20px;border:1px solid var(--border);border-radius:12px;background:var(--bg3);">
                                <div style="margin-bottom:16px;"><p style="font-size:11px;color:var(--text-primary);font-weight:700;letter-spacing:.08em;margin:0 0 4px;">VIDEO CONTENT</p><h3 style="font-size:17px;margin:0;">เพิ่มและจัดการวิดีโอ</h3></div>
                                <div id="inlineVideoList" style="margin-bottom:16px;color:var(--text-muted);font-size:13px;">กำลังโหลดวิดีโอ...</div>
                                <form id="inlineVideoForm" enctype="multipart/form-data">
                                    <input type="hidden" id="inlineVideoId">
                                    <input type="hidden" id="inlineVideoTitle" value="วิดีโอบทเรียน">
                                    <p style="font-size:13px;color:var(--text-muted);margin:-2px 0 14px;">วิดีโอนี้จะถูกผูกกับบทเรียนที่กรอกด้านบนโดยอัตโนมัติ</p>
                                    <div class="form-group"><label>ไฟล์วิดีโอ <span id="inlineVideoFileRequired">*</span></label><input type="file" class="form-input" id="inlineVideoFile" accept="video/mp4,.mp4" required><small id="inlineVideoFileHint" style="color:var(--text-muted);">เลือกไฟล์ MP4 เพื่อบันทึก</small></div>
                                    <button type="button" class="btn-save" id="saveInlineVideoBtn">บันทึกวิดีโอ</button>
                                    <p class="save-feedback" id="inlineVideoSaveStatus" role="status" aria-live="polite"></p>
                                </form>
                            </section>
                        </div>
                          </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="lesson-tab-content card" id="lessonTab-students" style="display:none">
                <div class="card-header">
                    <h3 class="card-title" style="font-size:14px">นักเรียนในวิชานี้</h3>
                    <input class="search-input" type="text" id="detailStudentSearch" placeholder=" ค้นหานักเรียน...">
                </div>
                <div class="table-wrap">
                    <table class="lessons-table" id="detailStudentTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>ชื่อ-นามสกุล</th>
                                <th>ระดับชั้น</th>
                                <th style="width: 25%">ความคืบหน้า</th>
                                <th>คะแนนแยกตามบท</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $index => $student): ?>
                            <tr class="lesson-row detail-student-row" data-subject-ids="<?= h($student['subject_ids']) ?>">
                                <td class="mono"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></td>
                                <td>
                                    <a href="#" class="student-progress-link"
                                       data-id="<?= h($student['id']) ?>"
                                       data-name="<?= h($student['name']) ?>"
                                       data-class="<?= h($student['class']) ?>"
                                       data-score="<?= h($student['score']) ?>"
                                       data-completed="<?= h($student['completed_lessons']) ?>"
                                       data-total="<?= h($student['total_lessons']) ?>"
                                       data-pct="<?= h($student['progress_pct']) ?>"
                                       data-json="<?= $student['completed_names_json'] ?>"
                                       onclick="openStudentModal(this); return false;"
                                       style="color:#60a5fa; font-weight:500; text-decoration:none; display:flex; align-items:center; gap:6px;" title="คลิกเพื่อดูข้อมูลนักเรียน">
                                        <?= h($student['name']) ?> <span style="font-size:12px; color:var(--text-dim);">ℹ️</span>
                                    </a>
                                </td>
                                <td><?= h($student['class']) ?></td>
                                <td>
                                    <div class="progress-wrap">
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="--pct:<?= $student['progress_pct'] ?>%"></div>
                                        </div>
                                        <span class="progress-num"><?= $student['progress_pct'] ?>%</span>
                                    </div>
                                    <div style="font-size:10.5px; color:var(--text-muted); margin-top:4px;">
                                        สำเร็จ <?= $student['completed_lessons'] ?> / <?= $student['total_lessons'] ?> บทเรียน
                                    </div>
                                </td>
                                <td>
                                    <div style="display:flex;flex-wrap:wrap;gap:6px;">
                                        <?php foreach ($student['lesson_scores'] as $lessonScore): ?>
                                            <?php if ($lessonScore['course_id'] !== $selectedSubjectId) continue; ?>
                                            <?php if ($lessonScore['transferred']): ?>
                                                <span title="<?= h($lessonScore['title']) ?>" style="background:#9FA8DA;color:#303F9F;padding:4px 8px;border-radius:6px;font-size:.85rem;">บท <?= (int) $lessonScore['position'] ?> : TR</span>
                                            <?php elseif ($lessonScore['attempted']): ?>
                                                <span title="<?= h($lessonScore['title']) ?>" style="background:#e6f4ea;color:#1e8e3e;padding:4px 8px;border-radius:6px;font-size:.85rem;">บท <?= (int) $lessonScore['position'] ?> : <?= h((string) $lessonScore['score']) ?>/<?= h((string) $lessonScore['total_score']) ?></span>
                                            <?php else: ?>
                                                <span title="<?= h($lessonScore['title']) ?>" style="background:#f1f3f4;color:#5f6368;padding:4px 8px;border-radius:6px;font-size:.85rem;">บท <?= (int) $lessonScore['position'] ?> : ยังไม่สอบ</span>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="lesson-tab-content card" id="lessonTab-quiz" style="display:none">
                <div class="card-header">
                    <h3 class="card-title" style="font-size:14px">คลังแบบทดสอบแยกตามบทเรียน</h3>
                </div>
                <div id="quizList" style="display:flex;flex-direction:column;gap:12px;">
                    <?php
                    $quizzesByLesson = [];
                    foreach ($quizzes as $quiz) $quizzesByLesson[(string) $quiz['lessons_id']][] = $quiz;
                    $lessonGroups = $subLessonsBySubject[$defaultSubjectId] ?? [];
                    ?>
                    <?php if (empty($lessonGroups)): ?>
                        <div id="quizEmpty" style="text-align:center;padding:40px;color:var(--text-muted);font-size:13px; border:1px dashed var(--border);border-radius:var(--radius-sm);">ยังไม่มีบทเรียนย่อยสำหรับเพิ่มข้อสอบ</div>
                    <?php else: foreach ($lessonGroups as $lessonIndex => $lessonGroup):
                        $lessonId = (string) ($lessonGroup['id'] ?? '');
                        $lessonQuizzes = $quizzesByLesson[$lessonId] ?? [];
                    ?>
                        <details class="quiz-lesson-group" <?= $lessonIndex === 0 ? 'open' : '' ?> style="background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius-sm);overflow:hidden;">
                            <summary style="cursor:pointer;padding:15px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px;color:var(--text);font-weight:600;">
                                <span>บทที่ <?= $lessonIndex + 1 ?>: <?= h($lessonGroup['title']) ?></span>
                                <span class="badge badge-draft"><?= count($lessonQuizzes) ?> ข้อ</span>
                            </summary>
                            <div style="padding:0 16px 16px;display:flex;flex-direction:column;gap:10px;">
                                <?php $quizDurationMinutes = max(1, (int) ($lessonGroup['quiz_duration_minutes'] ?? 60)); ?>
                                <div class="quiz-duration-panel">
                                    <strong class="quiz-duration-heading">กำหนดเวลาทำข้อสอบ</strong>
                                    <label class="quiz-duration-field">ชั่วโมง <input class="quiz-duration-hours" type="number" min="0" max="24" value="<?= intdiv($quizDurationMinutes, 60) ?>"></label>
                                    <label class="quiz-duration-field">นาที <input class="quiz-duration-minutes" type="number" min="0" max="59" value="<?= $quizDurationMinutes % 60 ?>"></label>
                                    <button type="button" class="btn-save-quiz-duration" data-lesson-id="<?= h($lessonId) ?>">บันทึกเวลา</button>
                                    <span class="quiz-duration-status" aria-live="polite" style="font-size:12px;color:var(--text-muted);"></span>
                                </div>
                                <button class="btn-open-add-quiz" data-id="<?= h($lessonId) ?>" data-name="<?= h($lessonGroup['title']) ?>" style="align-self:flex-start;"><span style="font-size:16px;line-height:1;">+</span> เพิ่มข้อสอบบทนี้</button>
                                <?php if (empty($lessonQuizzes)): ?>
                                    <div style="padding:18px;color:var(--text-muted);font-size:13px;border:1px dashed var(--border);border-radius:8px;">ยังไม่มีข้อสอบในบทนี้</div>
                                <?php else: foreach ($lessonQuizzes as $i => $q):
                                    $qType = $q['correct_answer'] === '-' ? 'essay' : (($q['choice_a'] === 'ถูก' || $q['choice_a'] === 'ถูก (True)') ? 'truefalse' : 'choice');
                                    $qLabel = $qType === 'essay' ? 'อัตนัย (ข้อเขียน)' : ($qType === 'truefalse' ? 'ถูก/ผิด' : 'ปรนัย (4 ตัวเลือก)');
                                ?>
                                    <div class="quiz-item" style="border:1px solid var(--border);border-radius:8px;padding:13px;display:flex;justify-content:space-between;gap:12px;align-items:flex-start;">
                                        <div style="display:flex;gap:10px;min-width:0;"><b class="mono" style="color:var(--text-primary);">Q<?= $i + 1 ?></b><div><div style="font-size:14px;color:var(--text);margin-bottom:5px;"><?= h($q['questions_text']) ?></div><span class="badge badge-draft" style="font-size:10.5px;"><?= $qLabel ?> · <?= number_format((float) $q['question_max_score'], 0) ?> คะแนน</span><div style="color:var(--text-muted);font-size:11px;margin-top:5px;">แก้ไขล่าสุด <?= h(formatTeacherEditDate($q['last_modified_at'] ?? null)) ?></div></div></div>
                                        <div style="display:flex;gap:4px;flex-shrink:0;"><button class="action-icon-btn btn-edit-quiz" data-id="<?= h($q['questions_id']) ?>" data-type="<?= $qType ?>" data-question="<?= h($q['questions_text']) ?>" data-ca="<?= h($q['choice_a']) ?>" data-cb="<?= h($q['choice_b']) ?>" data-cc="<?= h($q['choice_c']) ?>" data-cd="<?= h($q['choice_d']) ?>" data-answer="<?= h($q['correct_answer']) ?>" data-explanation="<?= h($q['teacher_explanation']) ?>" data-max-score="<?= number_format((float) $q['question_max_score'], 0, '.', '') ?>" title="แก้ไขคำถาม">แก้ไข</button><button class="action-icon-btn btn-del-quiz" data-id="<?= h($q['questions_id']) ?>" title="ลบคำถาม" style="color:#ef4444;">ลบ</button></div>
                                    </div>
                                <?php endforeach; endif; ?>
                            </div>
                        </details>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <div class="lesson-tab-content card" id="lessonTab-essay" style="display:none">
                <div class="card-header">
                    <div><h3 class="card-title" style="font-size:14px">ตรวจและให้คะแนนข้อเขียน</h3><p style="font-size:12px;color:var(--text-muted);margin-top:4px">ข้อกาได้คะแนนอัตโนมัติตามเฉลย ส่วนข้อเขียนให้คะแนนรายข้อได้ถึงคะแนนเต็มที่กำหนดไว้</p></div>
                </div>
                <div style="display:flex;gap:8px;margin:16px 0;border-bottom:1px solid var(--border);padding-bottom:12px">
                    <button type="button" class="essay-filter-btn active" data-essay-filter="pending">รอตรวจ <span id="essayPendingCount">0</span></button>
                    <button type="button" class="essay-filter-btn" data-essay-filter="reviewed">ตรวจแล้ว <span id="essayReviewedCount">0</span></button>
                </div>
                <div id="essayPendingList" style="display:flex;flex-direction:column;gap:12px"><div style="padding:24px;color:var(--text-muted)">กำลังโหลดคำตอบข้อเขียน...</div></div>
                <div id="essayReviewedList" style="display:none;flex-direction:column;gap:12px"></div>
            </div>

        </div>

        <div class="modal-overlay" id="modalOverlay">
            <div class="modal">
                <div class="modal-header">
                    <h3 class="modal-title"> เพิ่มบทเรียนย่อย</h3>
                    <button class="modal-close" onclick="document.getElementById('modalOverlay').classList.remove('open')">ปิด</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>ชื่อบทเรียนย่อย</label>
                        <input type="text" class="form-input" id="lessonNameInput" placeholder="เช่น 1.1 ตรรกศาสตร์เบื้องต้น">
                    </div>
                    <div class="form-group">
                        <label>เอกสารประกอบบทเรียน</label>
                        <input type="file" class="form-input" id="lessonDocument" accept="application/pdf,.pdf">
                    </div>
                    <div class="form-group">
                        <label>วิดีโอบทเรียน (MP4)</label>
                        <input type="file" class="form-input" id="lessonVideo" accept="video/mp4,.mp4">
                    </div>
                    <input type="hidden" id="lessonSubjectId" value="<?= h($defaultSubjectId) ?>">
                    <p class="save-feedback" id="lessonSaveStatus" role="status" aria-live="polite"></p>
                </div>
                <div class="modal-footer">
                    <button class="btn-cancel" onclick="document.getElementById('modalOverlay').classList.remove('open')">ยกเลิก</button>
                    <button class="btn-save" id="saveLessonBtn"> บันทึกบทเรียน</button>
                </div>
            </div>
        </div>

        <div class="modal-overlay" id="editLessonModal">
            <div class="modal">
                <div class="modal-header">
                    <h3 class="modal-title"> แก้ไขชื่อบทเรียน</h3>
                    <button class="modal-close" onclick="document.getElementById('editLessonModal').classList.remove('open')">ปิด</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>ชื่อบทเรียนใหม่</label>
                        <input type="text" class="form-input" id="editLsnNameInput">
                    </div>
                    <input type="hidden" id="editLsnIdInput">
                </div>
                <div class="modal-footer">
                    <button class="btn-cancel" onclick="document.getElementById('editLessonModal').classList.remove('open')">ยกเลิก</button>
                    <button class="btn-save" id="saveEditLsnBtn"> บันทึกการแก้ไข</button>
                </div>
            </div>
        </div>

        <div class="modal-overlay" id="quizModalOverlay">
            <div class="modal" style="max-width: 520px;">
                <div class="modal-header">
                    <h3 class="modal-title"> เพิ่มคำถามลงในบทเรียน</h3>
                    <button class="modal-close" onclick="document.getElementById('quizModalOverlay').classList.remove('open')">ปิด</button>
                </div>
                <div class="modal-body">
                    <div style="background:var(--orange-dim); color:var(--text-primary); padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:10px; border:1px solid rgba(92, 107, 192,0.3);">
                        <b>เพิ่มลงใน:</b> <span id="quizTargetLessonName"></span>
                    </div>
                    <input type="hidden" id="quizTargetLessonId" value="">

                    <div class="form-group">
                        <label>รูปแบบข้อสอบ</label>
                        <select class="form-input" id="quizTypeAdd">
                            <option value="choice">ปรนัย (4 ตัวเลือก)</option>
                            <option value="truefalse">ถูก/ผิด</option>
                            <option value="essay">อัตนัย (ข้อเขียน รออาจารย์ตรวจ)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>คำถาม</label>
                        <textarea class="form-input" id="quizQuestionAdd" rows="3" placeholder="พิมพ์คำถาม..."></textarea>
                    </div>
                    <div class="form-group"><label for="teacherExplanationAdd">คำอธิบายเพิ่มเติมจากอาจารย์ (ถ้ามี)</label><textarea class="form-input" id="teacherExplanationAdd" rows="3" placeholder="อธิบายแนวคิดหรือเหตุผลของคำตอบที่ถูกต้อง"></textarea></div>
                    <div class="form-group"><label for="quizMaxScoreAdd">คะแนนเต็มข้อนี้</label><input class="form-input" id="quizMaxScoreAdd" type="number" min="1" max="10000" step="1" value="1" required></div>

                    <div class="form-group" id="quizChoiceGroupAdd">
                        <label>ตัวเลือก (เลือกวงกลมหน้าข้อที่เป็นเฉลย)</label>
                        <div style="display:flex; flex-direction:column; gap:8px;">
                            <div class="choice-row"><input type="radio" name="correctAdd" value="A" checked><span class="choice-label">A.</span><input type="text" class="form-input" id="chA_Add" placeholder="ตัวเลือก ก"></div>
                            <div class="choice-row"><input type="radio" name="correctAdd" value="B"><span class="choice-label">B.</span><input type="text" class="form-input" id="chB_Add" placeholder="ตัวเลือก ข"></div>
                            <div class="choice-row"><input type="radio" name="correctAdd" value="C"><span class="choice-label">C.</span><input type="text" class="form-input" id="chC_Add" placeholder="ตัวเลือก ค"></div>
                            <div class="choice-row"><input type="radio" name="correctAdd" value="D"><span class="choice-label">D.</span><input type="text" class="form-input" id="chD_Add" placeholder="ตัวเลือก ง"></div>
                        </div>
                    </div>

                    <div class="form-group" id="quizTFGroupAdd" style="display:none;">
                        <label>เฉลย (เลือกข้อที่ถูกต้อง)</label>
                        <div style="display:flex; gap:20px; margin-top:8px;">
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;color:var(--text);"><input type="radio" name="tfAdd" value="A" checked> ถูก (True)</label>
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;color:var(--text);"><input type="radio" name="tfAdd" value="B"> ผิด (False)</label>
                        </div>
                    </div>

                    <div class="form-group" id="quizEssayGroupAdd" style="display:none;">
                        <div style="padding:12px; background:rgba(92, 107, 192,0.1); color:#303F9F; border-radius:6px; font-size:12px; border:1px dashed rgba(92, 107, 192,0.3);">
                             <b>โหมดข้อเขียน:</b> นักเรียนจะต้องพิมพ์คำตอบส่งมา และคุณจะต้องเป็นผู้ตรวจให้คะแนนเองในภายหลัง
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn-cancel" onclick="document.getElementById('quizModalOverlay').classList.remove('open')">ยกเลิก</button>
                    <button class="btn-save" id="saveQuizBtn"> เพิ่มคำถาม</button>
                </div>
            </div>
        </div>

        <div class="modal-overlay" id="editQuizModal">
            <div class="modal" style="max-width: 520px;">
                <div class="modal-header">
                    <h3 class="modal-title"> แก้ไขคำถาม</h3>
                    <button class="modal-close" onclick="document.getElementById('editQuizModal').classList.remove('open')">ปิด</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="editQuizIdInput" value="">
                    <input type="hidden" id="editQuizTypeHidden" value="choice">

                    <div class="form-group">
                        <label>คำถาม</label>
                        <textarea class="form-input" id="editQuizQuestion" rows="3"></textarea>
                    </div>
                    <div class="form-group"><label for="teacherExplanationEdit">คำอธิบายเพิ่มเติมจากอาจารย์ (ถ้ามี)</label><textarea class="form-input" id="teacherExplanationEdit" rows="3" placeholder="อธิบายแนวคิดหรือเหตุผลของคำตอบที่ถูกต้อง"></textarea></div>
                    <div class="form-group"><label for="editQuizMaxScore">คะแนนเต็มข้อนี้</label><input class="form-input" id="editQuizMaxScore" type="number" min="1" max="10000" step="1" required></div>

                    <div class="form-group" id="quizChoiceGroupEdit">
                        <label>ตัวเลือก (เลือกวงกลมหน้าข้อที่เป็นเฉลย)</label>
                        <div style="display:flex; flex-direction:column; gap:8px;">
                            <div class="choice-row"><input type="radio" name="correctEdit" value="A"><span class="choice-label">A.</span><input type="text" class="form-input" id="chA_Edit"></div>
                            <div class="choice-row"><input type="radio" name="correctEdit" value="B"><span class="choice-label">B.</span><input type="text" class="form-input" id="chB_Edit"></div>
                            <div class="choice-row"><input type="radio" name="correctEdit" value="C"><span class="choice-label">C.</span><input type="text" class="form-input" id="chC_Edit"></div>
                            <div class="choice-row"><input type="radio" name="correctEdit" value="D"><span class="choice-label">D.</span><input type="text" class="form-input" id="chD_Edit"></div>
                        </div>
                    </div>

                    <div class="form-group" id="quizTFGroupEdit" style="display:none;">
                        <label>เฉลย (เลือกข้อที่ถูกต้อง)</label>
                        <div style="display:flex; gap:20px; margin-top:8px;">
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;color:var(--text);"><input type="radio" name="tfEdit" value="A"> ถูก (True)</label>
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;color:var(--text);"><input type="radio" name="tfEdit" value="B"> ผิด (False)</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn-cancel" onclick="document.getElementById('editQuizModal').classList.remove('open')">ยกเลิก</button>
                    <button class="btn-save" id="saveEditQuizBtn"> บันทึกการแก้ไข</button>
                </div>
            </div>
        </div>

    </div>

    <div id="view-reports" class="page-view" style="display:none">
        <header class="topbar">
            <div class="topbar-left">
                <h1 class="page-title">สรุปการทำงานและสถานะนักเรียน</h1>
            </div>
            <button type="button" class="btn-add-lesson" onclick="document.getElementById('teacherReportFrame').contentWindow.print()">พิมพ์ / บันทึกเป็น PDF</button>
        </header>
        <div class="content-grid" style="grid-template-columns:1fr;">
            <iframe id="teacherReportFrame" title="รายงานผลการสอนของอาจารย์" src="teacher_report.php?embed=1" onload="this.style.height=(this.contentWindow.document.documentElement.scrollHeight + 20)+'px'" style="display:block;width:100%;min-height:900px;border:0;border-radius:12px;background:transparent;"></iframe>
        </div>
    </div>

    <div id="view-settings" class="page-view" style="display:none">
        <header class="topbar profile-settings-header">
            <div class="topbar-left">
                <h1 class="page-title">ข้อมูลส่วนตัว</h1>
                <span class="page-sub">จัดการโปรไฟล์และการตั้งค่าของคุณ</span>
            </div>
        </header>

        <div class="profile-settings-layout" style="display:flex;flex-direction:column;gap:20px;max-width:620px;">

            <div class="card profile-settings-card" style="padding:28px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:22px;">
                    <span style="font-size:16px;"></span>
                    <h3 style="display:flex;align-items:center;gap:8px;color:var(--text);font-family:'Kanit',sans-serif;font-size:15px;font-weight:600;"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="9" r="3"/><path d="M6.8 18a5.5 5.5 0 0 1 10.4 0"/></svg> โปรไฟล์อาจารย์</h3>
                </div>

                <div style="display:flex;align-items:center;gap:20px;margin-bottom:24px;">
                    <div style="position:relative;width:80px;height:80px;flex-shrink:0;">
                        <div id="settingsAvatarWrap" style="width:80px;height:80px;border-radius:50%;background:var(--bg3);border:2px solid var(--orange);overflow:hidden;display:flex;align-items:center;justify-content:center;font-size:32px;color:var(--text-primary);">
                            <?php if (!empty($teacher['avatar_url'])): ?>
                                <img src="<?= h($teacher['avatar_url']) ?>" style="width:100%;height:100%;object-fit:cover;" id="settingsAvatarImg">
                            <?php else: ?>
                                <span id="settingsAvatarInitial"><?= h($teacher['avatar']) ?></span>
                            <?php endif; ?>
                        </div>
                        <input type="file" id="avatarFileInput" accept="image/*" style="display:none">
                        <button type="button" onclick="document.getElementById('avatarFileInput').click()" aria-label="เปลี่ยนรูปโปรไฟล์" title="เปลี่ยนรูปโปรไฟล์" style="position:absolute;right:-3px;bottom:-3px;width:28px;height:28px;border:2px solid var(--bg);border-radius:50%;background:var(--orange);color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;z-index:1;"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 4h-4l-2 3H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3z"/><circle cx="12" cy="13" r="3"/></svg></button>
                    </div>
                    <div>
                        <div style="font-size:17px;font-weight:700;color:var(--text);"><?= h($teacher['name']) ?></div>
                        <div style="font-size:12px;color:var(--text-muted);margin-top:3px;">อาจารย์ผู้สอน</div>
                        <div style="font-size:12px;color:var(--text-dim);margin-top:2px;">รูปโปรไฟล์</div>
                    </div>
                </div>

                    <div style="display:flex;flex-direction:column;gap:14px;">
                    <div class="profile-settings-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <label style="display:flex;flex-direction:column;gap:6px;font-size:12px;color:var(--text-muted);font-weight:500;">ชื่อ<input type="text" id="settingsFirstName" class="form-input" value="<?= h($teacherProfileFirstName) ?>" autocomplete="given-name"></label>
                        <label style="display:flex;flex-direction:column;gap:6px;font-size:12px;color:var(--text-muted);font-weight:500;">นามสกุล<input type="text" id="settingsLastName" class="form-input" value="<?= h($teacherProfileLastName) ?>" autocomplete="family-name"></label>
                    </div>
                    <div class="profile-settings-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <div style="display:flex;flex-direction:column;gap:6px;">
                            <label style="font-size:12px;color:var(--text-muted);font-weight:500;">เลขบัตรประจำตัวประชาชน</label>
                            <input class="form-input profile-readonly" value="<?= h($teacherRow['user_id'] ?? '') ?>" <?= $profileContactReadonly ?>>
                        </div>
                        <div style="display:flex;flex-direction:column;gap:6px;">
                            <label style="font-size:12px;color:var(--text-muted);font-weight:500;">อีเมล</label>
                            <input class="form-input profile-readonly" type="email" value="<?= h($teacherRow['email'] ?? '') ?>" <?= $profileContactReadonly ?>>
                        </div>
                    </div>
                    <div class="profile-settings-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <label style="display:flex;flex-direction:column;gap:6px;font-size:12px;color:var(--text-muted);font-weight:500;">เบอร์โทรศัพท์<input class="form-input profile-readonly" type="tel" value="<?= h($teacherRow['phone'] ?? '') ?>" <?= $profileContactReadonly ?>></label>
                        <label style="display:flex;flex-direction:column;gap:6px;font-size:12px;color:var(--text-muted);font-weight:500;">บ้านเลขที่ / ถนน<input class="form-input profile-readonly" value="<?= h($teacherRow['address_line'] ?? '') ?>" <?= $profileContactReadonly ?>></label>
                    </div>
                    <div class="profile-settings-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <label style="display:flex;flex-direction:column;gap:6px;font-size:12px;color:var(--text-muted);font-weight:500;">ตำบล / แขวง<input class="form-input profile-readonly" value="<?= h($teacherRow['subdistrict'] ?? '') ?>" <?= $profileContactReadonly ?>></label>
                        <label style="display:flex;flex-direction:column;gap:6px;font-size:12px;color:var(--text-muted);font-weight:500;">อำเภอ / เขต<input class="form-input profile-readonly" value="<?= h($teacherRow['district'] ?? '') ?>" <?= $profileContactReadonly ?>></label>
                    </div>
                    <div class="profile-settings-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <label style="display:flex;flex-direction:column;gap:6px;font-size:12px;color:var(--text-muted);font-weight:500;">จังหวัด<input class="form-input profile-readonly" value="<?= h($teacherRow['province'] ?? '') ?>" <?= $profileContactReadonly ?>></label>
                        <label style="display:flex;flex-direction:column;gap:6px;font-size:12px;color:var(--text-muted);font-weight:500;">รหัสไปรษณีย์<input class="form-input profile-readonly" value="<?= h($teacherRow['postal_code'] ?? '') ?>" <?= $profileContactReadonly ?>></label>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:6px;">
                        <label style="font-size:12px;color:var(--text-muted);font-weight:500;">วิชาที่สอน</label>
                        <div style="background:var(--bg3);padding:11px 14px;border-radius:var(--radius-sm);border:1px solid var(--border);color:var(--text-dim);font-size:13px;">
                            <?= h($teacher['subject']) ?>
                        </div>
                    </div>
                </div>

                <div id="profileMsg" style="display:none;margin-top:14px;padding:10px 14px;border-radius:6px;font-size:13px;"></div>

                <button onclick="saveTeacherProfile()" style="margin-top:18px;width:100%;padding:12px;background:var(--orange);color:#ffffff;border:none;border-radius:var(--radius-sm);font-family:'Kanit',sans-serif;font-size:14px;font-weight:600;cursor:pointer;transition:opacity .2s;" onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                     บันทึกข้อมูล
                </button>
            </div>

            <div class="card profile-settings-card" style="padding:28px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:22px;">
                    <span style="font-size:16px;"></span>
                    <h3 style="color:var(--text);font-size:15px;font-weight:600;">เปลี่ยนรหัสผ่าน</h3>
                </div>
                <div style="display:flex;flex-direction:column;gap:14px;">
                    <div style="display:flex;flex-direction:column;gap:6px;">
                        <label style="font-size:12px;color:var(--text-muted);font-weight:500;">รหัสผ่านปัจจุบัน</label>
                        <div class="teacher-password-input-wrap"><input type="password" id="currentPassword" class="form-input" placeholder="ใส่รหัสผ่านปัจจุบัน" style="font-size:13px;"><button type="button" class="teacher-password-toggle" onclick="toggleTeacherPassword('currentPassword',this)" aria-label="แสดงรหัสผ่าน" title="แสดงรหัสผ่าน"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:6px;">
                        <label style="font-size:12px;color:var(--text-muted);font-weight:500;">รหัสผ่านใหม่</label>
                        <div class="teacher-password-input-wrap"><input type="password" id="newPassword" class="form-input" placeholder="อย่างน้อย 6 ตัวอักษร" style="font-size:13px;"><button type="button" class="teacher-password-toggle" onclick="toggleTeacherPassword('newPassword',this)" aria-label="แสดงรหัสผ่าน" title="แสดงรหัสผ่าน"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:6px;">
                        <label style="font-size:12px;color:var(--text-muted);font-weight:500;">ยืนยันรหัสผ่านใหม่</label>
                        <div class="teacher-password-input-wrap"><input type="password" id="confirmPassword" class="form-input" placeholder="พิมพ์รหัสผ่านใหม่อีกครั้ง" style="font-size:13px;"><button type="button" class="teacher-password-toggle" onclick="toggleTeacherPassword('confirmPassword',this)" aria-label="แสดงรหัสผ่าน" title="แสดงรหัสผ่าน"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></div>
                    </div>
                </div>

                <div id="passwordMsg" style="display:none;margin-top:14px;padding:10px 14px;border-radius:6px;font-size:13px;"></div>

                <button onclick="saveTeacherPassword()" style="margin-top:18px;width:100%;padding:12px;background:#31578f;color:#ffffff;border:1px solid #31578f;border-radius:var(--radius-sm);font-family:'Kanit',sans-serif;font-size:14px;font-weight:600;cursor:pointer;transition:background .2s,transform .2s;" onmouseover="this.style.background='#284a80';this.style.transform='translateY(-1px)'" onmouseout="this.style.background='#31578f';this.style.transform='translateY(0)'">
                     เปลี่ยนรหัสผ่าน
                </button>
            </div>

        </div>
    </div>

    <?php if ($canAccessTransferApproval): ?>
    <div id="view-graduation" class="page-view teacher-graduation-view" style="display:none">
        <header class="topbar"><div class="topbar-left"><h1 class="page-title">อนุมัติจบการศึกษา</h1><span class="page-sub">ตรวจสอบผลการเรียนและออกใบสรุปผลการเรียน PDF</span></div></header>
        <div id="teacherGraduationMessage" class="teacher-graduation-message" role="status">กำลังโหลดคำขอ...</div>
        <div class="teacher-graduation-toolbar">
          <div class="teacher-graduation-filters" role="tablist" aria-label="สถานะคำขอจบการศึกษา">
            <button type="button" class="active" data-teacher-graduation-filter="pending">รออนุมัติ <span id="teacherGraduationPendingCount">0</span></button>
            <button type="button" data-teacher-graduation-filter="approved">อนุมัติแล้ว <span id="teacherGraduationApprovedCount">0</span></button>
          </div>
          <label class="teacher-graduation-search"><span aria-hidden="true">⌕</span><input id="teacherGraduationSearch" type="search" placeholder="ค้นหาชื่อนักเรียน หรือรหัสนักเรียน" autocomplete="off"></label>
        </div>
        <div id="teacherGraduationList" class="teacher-graduation-list"></div>
    </div>
    <?php endif; ?>

</main>

<div id="cropModalOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.75);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:var(--surface,#1e1e2e);border-radius:16px;padding:24px;width:min(420px,92vw);display:flex;flex-direction:column;gap:16px;box-shadow:0 20px 60px rgba(0,0,0,0.5);">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <span style="font-weight:700;font-size:16px;">️ ครอบรูปโปรไฟล์</span>
            <button id="cropCancelBtn" style="background:none;border:none;color:var(--text-dim,#aaa);font-size:14px;cursor:pointer;line-height:1;">ยกเลิก</button>
        </div>

        <div style="position:relative;width:100%;aspect-ratio:1;background:#111;border-radius:12px;overflow:hidden;touch-action:none;" id="cropArea">
            <img id="cropImg" style="position:absolute;transform-origin:0 0;user-select:none;-webkit-user-select:none;max-width:none;">
            <div id="cropCircle" style="position:absolute;border:2px dashed rgba(92, 107, 192,0.9);border-radius:50%;pointer-events:none;box-shadow:0 0 0 9999px rgba(0,0,0,0.5);"></div>
        </div>

        <div style="display:flex;align-items:center;gap:10px;">
            <span style="font-size:13px;color:var(--text-dim,#aaa);"> </span>
            <input type="range" id="cropZoom" min="100" max="400" value="100" style="flex:1;accent-color:var(--text-primary);">
            <span style="font-size:13px;color:var(--text-dim,#aaa);">ซูม</span>
        </div>

        <div style="display:flex;gap:10px;">
            <button id="cropCancelBtn2" style="flex:1;padding:10px;border-radius:8px;border:1px solid #444;background:none;color:#ccc;cursor:pointer;font-size:14px;">ยกเลิก</button>
            <button id="cropConfirmBtn" style="flex:2;padding:10px;border-radius:8px;border:none;background:var(--orange,#5C6BC0);color:var(--text);font-weight:700;cursor:pointer;font-size:14px;"> ใช้รูปนี้</button>
        </div>
    </div>
</div>

<script>

(function() {
    const overlay   = document.getElementById('cropModalOverlay');
    const cropImg   = document.getElementById('cropImg');
    const cropArea  = document.getElementById('cropArea');
    const cropCircle= document.getElementById('cropCircle');
    const zoomSlider= document.getElementById('cropZoom');

    let imgNaturalW = 0, imgNaturalH = 0;
    let scale = 1, minScale = 1;
    let posX = 0, posY = 0;
    let areaSize = 0, circleSize = 0, circleOffset = 0;

    function openCrop(src) {
        overlay.style.display = 'flex';
        cropImg.src = src;
        zoomSlider.value = 100;
        cropImg.onload = function() {
            imgNaturalW = cropImg.naturalWidth;
            imgNaturalH = cropImg.naturalHeight;
            areaSize    = cropArea.offsetWidth;
            circleSize  = Math.round(areaSize * 0.82);
            circleOffset= Math.round((areaSize - circleSize) / 2);

            cropCircle.style.width  = circleSize + 'px';
            cropCircle.style.height = circleSize + 'px';
            cropCircle.style.left   = circleOffset + 'px';
            cropCircle.style.top    = circleOffset + 'px';

            minScale = circleSize / Math.min(imgNaturalW, imgNaturalH);
            scale    = minScale;
            centerImage();
        };
    }

    function centerImage() {
        const w = imgNaturalW * scale;
        const h = imgNaturalH * scale;
        posX = circleOffset + (circleSize - w) / 2;
        posY = circleOffset + (circleSize - h) / 2;
        applyTransform();
    }

    function applyTransform() {
        cropImg.style.transform = `translate(${posX}px,${posY}px) scale(${scale})`;
    }

    zoomSlider.addEventListener('input', function() {
        const newScale = minScale * (this.value / 100);
        const cx = circleOffset + circleSize / 2;
        const cy = circleOffset + circleSize / 2;
        posX = cx - (cx - posX) * (newScale / scale);
        posY = cy - (cy - posY) * (newScale / scale);
        scale = newScale;
        clamp();
        applyTransform();
    });

    let dragging = false, startX = 0, startY = 0, startPosX = 0, startPosY = 0;

    function onDragStart(ex, ey) { dragging=true; startX=ex; startY=ey; startPosX=posX; startPosY=posY; }
    function onDragMove(ex, ey) {
        if (!dragging) return;
        posX = startPosX + (ex - startX);
        posY = startPosY + (ey - startY);
        clamp();
        applyTransform();
    }
    function onDragEnd() { dragging = false; }

    cropArea.addEventListener('mousedown',  e => { e.preventDefault(); onDragStart(e.clientX, e.clientY); });
    window .addEventListener('mousemove',   e => onDragMove(e.clientX, e.clientY));
    window .addEventListener('mouseup',     onDragEnd);
    cropArea.addEventListener('touchstart', e => { e.preventDefault(); onDragStart(e.touches[0].clientX, e.touches[0].clientY); }, {passive:false});
    window .addEventListener('touchmove',   e => onDragMove(e.touches[0].clientX, e.touches[0].clientY), {passive:false});
    window .addEventListener('touchend',    onDragEnd);

    let lastDist = 0;
    cropArea.addEventListener('touchstart', e => { if (e.touches.length === 2) lastDist = Math.hypot(e.touches[0].clientX-e.touches[1].clientX, e.touches[0].clientY-e.touches[1].clientY); }, {passive:true});
    cropArea.addEventListener('touchmove', e => {
        if (e.touches.length !== 2) return;
        const dist = Math.hypot(e.touches[0].clientX-e.touches[1].clientX, e.touches[0].clientY-e.touches[1].clientY);
        const ratio = dist / lastDist;
        lastDist = dist;
        const newScale = Math.max(minScale, Math.min(minScale*4, scale * ratio));
        const cx = circleOffset + circleSize/2, cy = circleOffset + circleSize/2;
        posX = cx - (cx-posX)*(newScale/scale);
        posY = cy - (cy-posY)*(newScale/scale);
        scale = newScale;
        zoomSlider.value = Math.round((scale/minScale)*100);
        clamp(); applyTransform();
    }, {passive:true});

    function clamp() {
        const w = imgNaturalW * scale, h = imgNaturalH * scale;
        const maxX = circleOffset, minX = circleOffset + circleSize - w;
        const maxY = circleOffset, minY = circleOffset + circleSize - h;
        posX = Math.min(maxX, Math.max(minX, posX));
        posY = Math.min(maxY, Math.max(minY, posY));
    }

    function exportCircle() {
        const outputSize = 400;
        const canvas = document.createElement('canvas');
        canvas.width = outputSize; canvas.height = outputSize;
        const ctx = canvas.getContext('2d');

        ctx.beginPath();
        ctx.arc(outputSize/2, outputSize/2, outputSize/2, 0, Math.PI*2);
        ctx.clip();

        const ratio = outputSize / circleSize;
        const drawX = (posX - circleOffset) * ratio;
        const drawY = (posY - circleOffset) * ratio;
        const drawW = imgNaturalW * scale * ratio;
        const drawH = imgNaturalH * scale * ratio;
        ctx.drawImage(cropImg, drawX, drawY, drawW, drawH);

        return canvas.toDataURL('image/png');
    }

    document.getElementById('cropConfirmBtn').addEventListener('click', async function() {
        const btn = this;
        if (!imgNaturalW || !imgNaturalH) {
            showMsg('profileMsg', 'กรุณารอให้รูปโหลดเสร็จก่อน', false);
            return;
        }
        btn.textContent = '⏳ กำลังอัปโหลด...'; btn.disabled = true;
        showMsg('profileMsg', '⏳ กำลังอัปโหลดรูป...', true);
        try {
            const base64 = exportCircle();
            const res = await fetch('uploadavatar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ image: base64 })
            });
            const data = await res.json().catch(() => ({}));
            if (data.success) {
                const newUrl = data.url;
                ['sidebarAvatarWrap', 'settingsAvatarWrap'].forEach(id => {
                    const wrap = document.getElementById(id);
                    if (wrap) wrap.innerHTML = `<img src="${newUrl}" style="width:100%;height:100%;object-fit:cover;border-radius:50%">`;
                });
                overlay.style.display = 'none';
                showMsg('profileMsg', ' เปลี่ยนรูปโปรไฟล์สำเร็จ!', true);
            } else {
                showMsg('profileMsg', ' ' + (data.message || 'บันทึกรูปไม่สำเร็จ กรุณาลองใหม่'), false);
            }
        } catch(err) {
            showMsg('profileMsg', ' บันทึกรูปไม่สำเร็จ กรุณาตรวจสอบการเชื่อมต่อแล้วลองใหม่', false);
        }
        btn.textContent = ' ใช้รูปนี้'; btn.disabled = false;
    });

    ['cropCancelBtn','cropCancelBtn2'].forEach(id => {
        document.getElementById(id).addEventListener('click', () => { overlay.style.display='none'; });
    });

    window._openCrop = openCrop;
})();

document.getElementById('avatarFileInput').addEventListener('change', function() {
    const file = this.files[0];
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) { alert('ไฟล์รูปต้องมีขนาดไม่เกิน 5MB'); return; }
    const reader = new FileReader();
    reader.onload = e => window._openCrop(e.target.result);
    reader.readAsDataURL(file);
    this.value = '';
});

async function saveTeacherProfile() {
    const formData = new FormData();
    formData.append('first_name', document.getElementById('settingsFirstName').value.trim());
    formData.append('last_name', document.getElementById('settingsLastName').value.trim());

    const res = await fetch('update_profile.php', { method: 'POST', body: formData });
    const data = await res.json();

    if (data.success) {
        const nameEl = document.querySelector('.profile-name');
        if (nameEl) nameEl.textContent = data.name;
        showMsg('profileMsg', ' บันทึกข้อมูลสำเร็จ!', true);
    } else {
        showMsg('profileMsg', ' ' + data.message, false);
    }
}

function toggleTeacherPassword(id, button) {
    const input = document.getElementById(id);
    if (!input || !button) return;
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    const label = show ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน';
    button.setAttribute('aria-label', label);
    button.title = label;
    button.innerHTML = show
        ? '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18M10.6 10.6a2 2 0 002.8 2.8M9.9 5.2A10.8 10.8 0 0112 5c6.4 0 10 7 10 7a14.7 14.7 0 01-3 3.8M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7c1.4 0 2.7-.3 3.8-.8"/></svg>'
        : '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';
}

async function saveTeacherPassword() {
    const current = document.getElementById('currentPassword').value;
    const newPw   = document.getElementById('newPassword').value;
    const confirm = document.getElementById('confirmPassword').value;

    if (!current || !newPw || !confirm) { showMsg('passwordMsg', ' กรุณากรอกข้อมูลให้ครบ', false); return; }
    if (newPw.length < 6) { showMsg('passwordMsg', ' รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร', false); return; }
    if (newPw !== confirm) { showMsg('passwordMsg', ' รหัสผ่านใหม่ไม่ตรงกัน', false); return; }

    const formData = new FormData();
    formData.append('pwd_current', current);
    formData.append('pwd_new', newPw);

    const res = await fetch('update_profile.php', { method: 'POST', body: formData });
    const data = await res.json();

    if (data.success) {
        document.getElementById('currentPassword').value = '';
        document.getElementById('newPassword').value = '';
        document.getElementById('confirmPassword').value = '';
        showMsg('passwordMsg', ' เปลี่ยนรหัสผ่านสำเร็จ!', true);
    } else {
        showMsg('passwordMsg', ' ' + data.message, false);
    }
}

function showMsg(id, msg, success) {
    const el = document.getElementById(id);
    el.style.display = 'block';
    el.style.background = success ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.1)';
    el.style.color = success ? '#10b981' : '#ef4444';
    el.style.border = success ? '1px solid rgba(16,185,129,0.3)' : '1px solid rgba(239,68,68,0.3)';
    el.textContent = msg;
    setTimeout(() => { el.style.display = 'none'; }, 4000);
}
</script>


<div class="modal-overlay" id="studentDetailModal" style="z-index:1100;">
    <div class="modal" style="max-width:620px;width:95%;max-height:90vh;overflow-y:auto;">
        <div class="modal-header" style="position:sticky;top:0;background:var(--bg2);z-index:1;border-bottom:1px solid var(--border);padding:20px 24px;">
            <div style="display:flex;align-items:center;gap:14px;">
                <div id="sdm-avatar" style="width:48px;height:48px;border-radius:50%;background:var(--orange-dim);border:2px solid var(--orange);display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;color:var(--text-primary);flex-shrink:0;"></div>
                <div>
                    <h3 class="modal-title" id="sdm-name" style="font-size:17px;margin:0;"></h3>
                    <div id="sdm-class" style="font-size:12px;color:var(--text-muted);margin-top:3px;"></div>
                </div>
            </div>
            <button class="modal-close" onclick="document.getElementById('studentDetailModal').classList.remove('open')">ปิด</button>
        </div>

        <div class="modal-body" style="padding:24px;">

            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:24px;">
                <div style="background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px;text-align:center;">
                    <div style="font-size:11px;color:var(--text-muted);margin-bottom:6px;">คะแนนเฉลี่ยของข้อสอบ</div>
                    <div id="sdm-score" style="font-size:22px;font-weight:700;font-family:'IBM Plex Mono',monospace;"></div>
                </div>
                <div style="background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px;text-align:center;">
                    <div style="font-size:11px;color:var(--text-muted);margin-bottom:6px;">ความคืบหน้า</div>
                    <div id="sdm-pct" style="font-size:22px;font-weight:700;font-family:'IBM Plex Mono',monospace;color:#60a5fa;"></div>
                </div>
                <div style="background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px;text-align:center;">
                    <div style="font-size:11px;color:var(--text-muted);margin-bottom:6px;">บทที่ผ่าน</div>
                    <div id="sdm-lessons" style="font-size:22px;font-weight:700;font-family:'IBM Plex Mono',monospace;color:#10b981;"></div>
                </div>
            </div>


            <div style="margin-bottom:24px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <span style="font-size:12px;color:var(--text-muted);font-weight:500;">ความคืบหน้าโดยรวม</span>
                    <span id="sdm-pct-label" style="font-size:12px;color:var(--text-dim);"></span>
                </div>
                <div style="height:8px;background:var(--bg3);border-radius:100px;overflow:hidden;border:1px solid var(--border);">
                    <div id="sdm-progress-bar" style="height:100%;background:linear-gradient(90deg,#5C6BC0,#5C6BC0);border-radius:100px;transition:width .5s ease;width:0%;"></div>
                </div>
            </div>


            <div style="margin-bottom:8px;">
                <div style="font-size:13px;font-weight:600;color:var(--text);margin-bottom:14px;display:flex;align-items:center;gap:8px;">
                     คะแนนแต่ละบทเรียน
                </div>
                <div id="sdm-lesson-list" style="display:flex;flex-direction:column;gap:10px;">
                    <div style="text-align:center;padding:30px;color:var(--text-muted);font-size:13px;">
                        <div style="font-size:24px;margin-bottom:8px;">⏳</div>
                        กำลังโหลดข้อมูล...
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
async function openStudentModal(el) {
    const id       = el.dataset.id;
    const name     = el.dataset.name;
    const cls      = el.dataset.class;
    const score    = parseFloat(el.dataset.score);
    const completed= parseInt(el.dataset.completed);
    const total    = parseInt(el.dataset.total);
    const pct      = parseInt(el.dataset.pct);
    window.currentDetailStudentId = id;

    document.getElementById('sdm-avatar').textContent = name.charAt(0).toUpperCase();
    document.getElementById('sdm-name').textContent   = name;
    document.getElementById('sdm-class').textContent  = 'ระดับชั้น: ' + (cls || '-');


    const scoreEl = document.getElementById('sdm-score');
    scoreEl.textContent = String(Math.round(score));
    scoreEl.style.color = '#60a5fa';

    document.getElementById('sdm-pct').textContent     = pct + '%';
    document.getElementById('sdm-lessons').textContent = completed + '/' + total;


    document.getElementById('sdm-progress-bar').style.width = pct + '%';
    document.getElementById('sdm-pct-label').textContent     = 'สำเร็จ ' + completed + ' จาก ' + total + ' บทเรียน';


    document.getElementById('sdm-lesson-list').innerHTML = `
        <div style="text-align:center;padding:30px;color:var(--text-muted);font-size:13px;">
            <div style="font-size:24px;margin-bottom:8px;">⏳</div>กำลังโหลดข้อมูล...
        </div>`;

    document.getElementById('studentDetailModal').classList.add('open');


    try {
        const res  = await fetch('get_student_detail.php?student_id=' + encodeURIComponent(id));
        const data = await res.json();
        renderLessonScores(data.lessons || []);
    } catch (e) {
        document.getElementById('sdm-lesson-list').innerHTML =
            '<div style="color:#ef4444;text-align:center;padding:20px;font-size:13px;"> โหลดข้อมูลไม่สำเร็จ</div>';
    }
}

function renderLessonScores(lessons) {
    const container = document.getElementById('sdm-lesson-list');
    if (!lessons.length) {
        container.innerHTML = '<div style="text-align:center;padding:30px;color:var(--text-muted);font-size:13px;background:var(--bg3);border-radius:var(--radius-sm);border:1px dashed var(--border);"> ยังไม่มีข้อมูลคะแนน</div>';
        return;
    }

    container.innerHTML = lessons.map((l, i) => {
        const rawScore = Math.round(Number(l.best_quiz_score) || 0);
        const rawTotal = Math.round(Number(l.quiz_total_score) || 0);
        const pct    = rawTotal > 0 ? Math.round((rawScore / rawTotal) * 100) : 0;
        const passed = pct >= 60;
        const bar    = passed ? '#10b981' : (pct > 0 ? '#5C6BC0' : '#374151');
        const badge  = passed
            ? '<span style="font-size:11px;padding:2px 8px;border-radius:100px;background:rgba(16,185,129,.15);color:#10b981;border:1px solid rgba(16,185,129,.3);"> ผ่าน</span>'
            : (pct > 0
                ? '<span style="font-size:11px;padding:2px 8px;border-radius:100px;background:rgba(92, 107, 192,.12);color:#303F9F;border:1px solid rgba(92, 107, 192,.3);">️ ไม่ผ่าน</span>'
                : '<span style="font-size:11px;padding:2px 8px;border-radius:100px;background:rgba(75,85,99,.2);color:#6b7280;border:1px solid rgba(75,85,99,.3);">— ยังไม่ทำ</span>');

        return `
        <div style="background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px 16px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;gap:10px;flex-wrap:wrap;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:28px;height:28px;border-radius:50%;background:var(--bg2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:12px;color:var(--text-primary);font-weight:600;flex-shrink:0;">${i+1}</div>
                    <div>
                        <div style="font-size:13.5px;color:var(--text);font-weight:500;line-height:1.3;">${escHtml(l.lesson_name)}</div>
                        <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">${escHtml(l.subject_name)}</div>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:10px;flex-shrink:0;">
                    <span style="font-family:'IBM Plex Mono',monospace;font-size:13px;color:${bar};font-weight:600;">${rawScore}/${rawTotal}</span>
                    ${badge}
                </div>
            </div>
            <div style="height:5px;background:var(--bg2);border-radius:100px;overflow:hidden;">
                <div style="height:100%;width:${pct}%;background:${bar};border-radius:100px;transition:width .4s ease;"></div>
            </div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:5px;text-align:right;">${pct}%</div>
            <button type="button" class="student-answer-detail-button" data-lesson-id="${escHtml(l.lesson_id)}" style="margin-top:12px;border:1px solid var(--border);border-radius:7px;padding:7px 11px;background:var(--bg2);color:var(--text);cursor:pointer;font:inherit;font-size:12px;">ดูรายละเอียดคำตอบข้อกา</button>
            <div class="student-answer-detail" hidden style="margin-top:12px;"></div>
        </div>`;
    }).join('');
}

document.getElementById('sdm-lesson-list').addEventListener('click', async (event) => {
    const button = event.target.closest('.student-answer-detail-button');
    if (!button) return;
    const detail = button.nextElementSibling;
    if (!detail.hidden) { detail.hidden = true; button.textContent = 'ดูรายละเอียดคำตอบข้อกา'; return; }
    detail.hidden = false;
    detail.innerHTML = '<div style="padding:12px;color:var(--text-muted);">กำลังโหลดรายละเอียดคำตอบ...</div>';
    button.disabled = true;
    try {
        const url = `get_student_quiz_answers.php?student_id=${encodeURIComponent(window.currentDetailStudentId || '')}&lesson_id=${encodeURIComponent(button.dataset.lessonId)}`;
        const response = await fetch(url);
        const data = await response.json();
        if (!response.ok || data.error) throw new Error(data.error || 'โหลดข้อมูลไม่สำเร็จ');
        detail.innerHTML = renderAnswerDetails(data.questions || []);
        button.textContent = 'ซ่อนรายละเอียดคำตอบข้อกา';
    } catch (error) {
        detail.innerHTML = `<div style="padding:12px;color:#dc2626;">${escHtml(error.message || 'โหลดรายละเอียดไม่สำเร็จ')}</div>`;
    } finally { button.disabled = false; }
});

function renderAnswerDetails(questions) {
    if (!questions.length) return '<div style="padding:12px;color:var(--text-muted);">บทเรียนนี้ไม่มีข้อสอบปรนัย</div>';
    const maxWrong = Math.max(...questions.map(question => Number(question.class_incorrect_count) || 0));
    const mostMissed = maxWrong > 0 ? questions.filter(question => Number(question.class_incorrect_count) === maxWrong) : [];
    const hasClassAnswers = questions.some(question => Number(question.class_answered_count) > 0);
    const summary = mostMissed.length
        ? `<div style="padding:11px 13px;margin-bottom:12px;border-radius:8px;background:#9FA8DA;color:#303F9F;font-size:12px;"><strong>ข้อที่ตอบผิดมากที่สุดในรายวิชา:</strong> ${mostMissed.map(question => `ข้อ ${question.question_id} (${question.class_incorrect_count}/${question.class_answered_count} คนตอบผิด)`).join(' · ')}</div>`
        : `<div style="padding:11px 13px;margin-bottom:12px;border-radius:8px;background:var(--bg2);color:var(--text-muted);font-size:12px;">${hasClassAnswers ? 'ยังไม่มีข้อที่นักเรียนตอบผิดในบทเรียนนี้' : 'ยังไม่มีข้อมูลคำตอบจากนักเรียนในรายวิชา'}</div>`;
    const rows = questions.map((question, index) => {
        if (question.question_type === 'essay') {
            const essaySubmitted = Boolean(question.answer_text);
            const essayReviewed = question.review_status === 'graded';
            const essayStatus = !essaySubmitted ? 'ยังไม่ตอบ' : !essayReviewed ? 'รอตรวจ' : question.result_status === 'passed' ? 'ตรวจแล้ว · ผ่าน' : 'ตรวจแล้ว · ไม่ผ่าน';
            const essayColor = !essaySubmitted ? '#64748b' : !essayReviewed ? '#303F9F' : question.result_status === 'passed' ? '#15803d' : '#b91c1c';
            const essayScore = question.score === null ? '—' : String(Math.round(Number(question.score) || 0));
            return `<article style="padding:12px;border:1px solid var(--border);border-radius:8px;margin-top:10px;"><div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;"><strong style="font-size:12px;">ข้อ ${index + 1} · ข้อเขียน: ${escHtml(question.question_text)}</strong><span style="color:${essayColor};font-weight:700;font-size:12px;white-space:nowrap;">${essayStatus}</span></div><div style="white-space:pre-wrap;overflow-wrap:anywhere;padding:10px;margin-top:8px;border-radius:7px;background:var(--bg2);font-size:12px;">${essaySubmitted ? escHtml(question.answer_text) : 'นักเรียนยังไม่ได้ส่งคำตอบ'}</div><div style="margin-top:7px;color:var(--text-muted);font-size:11px;">คะแนน: ${essayReviewed ? `${essayScore}/${Math.round(Number(question.max_score) || 0)}` : `รอตรวจ · เต็ม ${Math.round(Number(question.max_score) || 0)} คะแนน`}</div></article>`;
        }
        const selected = question.selected_choice || '';
        const correct = question.correct_choice || '';
        const answered = Boolean(selected);
        const isCorrect = answered && selected === correct;
        const marker = !answered ? 'ยังไม่ตอบ' : isCorrect ? '✓ ตอบถูก' : '✗ ตอบผิด';
        const markerColor = !answered ? '#64748b' : isCorrect ? '#15803d' : '#b91c1c';
        const options = (question.options || []).map((option, optionIndex) => {
            const letter = String.fromCharCode(65 + optionIndex);
            const isAnswer = correct === letter;
            const isSelected = selected === letter;
            const background = isAnswer ? '#e6f4ea' : isSelected ? '#fef2f2' : 'var(--bg2)';
            const color = isAnswer ? '#166534' : isSelected ? '#b91c1c' : 'var(--text)';
            const tags = [isAnswer ? '✓ เฉลย' : '', isSelected ? (isCorrect ? '✓ นักเรียนเลือก' : '✗ นักเรียนเลือก') : ''].filter(Boolean).join(' · ');
            return `<div style="padding:8px 10px;border-radius:7px;background:${background};color:${color};font-size:12px;margin-top:5px;"><strong>${letter}.</strong> ${escHtml(String(option))}${tags ? ` <strong style="margin-left:5px;">${tags}</strong>` : ''}</div>`;
        }).join('');
        const teacherExplanation = String(question.teacher_explanation || '').trim();
        const explanationHtml = isCorrect
            ? (teacherExplanation ? `<div style="margin-top:8px;padding:9px;border-radius:7px;background:#f0fdf4;color:#166534;font-size:12px;"><strong>คำอธิบายจากอาจารย์</strong><div style="white-space:pre-wrap">${escHtml(teacherExplanation)}</div></div>` : '')
            : (teacherExplanation ? `<div style="margin-top:8px;padding:9px;border-radius:7px;background:#f8fafc;font-size:12px;"><strong>คำอธิบายจากอาจารย์</strong><div style="white-space:pre-wrap">${escHtml(teacherExplanation)}</div></div>` : '');
        return `<article style="padding:12px;border:1px solid var(--border);border-radius:8px;margin-top:10px;"><div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;"><strong style="font-size:12px;">ข้อ ${index + 1}: ${escHtml(question.question_text)}</strong><span style="color:${markerColor};font-weight:700;font-size:12px;white-space:nowrap;">${marker}</span></div>${options}<div style="margin-top:7px;color:var(--text-muted);font-size:11px;">นักเรียนตอบ: ${answered ? escHtml(selected) : 'ยังไม่ตอบ'} · เฉลย: ${escHtml(correct || '-')} · ผิด ${Number(question.class_incorrect_count) || 0}/${Number(question.class_answered_count) || 0} คน</div>${explanationHtml}</article>`;
    }).join('');
    return summary + rows;
}

function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str || '';
    return d.innerHTML;
}


document.getElementById('studentDetailModal').addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('open');
});
</script>

    <script src="assets/js/teacherdash.js?v=20261026-delete-quiz-confirm-v1"></script>
    <script src="assets/js/learning_progress_report.js?v=20261028-five-events-per-page"></script>
<?php if ($canAccessTransferApproval): ?>
    <script src="assets/js/teacher_graduation.js?v=20261022-graduation-status-colors"></script>
<?php endif; ?>
<script src="assets/js/responsive.js?v=20261022-menu-auto-close" defer></script>
</body>
</html>
