<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (($_SESSION['role'] ?? '') !== 'director' || empty($_SESSION['user_id'])) {
    http_response_code(403);
    exit('ไม่มีสิทธิ์เข้าถึงรายงานนี้');
}

require_once __DIR__ . '/config/db.php';

$authorization = $conn->prepare("SELECT 1 FROM public.users WHERE user_id = :user_id AND role = 'director' AND COALESCE(account_status, 'active') = 'active'");
$authorization->execute([':user_id' => (string) $_SESSION['user_id']]);
if (!$authorization->fetchColumn()) {
    http_response_code(403);
    exit('ไม่มีสิทธิ์เข้าถึงรายงานนี้');
}

$rows = $conn->query("SELECT s.user_id, s.full_name, s.student_level, c.name AS course_name,
       COALESCE(progress.lesson_count, 0) AS lesson_count,
       COALESCE(progress.completed_lessons, 0) AS completed_lessons,
       COALESCE(progress.progress_percent, 0) AS progress_percent,
       quiz.average_score_percent,
       activity.last_activity_at
FROM public.students s
LEFT JOIN public.student_courses sc ON sc.student_id = s.user_id AND sc.status = 'active'
LEFT JOIN public.courses c ON c.course_id = sc.course_id
LEFT JOIN LATERAL (
    SELECT COUNT(l.lesson_id)::int AS lesson_count,
           COUNT(l.lesson_id) FILTER (WHERE COALESCE(lp.is_completed, FALSE))::int AS completed_lessons,
           CASE WHEN COUNT(l.lesson_id) = 0 THEN 0
                ELSE ROUND(100.0 * COUNT(l.lesson_id) FILTER (WHERE COALESCE(lp.is_completed, FALSE)) / COUNT(l.lesson_id), 1)
           END AS progress_percent
    FROM public.lessons l
    LEFT JOIN public.lesson_progress_summary lp ON lp.lesson_id = l.lesson_id AND lp.student_id = s.user_id
    WHERE l.course_id = c.course_id
) progress ON TRUE
LEFT JOIN LATERAL (
    SELECT ROUND(AVG(100.0 * latest.score / NULLIF(latest.total_score, 0)), 1) AS average_score_percent
    FROM (
        SELECT DISTINCT ON (qa.lesson_id) qa.lesson_id, qa.score, qa.total_score
        FROM public.quiz_attempts qa
        INNER JOIN public.lessons ql ON ql.lesson_id = qa.lesson_id AND ql.course_id = c.course_id
        WHERE qa.student_id = s.user_id
        ORDER BY qa.lesson_id, qa.submitted_at DESC NULLS LAST, qa.attempt_number DESC
    ) latest
) quiz ON TRUE
LEFT JOIN LATERAL (
    SELECT MAX(COALESCE(lp.last_activity_at, lp.updated_at)) AS last_activity_at
    FROM public.lessons al
    LEFT JOIN public.lesson_progress_summary lp ON lp.lesson_id = al.lesson_id AND lp.student_id = s.user_id
    WHERE al.course_id = c.course_id
) activity ON TRUE
ORDER BY s.full_name NULLS LAST, s.user_id, c.name NULLS FIRST")->fetchAll(PDO::FETCH_ASSOC);

$dashboardStats = $conn->query("SELECT
    COUNT(*) FILTER (WHERE role = 'student')::int AS students,
    COUNT(*) FILTER (WHERE role = 'teacher')::int AS teachers,
    COUNT(*) FILTER (WHERE role IN ('staff', 'director'))::int AS staff
    FROM public.users")->fetch(PDO::FETCH_ASSOC) ?: [];
$dashboardCourseCount = (int) $conn->query('SELECT COUNT(*) FROM public.courses')->fetchColumn();
$courseChartRows = $conn->query("SELECT c.name, COUNT(sc.student_id) FILTER (WHERE sc.status = 'active')::int AS student_count
    FROM public.courses c
    LEFT JOIN public.student_courses sc ON sc.course_id = c.course_id
    GROUP BY c.course_id, c.name
    ORDER BY COUNT(sc.student_id) FILTER (WHERE sc.status = 'active') DESC, c.name")->fetchAll(PDO::FETCH_ASSOC) ?: [];
require_once __DIR__ . '/director_graduation_status.php';
$graduationStatusCounts = directorGraduationStatusCounts($conn);
$inactiveStudents = $conn->query("WITH student_activity AS (
    SELECT s.user_id, s.full_name, s.student_level, s.created_at,
           MAX(COALESCE(lp.last_activity_at, lp.updated_at)) AS last_learning_activity,
           MAX(sc.enrolled_at) FILTER (WHERE sc.status = 'active') AS last_enrolled_at
    FROM public.students s
    LEFT JOIN public.student_courses sc ON sc.student_id = s.user_id
    LEFT JOIN public.lessons l ON l.course_id = sc.course_id
    LEFT JOIN public.lesson_progress_summary lp ON lp.student_id = s.user_id AND lp.lesson_id = l.lesson_id
    GROUP BY s.user_id, s.full_name, s.student_level, s.created_at
), activity_dates AS (
    SELECT *, COALESCE(last_learning_activity, last_enrolled_at, created_at) AS last_activity_at FROM student_activity
)
SELECT user_id, full_name, last_activity_at,
       EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - last_activity_at)) / 86400.0 AS inactive_days
FROM activity_dates WHERE last_activity_at < CURRENT_TIMESTAMP - INTERVAL '7 days'
ORDER BY last_activity_at ASC NULLS FIRST LIMIT 5")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$activities = $conn->query("SELECT activity_type, title, occurred_at FROM (
    SELECT 'นักเรียนใหม่'::text AS activity_type, s.full_name AS title, s.created_at AS occurred_at FROM public.students s
    UNION ALL
    SELECT 'รายวิชาใหม่'::text AS activity_type, c.name AS title, c.created_at AS occurred_at FROM public.courses c
) recent ORDER BY occurred_at DESC NULLS LAST LIMIT 5")->fetchAll(PDO::FETCH_ASSOC) ?: [];

function directorReportEscape(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$studentCount = count(array_unique(array_column($rows, 'user_id')));
$courseCount = count(array_unique(array_filter(array_column($rows, 'course_name'), static fn ($name): bool => $name !== null && $name !== '')));
$progressValues = array_map(static fn ($row): float => (float) $row['progress_percent'], $rows);
$scoredRows = array_filter($rows, static fn ($row): bool => $row['average_score_percent'] !== null);
$averageProgress = $progressValues ? array_sum($progressValues) / count($progressValues) : 0;
$averageScore = $scoredRows ? array_sum(array_map(static fn ($row): float => (float) $row['average_score_percent'], $scoredRows)) / count($scoredRows) : null;
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>รายงานภาพรวมการเรียน - Flexible Learning Hub</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    *{box-sizing:border-box}body{margin:0;background:#f2f7fd;color:#203451;font-family:'IBM Plex Sans Thai',sans-serif}.report{max-width:1200px;margin:28px auto;padding:30px;background:#fff;border:1px solid #dfe8f2;border-radius:16px;box-shadow:0 8px 24px rgba(37,73,111,.055)}.report-page{min-height:760px}.report-header{display:flex;justify-content:space-between;align-items:center;gap:20px;border-bottom:2px solid #397fbd;padding:0 0 16px}.brand{display:flex;align-items:center;gap:14px}.brand img{width:175px;height:auto}.eyebrow{margin:0 0 4px;color:#397fbd;font-size:11px;font-weight:700;letter-spacing:.12em}.report h1{margin:0;font-size:25px}.meta{color:#687b96;font-size:12px}.summary{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:18px 0}.metric,.panel{background:#fff;border:1px solid #dfe8f2;border-radius:12px;box-shadow:0 5px 16px rgba(37,73,111,.04)}.metric{padding:14px 16px}.metric span,.metric strong{display:block}.metric span{color:#687b96;font-size:12px}.metric strong{margin-top:4px;color:#397fbd;font-size:23px}.report h2{font-size:16px;margin:0}.report-panels{display:grid;grid-template-columns:1.25fr .85fr;gap:14px;margin-bottom:14px;align-items:stretch}.panel{padding:16px;min-width:0}.panel-heading{margin-bottom:10px}.panel-heading h2{font-size:15px}.panel-heading p{margin:3px 0 0;color:#687b96;font-size:11px}.chart-wrap{position:relative;height:255px}.donut-wrap{position:relative;height:210px}.legend{display:grid;gap:5px;padding-top:4px}.legend span{display:flex;justify-content:space-between;color:#687b96;font-size:11px}.legend strong{color:#203451}.lower-panels{display:grid;grid-template-columns:1.2fr .8fr;gap:14px}.compact-table{width:100%;border-collapse:collapse;font-size:10px}.compact-table th,.compact-table td{padding:6px;border-bottom:1px solid #e8eff7;text-align:left;vertical-align:top}.compact-table th{color:#687b96;font-weight:600}.compact-table td:last-child{text-align:right;white-space:nowrap}.activity{display:grid;grid-template-columns:1fr auto;gap:3px 8px;padding:7px 0;border-bottom:1px solid #e8eff7;font-size:10px}.activity strong{font-size:11px}.activity time{color:#8b9ab0;white-space:nowrap}.empty{color:#687b96;text-align:center;padding:22px 8px;font-size:12px}.actions{display:flex;justify-content:flex-end;gap:10px;margin-bottom:12px}.actions button,.actions a{border:0;border-radius:8px;padding:10px 14px;background:#397fbd;color:#fff;font:inherit;font-size:13px;text-decoration:none;cursor:pointer}.actions a{background:#edf5fc;color:#28689f}.hint{margin:0 0 12px;color:#687b96;font-size:12px;text-align:right}.table-page{break-before:page;page-break-before:always}.table-page h2{margin:0 0 4px}.table-page .meta{display:block;margin-bottom:12px}.detail-table{width:100%;border-collapse:collapse;font-size:9.5pt}.detail-table thead{display:table-header-group}.detail-table th{background:#edf5fc;color:#385573;font-weight:600}.detail-table th,.detail-table td{padding:8px 7px;border:1px solid #dfe8f2;text-align:left;vertical-align:top}.detail-table td.numeric,.detail-table th.numeric{text-align:center;white-space:nowrap}@page{size:A4 landscape;margin:10mm}@media print{body{background:#fff}.report{max-width:none;margin:0;padding:0;border:0;border-radius:0;box-shadow:none}.report-page{min-height:0;height:185mm;break-after:page;page-break-after:always}.report-header{padding-bottom:8px}.brand img{width:145px}.summary{margin:12px 0;gap:8px}.metric{padding:9px 12px;box-shadow:none}.metric strong{font-size:20px}.panel{padding:11px;box-shadow:none}.chart-wrap{height:220px}.donut-wrap{height:185px}.lower-panels{gap:10px}.compact-table{font-size:9px}.activity{font-size:9px;padding:5px 0}.activity strong{font-size:10px}.table-page{break-before:auto;page-break-before:auto}.detail-table{font-size:9pt}.detail-table th,.detail-table td{padding:6px 5px}.actions,.hint{display:none}}@media(max-width:700px){.report{margin:10px;padding:18px}.brand img{width:125px}.report-header{align-items:flex-start}.summary{grid-template-columns:repeat(2,1fr)}.report-panels,.lower-panels{grid-template-columns:1fr}.report-page{min-height:0}.chart-wrap{height:260px}.table-page{overflow-x:auto}.detail-table{min-width:900px}}
  </style>
</head>
<body>
  <main class="report">
    <div class="actions"><a href="director_dash.php">กลับหน้า Dashboard</a><button type="button" onclick="window.print()">พิมพ์ / บันทึกเป็น PDF</button></div>
    <p class="hint">ในหน้าต่างพิมพ์ เลือกปลายทางเป็น “บันทึกเป็น PDF” เพื่อดาวน์โหลดรายงาน</p>
    <section class="report-page">
      <header class="report-header"><div class="brand"><img src="images/flexible-learning-hub-logo-transparent.png" alt="Flexible Learning Hub"><div><p class="eyebrow">EXECUTIVE OVERVIEW</p><h1>ภาพรวมระบบการเรียน</h1><span class="meta">ข้อมูล ณ <?= date('d/m/Y H:i') ?></span></div></div><span class="meta">รายงานสำหรับผู้บริหาร</span></header>
      <section class="summary" aria-label="สถิติภาพรวม">
        <div class="metric"><span>นักเรียนทั้งหมด</span><strong><?= number_format((int) ($dashboardStats['students'] ?? 0)) ?></strong></div>
        <div class="metric"><span>ครูผู้สอน</span><strong><?= number_format((int) ($dashboardStats['teachers'] ?? 0)) ?></strong></div>
        <div class="metric"><span>รายวิชาทั้งหมด</span><strong><?= number_format($dashboardCourseCount) ?></strong></div>
        <div class="metric"><span>เจ้าหน้าที่และผู้บริหาร</span><strong><?= number_format((int) ($dashboardStats['staff'] ?? 0)) ?></strong></div>
      </section>
      <section class="report-panels">
        <article class="panel"><div class="panel-heading"><h2>นักเรียนลงทะเบียนรายวิชา</h2><p>นับเฉพาะการลงทะเบียนที่ยังใช้งานอยู่</p></div><div class="chart-wrap"><canvas id="courseChart"></canvas></div></article>
        <article class="panel"><div class="panel-heading"><h2>สถานะการยื่นจบการศึกษา</h2><p>แสดงเฉพาะนักเรียนในขั้นตอนการยื่นจบ</p></div><div class="donut-wrap"><canvas id="completionChart"></canvas></div><div class="legend"><?php foreach ($graduationStatusCounts as $label => $count): ?><span><?= directorReportEscape($label) ?><strong><?= number_format($count) ?> คน</strong></span><?php endforeach; ?></div></article>
      </section>
      <section class="lower-panels">
        <article class="panel"><div class="panel-heading"><h2>นักเรียนที่ไม่มีความเคลื่อนไหวเกิน 7 วัน</h2></div>
          <?php if (!$inactiveStudents): ?><p class="empty">ไม่พบนักเรียนที่ไม่มีความเคลื่อนไหวเกิน 7 วัน</p><?php else: ?><table class="compact-table"><thead><tr><th>นักเรียน</th><th>รหัส</th><th>กิจกรรมล่าสุด</th><th>ไม่ได้ใช้งาน</th></tr></thead><tbody><?php foreach ($inactiveStudents as $student): ?><tr><td><?= directorReportEscape($student['full_name']) ?></td><td><?= directorReportEscape($student['user_id']) ?></td><td><?= directorReportEscape($student['last_activity_at'] ? date('d/m/Y', strtotime((string) $student['last_activity_at'])) : '-') ?></td><td><?= max(0, (int) floor((float) $student['inactive_days'])) ?> วัน</td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
        </article>
        <article class="panel"><div class="panel-heading"><h2>กิจกรรมล่าสุด</h2><p>นักเรียนสมัครใหม่และรายวิชาที่เพิ่ม</p></div>
          <?php if (!$activities): ?><p class="empty">ยังไม่มีกิจกรรมล่าสุด</p><?php else: ?><?php foreach ($activities as $activity): ?><div class="activity"><strong><?= directorReportEscape($activity['activity_type']) ?> · <?= directorReportEscape($activity['title']) ?></strong><time><?= directorReportEscape($activity['occurred_at'] ? date('d/m H:i', strtotime((string) $activity['occurred_at'])) : '-') ?></time></div><?php endforeach; ?><?php endif; ?>
        </article>
      </section>
    </section>
    <section class="table-page"><h2>ความคืบหน้ารายวิชาของนักเรียน</h2><span class="meta">นักเรียน <?= number_format($studentCount) ?> คน · รายวิชา <?= number_format($courseCount) ?> วิชา · ความคืบหน้าเฉลี่ย <?= number_format($averageProgress, 1) ?>% · คะแนนเฉลี่ย <?= $averageScore === null ? '-' : number_format($averageScore, 1) . '%' ?></span><table class="detail-table">
      <thead><tr><th>รหัสนักเรียน</th><th>ชื่อ-นามสกุล</th><th>รายวิชา</th><th class="numeric">เรียนจบ / ทั้งหมด</th><th class="numeric">ความคืบหน้า</th><th class="numeric">คะแนนเฉลี่ยข้อสอบ</th><th>กิจกรรมล่าสุด</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td><?= directorReportEscape($row['user_id']) ?></td>
          <td><?= directorReportEscape($row['full_name']) ?></td>
          <td><?= directorReportEscape($row['course_name'] ?? 'ยังไม่ลงทะเบียน') ?></td>
          <td class="numeric"><?= (int) $row['completed_lessons'] ?> / <?= (int) $row['lesson_count'] ?></td>
          <td class="numeric"><?= number_format((float) $row['progress_percent'], 1) ?>%</td>
          <td class="numeric"><?= $row['average_score_percent'] === null ? '-' : number_format((float) $row['average_score_percent'], 1) . '%' ?></td>
          <td><?= directorReportEscape($row['last_activity_at'] ? date('d/m/Y H:i', strtotime((string) $row['last_activity_at'])) : '-') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($rows === []): ?><tr><td colspan="7" style="text-align:center">ยังไม่มีข้อมูลนักเรียน</td></tr><?php endif; ?>
      </tbody>
    </table></section>
  </main>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
  <script>
    const courseRows = <?= json_encode($courseChartRows, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const graduationStatusCounts = <?= json_encode($graduationStatusCounts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    if (window.Chart) {
      new Chart(document.getElementById('courseChart'), {type:'bar',data:{labels:courseRows.map(row=>row.name),datasets:[{data:courseRows.map(row=>Number(row.student_count)),backgroundColor:'rgba(65,145,221,.78)',borderColor:'#3576b9',borderWidth:1,borderRadius:6}]},options:{indexAxis:'y',maintainAspectRatio:false,responsive:true,animation:false,plugins:{legend:{display:false}},scales:{x:{beginAtZero:true,ticks:{precision:0,color:'#52647d'},grid:{color:'#e8eff7'}},y:{grid:{display:false},ticks:{color:'#52647d'}}}}});
      new Chart(document.getElementById('completionChart'), {type:'doughnut',data:{labels:Object.keys(graduationStatusCounts),datasets:[{data:Object.values(graduationStatusCounts),backgroundColor:['#10B981','#F59E0B','#EF4444','#3B82F6'],borderColor:'#fff',borderWidth:3}]},options:{maintainAspectRatio:false,responsive:true,animation:false,cutout:'68%',plugins:{legend:{position:'bottom',labels:{color:'#52647d',padding:12,usePointStyle:true,font:{size:10}}}}}});
    }
    window.addEventListener('load', () => setTimeout(() => window.print(), 500));
  </script>
</body>
</html>
