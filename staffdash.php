<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$staffRole = strtolower(trim((string) ($_SESSION['role'] ?? '')));
$profileCanEditContact = $staffRole === 'staff';
if (empty($_SESSION['user_id']) || $staffRole !== 'staff') {
    header('Location: login.php');
    exit;
}

$displayName    = $_SESSION['name'] ?? 'บัญชีเจ้าหน้าที่';
$displayRole    = 'เจ้าหน้าที่';
$avatarInitials = 'ST';
$staffAvatarUrl = '';
$staffProfile = [];
$staffAddress = [];
$profileFirstName = '';
$profileLastName = '';
try {
    require_once __DIR__ . '/config/db.php';
    $conn->exec('ALTER TABLE public.staff ADD COLUMN IF NOT EXISTS avatar_url TEXT');
    $avatarStmt = $conn->prepare('SELECT first_name, last_name, email, phone, avatar_url FROM public.staff WHERE user_id = :id LIMIT 1');
    $avatarStmt->execute([':id' => (string) $_SESSION['user_id']]);
    $staffProfile = $avatarStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $staffAvatarUrl = (string) ($staffProfile['avatar_url'] ?? '');
    $profileFirstName = trim((string) ($staffProfile['first_name'] ?? ''));
    $profileLastName = trim((string) ($staffProfile['last_name'] ?? ''));
    $profileName = trim((string) ($staffProfile['first_name'] ?? '') . ' ' . (string) ($staffProfile['last_name'] ?? ''));
    if ($profileName !== '') $displayName = $profileName;
    $addressStmt = $conn->prepare('SELECT address_line, subdistrict, district, province, postal_code FROM public.user_addresses WHERE user_id = :id ORDER BY is_primary DESC, address_id ASC LIMIT 1');
    $addressStmt->execute([':id' => (string) $_SESSION['user_id']]);
    $staffAddress = $addressStmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $error) {
    error_log('Staff avatar lookup failed: ' . $error->getMessage());
}


