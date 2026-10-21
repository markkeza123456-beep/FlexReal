<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();
if (($_SESSION['role'] ?? '') !== 'teacher' || empty($_SESSION['user_id'])) {
    http_response_code(isset($_SESSION['user_id']) ? 403 : 401);
    if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
    exit('คุณไม่มีสิทธิ์เข้าถึงรายงานอาจารย์');
}

require_once __DIR__ . '/config/db.php';
$teacherId = (string) $_SESSION['user_id'];
$isEmbedded = (string) ($_GET['embed'] ?? '') === '1';
$reportDate = new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok'));
$teacherStmt = $conn->prepare('SELECT full_name FROM public.teachers WHERE user_id = :teacher_id LIMIT 1');
$teacherStmt->execute([':teacher_id' => $teacherId]);
$teacherName = trim((string) ($teacherStmt->fetchColumn() ?: ($_SESSION['name'] ?? $teacherId)));

$courseStmt = $conn->prepare(
    'SELECT c.course_id, c.code, c.name
     FROM public.courses c
     INNER JOIN public.course_teachers ct ON ct.course_id = c.course_id
     WHERE ct.teacher_id = :teacher_id
     ORDER BY c.name, c.course_id'
);
$courseStmt->execute([':teacher_id' => $teacherId]);
$courses = $courseStmt->fetchAll(PDO::FETCH_ASSOC);
$courseIds = array_map(static fn(array $course): string => (string) $course['course_id'], $courses);
$courseNames = array_map(static fn(array $course): string => trim((string) $course['name']), $courses);

$students = [];
$lessonStats = [];
$gradingRows = [];
$gradingRowsByStudent = [];
$overallAverage = 0;
$overallAverageMaxScore = 0;
$averageSampleCount = 0;
$totalScoreSum = 0.0;
$essayPending = 0;
$essayGraded = 0;

