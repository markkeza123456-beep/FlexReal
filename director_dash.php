<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (($_SESSION['role'] ?? '') !== 'director' || empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/db.php';

$userId = (string) $_SESSION['user_id'];
$authorization = $conn->prepare("SELECT 1 FROM public.users WHERE user_id = :user_id AND role = 'director' AND COALESCE(account_status, 'active') = 'active'");
$authorization->execute([':user_id' => $userId]);
if (!$authorization->fetchColumn()) {
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit;
}

function directorEscape(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$profile = $conn->prepare('SELECT first_name, last_name, avatar_url FROM public.staff WHERE user_id = :user_id LIMIT 1');
$profile->execute([':user_id' => $userId]);
$profileRow = $profile->fetch(PDO::FETCH_ASSOC) ?: [];
$directorName = trim((string) ($profileRow['first_name'] ?? '') . ' ' . (string) ($profileRow['last_name'] ?? '')) ?: 'ผู้บริหาร';
$directorAvatarUrl = trim((string) ($profileRow['avatar_url'] ?? ''));
$directorInitial = mb_substr($directorName, 0, 1, 'UTF-8');

$kpis = $conn->query("SELECT
    COUNT(*) FILTER (WHERE role = 'student') AS students,
    COUNT(*) FILTER (WHERE role = 'teacher') AS teachers,
    COUNT(*) FILTER (WHERE role IN ('staff', 'director')) AS staff
    FROM public.users")->fetch(PDO::FETCH_ASSOC) ?: [];
$courseCount = (int) $conn->query('SELECT COUNT(*) FROM public.courses')->fetchColumn();
$dailyVisitors = (int) $conn->query("SELECT COUNT(DISTINCT user_id)
    FROM public.site_visits
    WHERE entered_at::date = CURRENT_DATE")->fetchColumn();

$courseRows = $conn->query("SELECT c.name, COUNT(sc.student_id) FILTER (WHERE sc.status = 'active') AS student_count
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
    SELECT *, COALESCE(last_learning_activity, last_enrolled_at, created_at) AS last_activity_at
    FROM student_activity
)
SELECT user_id, full_name, student_level, last_activity_at,
       EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - last_activity_at)) / 86400.0 AS inactive_days
FROM activity_dates
WHERE last_activity_at < CURRENT_TIMESTAMP - INTERVAL '7 days'
ORDER BY last_activity_at ASC NULLS FIRST
LIMIT 25")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$activities = $conn->query("SELECT activity_type, title, detail, occurred_at FROM (
    SELECT 'นักเรียนใหม่'::text AS activity_type, s.full_name AS title,
           'รหัส ' || s.user_id::text AS detail, s.created_at AS occurred_at
    FROM public.students s
    UNION ALL
    SELECT 'รายวิชาใหม่'::text AS activity_type, c.name AS title,
           'รหัสวิชา ' || c.code::text AS detail, c.created_at AS occurred_at
    FROM public.courses c
) recent
ORDER BY occurred_at DESC NULLS LAST
LIMIT 5")->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Executive Dashboard - Flexible Learning Hub</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/theme.css?v=20261022-mobile-layout-v2">
  <link rel="stylesheet" href="assets/css/director_dash.css?v=20261022-director-personal-info-underline-fix">
  <link rel="stylesheet" href="assets/css/responsive.css?v=20261022-mobile-layout-v2">
</head>
<body class="director-dashboard">
  <header class="director-topbar">
    <a class="director-brand" href="index.php" aria-label="กลับหน้าหลัก">
      <img src="images/flexible-learning-hub-logo-transparent.png" alt="Flexible Learning Hub">
    </a>
    <div class="director-account">
      <a class="director-download" href="export_director_report.php" target="_blank" rel="noopener">📥 ดาวน์โหลดไฟล์ PDF</a>
      <details class="director-profile-menu">
        <summary>
          <span class="director-avatar" aria-hidden="true"><?php if ($directorAvatarUrl !== ''): ?><img src="<?= directorEscape($directorAvatarUrl) ?>" alt=""><?php else: ?><?= directorEscape($directorInitial) ?><?php endif; ?></span>
          <strong><?= directorEscape($directorName) ?></strong>
        </summary>
        <div class="director-profile-dropdown">
          <a class="director-menu-item" href="director_profile.php">ข้อมูลส่วนตัว</a>
          <a class="director-menu-logout" href="logout.php">ออกจากระบบ</a>
        </div>
      </details>
    </div>
  </header>

  <main class="director-main">
    <section class="director-heading">
      <div><p class="director-eyebrow">EXECUTIVE OVERVIEW</p><h1>ภาพรวมระบบการเรียน</h1><p>ข้อมูลสรุปจากฐานข้อมูล Flexible Learning Hub</p></div>
      <span class="director-updated">ข้อมูล ณ <?= date('d/m/Y H:i') ?></span>
    </section>

    <section class="director-kpis" aria-label="สถิติภาพรวม">
      <article class="director-kpi"><span>นักเรียนทั้งหมด</span><strong><?= number_format((int) ($kpis['students'] ?? 0)) ?></strong><small>บัญชีบทบาทนักเรียน</small></article>
      <article class="director-kpi"><span>ครูผู้สอน</span><strong><?= number_format((int) ($kpis['teachers'] ?? 0)) ?></strong><small>บัญชีบทบาทอาจารย์</small></article>
      <article class="director-kpi"><span>รายวิชาทั้งหมด</span><strong><?= number_format($courseCount) ?></strong><small>รายการในระบบ</small></article>
      <article class="director-kpi"><span>เจ้าหน้าที่และผู้บริหาร</span><strong><?= number_format((int) ($kpis['staff'] ?? 0)) ?></strong><small>บัญชี staff และ director</small></article>
      <article class="director-kpi"><span>ผู้ใช้ที่เข้าสู่ระบบวันนี้</span><strong><?= number_format($dailyVisitors) ?></strong><small>นับบัญชีผู้ใช้ไม่ซ้ำจากบันทึกเข้าเว็บไซต์</small></article>
    </section>

    <section class="director-panels director-analytics">
      <article class="director-panel director-chart-panel">
        <div class="director-panel-heading"><div><h2>นักเรียนลงทะเบียนรายวิชา</h2><p>นับเฉพาะการลงทะเบียนที่ยังใช้งานอยู่</p></div></div>
        <div class="director-chart-wrap" style="height:<?= max(310, count($courseRows) * 42) ?>px"><canvas id="courseEnrollmentChart" aria-label="กราฟจำนวนนักเรียนในแต่ละรายวิชา"></canvas></div>
        <?php if ($courseRows === []): ?><p class="director-empty">ยังไม่มีรายวิชาในระบบ</p><?php endif; ?>
      </article>

      <article class="director-panel director-completion-panel">
        <div class="director-panel-heading"><div><h2>สถานะนักเรียนและการยื่นจบ</h2><p>แบ่งนักเรียนตามสถานะการเรียนและสถานะคำร้องจบการศึกษา</p></div></div>
        <div class="director-doughnut-wrap"><canvas id="completionChart" aria-label="กราฟสัดส่วนการเรียนจบ"></canvas></div>
        <div class="director-chart-legend">
          <?php foreach ($graduationStatusCounts as $label => $count): ?>
            <span><?= directorEscape($label) ?><strong><?= number_format($count) ?> คน</strong></span>
          <?php endforeach; ?>
        </div>
      </article>
    </section>

    <section class="director-graduation-kpis" aria-label="สถานะคำขอจบการศึกษา">
      <article class="director-graduation-kpi is-approved"><span>🟢 อนุมัติแล้ว (ออกใบได้)</span><strong><?= number_format($graduationStatusCounts['อนุมัติแล้ว (ออกใบได้)']) ?></strong><small>นักเรียนที่เจ้าหน้าที่อนุมัติแล้ว</small></article>
      <article class="director-graduation-kpi is-pending"><span>🟡 รออนุมัติ</span><strong><?= number_format($graduationStatusCounts['รออนุมัติ']) ?></strong><small>ส่งคำร้องแล้ว รอเจ้าหน้าที่พิจารณา</small></article>
      <article class="director-graduation-kpi is-eligible"><span>🔴 รอยื่นจบ</span><strong><?= number_format($graduationStatusCounts['รอยื่นจบ']) ?></strong><small>ผ่านเกณฑ์วิชาบังคับ แต่ยังไม่ส่งคำร้อง</small></article>
      <article class="director-graduation-kpi is-normal"><span>🔵 นักเรียนปกติ</span><strong><?= number_format($graduationStatusCounts['นักเรียนปกติ']) ?></strong><small>ยังไม่ผ่านเกณฑ์ยื่นจบและไม่มีคำร้องค้าง</small></article>
    </section>

    <section class="director-panels director-follow-up">
      <article class="director-panel director-risk-panel" id="risk-alerts">
        <div class="director-panel-heading"><div><h2>นักเรียนที่ไม่มีความเคลื่อนไหว (Inactive &gt; 7 Days)</h2><p>พิจารณาจากกิจกรรมบทเรียนล่าสุด หรือวันลงทะเบียน/สร้างบัญชี</p></div></div>
        <?php if ($inactiveStudents === []): ?>
          <p class="director-empty">ไม่พบนักเรียนที่ไม่มีความเคลื่อนไหวเกิน 7 วัน</p>
        <?php else: ?>
          <div class="director-table-scroll"><table class="director-table">
            <thead><tr><th>นักเรียน</th><th>ระดับ</th><th>กิจกรรมล่าสุด</th><th>ไม่ได้ใช้งาน</th><th></th></tr></thead>
            <tbody><?php foreach ($inactiveStudents as $student): ?>
              <tr>
                <td><strong><?= directorEscape($student['full_name'] ?: $student['user_id']) ?></strong><small><?= directorEscape($student['user_id']) ?></small></td>
                <td><?= directorEscape($student['student_level'] ?: '-') ?></td>
                <td><?= directorEscape($student['last_activity_at'] ? date('d/m/Y', strtotime((string) $student['last_activity_at'])) : '-') ?></td>
                <td><?= max(0, (int) floor((float) $student['inactive_days'])) ?> วัน</td>
                <td><a class="director-detail-link" href="director_student_detail.php?student_id=<?= rawurlencode((string) $student['user_id']) ?>">ดูรายละเอียด</a></td>
              </tr>
            <?php endforeach; ?></tbody>
          </table></div>
        <?php endif; ?>
      </article>

      <article class="director-panel director-activity-panel">
        <div class="director-panel-heading"><div><h2>กิจกรรมล่าสุด</h2><p>นักเรียนสมัครใหม่และรายวิชาที่เพิ่มล่าสุด</p></div></div>
        <?php if ($activities === []): ?>
          <p class="director-empty">ยังไม่มีกิจกรรมล่าสุด</p>
        <?php else: ?>
          <div class="director-activity-list">
            <?php foreach ($activities as $activity): ?>
              <article class="director-activity">
                <span class="director-activity-type"><?= directorEscape($activity['activity_type']) ?></span>
                <strong><?= directorEscape($activity['title']) ?></strong>
                <span><?= directorEscape($activity['detail']) ?></span>
                <time><?= directorEscape($activity['occurred_at'] ? date('d/m/Y H:i', strtotime((string) $activity['occurred_at'])) : '-') ?></time>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </article>
    </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
  <script>
    const courseRows = <?= json_encode($courseRows, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const graduationStatusCounts = <?= json_encode($graduationStatusCounts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const chartCanvas = document.getElementById('courseEnrollmentChart');
    if (chartCanvas && window.Chart) {
      new Chart(chartCanvas, {
        type: 'bar',
        data: {
          labels: courseRows.map(course => course.name),
          datasets: [{
            label: 'นักเรียนที่ลงทะเบียน',
            data: courseRows.map(course => Number(course.student_count)),
            backgroundColor: 'rgba(65, 145, 221, 0.78)',
            borderColor: '#3576b9',
            borderWidth: 1,
            borderRadius: 7,
            maxBarThickness: 42
          }]
        },
        options: {
          indexAxis: 'y',
          maintainAspectRatio: false,
          responsive: true,
          plugins: { legend: { display: false } },
          scales: {
            x: { beginAtZero: true, ticks: { precision: 0, color: '#52647d' }, grid: { color: '#e8eff7' } },
            y: { grid: { display: false }, ticks: { color: '#52647d' } }
          }
        }
      });
    }
    const completionCanvas = document.getElementById('completionChart');
    if (completionCanvas && window.Chart) {
      new Chart(completionCanvas, {
        type: 'doughnut',
        data: {
          labels: Object.keys(graduationStatusCounts),
          datasets: [{ data: Object.values(graduationStatusCounts), backgroundColor: ['#10B981', '#F59E0B', '#EF4444', '#3B82F6'], borderColor: '#fff', borderWidth: 3 }]
        },
        options: { maintainAspectRatio: false, responsive: true, cutout: '68%', plugins: { legend: { position: 'bottom', labels: { color: '#52647d', padding: 18, usePointStyle: true } } } }
      });
    }
  </script>
</body>
</html>