if ($profileFirstName === '' && $profileLastName === '' && $displayName !== '') {
    $nameParts = preg_split('/\s+/u', trim($displayName), 2) ?: [];
    $profileFirstName = $nameParts[0] ?? '';
    $profileLastName = $nameParts[1] ?? '';
}
if ($displayName !== '') {
    $parts    = preg_split('/\s+/u', trim($displayName));
    $initials = '';
    foreach ($parts as $p) {
        $initials .= mb_strtoupper(mb_substr($p, 0, 1, 'UTF-8'), 'UTF-8');
        if (mb_strlen($initials, 'UTF-8') >= 2) break;
    }
    if ($initials) $avatarInitials = $initials;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>แดชบอร์ดเจ้าหน้าที่ | Flexible Learning Hub</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@600;700;800&family=IBM+Plex+Sans+Thai:wght@300;400;500;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/staffdash.css?v=20261021-password-button-style" />
  <link rel="stylesheet" href="assets/css/theme.css?v=20261021-sidebar-header-fullbleed" />
    <link rel="stylesheet" href="assets/css/responsive.css">
</head>
<body>


  <div class="bg-grid"></div>
  <div class="glow-orb orb-1"></div>
  <div class="glow-orb orb-2"></div>

  <div class="dashboard-container">

  <?php $staffSidebarInteractiveProfile = true; include __DIR__ . "/components/staff_sidebar.php"; ?>


  <div class="main-wrap">


    <header class="topbar">
      <button class="menu-btn" id="menuBtn" title="เปิดเมนู">เมนู
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
        </svg>
      </button>
      <span class="topbar-title" id="topbarTitle">แดชบอร์ด</span>
    </header>


    <main class="content">


      <section class="page active" id="page-dashboard">
        <div class="page-header">
          <div>
            <div class="page-title">แดชบอร์ด</div>
            <div class="page-sub">ภาพรวมระบบเจ้าหน้าที่</div>
          </div>
        </div>

        <div class="stat-grid">
          <div class="stat-card">
            <div class="stat-icon orange">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
              </svg>
            </div>
            <div class="stat-body">
              <div class="stat-val" id="stat-curriculum">-</div>
              <div class="stat-lbl">หลักสูตรทั้งหมด</div>
            </div>
          </div>
          <div class="stat-card">
            <div class="stat-icon blue">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
              </svg>
            </div>
            <div class="stat-body">
              <div class="stat-val" id="stat-subject">-</div>
              <div class="stat-lbl">รายวิชาทั้งหมด</div>
            </div>
          </div>
          <div class="stat-card">
            <div class="stat-icon green">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
              </svg>
            </div>
            <div class="stat-body">
              <div class="stat-val" id="stat-student">-</div>
              <div class="stat-lbl">สมาชิกทั้งหมด</div>
            </div>
          </div>
        </div>

        <div class="panel-row">
          <div class="panel">
            <div class="panel-head">
              <span class="panel-title">หลักสูตรล่าสุด</span>
              <button class="panel-link" data-page="curriculum-list">ดูทั้งหมด →</button>
            </div>
            <table class="mini-table">
              <thead>
                <tr>
                  <th>รหัส</th>
                  <th>ชื่อหลักสูตร</th>
                  <th>ระดับ</th>
                </tr>
              </thead>
              <tbody id="dashCurriculumBody">
                <tr><td colspan="3" style="text-align:center;padding:20px;color:var(--text-muted);">กำลังโหลด...</td></tr>
              </tbody>
            </table>
          </div>
          <div class="panel">
            <div class="panel-head">
              <span class="panel-title">รายวิชาล่าสุด</span>
              <button class="panel-link" data-page="subject-list">ดูทั้งหมด →</button>
            </div>
            <table class="mini-table">
              <thead>
                <tr>
                  <th>รหัส</th>
                  <th>ชื่อวิชา</th>
                  <th>หน่วยกิต</th>
                </tr>
              </thead>
              <tbody id="dashSubjectBody">
                <tr><td colspan="3" style="text-align:center;padding:20px;color:var(--text-muted);">กำลังโหลด...</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>


      <section class="page" id="page-curriculum-list">
        <div class="page-header">
          <div>
            <div class="page-title">รายการหลักสูตร</div>
            <div class="page-sub">จัดการหลักสูตรทั้งหมดในระบบ</div>
          </div>
          <button class="btn-primary" data-goto="curriculum-add">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="17" height="17">
              <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            <span class="btn-text">เพิ่มหลักสูตรใหม่</span>
          </button>
        </div>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>รหัส</th>
                <th>ชื่อหลักสูตร</th>
                <th>ระดับ</th>
                <th>สถานะ</th>
                <th>ผู้สร้าง</th>
                <th>ผู้แก้ไขล่าสุด</th>
                <th>สร้างเมื่อ</th>
                <th>แก้ไขล่าสุด</th>
                <th style="width:140px;">จัดการ</th>
              </tr>
            </thead>
            <tbody id="curriculumBody">
              <tr><td colspan="9" style="text-align:center;padding:30px;">กำลังโหลด...</td></tr>
            </tbody>
          </table>
        </div>
      </section>


      <section class="page" id="page-curriculum-add">
        <div class="page-header">
          <div>
            <div class="page-title" id="curriculumFormTitle">เพิ่มหลักสูตรใหม่</div>
            <div class="page-sub">กรอกข้อมูลหลักสูตรให้ครบถ้วน</div>
          </div>
          <button class="btn-ghost btn-back" data-goto="curriculum-list">← กลับรายการ</button>
        </div>
        <form class="form-card" id="curriculumForm">
          <input type="hidden" id="cf-id" />
          <div class="form-section-label">ข้อมูลหลักสูตร</div>
          <div class="form-grid-2">
            <div class="form-field">
              <label class="form-label" for="cf-code">รหัสหลักสูตร <span class="req">*</span></label>
              <div class="input-wrap">
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
                <input type="text" id="cf-code" placeholder="เช่น CURR-001" required />
              </div>
            </div>
            <div class="form-field">
              <label class="form-label" for="cf-level">ระดับการศึกษา <span class="req">*</span></label>
              <div class="input-wrap select-wrap">
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                <select id="cf-level">
                  <option value="ม.ต้น">ม.ต้น</option>
                  <option value="ม.ปลาย" selected>ม.ปลาย</option>
                  <option value="ปวช.">ปวช.</option>
                  <option value="ปวส.">ปวส.</option>
                  <option value="ปริญญาตรี">ปริญญาตรี</option>
                </select>
                <svg class="select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
              </div>
            </div>
          </div>
          <div class="form-field">
            <label class="form-label" for="cf-name">ชื่อหลักสูตร <span class="req">*</span></label>
            <div class="input-wrap">
              <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
              <input type="text" id="cf-name" placeholder="ชื่อหลักสูตร" required />
            </div>
          </div>
          <div class="form-grid-2">
            <div class="form-field">
              <label class="form-label" for="cf-year">ปีการศึกษา</label>
              <div class="input-wrap">
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <input type="text" id="cf-year" placeholder="เช่น 2567" />
              </div>
            </div>
            <div class="form-field">
              <label class="form-label" for="cf-status">สถานะ</label>
              <div class="input-wrap select-wrap">
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <select id="cf-status">
                  <option value="active">เปิดใช้งาน</option>
                  <option value="draft">ฉบับร่าง</option>
                  <option value="inactive">ปิดใช้งาน</option>
                </select>
                <svg class="select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
              </div>
            </div>
          </div>
          <div class="form-field">
            <label class="form-label" for="cf-desc">คำอธิบายหลักสูตร</label>
            <div class="input-wrap">
              <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="top:14px;position:absolute;"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
              <textarea id="cf-desc" rows="4" placeholder="รายละเอียดของหลักสูตรนี้" style="padding-top:12px;"></textarea>
            </div>
          </div>
          <div class="form-actions">
            <button type="button" class="btn-ghost" data-goto="curriculum-list">ยกเลิก</button>
            <button type="submit" class="btn-primary"><span class="btn-text">บันทึกหลักสูตร</span></button>
          </div>
        </form>
      </section>


      <section class="page" id="page-curriculum-subjects">
        <div class="page-header">
          <div>
            <div class="page-title" id="csTitle">จัดการวิชาเข้าหลักสูตร</div>
            <div class="page-sub">เลือกรายวิชาที่ต้องการรวมไว้ในหลักสูตรนี้</div>
          </div>
          <button class="btn-ghost btn-back" data-goto="curriculum-list">← กลับรายการ</button>
        </div>
        <form class="form-card" id="csForm" style="max-width:100%;">
          <input type="hidden" id="cs-curriculum-id" />
          <div class="form-section-label">รายวิชาในระบบ</div>
          <div id="cs-subject-list" style="display:flex;flex-direction:column;gap:4px;margin-bottom:24px;">
            <p style="color:var(--text-muted);text-align:center;padding:20px;">กำลังโหลดรายวิชา...</p>
          </div>
          <div class="form-actions">
            <button type="button" class="btn-ghost" data-goto="curriculum-list">ยกเลิก</button>
            <button type="submit" class="btn-primary"><span class="btn-text">บันทึกรายวิชาในหลักสูตร</span></button>
          </div>
        </form>
      </section>


      <section class="page" id="page-subject-list">
        <div class="page-header">
          <div>
            <div class="page-title">รายการรายวิชา</div>
            <div class="page-sub">จัดการรายวิชาทั้งหมดในระบบ</div>
          </div>
          <button class="btn-primary" data-goto="subject-add">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="17" height="17">
              <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            <span class="btn-text">เพิ่มรายวิชาใหม่</span>
          </button>
        </div>
        <div style="display:flex;align-items:center;gap:12px;margin:0 0 16px;flex-wrap:wrap;">
          <label for="subjectCurriculumFilter" style="font-weight:600;color:var(--text);">กรองตามหลักสูตร</label>
          <select id="subjectCurriculumFilter" class="form-input" style="width:min(420px,100%);">
            <option value="">รายวิชาทั้งหมด</option>
          </select>
        </div>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>รหัสวิชา</th>
                <th>ชื่อวิชา</th>
                <th>ประเภท</th>
                <th>อาจารย์ผู้ดูแล</th>
                <th>ผู้สร้าง</th>
                <th>ผู้แก้ไขล่าสุด</th>
                <th>สร้างเมื่อ</th>
                <th>แก้ไขล่าสุด</th>
                <th style="width:140px;">จัดการ</th>
              </tr>
            </thead>
            <tbody id="subjectBody">
              <tr><td colspan="9" style="text-align:center;padding:30px;">กำลังโหลด...</td></tr>
            </tbody>
          </table>
        </div>
      </section>


      <section class="page" id="page-subject-add">
        <div class="page-header">
          <div>
            <div class="page-title" id="subjectFormTitle">เพิ่มรายวิชาใหม่</div>
            <div class="page-sub">กรอกข้อมูลรายวิชาให้ครบถ้วน</div>
          </div>
          <button class="btn-ghost btn-back" data-goto="subject-list">← กลับรายการ</button>
        </div>
        <form class="form-card" id="subjectForm" method="post" enctype="multipart/form-data">
          <input type="hidden" id="sf-id" />
          <div class="form-section-label">ข้อมูลรายวิชา</div>
          <div class="form-grid-2">
            <div class="form-field">
              <label class="form-label" for="sf-code">รหัสวิชา <span class="req">*</span></label>
              <div class="input-wrap">
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
                <input type="text" id="sf-code" placeholder="เช่น CS101" required />
              </div>
            </div>
            <div class="form-field">
              <label class="form-label" for="sf-credit">หน่วยกิต <span class="req">*</span></label>
              <div class="input-wrap">
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
                <input type="number" id="sf-credit" min="0" max="9" placeholder="3" required />
              </div>
            </div>
          </div>
          <div class="form-field">
            <label class="form-label" for="sf-name">ชื่อวิชา <span class="req">*</span></label>
            <div class="input-wrap">
              <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
              <input type="text" id="sf-name" placeholder="ชื่อวิชา" required />
            </div>
          </div>
          <div class="form-grid-2">
            <div class="form-field">
              <label class="form-label" for="sf-type">ประเภทวิชา</label>
              <div class="input-wrap select-wrap">
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                <select id="sf-type">
                  <option value="required">วิชาบังคับ</option>
                  <option value="elective">วิชาเลือก</option>
                </select>
                <svg class="select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
              </div>
            </div>
            <div class="form-field">
              <label class="form-label" for="sf-teacher">อาจารย์ผู้สอน</label>
              <div class="input-wrap select-wrap">
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <select id="sf-teacher">
                  <option value="">-- เลือกอาจารย์ผู้สอน --</option>
                </select>
                <svg class="select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
              </div>
              <div id="sf-selected-teachers" aria-live="polite" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;"></div>

            </div>
          </div>
          <div class="form-field">
            <label class="form-label" for="sf-description">คำอธิบายรายวิชา</label>
            <textarea id="sf-description" rows="4" placeholder="กรอกรายละเอียดรายวิชา"></textarea>
          </div>
          <div class="form-field">
            <label class="form-label" for="sf-image">รูปภาพปก</label>
            <div class="file-upload-control">
              <input class="file-input-visually-hidden" type="file" id="sf-image" name="course_image" accept="image/*">
              <label class="file-upload-button" for="sf-image">เลือกรูปภาพ</label>
              <span id="sf-image-name" class="file-upload-name">ยังไม่ได้เลือกไฟล์</span>
            </div>
            <div id="sf-image-preview-wrap" hidden style="margin-top:10px"><img id="sf-image-preview" alt="ตัวอย่างรูปปก" style="max-width:220px;max-height:140px;object-fit:cover;border-radius:10px"></div>
          </div>
          <div class="form-actions">
            <button type="button" class="btn-ghost" data-goto="subject-list">ยกเลิก</button>
            <button type="submit" class="btn-primary"><span class="btn-text">บันทึกรายวิชา</span></button>
          </div>
        </form>
      </section>


      <section class="page" id="page-subject-detail">
        <div class="page-header">
          <div>
            <div class="page-title">จัดการบทเรียน</div>
            <div class="page-sub">รายการบทเรียนในวิชานี้</div>
          </div>
          <div style="display:flex;gap:10px;">
            <button class="btn-ghost btn-back" data-goto="subject-list">← กลับรายการ</button>
            <button class="btn-primary" onclick="openAddLesson()">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="17" height="17">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
              </svg>
              <span class="btn-text">เพิ่มบทเรียน</span>
            </button>
          </div>
        </div>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th style="width:90px;">รูปภาพ</th>
                <th>ชื่อบทเรียน</th>
                <th>วิดีโอ</th>
                <th>สร้างเมื่อ</th>
                <th>แก้ไขล่าสุด</th>
                <th style="width:120px;">จัดการ</th>
              </tr>
            </thead>
            <tbody id="lessonBody">
              <tr><td colspan="6" style="text-align:center;padding:30px;">กำลังโหลด...</td></tr>
            </tbody>
          </table>
        </div>
      </section>


      <section class="page" id="page-lesson-add">
        <div class="page-header">
          <div>
            <div class="page-title" id="lessonFormTitle">เพิ่มบทเรียนใหม่</div>
            <div class="page-sub">กรอกข้อมูลบทเรียนให้ครบถ้วน</div>
          </div>
          <button class="btn-ghost btn-back" data-goto="subject-detail">← กลับรายการบทเรียน</button>
        </div>
        <form class="form-card" id="lessonForm" enctype="multipart/form-data">
          <input type="hidden" id="lf-id" />
          <div class="form-section-label">ข้อมูลบทเรียน</div>
          <div class="form-field">
            <label class="form-label" for="lf-title">ชื่อบทเรียน <span class="req">*</span></label>
            <div class="input-wrap">
              <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              <input type="text" id="lf-title" placeholder="ชื่อบทเรียน" required />
            </div>
          </div>
          <div class="form-field">
            <label class="form-label" for="lf-content">เนื้อหาบทเรียน</label>
            <div class="input-wrap">
              <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="top:14px;position:absolute;"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
              <textarea id="lf-content" rows="6" placeholder="รายละเอียดเนื้อหาบทเรียน"></textarea>
            </div>
          </div>
          <div class="form-grid-2">
            <div class="form-field">
              <label class="form-label" for="lf-image">รูปภาพหน้าปก</label>
              <div class="input-wrap">
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                <input type="file" id="lf-image" accept="image/*" style="padding-right:12px;" />
              </div>
            </div>
            <div class="form-field">
              <label class="form-label" for="lf-video">ลิงก์วิดีโอ</label>
              <div class="input-wrap">
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
                <input type="url" id="lf-video" placeholder="https://..." />
              </div>
            </div>
          </div>
          <div class="form-actions">
            <button type="button" class="btn-ghost" data-goto="subject-detail">ยกเลิก</button>
            <button type="submit" class="btn-primary"><span class="btn-text">บันทึกบทเรียน</span></button>
          </div>
        </form>
      </section>


      <section class="page" id="page-member-list">
        <div class="page-header">
          <div>
            <div class="page-title">จัดการผู้ใช้งาน</div>
            <div class="page-sub">รายชื่อสมาชิกทั้งหมดในระบบ</div>
          </div>
          <div class="member-list-tools">
            <select id="memberStatusFilter" class="filter-select" aria-label="กรองสถานะบัญชี">
              <option value="active">บัญชีปกติ</option>
              <option value="inactive">บัญชีระงับ</option>
              <option value="all">ทุกสถานะ</option>
            </select>
            <label class="search-wrap member-search-wrap" for="memberSearch">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
              </svg>
              <input id="memberSearch" type="search" placeholder="ค้นหาชื่อ อีเมล หรือเบอร์โทร..." autocomplete="off" />
            </label>
          </div>
        </div>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>ชื่อ-นามสกุล</th>
                <th>อีเมล</th>
                <th>เบอร์โทรศัพท์</th>
                <th>บทบาท</th>
                <th>สถานะ</th>
                <th>วันที่สมัคร</th>
                <th>แก้ไขล่าสุด</th>
                <th>ผู้เพิ่มข้อมูล</th>
                <th>ผู้แก้ไขล่าสุด</th>
                <th style="width:120px;">จัดการ</th>
              </tr>
            </thead>
            <tbody id="memberBody">
              <tr><td colspan="10" style="text-align:center;padding:30px;">กำลังโหลด...</td></tr>
            </tbody>
          </table>
        </div>
      </section>


      <section class="page" id="page-member-edit">
        <div class="page-header">
          <div>
            <div class="page-title">แก้ไขข้อมูลสมาชิก</div>
            <div class="page-sub">แก้ไขชื่อ อีเมล บทบาท และสถานะของสมาชิก</div>
          </div>
          <button class="btn-ghost btn-back" data-goto="member-list">← กลับรายการ</button>
        </div>
        <form class="form-card" id="memberForm">
          <input type="hidden" id="mf-id" />
          <div class="form-field"><label class="form-label" for="mf-user-id">รหัสประจำตัว</label><div class="input-wrap"><input type="text" id="mf-user-id" readonly /></div></div>
          <div class="form-section-label">ข้อมูลสมาชิก</div>
          <div class="form-grid-2">
            <div class="form-field">
              <label class="form-label" for="mf-firstname">ชื่อ <span class="req">*</span></label>
              <div class="input-wrap">
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <input type="text" id="mf-firstname" placeholder="ชื่อ" required />
              </div>
            </div>
            <div class="form-field">
              <label class="form-label" for="mf-lastname">นามสกุล</label>
              <div class="input-wrap">
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <input type="text" id="mf-lastname" placeholder="นามสกุล" />
              </div>
            </div>
          </div>
          <div class="form-field">
            <label class="form-label" for="mf-email">อีเมล</label>
            <div class="input-wrap">
              <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
              <input type="email" id="mf-email" placeholder="email@example.com" />
            </div>
          </div>
          <div class="form-field">
            <label class="form-label" for="mf-phone">เบอร์โทรศัพท์</label>
            <div class="input-wrap"><input type="tel" id="mf-phone" placeholder="เบอร์โทรศัพท์" /></div>
          </div>
          <div class="form-grid-2">
            <div class="form-field">
              <label class="form-label" for="mf-role">บทบาท</label>
              <div class="input-wrap select-wrap">
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <select id="mf-role">
                  <option value="student">นักเรียน</option>
                  <option value="teacher">อาจารย์</option>
                  <option value="parent">ผู้ปกครอง</option>
                  <option value="staff">เจ้าหน้าที่</option>
                </select>
                <svg class="select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
              </div>
            </div>
            <div class="form-field">
              <label class="form-label" for="mf-status">สถานะบัญชี</label>
              <div class="input-wrap select-wrap">
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <select id="mf-status">
                  <option value="active">ปกติ</option>
                  <option value="inactive">ระงับบัญชี</option>
                </select>
                <svg class="select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
              </div>
            </div>
          </div>
          <div class="form-actions">
            <button type="button" class="btn-ghost" data-goto="member-list">ยกเลิก</button>
            <button type="submit" class="btn-primary"><span class="btn-text">บันทึกข้อมูล</span></button>
          </div>
        </form>
      </section>


      <section class="page" id="page-staff-add">
        <div class="page-header">
          <div>
            <div class="page-title">เพิ่มเจ้าหน้าที่</div>
            <div class="page-sub">สร้างบัญชีเจ้าหน้าที่ใหม่ในระบบ</div>
          </div>
        </div>
        <form class="form-card" id="staffCreateForm">
          <div class="form-section-label">ข้อมูลเจ้าหน้าที่</div>
          <div class="form-field">
            <label class="form-label" for="stf-user-id">เลขบัตรประชาชน <span class="req">*</span></label>
            <div class="input-wrap">
              <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
              <input type="text" id="stf-user-id" placeholder="เลขบัตรประชาชน 13 หลัก" maxlength="13" required />
            </div>
          </div>
          <div class="form-grid-2">
            <div class="form-field">
              <label class="form-label" for="stf-firstname">ชื่อ <span class="req">*</span></label>
              <div class="input-wrap">
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <input type="text" id="stf-firstname" placeholder="ชื่อ" required />
              </div>
            </div>
            <div class="form-field">
              <label class="form-label" for="stf-lastname">นามสกุล <span class="req">*</span></label>
              <div class="input-wrap">
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <input type="text" id="stf-lastname" placeholder="นามสกุล" required />
              </div>
            </div>
          </div>
          <div class="form-grid-2">
            <div class="form-field"><label class="form-label" for="stf-email">อีเมล</label><div class="input-wrap"><input type="email" id="stf-email" placeholder="email@example.com" /></div></div>
            <div class="form-field"><label class="form-label" for="stf-phone">เบอร์โทรศัพท์</label><div class="input-wrap"><input type="tel" id="stf-phone" placeholder="เบอร์โทรศัพท์" /></div></div>
          </div>
          <div class="form-grid-2">
            <div class="form-field">
              <label class="form-label" for="stf-password">รหัสผ่าน <span class="req">*</span></label>
              <div class="input-wrap">
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <input type="password" id="stf-password" placeholder="อย่างน้อย 6 ตัวอักษร" required />
              </div>
            </div>
            <div class="form-field">
              <label class="form-label" for="stf-password-confirm">ยืนยันรหัสผ่าน <span class="req">*</span></label>
              <div class="input-wrap">
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <input type="password" id="stf-password-confirm" placeholder="ยืนยันรหัสผ่าน" required />
              </div>
            </div>
          </div>
          <div class="form-actions">
            <button type="button" class="btn-ghost" id="staffResetBtn">ล้างฟอร์ม</button>
            <button type="submit" class="btn-primary"><span class="btn-text">สร้างบัญชีเจ้าหน้าที่</span></button>
          </div>
        </form>

        <div class="form-card" style="margin-top:20px;">
          <div class="form-section-label">รายชื่อเจ้าหน้าที่</div>
          <div class="table-wrap">
            <table class="data-table">
              <thead>
                <tr><th>ชื่อ-นามสกุล</th><th>เลขสมาชิก</th><th>สถานะ</th><th>วันที่สมัคร</th><th>แก้ไขล่าสุด</th><th>ผู้เพิ่มข้อมูล</th><th>ผู้แก้ไขล่าสุด</th><th style="width:100px;">จัดการ</th></tr>
              </thead>
              <tbody id="staffListBody">
                <tr><td colspan="8" style="text-align:center;padding:24px;">กำลังโหลด...</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <section class="page" id="page-graduation-requests">
        <div class="page-header">
          <div>
            <div class="page-title">อนุมัติจบการศึกษา</div>
            <div class="page-sub">ตรวจสอบผลการเรียนและอนุมัติพร้อมออกใบสรุปผลการเรียน PDF</div>
          </div>
        </div>
        <div id="graduationRequestsMessage" class="form-card graduation-review-message" role="status" aria-live="polite">กำลังโหลดคำขอ...</div>
        <div id="graduationRequestsList" class="graduation-request-list"></div>
      </section>

      <section class="page staff-profile-page" id="page-profile">
        <div class="page-header staff-profile-page-header">
          <div>
            <div class="page-title">โปรไฟล์ของฉัน</div>
            <div class="page-sub">ดูและแก้ไขข้อมูลบัญชีเจ้าหน้าที่</div>
          </div>
        </div>
        <div class="form-card staff-profile-page-card">
          <h2 class="staff-profile-card-title">👤 โปรไฟล์</h2>
          <div class="staff-profile-identity">
            <button type="button" class="staff-profile-avatar" onclick="document.getElementById('staffAvatarInput').click()" title="คลิกเพื่อเปลี่ยนรูปโปรไฟล์" aria-label="เปลี่ยนรูปโปรไฟล์">
              <img id="staffProfileAvatarImg" src="<?= htmlspecialchars($staffAvatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="รูปโปรไฟล์"
                   style="<?= $staffAvatarUrl !== '' ? 'display:block;' : 'display:none;' ?>">
              <span id="staffProfileAvatarInitial" style="<?= $staffAvatarUrl !== '' ? 'display:none;' : '' ?>"><?= htmlspecialchars($avatarInitials, ENT_QUOTES, 'UTF-8') ?></span>
            </button>
            <div class="staff-profile-identity-info"><h2><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></h2><p id="staffProfileIdentifier">เจ้าหน้าที่ · รหัส <?= htmlspecialchars((string) $_SESSION['user_id'], ENT_QUOTES, 'UTF-8') ?></p><span>คลิกที่รูปเพื่อเปลี่ยน</span></div>
          </div>
          <form id="staffProfileForm">
            <label class="form-field"><span class="form-label">เลขบัตรประจำตัวประชาชน</span><input class="staff-profile-input" name="user_id" inputmode="numeric" maxlength="32" required value="<?= htmlspecialchars((string) $_SESSION['user_id'], ENT_QUOTES, 'UTF-8') ?>" <?= $profileCanEditContact ? '' : 'readonly' ?>></label>
            <div class="form-grid-2">
              <label class="form-field"><span class="form-label">ชื่อ</span><input class="staff-profile-input" name="first_name" required maxlength="100" value="<?= htmlspecialchars($profileFirstName, ENT_QUOTES, 'UTF-8') ?>"></label>
              <label class="form-field"><span class="form-label">นามสกุล</span><input class="staff-profile-input" name="last_name" required maxlength="100" value="<?= htmlspecialchars($profileLastName, ENT_QUOTES, 'UTF-8') ?>"></label>
            </div>
            <label class="form-field"><span class="form-label">อีเมล</span><input class="staff-profile-input" name="email" type="email" maxlength="254" value="<?= htmlspecialchars((string) ($staffProfile['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= $profileCanEditContact ? '' : 'readonly' ?>></label>
            <label class="form-field"><span class="form-label">เบอร์โทรศัพท์</span><input class="staff-profile-input" name="phone" type="tel" maxlength="30" value="<?= htmlspecialchars((string) ($staffProfile['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= $profileCanEditContact ? '' : 'readonly' ?>></label>
            <div class="form-grid-2">
              <label class="form-field"><span class="form-label">บ้านเลขที่ / ถนน</span><input class="staff-profile-input" name="address_line" maxlength="255" value="<?= htmlspecialchars((string) ($staffAddress['address_line'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= $profileCanEditContact ? '' : 'readonly' ?>></label>
              <label class="form-field"><span class="form-label">ตำบล / แขวง</span><input class="staff-profile-input" name="subdistrict" maxlength="150" value="<?= htmlspecialchars((string) ($staffAddress['subdistrict'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= $profileCanEditContact ? '' : 'readonly' ?>></label>
            </div>
            <div class="form-grid-2">
              <label class="form-field"><span class="form-label">อำเภอ / เขต</span><input class="staff-profile-input" name="district" maxlength="150" value="<?= htmlspecialchars((string) ($staffAddress['district'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= $profileCanEditContact ? '' : 'readonly' ?>></label>
              <label class="form-field"><span class="form-label">จังหวัด</span><input class="staff-profile-input" name="province" maxlength="150" value="<?= htmlspecialchars((string) ($staffAddress['province'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= $profileCanEditContact ? '' : 'readonly' ?>></label>
            </div>
            <label class="form-field"><span class="form-label">รหัสไปรษณีย์</span><input class="staff-profile-input" name="postal_code" inputmode="numeric" maxlength="5" pattern="[0-9]{5}" value="<?= htmlspecialchars((string) ($staffAddress['postal_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= $profileCanEditContact ? '' : 'readonly' ?>></label>
            <div class="staff-profile-message" id="staffProfileMessage" role="status" aria-live="polite"></div>
            <div class="staff-profile-actions"><button type="button" class="btn-ghost" onclick="closeStaffProfile()">กลับ</button><button type="submit" class="btn-primary" id="saveStaffProfileBtn"><span class="btn-text">บันทึกข้อมูล</span></button></div>
          </form>
        </div>

        <form class="form-card staff-profile-page-card staff-password-card" id="staffPasswordForm">
          <h2 class="staff-profile-card-title">🔐 เปลี่ยนรหัสผ่าน</h2>
          <div class="staff-password-section">
            <label class="form-field"><span class="form-label">รหัสผ่านปัจจุบัน</span><span class="staff-password-input-wrap"><input class="staff-profile-input" id="staffPwdCurrent" name="pwd_current" type="password" autocomplete="current-password" required><button type="button" class="staff-password-toggle" aria-label="แสดงรหัสผ่าน" title="แสดงรหัสผ่าน" onclick="toggleStaffPwd('staffPwdCurrent', this)"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></span></label>
            <label class="form-field"><span class="form-label">รหัสผ่านใหม่</span><span class="staff-password-input-wrap"><input class="staff-profile-input" id="staffPwdNew" name="pwd_new" type="password" minlength="6" autocomplete="new-password" oninput="checkStaffPwdStrength(this.value)" required><button type="button" class="staff-password-toggle" aria-label="แสดงรหัสผ่าน" title="แสดงรหัสผ่าน" onclick="toggleStaffPwd('staffPwdNew', this)"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></span><span id="staffPwdStrengthWrap" class="staff-password-strength" style="display:none"><span class="staff-password-strength-bg"><span id="staffPwdStrengthBar"></span></span><span id="staffPwdStrengthLabel"></span></span></label>
            <label class="form-field"><span class="form-label">ยืนยันรหัสผ่านใหม่</span><span class="staff-password-input-wrap"><input class="staff-profile-input" id="staffPwdConfirm" name="pwd_confirm" type="password" minlength="6" autocomplete="new-password" oninput="checkStaffPwdMatch()" required><button type="button" class="staff-password-toggle" aria-label="แสดงรหัสผ่าน" title="แสดงรหัสผ่าน" onclick="toggleStaffPwd('staffPwdConfirm', this)"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></span><span id="staffPwdMatchMsg" class="staff-password-match"></span></label>
          </div>
          <div class="staff-profile-message" id="staffPasswordMessage" role="status" aria-live="polite"></div>
          <div class="staff-profile-actions"><button type="submit" class="btn-primary" id="saveStaffPasswordBtn"><span class="btn-text">เปลี่ยนรหัสผ่าน</span></button></div>
        </form>
      </section>
    </main>
  </div>

  </div>

  <div class="modal-overlay" id="modalOverlay">
    <div class="modal">
      <div class="modal-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/>
          <path d="M9 6V4h6v2"/>
        </svg>
      </div>
      <div class="modal-title" id="modalTitle">ยืนยันการลบ</div>
      <div class="modal-body" id="modalBody">คุณต้องการลบรายการนี้ใช่ไหม?</div>
      <div class="modal-actions">
        <button class="btn-ghost" id="modalCancel">ยกเลิก</button>
        <button class="btn-danger" id="modalConfirm">ยืนยันลบ</button>
      </div>
    </div>
  </div>

  <div class="toast" id="toast"></div>

  <script src="assets/js/staffdash.js?v=20261021-separated-password-card"></script>
<script src="assets/js/responsive.js?v=20261021-responsive" defer></script>
</body>
</html>