if ($courseIds !== []) {
    $catalogStmt = $conn->prepare(
        'SELECT c.name AS course_name, l.lesson_id, l.position, l.title AS lesson_name
         FROM public.lessons l
         INNER JOIN public.courses c ON c.course_id = l.course_id
         INNER JOIN public.course_teachers ct ON ct.course_id = c.course_id
         WHERE ct.teacher_id = :teacher_id
         ORDER BY c.name, l.position, l.lesson_id'
    );
    $catalogStmt->execute([':teacher_id' => $teacherId]);
    foreach ($catalogStmt->fetchAll(PDO::FETCH_ASSOC) as $lesson) {
        $lessonId = (string) $lesson['lesson_id'];
        $lessonStats[$lessonId] = [
            'id' => $lessonId,
            'course' => (string) $lesson['course_name'],
            'position' => (int) $lesson['position'],
            'name' => (string) $lesson['lesson_name'],
            'score_sum' => 0.0,
            'max_score_sum' => 0.0,
            'attempts' => 0,
        ];
    }

    $studentStmt = $conn->prepare(
        "SELECT DISTINCT st.user_id AS student_id, COALESCE(st.full_name, st.user_id::text) AS student_name,
                COALESCE(NULLIF(BTRIM(st.student_level), ''), '-') AS class_name
         FROM public.students st
         INNER JOIN public.student_courses sc ON sc.student_id = st.user_id AND sc.status = 'active'
         INNER JOIN public.course_teachers ct ON ct.course_id = sc.course_id AND ct.teacher_id = :teacher_id
         ORDER BY student_name, student_id"
    );
    $studentStmt->execute([':teacher_id' => $teacherId]);
    foreach ($studentStmt->fetchAll(PDO::FETCH_ASSOC) as $student) {
        $id = (string) $student['student_id'];
        $students[$id] = [
            'id' => $id,
            'name' => (string) $student['student_name'],
            'class' => (string) $student['class_name'],
            'total' => 0,
            'completed' => 0,
            'progress' => 0,
        ];
    }

    $lessonStmt = $conn->prepare(
        "SELECT c.course_id, c.name AS course_name, l.lesson_id, l.position, l.title AS lesson_name,
                st.user_id AS student_id, qa.attempt_id, qa.score, qa.total_score, qa.status AS quiz_status,
                breakdown.objective_score, breakdown.objective_total,
                breakdown.essay_score, breakdown.essay_total, breakdown.essay_pending_count,
                breakdown.essay_graded_count, breakdown.essay_question_count,
                CASE WHEN COALESCE(lp.is_transferred, FALSE) OR UPPER(COALESCE(lp.grade, '')) = 'TR'
                          OR EXISTS (
                              SELECT 1 FROM public.transcript_request_lessons trl
                              INNER JOIN public.transcript_requests tr ON tr.request_id = trl.request_id
                              WHERE trl.lesson_id = l.lesson_id AND trl.is_approved = TRUE
                                AND tr.student_id = st.user_id AND tr.status = 'approved'
                          )
                     THEN TRUE ELSE FALSE END AS transferred
         FROM public.course_teachers ct
         INNER JOIN public.courses c ON c.course_id = ct.course_id
         INNER JOIN public.lessons l ON l.course_id = c.course_id
         INNER JOIN public.student_courses sc ON sc.course_id = c.course_id AND sc.status = 'active'
         INNER JOIN public.students st ON st.user_id = sc.student_id
         LEFT JOIN public.lesson_progress lp ON lp.student_id = st.user_id AND lp.lesson_id = l.lesson_id
         LEFT JOIN LATERAL (
             SELECT attempt_id, score, total_score, status
             FROM public.quiz_attempts a
             WHERE a.student_id = st.user_id AND a.lesson_id = l.lesson_id
             ORDER BY a.attempt_number DESC, a.submitted_at DESC
             LIMIT 1
         ) qa ON TRUE
         LEFT JOIN LATERAL (
             SELECT
                 SUM(aa.score) FILTER (WHERE q.question_type <> 'essay') AS objective_score,
                 SUM(q.max_score) FILTER (WHERE q.question_type <> 'essay') AS objective_total,
                 SUM(aa.score) FILTER (WHERE q.question_type = 'essay' AND aa.review_status = 'graded' AND aa.score IS NOT NULL) AS essay_score,
                 SUM(q.max_score) FILTER (WHERE q.question_type = 'essay') AS essay_total,
                 COUNT(*) FILTER (WHERE q.question_type = 'essay' AND (aa.review_status = 'pending' OR aa.score IS NULL)) AS essay_pending_count,
                 COUNT(*) FILTER (WHERE q.question_type = 'essay' AND aa.review_status = 'graded' AND aa.score IS NOT NULL) AS essay_graded_count,
                 COUNT(*) FILTER (WHERE q.question_type = 'essay') AS essay_question_count
             FROM public.quiz_attempt_answers aa
             INNER JOIN public.questions q ON q.question_id = aa.question_id
             WHERE aa.attempt_id = qa.attempt_id
         ) breakdown ON TRUE
         WHERE ct.teacher_id = :teacher_id
         ORDER BY c.name, l.position, st.full_name"
    );
    $lessonStmt->execute([':teacher_id' => $teacherId]);
    $lessonRows = $lessonStmt->fetchAll(PDO::FETCH_ASSOC);
    $scoreSum = 0.0;
    foreach ($lessonRows as $row) {
        $studentId = (string) $row['student_id'];
        $lessonId = (string) $row['lesson_id'];
        $key = $lessonId;
        if (!isset($students[$studentId])) continue;
        $transferred = filter_var($row['transferred'], FILTER_VALIDATE_BOOLEAN);
        $attempted = $row['attempt_id'] !== null;
        $score = $attempted ? (float) $row['score'] : null;
        $totalScore = $attempted ? (float) $row['total_score'] : null;
        $completed = $transferred || $attempted;

        $students[$studentId]['total']++;
        if ($completed) $students[$studentId]['completed']++;
        $essayQuestionCount = (int) ($row['essay_question_count'] ?? 0);
        $essayPendingCount = (int) ($row['essay_pending_count'] ?? 0);
        $essayGradedCount = (int) ($row['essay_graded_count'] ?? 0);
        $objectiveScore = $row['objective_score'] !== null ? (float) $row['objective_score'] : null;
        $objectiveTotal = $row['objective_total'] !== null ? (float) $row['objective_total'] : null;
        $essayScore = $essayGradedCount > 0 ? (float) $row['essay_score'] : null;
        $essayTotal = $essayQuestionCount > 0 ? (float) $row['essay_total'] : null;

        if (!$attempted && $transferred) {
            $objectiveDisplay = 'TR';
            $essayDisplay = 'TR';
            $assessmentStatus = 'เทียบโอน (TR)';
        } elseif (!$attempted) {
            $objectiveDisplay = '—';
            $essayDisplay = '—';
            $assessmentStatus = 'ยังไม่สอบ';
        } else {
            $objectiveDisplay = $objectiveTotal !== null && $objectiveTotal > 0
                ? number_format($objectiveScore ?? 0, 0) . ' / ' . number_format($objectiveTotal, 0)
                : '—';
            if ($essayQuestionCount === 0) {
                $essayDisplay = '—';
            } elseif ($essayScore !== null) {
                $essayDisplay = number_format($essayScore, 0) . ' / ' . number_format($essayTotal ?? 0, 0);
            } else {
                $essayDisplay = 'รอตรวจ';
            }
            $gradedScore = ($objectiveScore ?? 0) + ($essayScore ?? 0);
            $gradedTotal = ($objectiveTotal ?? 0) + ($essayTotal ?? 0);
            if ($essayPendingCount > 0) {
                $assessmentStatus = 'รอครูตรวจข้อเขียน (' . $essayPendingCount . ' ข้อ)';
            } elseif ($gradedTotal > 0 && $gradedScore / $gradedTotal < 0.6) {
                $assessmentStatus = 'ต้องซ่อมเสริม (คะแนนต่ำ)';
            } else {
                $assessmentStatus = 'ตรวจครบเรียบร้อย';
            }
        }
        $gradingRows[] = [
            'student_id' => $studentId,
            'student_name' => $students[$studentId]['name'],
            'class_name' => $students[$studentId]['class'],
            'course_name' => (string) $row['course_name'],
            'lesson_id' => $lessonId,
            'position' => (int) $row['position'],
            'lesson_name' => (string) $row['lesson_name'],
            'objective_display' => $objectiveDisplay,
            'essay_display' => $essayDisplay,
            'total_display' => $attempted
                ? number_format(($objectiveScore ?? 0) + ($essayScore ?? 0), 0) . ' / ' . number_format(($objectiveTotal ?? 0) + ($essayTotal ?? 0), 0)
                : '—',
            'essay_pending' => $attempted && $essayPendingCount > 0,
            'assessment_status' => $assessmentStatus,
        ];
        if ($attempted && $totalScore !== null && $totalScore > 0) {
            $lessonStats[$key]['score_sum'] += $score;
            $lessonStats[$key]['max_score_sum'] += $totalScore;
            $lessonStats[$key]['attempts']++;
            $scoreSum += $score;
            $totalScoreSum += $totalScore;
            $averageSampleCount++;
        }
    }
    $overallAverage = $averageSampleCount > 0 ? (int) round($scoreSum / $averageSampleCount) : 0;
    $overallAverageMaxScore = $averageSampleCount > 0 ? (int) round($totalScoreSum / $averageSampleCount) : 0;
    foreach ($students as &$student) {
        $student['progress'] = $student['total'] > 0 ? (int) round($student['completed'] * 100 / $student['total']) : 0;
    }
    unset($student);
    foreach ($lessonStats as &$lesson) {
        $lesson['average'] = $lesson['attempts'] > 0 ? (int) round($lesson['score_sum'] / $lesson['attempts']) : null;
        $lesson['average_max_score'] = $lesson['attempts'] > 0 ? (int) round($lesson['max_score_sum'] / $lesson['attempts']) : null;
        $lesson['average_percent'] = $lesson['attempts'] > 0 && $lesson['max_score_sum'] > 0
            ? ($lesson['score_sum'] / $lesson['max_score_sum']) * 100
            : null;
        unset($lesson['score_sum']);
        unset($lesson['max_score_sum']);
    }
    unset($lesson);

    usort($gradingRows, static function (array $left, array $right) use ($students): int {
        $studentOrder = strcmp($students[$left['student_id']]['name'], $students[$right['student_id']]['name']);
        if ($studentOrder !== 0) return $studentOrder;
        $studentIdOrder = strcmp($left['student_id'], $right['student_id']);
        if ($studentIdOrder !== 0) return $studentIdOrder;
        $courseOrder = strcmp($left['course_name'], $right['course_name']);
        if ($courseOrder !== 0) return $courseOrder;
        return $left['position'] <=> $right['position'];
    });
    $gradingRowsByStudent = [];
    foreach ($gradingRows as $gradingRow) {
        $gradingRowsByStudent[$gradingRow['student_id']][] = $gradingRow;
    }

    $essayStmt = $conn->prepare(
        "SELECT COUNT(*) FILTER (WHERE aa.review_status = 'pending') AS pending,
                COUNT(*) FILTER (WHERE aa.review_status = 'graded') AS graded
         FROM public.quiz_attempt_answers aa
         INNER JOIN public.quiz_attempts a ON a.attempt_id = aa.attempt_id
         INNER JOIN public.questions q ON q.question_id = aa.question_id
         INNER JOIN public.lessons l ON l.lesson_id = a.lesson_id
         INNER JOIN public.course_teachers ct ON ct.course_id = l.course_id
         WHERE ct.teacher_id = :teacher_id AND q.question_type = 'essay'
           AND aa.review_status IN ('pending', 'graded')"
    );
    $essayStmt->execute([':teacher_id' => $teacherId]);
    $essayCounts = $essayStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $essayPending = (int) ($essayCounts['pending'] ?? 0);
    $essayGraded = (int) ($essayCounts['graded'] ?? 0);

}

