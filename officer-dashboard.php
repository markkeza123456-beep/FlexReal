<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');

$dbError = null;
$conn = null;

require_once __DIR__ . '/db_connect.php';

function icon(string $name): string
{
    $icons = [
        'home' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.8V21h14V9.8"/></svg>',
        'stats' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M22 20v-3"/></svg>',
        'queue' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 9h8"/><path d="M8 13h8"/><path d="M8 17h5"/></svg>',
        'calendar' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 2v4M16 2v4"/><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 10h18"/></svg>',
        'feed' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h10"/></svg>',
        'empty' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16"/><path d="M7 12h10"/><path d="M10 17h4"/></svg>',
    ];

    return $icons[$name] ?? $icons['empty'];
}

function scalarValue(PDO $conn, string $sql): string
{
    $value = $conn->query($sql)->fetchColumn();
    return $value === false || $value === null ? '0' : (string) $value;
}

function fetchRows(PDO $conn, string $sql): array
{
    $stmt = $conn->query($sql);
    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

function thaiDateTime(?string $value): string
{
    if (!$value) {
        return '-';
    }

    try {
        $date = new DateTime($value);
    } catch (Throwable) {
        return $value;
    }

    if (class_exists('IntlDateFormatter')) {
        $formatter = new IntlDateFormatter(
            'th_TH',
            IntlDateFormatter::MEDIUM,
            IntlDateFormatter::SHORT,
            date_default_timezone_get(),
            IntlDateFormatter::GREGORIAN,
            'd MMM y HH:mm'
        );

        return $formatter->format($date) ?: $value;
    }

    return $date->format('d/m/Y H:i');
}

function thaiDate(?string $value): string
{
    if (!$value) {
        return '-';
    }

    try {
        $date = new DateTime($value);
    } catch (Throwable) {
        return $value;
    }

    if (class_exists('IntlDateFormatter')) {
        $formatter = new IntlDateFormatter(
            'th_TH',
            IntlDateFormatter::MEDIUM,
            IntlDateFormatter::NONE,
            date_default_timezone_get(),
            IntlDateFormatter::GREGORIAN,
            'd MMM y'
        );

        return $formatter->format($date) ?: $value;
    }

    return $date->format('d/m/Y');
}

$dashboardTitle = 'แดชบอร์ดเจ้าหน้าที่';
$dashboardSubtitle = 'ข้อมูลสดจากฐานข้อมูล PostgreSQL / Supabase';
$officerName = 'ยังไม่มีข้อมูลเจ้าหน้าที่';
$officerRole = 'ตาราง officer ยังไม่มีข้อมูล';
$heroTitle = 'ภาพรวมการทำงานของเจ้าหน้าที่';
$heroDescription = 'แดชบอร์ดนี้ดึงข้อมูลจริงจากฐานข้อมูลโดยตรง และจะอัปเดตอัตโนมัติเมื่อมีข้อมูลในตาราง public';
$lastUpdated = thaiDateTime(date('Y-m-d H:i:s'));

$summaryCards = [];
$queues = [];
$appointments = [];
$activities = [];

try {
    if (!isset($conn) || !$conn instanceof PDO) {
        throw new RuntimeException('ไม่พบการเชื่อมต่อฐานข้อมูล');
    }

    $officerRow = fetchRows($conn, 'SELECT officer_id, emp_id, tel, work_date, work_time FROM public.officer ORDER BY work_date DESC NULLS LAST, work_time DESC NULLS LAST LIMIT 1');
    if ($officerRow !== []) {
        $officerName = 'เจ้าหน้าที่ ' . ($officerRow[0]['officer_id'] ?: '-');
        $officerRole = 'รหัสพนักงาน ' . ($officerRow[0]['emp_id'] ?: '-') . ' • โทร ' . ($officerRow[0]['tel'] ?: '-');
    }

    $studentCount = scalarValue($conn, 'SELECT COUNT(*) FROM public.student');
    $registrationCount = scalarValue($conn, 'SELECT COUNT(*) FROM public.registrations');
    $creditTransferCount = scalarValue($conn, 'SELECT COUNT(*) FROM public.credit_transfers');
    $certificateCount = scalarValue($conn, 'SELECT COUNT(*) FROM public.certificates');
    $learningRecordCount = scalarValue($conn, 'SELECT COUNT(*) FROM public.learning_records');
    $testCount = scalarValue($conn, 'SELECT COUNT(*) FROM public.test');

    $summaryCards = [
        ['label' => 'นักเรียนทั้งหมด', 'value' => $studentCount, 'subtext' => 'จากตาราง student'],
        ['label' => 'การลงทะเบียน', 'value' => $registrationCount, 'subtext' => 'จากตาราง registrations'],
        ['label' => 'คำขอเทียบโอน', 'value' => $creditTransferCount, 'subtext' => 'จากตาราง credit_transfers'],
        ['label' => 'เอกสารใบรับรอง', 'value' => $certificateCount, 'subtext' => 'จากตาราง certificates'],
        ['label' => 'บันทึกการเรียน', 'value' => $learningRecordCount, 'subtext' => 'จากตาราง learning_records'],
        ['label' => 'ผลการทดสอบ', 'value' => $testCount, 'subtext' => 'จากตาราง test'],
    ];

    $queues = fetchRows(
        $conn,
        "SELECT
            ct.transfer_id,
            ct.student_id,
            ct.status,
            ct.transfer_date,
            s.student_name
         FROM public.credit_transfers ct
         LEFT JOIN public.student s ON s.student_id = ct.student_id
         ORDER BY ct.transfer_date DESC NULLS LAST, ct.transfer_id DESC
         LIMIT 8"
    );

    $appointments = fetchRows(
        $conn,
        "SELECT
            c.certificates_id,
            c.certificates_name,
            c.department,
            c.receive_date,
            c.student_id,
            s.student_name
         FROM public.certificates c
         LEFT JOIN public.student s ON s.student_id = c.student_id
         ORDER BY c.receive_date DESC NULLS LAST, c.certificates_id DESC
         LIMIT 8"
    );

    $activities = fetchRows(
        $conn,
        "SELECT *
         FROM (
            SELECT
                'learning_record' AS source_type,
                lr.records_id::text AS ref_id,
                lr.student_id,
                s.student_name,
                lr.study_time AS activity_time,
                l.lessons_name AS title,
                ('บทเรียน ' || COALESCE(l.lessons_id, '-')) AS detail
            FROM public.learning_records lr
            LEFT JOIN public.student s ON s.student_id = lr.student_id
            LEFT JOIN public.lessons l ON l.lessons_id = lr.lessons_id

            UNION ALL

            SELECT
                'test' AS source_type,
                t.test_id::text AS ref_id,
                t.student_id,
                s.student_name,
                NULL::timestamp AS activity_time,
                ('คะแนนสอบ ' || COALESCE(t.score::text, '-')) AS title,
                ('สถานะ ' || COALESCE(t.status, '-')) AS detail
            FROM public.test t
            LEFT JOIN public.student s ON s.student_id = t.student_id

            UNION ALL

            SELECT
                'certificate' AS source_type,
                c.certificates_id::text AS ref_id,
                c.student_id,
                s.student_name,
                c.receive_date::timestamp AS activity_time,
                c.certificates_name AS title,
                ('หน่วยงาน ' || COALESCE(c.department, '-')) AS detail
            FROM public.certificates c
            LEFT JOIN public.student s ON s.student_id = c.student_id

            UNION ALL

            SELECT
                'credit_transfer' AS source_type,
                ct.transfer_id::text AS ref_id,
                ct.student_id,
                s.student_name,
                ct.transfer_date::timestamp AS activity_time,
                ('คำขอเทียบโอน #' || ct.transfer_id::text) AS title,
                ('สถานะ ' || COALESCE(ct.status, '-')) AS detail
            FROM public.credit_transfers ct
            LEFT JOIN public.student s ON s.student_id = ct.student_id
         ) activity_feed
         ORDER BY activity_time DESC NULLS LAST, ref_id DESC
         LIMIT 10"
    );

    if ($studentCount === '0' && $registrationCount === '0' && $creditTransferCount === '0' && $certificateCount === '0' && $learningRecordCount === '0' && $testCount === '0') {
        $heroTitle = 'เชื่อมฐานข้อมูลสำเร็จ แต่ยังไม่มีข้อมูลในตารางหลัก';
        $heroDescription = 'ตรวจแล้วตาราง public ที่ใช้กับแดชบอร์ด เช่น student, registrations, credit_transfers, certificates, learning_records และ test ยังไม่มีแถวข้อมูล';
    }
} catch (Throwable $e) {
    $dbError = $e->getMessage();
    $heroTitle = 'ไม่สามารถโหลดข้อมูลจากฐานข้อมูลได้';
    $heroDescription = 'ตรวจสอบการเชื่อมต่อหรือสิทธิ์เข้าถึงของฐานข้อมูลอีกครั้ง';
}

$hasData = $summaryCards !== [] || $queues !== [] || $appointments !== [] || $activities !== [];
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($dashboardTitle, ENT_QUOTES, 'UTF-8'); ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="officer-dashboard.css">
</head>
<body>
  <div class="page-shell">
    <aside class="sidebar">
      <div class="brand-block">
        <div class="brand-icon"><?= icon('home'); ?></div>
        <div>
          <div class="brand-title"><?= htmlspecialchars($dashboardTitle, ENT_QUOTES, 'UTF-8'); ?></div>
          <div class="brand-subtitle"><?= htmlspecialchars($dashboardSubtitle, ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
      </div>

      <div class="profile-card">
        <div class="profile-avatar"><?= htmlspecialchars(mb_substr($officerName, 0, 1, 'UTF-8') ?: '-', ENT_QUOTES, 'UTF-8'); ?></div>
        <div>
          <div class="profile-name"><?= htmlspecialchars($officerName, ENT_QUOTES, 'UTF-8'); ?></div>
          <div class="profile-role"><?= htmlspecialchars($officerRole, ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
      </div>

      <nav class="nav-list">
        <a class="nav-item active" href="#summary"><span><?= icon('stats'); ?></span>สรุปภาพรวม</a>
        <a class="nav-item" href="#queues"><span><?= icon('queue'); ?></span>คำขอเทียบโอน</a>
        <a class="nav-item" href="#appointments"><span><?= icon('calendar'); ?></span>ใบรับรองล่าสุด</a>
        <a class="nav-item" href="#activities"><span><?= icon('feed'); ?></span>กิจกรรมล่าสุด</a>
      </nav>

      <div class="sidebar-note">
        <div class="sidebar-note-title">แหล่งข้อมูล</div>
        <p>หน้าแดชบอร์ดนี้ดึงข้อมูลจาก `public.student`, `public.registrations`, `public.credit_transfers`, `public.certificates`, `public.learning_records` และ `public.test` โดยตรง</p>
      </div>
    </aside>

    <main class="main-content">
      <header class="topbar">
        <div>
          <p class="eyebrow">Officer Workspace</p>
          <h1><?= htmlspecialchars($heroTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
          <p class="hero-text"><?= htmlspecialchars($heroDescription, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <div class="updated-box">
          <span>อัปเดตล่าสุด</span>
          <strong><?= htmlspecialchars($lastUpdated, ENT_QUOTES, 'UTF-8'); ?></strong>
        </div>
      </header>

      <?php if ($dbError !== null): ?>
        <section class="empty-state">
          <div class="empty-icon"><?= icon('empty'); ?></div>
          <h2>เกิดข้อผิดพลาดในการโหลดฐานข้อมูล</h2>
          <p><?= htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8'); ?></p>
        </section>
      <?php elseif (!$hasData || ($queues === [] && $appointments === [] && $activities === [])): ?>
        <section class="empty-state">
          <div class="empty-icon"><?= icon('empty'); ?></div>
          <h2>เชื่อมฐานข้อมูลได้แล้ว</h2>
          <p>ตอนนี้แดชบอร์ดดึงข้อมูลจากฐานข้อมูลจริงสำเร็จ แต่ตารางที่ใช้ยังไม่มีรายการให้แสดง เมื่อมีการเพิ่มข้อมูล หน้าเว็บนี้จะอัปเดตเองทันที</p>
          <pre class="code-block">ตารางที่ตรวจแล้ว:
- public.student
- public.registrations
- public.credit_transfers
- public.certificates
- public.learning_records
- public.test</pre>
        </section>
      <?php endif; ?>

      <section class="content-grid" id="summary">
        <div class="panel panel-full">
          <div class="panel-header">
            <h2>สรุปภาพรวมจากฐานข้อมูล</h2>
          </div>
          <div class="cards-grid">
            <?php foreach ($summaryCards as $card): ?>
              <article class="metric-card">
                <div class="metric-label"><?= htmlspecialchars($card['label'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="metric-value"><?= htmlspecialchars($card['value'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="metric-subtext"><?= htmlspecialchars($card['subtext'], ENT_QUOTES, 'UTF-8'); ?></div>
              </article>
            <?php endforeach; ?>
          </div>
        </div>
      </section>

      <section class="content-grid content-grid-halves">
        <div class="panel" id="queues">
          <div class="panel-header">
            <h2>คำขอเทียบโอนล่าสุด</h2>
          </div>
          <?php if ($queues === []): ?>
            <div class="empty-panel">ยังไม่มีข้อมูลในตาราง `credit_transfers`</div>
          <?php else: ?>
            <div class="list-block">
              <?php foreach ($queues as $item): ?>
                <article class="list-item">
                  <div class="list-title">คำขอ #<?= htmlspecialchars((string) $item['transfer_id'], ENT_QUOTES, 'UTF-8'); ?> • <?= htmlspecialchars((string) ($item['student_name'] ?: $item['student_id'] ?: '-'), ENT_QUOTES, 'UTF-8'); ?></div>
                  <div class="list-detail">วันที่ยื่น <?= htmlspecialchars(thaiDate($item['transfer_date'] ?? null), ENT_QUOTES, 'UTF-8'); ?></div>
                  <div class="list-meta">สถานะ <?= htmlspecialchars((string) ($item['status'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="panel" id="appointments">
          <div class="panel-header">
            <h2>ใบรับรองล่าสุด</h2>
          </div>
          <?php if ($appointments === []): ?>
            <div class="empty-panel">ยังไม่มีข้อมูลในตาราง `certificates`</div>
          <?php else: ?>
            <div class="list-block">
              <?php foreach ($appointments as $item): ?>
                <article class="list-item">
                  <div class="list-time"><?= htmlspecialchars(thaiDate($item['receive_date'] ?? null), ENT_QUOTES, 'UTF-8'); ?></div>
                  <div class="list-title"><?= htmlspecialchars((string) ($item['certificates_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
                  <div class="list-detail">นักเรียน <?= htmlspecialchars((string) ($item['student_name'] ?: $item['student_id'] ?: '-'), ENT_QUOTES, 'UTF-8'); ?></div>
                  <div class="list-meta">หน่วยงาน <?= htmlspecialchars((string) ($item['department'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <section class="content-grid" id="activities">
        <div class="panel panel-full">
          <div class="panel-header">
            <h2>กิจกรรมล่าสุดจากระบบ</h2>
          </div>
          <?php if ($activities === []): ?>
            <div class="empty-panel">ยังไม่มีข้อมูล activity จาก `learning_records`, `test`, `certificates` หรือ `credit_transfers`</div>
          <?php else: ?>
            <div class="list-block">
              <?php foreach ($activities as $item): ?>
                <article class="list-item">
                  <div class="list-time"><?= htmlspecialchars(thaiDateTime($item['activity_time'] ?? null), ENT_QUOTES, 'UTF-8'); ?></div>
                  <div class="list-title"><?= htmlspecialchars((string) ($item['title'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
                  <div class="list-detail">นักเรียน <?= htmlspecialchars((string) ($item['student_name'] ?: $item['student_id'] ?: '-'), ENT_QUOTES, 'UTF-8'); ?></div>
                  <div class="list-meta"><?= htmlspecialchars((string) ($item['detail'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>
    </main>
  </div>
</body>
</html>
