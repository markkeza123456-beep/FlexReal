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
  $st = $conn->prepare("SELECT firstname, lastname, user_id FROM public.staff WHERE user_id=:u LIMIT 1");
  $st->execute([':u' => $uid]);
  $s = $st->fetch(PDO::FETCH_ASSOC) ?: null;
  if ($s) {
    $name = trim(((string)($s['firstname'] ?? '')) . ' ' . ((string)($s['lastname'] ?? '')));
    if ($name !== '') $officerName = $name;
    $officerRole = 'เจ้าหน้าที่ • ผู้ใช้ ' . $uid;
  }
}

$hasStudent = hasTable($conn, 'student');
$hasReg = hasTable($conn, 'registrations');
$hasTransfer = hasTable($conn, 'credit_transfers');
$hasCert = hasTable($conn, 'certificates');
$hasLearn = hasTable($conn, 'learning_records');
$hasTest = hasTable($conn, 'test');
$hasStudentSubject = hasTable($conn, 'student_subject');

$studentCount = $hasStudent ? q1($conn, "SELECT COUNT(*) FROM public.student") : '0';
$regCount = $hasReg ? q1($conn, "SELECT COUNT(*) FROM public.registrations") : '0';
$transferCount = $hasTransfer ? q1($conn, "SELECT COUNT(*) FROM public.credit_transfers") : '0';
$certCount = $hasCert ? q1($conn, "SELECT COUNT(*) FROM public.certificates") : '0';
$learnCount = $hasLearn ? q1($conn, "SELECT COUNT(*) FROM public.learning_records") : '0';
$testCount = $hasTest ? q1($conn, "SELECT COUNT(*) FROM public.test") : '0';

$students = $hasStudent ? rows($conn, "SELECT student_id, student_name, COALESCE(student_level,'-') AS student_level FROM public.student ORDER BY student_name ASC LIMIT 300") : [];
$regs = $hasStudentSubject ? rows($conn, "SELECT ss.student_id, COALESCE(st.student_name, ss.student_id) AS student_name, COALESCE(sb.subjects_name, ss.subjects_id) AS subject_name FROM public.student_subject ss LEFT JOIN public.student st ON st.student_id=ss.student_id LEFT JOIN public.subjects sb ON sb.subjects_id=ss.subjects_id ORDER BY ss.student_id DESC LIMIT 200") : [];
$transfers = $hasTransfer ? rows($conn, "SELECT ct.transfer_id, COALESCE(st.student_name, ct.student_id) AS student_name, COALESCE(ct.status,'-') AS status, ct.transfer_date FROM public.credit_transfers ct LEFT JOIN public.student st ON st.student_id=ct.student_id ORDER BY ct.transfer_date DESC NULLS LAST, ct.transfer_id DESC LIMIT 200") : [];
$certs = $hasCert ? rows($conn, "SELECT certificates_name, COALESCE(st.student_name, c.student_id) AS student_name, COALESCE(c.department,'-') AS department, c.receive_date FROM public.certificates c LEFT JOIN public.student st ON st.student_id=c.student_id ORDER BY c.receive_date DESC NULLS LAST LIMIT 200") : [];
$learns = $hasLearn ? rows($conn, "SELECT lr.records_id, COALESCE(st.student_name, lr.student_id) AS student_name, lr.study_time FROM public.learning_records lr LEFT JOIN public.student st ON st.student_id=lr.student_id ORDER BY lr.study_time DESC NULLS LAST LIMIT 200") : [];
$tests = $hasTest ? rows($conn, "SELECT test_id, student_id, COALESCE(course_name,'-') AS course_name, COALESCE(score,0) AS score, COALESCE(total_score,0) AS total_score, COALESCE(status,'-') AS status FROM public.test ORDER BY test_id DESC LIMIT 200") : [];