$totalLessons = count($lessonStats);
$totalStudents = count($students);
function teacherReportEscape(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Teacher Performance &amp; Analytics Report</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/theme.css?v=20261020-toa-blue-theme">
    <style>
        :root{--ink:#172033;--muted:#64748b;--line:#e2e8f0;--panel:#fff;--page:#f4f7fb;--blue:#1d4ed8;--green:#15803d;--red:#b91c1c;--amber:#223850}
        *{box-sizing:border-box}body{margin:0;background:var(--page);color:var(--ink);font-family:Kanit,Arial,sans-serif}.wrap{width:min(1440px,100%);margin:auto;padding:30px}.top{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:20px}.top h1{font-size:26px;margin:0}.top p{margin:5px 0 0;color:var(--muted)}.actions{display:flex;gap:10px}.button{display:inline-flex;align-items:center;justify-content:center;border:1px solid var(--line);border-radius:9px;background:#fff;color:var(--ink);padding:9px 14px;text-decoration:none;font:inherit;cursor:pointer}.button.primary{background:#1d4ed8;border-color:#1d4ed8;color:white}.identity,.panel,.kpi{background:var(--panel);border:1px solid var(--line);border-radius:14px;box-shadow:0 5px 20px #0f172a08}.identity{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;padding:18px 22px}.identity strong{font-weight:600}.identity-item{display:grid;gap:3px;align-content:center;min-width:0}.label{font-size:12px;color:var(--muted)}.kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:16px 0}.kpi{padding:18px}.kpi-label{font-size:12px;color:var(--muted)}.kpi-value{font-size:30px;font-weight:600;line-height:1.2;margin:7px 0}.kpi-sub{font-size:11px;color:var(--muted)}.panel{padding:20px;margin:16px 0}.panel-head{display:flex;justify-content:space-between;align-items:end;gap:12px;margin-bottom:12px}.panel h2{font-size:18px;margin:0}.panel-description{color:var(--muted);font-size:12px;margin:4px 0 0}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;min-width:720px}th,td{text-align:left;padding:10px 11px;border-bottom:1px solid #edf1f5;vertical-align:top}th{font-weight:500;color:#526179;font-size:12px;white-space:nowrap}td{font-size:13px}.num{text-align:right;font-variant-numeric:tabular-nums}.mono{font-variant-numeric:tabular-nums}.badge-list{display:flex;flex-wrap:wrap;gap:5px;min-width:200px}.badge{display:inline-flex;align-items:center;gap:4px;padding:4px 8px;border-radius:7px;font-size:11px;white-space:nowrap;background:#e6f4ea;color:#1e8e3e}.badge.transfer{background:#e8f0fe;color:#1a73e8}.badge.pending{background:#f1f3f4;color:#5f6368}.status-badge{display:inline-block;padding:4px 8px;border-radius:7px;font-size:11px;line-height:1.4}.status-badge.pending{background:#EEF4F8;color:#223850}.status-badge.complete{background:#e6f4ea;color:#1e8e3e}.status-badge.remedial{background:#fee2e2;color:#b91c1c}.status-badge.transfer{background:#e8f0fe;color:#1a73e8}.status-badge.not-started{background:#f1f3f4;color:#5f6368}.progress-cell{min-width:150px}.progress-line{height:7px;background:#e8edf3;border-radius:9px;overflow:hidden;margin:4px 0}.progress-line span{display:block;height:100%;background:linear-gradient(90deg,#2563eb,#60a5fa)}.muted{color:var(--muted)}.empty{text-align:center;color:var(--muted);padding:22px}.footnote{font-size:11px;color:var(--muted);margin-top:12px}
        .embed{background:transparent}.embed .wrap{width:100%;padding:10px}.embed .top,.embed .actions{display:none}
        @media(max-width:900px){.kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.wrap{padding:18px}.embed .wrap{padding:8px}}
        @media(max-width:560px){.wrap{padding:12px}.top{align-items:flex-start}.top h1{font-size:21px}.kpis{gap:8px}.kpi{padding:13px}.kpi-value{font-size:23px}.identity{grid-template-columns:1fr;gap:12px;padding:14px}.actions{flex-direction:column}}
        .student-group-cell{vertical-align:middle;background:#f8fafc}.student-group-start td{border-top:2px solid #cbd5e1}
        .report-table th,.report-table td{padding:12px 14px;border-bottom:1px solid #eaeaea}.report-table thead th{background:#f8fafc}.report-table tbody tr:nth-child(even){background:#fcfcfd}.report-table .center{text-align:center!important;vertical-align:middle}.course-group-cell{vertical-align:middle;background:#f8fafc;font-weight:500}.tr-score-cell{text-align:center!important;vertical-align:middle;color:#475569;background:transparent!important}.transfer-note{display:inline-block;padding:4px 9px;border:1px solid #dbe3ef;border-radius:999px;background:#f8fafc;color:#475569;font-size:11px;font-weight:500;white-space:nowrap}.total-subnote{display:block;color:var(--muted);font-size:10px;white-space:nowrap}
        @media print{ @page{size:A4 landscape;margin:12mm}body{background:#fff;color:#111;font-size:10pt}.wrap{width:100%;padding:0}.top{margin-bottom:10px}.top h1{font-size:19pt}.actions{display:none}.identity,.panel,.kpi{box-shadow:none;border-color:#cbd5e1;break-inside:avoid}.identity{grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.kpis{gap:8px;margin:8px 0}.kpi{padding:10px}.kpi-value{font-size:20pt}.panel{padding:12px;margin:9px 0}.table-wrap{overflow:visible}table{min-width:0;font-size:8pt}th,td{padding:6px 7px}.badge-list{min-width:0}.badge{font-size:7pt;padding:2px 5px}.student-group-cell{background:transparent}.panel table{break-inside:auto}tr{break-inside:avoid;break-after:auto}thead{display:table-header-group}a{color:inherit;text-decoration:none}}
    </style>
    <link rel="stylesheet" href="assets/css/responsive.css">
</head>
<body class="<?= $isEmbedded ? 'embed' : '' ?>">
<main class="wrap">
    <header class="top">
        <div><h1>Operational Summary</h1><p>รายงานสรุปการทำงานและสถานะนักเรียนสำหรับอาจารย์</p></div>
        <div class="actions"><a class="button" href="teacherdash.php">กลับหน้าจัดการรายวิชา</a><button class="button primary" type="button" onclick="window.print()">พิมพ์ / บันทึกเป็น PDF</button></div>
    </header>
    <section class="identity" aria-label="ข้อมูลรายงาน">
        <div class="identity-item"><span class="label">อาจารย์ผู้สอน</span><strong><?= teacherReportEscape($teacherName) ?></strong></div>
        <div class="identity-item"><span class="label">รายวิชาที่รับผิดชอบ</span><strong><?= $courseNames ? teacherReportEscape(implode(' · ', $courseNames)) : 'ยังไม่มีรายวิชาที่ได้รับมอบหมาย' ?></strong></div>
        <div class="identity-item"><span class="label">จัดทำรายงานเมื่อ</span><strong><?= $reportDate->format('d/m/') . ((int) $reportDate->format('Y') + 543) ?></strong></div>
    </section>

    <section class="kpis" aria-label="สรุปตัวชี้วัด">
        <article class="kpi"><div class="kpi-label">นักเรียนที่ได้รับมอบหมาย</div><div class="kpi-value"><?= $totalStudents ?></div><div class="kpi-sub">คนในรายวิชาที่อาจารย์รับผิดชอบ</div></article>
        <article class="kpi"><div class="kpi-label">บทเรียนทั้งหมด</div><div class="kpi-value"><?= $totalLessons ?></div><div class="kpi-sub">จาก <?= count($courses) ?> รายวิชา</div></article>
        <article class="kpi"><div class="kpi-label">คะแนนดิบเฉลี่ยรวม</div><div class="kpi-value"><?= number_format($overallAverage, 0) ?> / <?= number_format($overallAverageMaxScore, 0) ?></div><div class="kpi-sub">คะแนนเฉลี่ย / คะแนนเต็มเฉลี่ยจากผลสอบล่าสุดรายบท</div></article>
        <article class="kpi"><div class="kpi-label">การตรวจข้อเขียน</div><div class="kpi-value"><?= $essayPending ?> / <?= $essayGraded ?></div><div class="kpi-sub">รอตรวจ / ตรวจแล้ว (ข้อ)</div></article>
    </section>

    <section class="panel">
        <div class="panel-head"><div><h2>ผลการเรียนแยกตามบทเรียน</h2><p class="panel-description">คะแนนดิบเฉลี่ยจากผลสอบครั้งล่าสุดของนักเรียนที่มีผลในบทนั้น</p></div></div>
        <div class="table-wrap"><table class="report-table"><thead><tr><th>รายวิชา</th><th>บทเรียน</th><th class="center">คะแนนเฉลี่ย</th><th class="center">ผู้ทำข้อสอบ</th><th class="center">ข้อสังเกต</th></tr></thead><tbody>
        <?php $lessonStatsByCourse = []; foreach ($lessonStats as $lesson) $lessonStatsByCourse[$lesson['course']][] = $lesson; foreach ($lessonStatsByCourse as $courseName => $courseLessons): foreach ($courseLessons as $lessonIndex => $lesson): ?><tr><?php if ($lessonIndex === 0): ?><td class="course-group-cell" rowspan="<?= count($courseLessons) ?>"><?= teacherReportEscape($courseName) ?></td><?php endif; ?><td>บท <?= (int) $lesson['position'] ?> · <?= teacherReportEscape($lesson['name']) ?></td><td class="center mono"><?= $lesson['average'] === null ? '—' : number_format((int) $lesson['average'], 0) . ' / ' . number_format((int) $lesson['average_max_score'], 0) ?></td><td class="center mono"><?= (int) $lesson['attempts'] ?></td><td class="center"><?= $lesson['average'] === null ? '<span class="muted">ยังไม่มีผลสอบ</span>' : ($lesson['average_percent'] < 60 ? '<span style="color:#b91c1c">ควรทบทวน</span>' : '<span style="color:#15803d">ผลการเรียนอยู่ในเกณฑ์</span>') ?></td></tr><?php endforeach; endforeach; ?>
        <?php if (!$lessonStats): ?><tr><td class="empty" colspan="5">ยังไม่มีบทเรียนในรายวิชาที่รับผิดชอบ</td></tr><?php endif; ?></tbody></table></div>
    </section>

    <section class="panel">
        <div class="panel-head"><div><h2>สรุปคะแนนและสถานะการตรวจรายบุคคล</h2><p class="panel-description">แสดงคะแนนข้อกา/ข้อเขียนจากการสอบครั้งล่าสุด พร้อมความคืบหน้าที่นับบทส่งข้อสอบแล้วและ TR</p></div></div>
        <div class="table-wrap"><table class="report-table"><thead><tr><th>ชื่อ-นามสกุล / รหัส</th><th>บทเรียน</th><th class="center">คะแนนข้อกา (ปรนัย)</th><th class="center">คะแนนข้อเขียน (อัตนัย)</th><th class="center">คะแนนรวม</th><th class="center">สถานะการประเมิน</th><th class="center">ความคืบหน้า</th></tr></thead><tbody>
        <?php foreach ($gradingRowsByStudent as $studentId => $studentRows): $student = $students[$studentId]; $rowspan = count($studentRows); foreach ($studentRows as $rowIndex => $row): $statusClass = match (true) { str_starts_with($row['assessment_status'], 'รอครูตรวจ') => 'pending', $row['assessment_status'] === 'ตรวจครบเรียบร้อย' => 'complete', $row['assessment_status'] === 'ต้องซ่อมเสริม (คะแนนต่ำ)' => 'remedial', $row['assessment_status'] === 'เทียบโอน (TR)' => 'transfer', default => 'not-started' }; ?><tr class="<?= $rowIndex === 0 ? 'student-group-start' : '' ?>">
            <?php if ($rowIndex === 0): ?><td class="student-group-cell" rowspan="<?= $rowspan ?>"><strong><?= teacherReportEscape($student['name']) ?></strong><br><span class="muted"><?= teacherReportEscape($student['id']) ?> · <?= teacherReportEscape($student['class']) ?></span></td><?php endif; ?>
            <td><strong>บท <?= (int) $row['position'] ?> · <?= teacherReportEscape($row['lesson_name']) ?></strong><br><span class="muted"><?= teacherReportEscape($row['course_name']) ?></span></td>
            <?php if ($row['assessment_status'] === 'เทียบโอน (TR)'): ?><td colspan="3" class="tr-score-cell"><span class="transfer-note">TR (ผ่านการเทียบโอน)</span></td><?php else: ?>
            <td class="center mono"><?= teacherReportEscape($row['objective_display']) ?></td>
            <td class="center mono"><?php if ($row['essay_pending'] && $row['essay_display'] !== 'รอตรวจ'): ?><?= teacherReportEscape($row['essay_display']) ?><br><?php endif; ?><?php if ($row['essay_pending']): ?><span class="status-badge pending">รอตรวจ</span><?php else: ?><?= teacherReportEscape($row['essay_display']) ?><?php endif; ?></td>
            <td class="center mono"><?= teacherReportEscape($row['total_display']) ?><?php if ($row['essay_pending']): ?><span class="total-subnote">ยังไม่รวมข้อเขียนที่รอตรวจ</span><?php endif; ?></td><?php endif; ?>
            <td class="center"><span class="status-badge <?= $statusClass ?>"><?= teacherReportEscape($row['assessment_status']) ?></span></td>
            <?php if ($rowIndex === 0): ?><td class="student-group-cell" rowspan="<?= $rowspan ?>"><strong><?= (int) $student['progress'] ?>%</strong><br><span class="muted">สำเร็จ <?= (int) $student['completed'] ?> / <?= (int) $student['total'] ?> บท</span></td><?php endif; ?>
        </tr><?php endforeach; endforeach; ?>
        <?php if (!$gradingRows): ?><tr><td class="empty" colspan="7">ยังไม่มีนักเรียนหรือบทเรียนในรายวิชาที่รับผิดชอบ</td></tr><?php endif; ?></tbody></table></div>
    </section>
    <p class="footnote">คะแนนเฉลี่ยของนักเรียนคำนวณเป็นเปอร์เซ็นต์จากผลสอบล่าสุดรายบท · TR นับเป็นบทเรียนสำเร็จ</p>
</main>
<script src="assets/js/responsive.js?v=20261021-responsive" defer></script>
</body>
</html>
