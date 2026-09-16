<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/db_connect.php';

function h(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function q1(PDO $c, string $sql): string {
  try { $v = $c->query($sql)?->fetchColumn(); return ($v === false || $v === null) ? '0' : (string)$v; }
  catch (Throwable $e) { return '0'; }
}
function rows(PDO $c, string $sql): array {
  try { $st = $c->query($sql); return $st ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : []; }
  catch (Throwable $e) { return []; }
}
function hasTable(PDO $c, string $t): bool {
  $st = $c->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name=:t LIMIT 1");
  $st->execute([':t' => strtolower($t)]);
  return (bool)$st->fetchColumn();
}

$officerName = 'เจ้าหน้าที่ระบบ';
$officerRole = 'พร้อมใช้งาน';
$avatarUrl = '';
$uid = (string)($_SESSION['user_id'] ?? '');
if ($uid !== '' && hasTable($conn, 'staff')) {
  $st = $conn->prepare("SELECT first_name, last_name, user_id FROM public.staff WHERE user_id=:u LIMIT 1");
  $st->execute([':u' => $uid]);
  $s = $st->fetch(PDO::FETCH_ASSOC) ?: null;
  if ($s) {
    $name = trim(((string)($s['first_name'] ?? '')) . ' ' . ((string)($s['last_name'] ?? '')));
    if ($name !== '') $officerName = $name;
    $officerRole = 'เจ้าหน้าที่ • ผู้ใช้ ' . $uid;
  }
}

$hasStudent = hasTable($conn, 'students');
$hasEnrollments = hasTable($conn, 'student_courses');
$hasCurricula = hasTable($conn, 'student_curricula');
$hasCourses = hasTable($conn, 'courses');
$hasLearn = hasTable($conn, 'lesson_progress');
$hasTest = hasTable($conn, 'quiz_attempts');

$studentCount = $hasStudent ? q1($conn, "SELECT COUNT(*) FROM public.students") : '0';
$regCount = $hasEnrollments ? q1($conn, "SELECT COUNT(*) FROM public.student_courses") : '0';
$transferCount = $hasCurricula ? q1($conn, "SELECT COUNT(*) FROM public.student_curricula WHERE status = 'active'") : '0';
$certCount = $hasCourses ? q1($conn, "SELECT COUNT(*) FROM public.courses WHERE status = 'active'") : '0';
$learnCount = $hasLearn ? q1($conn, "SELECT COUNT(*) FROM public.lesson_progress") : '0';
$testCount = $hasTest ? q1($conn, "SELECT COUNT(*) FROM public.quiz_attempts") : '0';

$students = $hasStudent ? rows($conn, "SELECT user_id AS student_id, full_name AS student_name, COALESCE(student_level,'-') AS student_level FROM public.students ORDER BY full_name ASC LIMIT 300") : [];
$regs = $hasEnrollments ? rows($conn, "SELECT sc.student_id, s.full_name AS student_name, c.name AS subject_name, sc.status FROM public.student_courses sc JOIN public.students s ON s.user_id = sc.student_id JOIN public.courses c ON c.course_id = sc.course_id ORDER BY sc.enrolled_at DESC LIMIT 200") : [];
$transfers = $hasCurricula ? rows($conn, "SELECT sc.student_id, s.full_name AS student_name, c.name AS curriculum_name, sc.status, sc.enrolled_at FROM public.student_curricula sc JOIN public.students s ON s.user_id = sc.student_id JOIN public.curricula c ON c.curriculum_id = sc.curriculum_id ORDER BY sc.enrolled_at DESC LIMIT 200") : [];
$certs = $hasCourses ? rows($conn, "SELECT code, name, credits, status FROM public.courses ORDER BY created_at DESC LIMIT 200") : [];
$learns = $hasLearn ? rows($conn, "SELECT lp.student_id, s.full_name AS student_name, l.title AS lesson_name, lp.opened_count, lp.video_open_count, lp.last_activity_at FROM public.lesson_progress lp JOIN public.students s ON s.user_id = lp.student_id JOIN public.lessons l ON l.lesson_id = lp.lesson_id ORDER BY lp.last_activity_at DESC LIMIT 200") : [];
$tests = $hasTest ? rows($conn, "SELECT qa.attempt_id AS test_id, qa.student_id, c.name AS course_name, qa.score, qa.total_score, qa.status, qa.submitted_at FROM public.quiz_attempts qa JOIN public.lessons l ON l.lesson_id = qa.lesson_id JOIN public.courses c ON c.course_id = l.course_id ORDER BY qa.submitted_at DESC LIMIT 200") : [];

$summaryCards = [
  ['key' => 'students', 'label' => 'นักเรียนทั้งหมด', 'value' => $studentCount, 'sub' => 'จากตาราง students'],
  ['key' => 'registrations', 'label' => 'การลงทะเบียนรายวิชา', 'value' => $regCount, 'sub' => 'จากตาราง student_courses'],
  ['key' => 'transfers', 'label' => 'หลักสูตรที่กำลังเรียน', 'value' => $transferCount, 'sub' => 'จากตาราง student_curricula'],
  ['key' => 'certificates', 'label' => 'รายวิชาที่เปิดสอน', 'value' => $certCount, 'sub' => 'จากตาราง courses'],
  ['key' => 'learning', 'label' => 'บันทึกการเรียน', 'value' => $learnCount, 'sub' => 'จากตาราง lesson_progress'],
  ['key' => 'tests', 'label' => 'ผลการทดสอบ', 'value' => $testCount, 'sub' => 'จากตาราง quiz_attempts'],
];
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>แดชบอร์ดเจ้าหน้าที่</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="officer-dashboard.css">
    <link rel="stylesheet" href="theme.css">
</head>
<body>
  <div class="page-shell">
    <aside class="sidebar">
      <div class="brand-block">
        <div class="brand-icon">⌂</div>
        <div><div class="brand-title">แดชบอร์ดเจ้าหน้าที่</div><div class="brand-subtitle">ข้อมูลสดจากฐานข้อมูล PostgreSQL / Supabase</div></div>
      </div>
      <div class="profile-card">
        <div class="profile-avatar"><?php if ($avatarUrl !== ''): ?><img src="<?= h($avatarUrl) ?>" alt="avatar"><?php else: ?><?= h(mb_substr($officerName,0,1,'UTF-8') ?: 'ย') ?><?php endif; ?></div>
        <div><div class="profile-name"><?= h($officerName) ?></div><div class="profile-role"><?= h($officerRole) ?></div></div>
      </div>
      <nav class="nav-list">
        <a class="nav-item active" href="#summary"><span>⋯</span>สรุปภาพรวม</a>
        <a class="nav-item" href="#queues"><span>☰</span>หลักสูตรที่กำลังเรียน</a>
        <a class="nav-item" href="#certs"><span>◫</span>รายวิชาล่าสุด</a>
        <a class="nav-item" href="#activities"><span>Ξ</span>กิจกรรมล่าสุด</a>
      </nav>
    </aside>

    <main class="main-content">
      <header class="topbar">
        <div>
          <p class="eyebrow">OFFICER WORKSPACE</p>
          <h1>ภาพรวมการทำงานของเจ้าหน้าที่</h1>
          <p class="hero-text">กดที่การ์ดภาพรวมเพื่อดูรายละเอียดด้านใน และหน้ารีเฟรชอัตโนมัติทุก 60 วินาที</p>
        </div>
        <div class="updated-box"><span>อัปเดตล่าสุด</span><strong id="lastUpdated"><?= h(date('d/m/Y H:i:s')) ?></strong></div>
      </header>

      <section class="content-grid" id="summary">
        <div class="panel panel-full">
          <div class="panel-header"><h2>สรุปภาพรวมจากฐานข้อมูล</h2></div>
          <div class="cards-grid">
            <?php foreach ($summaryCards as $card): ?>
              <article class="metric-card detail-card" data-key="<?= h($card['key']) ?>" role="button" tabindex="0">
                <div class="metric-label"><?= h($card['label']) ?></div>
                <div class="metric-value"><?= h($card['value']) ?></div>
                <div class="metric-subtext"><?= h($card['sub']) ?></div>
              </article>
            <?php endforeach; ?>
          </div>
        </div>
      </section>

      <section class="content-grid" id="detail-view">
        <div class="panel panel-full">
          <div class="panel-header"><h2 id="detailTitle">รายชื่อนักเรียนทั้งหมด</h2></div>

          <div class="detail-block" data-block="students">
            <?php if ($students === []): ?><div class="empty-panel">ยังไม่มีข้อมูลนักเรียน</div><?php else: ?><div class="list-block"><?php foreach ($students as $s): ?><article class="list-item"><div class="list-title"><?= h((string)$s['student_name']) ?></div><div class="list-detail">รหัส <?= h((string)$s['student_id']) ?></div><div class="list-meta">ระดับ <?= h((string)$s['student_level']) ?></div></article><?php endforeach; ?></div><?php endif; ?>
          </div>

          <div class="detail-block" data-block="registrations" style="display:none">
            <?php if ($regs === []): ?><div class="empty-panel">ยังไม่มีข้อมูลลงทะเบียน</div><?php else: ?><div class="list-block"><?php foreach ($regs as $r): ?><article class="list-item"><div class="list-title"><?= h((string)$r['student_name']) ?></div><div class="list-detail">รหัส <?= h((string)$r['student_id']) ?></div><div class="list-meta">วิชา <?= h((string)$r['subject_name']) ?></div></article><?php endforeach; ?></div><?php endif; ?>
          </div>

          <div class="detail-block" data-block="transfers" style="display:none">
            <?php if ($transfers === []): ?><div class="empty-panel">ยังไม่มีข้อมูลหลักสูตรของนักเรียน</div><?php else: ?><div class="list-block"><?php foreach ($transfers as $t): ?><article class="list-item"><div class="list-title"><?= h((string)$t['student_name']) ?></div><div class="list-detail">หลักสูตร <?= h((string)$t['curriculum_name']) ?></div><div class="list-meta">สถานะ <?= h((string)$t['status']) ?> • ลงทะเบียน <?= h((string)($t['enrolled_at'] ?? '-')) ?></div></article><?php endforeach; ?></div><?php endif; ?>
          </div>

          <div class="detail-block" data-block="certificates" style="display:none">
            <?php if ($certs === []): ?><div class="empty-panel">ยังไม่มีรายวิชา</div><?php else: ?><div class="list-block"><?php foreach ($certs as $c): ?><article class="list-item"><div class="list-title"><?= h((string)$c['name']) ?></div><div class="list-detail">รหัสวิชา <?= h((string)$c['code']) ?></div><div class="list-meta">หน่วยกิต <?= h((string)$c['credits']) ?> • สถานะ <?= h((string)$c['status']) ?></div></article><?php endforeach; ?></div><?php endif; ?>
          </div>

          <div class="detail-block" data-block="learning" style="display:none">
            <?php if ($learns === []): ?><div class="empty-panel">ยังไม่มีบันทึกการเรียน</div><?php else: ?><div class="list-block"><?php foreach ($learns as $l): ?><article class="list-item"><div class="list-title"><?= h((string)$l['student_name']) ?> • <?= h((string)$l['lesson_name']) ?></div><div class="list-detail">เปิดบทเรียน <?= h((string)$l['opened_count']) ?> ครั้ง • เปิดวิดีโอ <?= h((string)$l['video_open_count']) ?> ครั้ง</div><div class="list-meta">ล่าสุด <?= h((string)($l['last_activity_at'] ?? '-')) ?></div></article><?php endforeach; ?></div><?php endif; ?>
          </div>

          <div class="detail-block" data-block="tests" style="display:none">
            <?php if ($tests === []): ?><div class="empty-panel">ยังไม่มีผลการทดสอบ</div><?php else: ?><div class="list-block"><?php foreach ($tests as $t): ?><article class="list-item"><div class="list-title">Test #<?= h((string)$t['test_id']) ?> • <?= h((string)$t['course_name']) ?></div><div class="list-detail">นักเรียน <?= h((string)$t['student_id']) ?></div><div class="list-meta">คะแนน <?= h((string)$t['score']) ?>/<?= h((string)$t['total_score']) ?> • <?= h((string)$t['status']) ?></div></article><?php endforeach; ?></div><?php endif; ?>
          </div>
        </div>
      </section>

      <section class="content-grid content-grid-halves">
        <div class="panel" id="queues">
          <div class="panel-header"><h2>หลักสูตรที่ลงทะเบียนล่าสุด</h2></div>
          <?php if ($transfers === []): ?>
            <div class="empty-panel">ยังไม่มีข้อมูลหลักสูตรของนักเรียน</div>
          <?php else: ?>
            <div class="list-block">
              <?php foreach (array_slice($transfers, 0, 8) as $t): ?>
                <article class="list-item">
                  <div class="list-title"><?= h((string)$t['student_name']) ?> • <?= h((string)$t['curriculum_name']) ?></div>
                  <div class="list-detail">ลงทะเบียน <?= h((string)($t['enrolled_at'] ?? '-')) ?></div>
                  <div class="list-meta">สถานะ <?= h((string)$t['status']) ?></div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="panel" id="certs">
          <div class="panel-header"><h2>รายวิชาล่าสุด</h2></div>
          <?php if ($certs === []): ?>
            <div class="empty-panel">ยังไม่มีรายวิชา</div>
          <?php else: ?>
            <div class="list-block">
              <?php foreach (array_slice($certs, 0, 8) as $c): ?>
                <article class="list-item">
                  <div class="list-title"><?= h((string)$c['name']) ?></div>
                  <div class="list-detail">รหัสวิชา <?= h((string)$c['code']) ?></div>
                  <div class="list-meta">หน่วยกิต <?= h((string)$c['credits']) ?> • <?= h((string)$c['status']) ?></div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <section class="content-grid" id="activities">
        <div class="panel panel-full">
          <div class="panel-header"><h2>กิจกรรมล่าสุด</h2></div>
          <?php if ($tests === []): ?>
            <div class="empty-panel">ยังไม่มีกิจกรรมล่าสุด</div>
          <?php else: ?>
            <div class="list-block">
              <?php foreach (array_slice($tests, 0, 10) as $t): ?>
                <article class="list-item">
                  <div class="list-title">Test #<?= h((string)$t['test_id']) ?> • <?= h((string)$t['course_name']) ?></div>
                  <div class="list-detail">นักเรียน <?= h((string)$t['student_id']) ?></div>
                  <div class="list-meta">คะแนน <?= h((string)$t['score']) ?>/<?= h((string)$t['total_score']) ?> • <?= h((string)$t['status']) ?></div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>
    </main>
  </div>

  <script>
    (() => {
      const map = {
        students: 'รายชื่อนักเรียนทั้งหมด',
        registrations: 'รายละเอียดการลงทะเบียน',
        transfers: 'รายละเอียดคำขอเทียบโอน',
        certificates: 'รายละเอียดใบรับรอง',
        learning: 'รายละเอียดบันทึกการเรียน',
        tests: 'รายละเอียดผลการทดสอบ'
      };
      const cards = document.querySelectorAll('.detail-card');
      const blocks = document.querySelectorAll('.detail-block');
      const titleEl = document.getElementById('detailTitle');
      const panel = document.getElementById('detail-view');
      const show = (k) => {
        blocks.forEach(b => b.style.display = (b.dataset.block === k ? 'block' : 'none'));
        if (titleEl) titleEl.textContent = map[k] || 'รายละเอียดข้อมูล';
        if (panel) panel.scrollIntoView({behavior:'smooth', block:'start'});
      };
      cards.forEach(card => {
        const fn = () => show(card.dataset.key || 'students');
        card.addEventListener('click', fn);
        card.addEventListener('keydown', (e) => {
          if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fn(); }
        });
      });
      setInterval(() => window.location.reload(), 60000);
    })();
  </script>
</body>
</html>