$summaryCards = [
  ['key' => 'students', 'label' => 'นักเรียนทั้งหมด', 'value' => $studentCount, 'sub' => 'จากตาราง student'],
  ['key' => 'registrations', 'label' => 'การลงทะเบียน', 'value' => $regCount, 'sub' => 'จากตาราง registrations'],
  ['key' => 'transfers', 'label' => 'คำขอเทียบโอน', 'value' => $transferCount, 'sub' => 'จากตาราง credit_transfers'],
  ['key' => 'certificates', 'label' => 'เอกสารใบรับรอง', 'value' => $certCount, 'sub' => 'จากตาราง certificates'],
  ['key' => 'learning', 'label' => 'บันทึกการเรียน', 'value' => $learnCount, 'sub' => 'จากตาราง learning_records'],
  ['key' => 'tests', 'label' => 'ผลการทดสอบ', 'value' => $testCount, 'sub' => 'จากตาราง test'],
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
        <a class="nav-item" href="#queues"><span>☰</span>คำขอเทียบโอน</a>
        <a class="nav-item" href="#certs"><span>◫</span>ใบรับรองล่าสุด</a>
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
            <?php if ($transfers === []): ?><div class="empty-panel">ยังไม่มีข้อมูลเทียบโอน</div><?php else: ?><div class="list-block"><?php foreach ($transfers as $t): ?><article class="list-item"><div class="list-title">คำขอ #<?= h((string)$t['transfer_id']) ?> • <?= h((string)$t['student_name']) ?></div><div class="list-detail">วันที่ <?= h((string)($t['transfer_date'] ?? '-')) ?></div><div class="list-meta">สถานะ <?= h((string)$t['status']) ?></div></article><?php endforeach; ?></div><?php endif; ?>
          </div>

          <div class="detail-block" data-block="certificates" style="display:none">
            <?php if ($certs === []): ?><div class="empty-panel">ยังไม่มีข้อมูลใบรับรอง</div><?php else: ?><div class="list-block"><?php foreach ($certs as $c): ?><article class="list-item"><div class="list-title"><?= h((string)$c['certificates_name']) ?></div><div class="list-detail">นักเรียน <?= h((string)$c['student_name']) ?></div><div class="list-meta">หน่วยงาน <?= h((string)$c['department']) ?> • วันที่ <?= h((string)($c['receive_date'] ?? '-')) ?></div></article><?php endforeach; ?></div><?php endif; ?>
          </div>

          <div class="detail-block" data-block="learning" style="display:none">
            <?php if ($learns === []): ?><div class="empty-panel">ยังไม่มีบันทึกการเรียน</div><?php else: ?><div class="list-block"><?php foreach ($learns as $l): ?><article class="list-item"><div class="list-title"><?= h((string)$l['student_name']) ?></div><div class="list-detail">Record #<?= h((string)$l['records_id']) ?></div><div class="list-meta">เวลา <?= h((string)($l['study_time'] ?? '-')) ?></div></article><?php endforeach; ?></div><?php endif; ?>
          </div>

          <div class="detail-block" data-block="tests" style="display:none">
            <?php if ($tests === []): ?><div class="empty-panel">ยังไม่มีผลการทดสอบ</div><?php else: ?><div class="list-block"><?php foreach ($tests as $t): ?><article class="list-item"><div class="list-title">Test #<?= h((string)$t['test_id']) ?> • <?= h((string)$t['course_name']) ?></div><div class="list-detail">นักเรียน <?= h((string)$t['student_id']) ?></div><div class="list-meta">คะแนน <?= h((string)$t['score']) ?>/<?= h((string)$t['total_score']) ?> • <?= h((string)$t['status']) ?></div></article><?php endforeach; ?></div><?php endif; ?>
          </div>
        </div>
      </section>

      <section class="content-grid content-grid-halves">
        <div class="panel" id="queues">
          <div class="panel-header"><h2>คำขอเทียบโอนล่าสุด</h2></div>
          <?php if ($transfers === []): ?>
            <div class="empty-panel">ยังไม่มีข้อมูลเทียบโอน</div>
          <?php else: ?>
            <div class="list-block">
              <?php foreach (array_slice($transfers, 0, 8) as $t): ?>
                <article class="list-item">
                  <div class="list-title">คำขอ #<?= h((string)$t['transfer_id']) ?> • <?= h((string)$t['student_name']) ?></div>
                  <div class="list-detail">วันที่ <?= h((string)($t['transfer_date'] ?? '-')) ?></div>
                  <div class="list-meta">สถานะ <?= h((string)$t['status']) ?></div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="panel" id="certs">
          <div class="panel-header"><h2>ใบรับรองล่าสุด</h2></div>
          <?php if ($certs === []): ?>
            <div class="empty-panel">ยังไม่มีข้อมูลใบรับรอง</div>
          <?php else: ?>
            <div class="list-block">
              <?php foreach (array_slice($certs, 0, 8) as $c): ?>
                <article class="list-item">
                  <div class="list-title"><?= h((string)$c['certificates_name']) ?></div>
                  <div class="list-detail">นักเรียน <?= h((string)$c['student_name']) ?></div>
                  <div class="list-meta">หน่วยงาน <?= h((string)$c['department']) ?> • วันที่ <?= h((string)($c['receive_date'] ?? '-')) ?></div>
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
