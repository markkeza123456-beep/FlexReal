<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (($_SESSION['role'] ?? '') !== 'director' || empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/db.php';
$auth = $conn->prepare("SELECT 1 FROM public.users WHERE user_id = :id AND role = 'director' AND COALESCE(account_status, 'active') = 'active'");
$auth->execute([':id' => (string) $_SESSION['user_id']]);
if (!$auth->fetchColumn()) {
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit;
}

function directorDetailEscape(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$studentId = trim((string) ($_GET['student_id'] ?? ''));
$studentStmt = $conn->prepare('SELECT user_id, full_name, email, phone, student_level, created_at FROM public.students WHERE user_id = :id');
$studentStmt->execute([':id' => $studentId]);
$student = $studentStmt->fetch(PDO::FETCH_ASSOC);
if (!$student) {
    http_response_code(404);
    exit('ไม่พบนักเรียน');
}

$coursesStmt = $conn->prepare("SELECT c.name,
       COUNT(l.lesson_id)::int AS lesson_count,
       COUNT(l.lesson_id) FILTER (WHERE COALESCE(lp.is_completed, FALSE))::int AS completed_lessons,
       CASE WHEN COUNT(l.lesson_id) = 0 THEN 0 ELSE ROUND(100.0 * COUNT(l.lesson_id) FILTER (WHERE COALESCE(lp.is_completed, FALSE)) / COUNT(l.lesson_id), 1) END AS progress_percent,
       MAX(COALESCE(lp.last_activity_at, lp.updated_at)) AS last_activity_at
FROM public.student_courses sc
JOIN public.courses c ON c.course_id = sc.course_id
LEFT JOIN public.lessons l ON l.course_id = c.course_id
LEFT JOIN public.lesson_progress_summary lp ON lp.lesson_id = l.lesson_id AND lp.student_id = sc.student_id
WHERE sc.student_id = :id AND sc.status = 'active'
GROUP BY c.course_id, c.name
ORDER BY c.name");
$coursesStmt->execute([':id' => $studentId]);
$courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ติดตามนักเรียน - Flexible Learning Hub</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/theme.css?v=20261022-mobile-layout-v2"><link rel="stylesheet" href="assets/css/director_dash.css"><link rel="stylesheet" href="assets/css/responsive.css?v=20261022-mobile-layout-v2">
</head>
<body class="director-dashboard">
  <main class="director-main director-student-detail">
    <a class="director-detail-link" href="director_dash.php#risk-alerts">← กลับหน้า Dashboard</a>
    <section class="director-heading"><div><p class="director-eyebrow">STUDENT FOLLOW-UP</p><h1><?= directorDetailEscape($student['full_name'] ?: $student['user_id']) ?></h1><p>รหัส <?= directorDetailEscape($student['user_id']) ?> · <?= directorDetailEscape($student['student_level'] ?: 'ไม่ระบุระดับ') ?></p></div></section>
    <section class="director-kpis director-student-profile">
      <article class="director-kpi"><span>อีเมล</span><strong class="director-profile-value"><?= directorDetailEscape($student['email'] ?: '-') ?></strong><small>ข้อมูลบัญชีนักเรียน</small></article>
      <article class="director-kpi"><span>เบอร์ติดต่อ</span><strong class="director-profile-value"><?= directorDetailEscape($student['phone'] ?: '-') ?></strong><small>ข้อมูลบัญชีนักเรียน</small></article>
      <article class="director-kpi"><span>สมัครเมื่อ</span><strong class="director-profile-value"><?= directorDetailEscape($student['created_at'] ? date('d/m/Y', strtotime((string) $student['created_at'])) : '-') ?></strong><small>วันที่สร้างบัญชี</small></article>
    </section>
    <section class="director-panel">
      <div class="director-panel-heading"><div><h2>ความคืบหน้ารายวิชา</h2><p>ใช้ติดตามและประสานงานช่วยเหลือนักเรียน</p></div></div>
      <?php if (!$courses): ?><p class="director-empty">นักเรียนยังไม่มีรายวิชาที่ลงทะเบียน</p><?php else: ?>
        <div class="director-table-scroll"><table class="director-table"><thead><tr><th>รายวิชา</th><th>บทเรียนที่จบ</th><th>ความคืบหน้า</th><th>กิจกรรมล่าสุด</th></tr></thead><tbody>
        <?php foreach ($courses as $course): ?><tr><td><strong><?= directorDetailEscape($course['name']) ?></strong></td><td><?= (int) $course['completed_lessons'] ?> / <?= (int) $course['lesson_count'] ?></td><td><?= directorDetailEscape($course['progress_percent']) ?>%</td><td><?= directorDetailEscape($course['last_activity_at'] ? date('d/m/Y H:i', strtotime((string) $course['last_activity_at'])) : 'ยังไม่เริ่ม') ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
