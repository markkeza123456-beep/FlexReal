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

$profile = $conn->prepare('SELECT first_name, last_name FROM public.staff WHERE user_id = :user_id LIMIT 1');
$profile->execute([':user_id' => $userId]);
$profileRow = $profile->fetch(PDO::FETCH_ASSOC) ?: [];
$directorName = trim((string) ($profileRow['first_name'] ?? '') . ' ' . (string) ($profileRow['last_name'] ?? '')) ?: 'ผู้บริหาร';

$kpis = $conn->query("SELECT
    COUNT(*) FILTER (WHERE role = 'student') AS students,
    COUNT(*) FILTER (WHERE role = 'teacher') AS teachers,
    COUNT(*) FILTER (WHERE role IN ('staff', 'director')) AS staff
    FROM public.users")->fetch(PDO::FETCH_ASSOC) ?: [];
$courseCount = (int) $conn->query('SELECT COUNT(*) FROM public.courses')->fetchColumn();

$courseRows = $conn->query("SELECT c.name, COUNT(sc.student_id) FILTER (WHERE sc.status = 'active') AS student_count
    FROM public.courses c
    LEFT JOIN public.student_courses sc ON sc.course_id = c.course_id
    GROUP BY c.course_id, c.name
    ORDER BY COUNT(sc.student_id) FILTER (WHERE sc.status = 'active') DESC, c.name")->fetchAll(PDO::FETCH_ASSOC) ?: [];

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
  <link rel="stylesheet" href="assets/css/theme.css">
  <link rel="stylesheet" href="assets/css/director_dash.css">
</head>
<body class="director-dashboard">
  <header class="director-topbar">
    <a class="director-brand" href="index.php" aria-label="กลับหน้าหลัก">
      <img src="images/flexible-learning-hub-logo-transparent.png" alt="Flexible Learning Hub">
    </a>
    <div class="director-account">
      <div><strong><?= directorEscape($directorName) ?></strong><span>ผู้บริหาร</span></div>
      <a href="logout.php">ออกจากระบบ</a>
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
    </section>

    <section class="director-panels">
      <article class="director-panel director-chart-panel">
        <div class="director-panel-heading"><div><h2>นักเรียนลงทะเบียนรายวิชา</h2><p>นับเฉพาะการลงทะเบียนที่ยังใช้งานอยู่</p></div></div>
        <div class="director-chart-wrap" style="height:<?= max(310, count($courseRows) * 42) ?>px"><canvas id="courseEnrollmentChart" aria-label="กราฟจำนวนนักเรียนในแต่ละรายวิชา"></canvas></div>
        <?php if ($courseRows === []): ?><p class="director-empty">ยังไม่มีรายวิชาในระบบ</p><?php endif; ?>
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
  </script>
</body>
</html>
