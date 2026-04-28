<?php
session_start();
require_once __DIR__ . '/db_connect.php';

if (!isset($_SESSION['user_id']) || strtolower((string) ($_SESSION['role'] ?? '')) !== 'staff') {
    header('Location: login.php');
    exit;
}

$staffName = 'เจ้าหน้าที่ระบบ';
$staffRole = 'เจ้าหน้าที่';
$staffInitials = 'ST';

try {
    $stmt = $conn->prepare(
        "SELECT firstname, lastname
         FROM public.staff
         WHERE user_id = :user_id
         LIMIT 1"
    );
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($staff) {
        $firstname = trim((string) ($staff['firstname'] ?? ''));
        $lastname = trim((string) ($staff['lastname'] ?? ''));
        $fullName = trim($firstname . ' ' . $lastname);

        if ($fullName !== '') {
            $staffName = $fullName;
        }

        $firstInitial = $firstname !== ''
            ? (function_exists('mb_substr') ? mb_substr($firstname, 0, 1, 'UTF-8') : substr($firstname, 0, 1))
            : '';
        $lastInitial = $lastname !== ''
            ? (function_exists('mb_substr') ? mb_substr($lastname, 0, 1, 'UTF-8') : substr($lastname, 0, 1))
            : '';

        if (trim($firstInitial . $lastInitial) !== '') {
            $staffInitials = $firstInitial . $lastInitial;
        }
    }
} catch (Throwable $e) {
    // Keep fallback display values if staff profile is missing.
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>แผงควบคุม — เจ้าหน้าที่ NEXORA</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@600;700;800&family=IBM+Plex+Sans+Thai:wght@300;400;500;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="staffdash.css" />
</head>
<body>
  <div class="bg-grid"></div>
  <div class="glow-orb orb-1"></div>
  <div class="glow-orb orb-2"></div>

  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="brand-icon">
        <svg viewBox="0 0 40 40" fill="none">
          <polygon points="20,2 38,12 38,28 20,38 2,28 2,12" fill="none" stroke="currentColor" stroke-width="2"/>
          <polygon points="20,10 30,16 30,24 20,30 10,24 10,16" fill="currentColor" opacity="0.3"/>
          <circle cx="20" cy="20" r="4" fill="currentColor"/>
        </svg>
      </div>
      <div class="brand-text">
        <span class="brand-name">NEXORA</span>
        <span class="brand-sub">STAFF PANEL</span>
      </div>
      <button class="sidebar-close" id="sidebarClose" aria-label="ปิดเมนู">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <nav class="nav">
      <div class="nav-section-label">ภาพรวม</div>
      <a class="nav-item active" data-page="dashboard" href="#">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        <span>แดชบอร์ด</span>
      </a>

      <div class="nav-section-label">จัดการหลักสูตร</div>
      <a class="nav-item" data-page="curriculum-list" href="#">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
        <span>รายการหลักสูตร</span>
      </a>
      <a class="nav-item" data-page="curriculum-add" href="#">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
        <span>เพิ่มหลักสูตร</span>
      </a>

      <div class="nav-section-label">จัดการรายวิชา</div>
      <a class="nav-item" data-page="subject-list" href="#">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
        <span>รายการรายวิชา</span>
      </a>
      <a class="nav-item" data-page="subject-add" href="#">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
        <span>เพิ่มรายวิชา</span>
      </a>

      <div class="nav-section-label">จัดการผู้ใช้งาน</div>
      <a class="nav-item" data-page="member-list" href="#">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <span>รายชื่อสมาชิก</span>
      </a>
      <a class="nav-item" data-page="staff-add" href="#">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="16" y1="11" x2="22" y2="11"/></svg>
        <span>เพิ่มเจ้าหน้าที่</span>
      </a>
    </nav>

    <div class="sidebar-footer">
      <div class="user-info" style="display:none;">
        <div class="user-avatar">จน</div>
        <div class="user-detail">
          <span class="user-name">เจ้าหน้าที่ ระบบ</span>
          <span class="user-role">แอดมิน</span>
        </div>
      </div>
      <div class="user-info">
        <div class="user-avatar"><?= htmlspecialchars($staffInitials, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="user-detail">
          <span class="user-name"><?= htmlspecialchars($staffName, ENT_QUOTES, 'UTF-8'); ?></span>
          <span class="user-role"><?= htmlspecialchars($staffRole, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
      </div>
      <a href="login.php" class="btn-logout">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      </a>
    </div>
  </aside>

  <div class="main-wrap">
    <header class="topbar">
      <button class="menu-btn" id="menuBtn" aria-label="เปิดเมนู">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div class="topbar-title" id="topbarTitle">แดชบอร์ด</div>
    </header>

    <main class="content">

      <div class="page active" id="page-dashboard">
        <div class="page-header">
          <h1 class="page-title">ภาพรวมระบบ</h1>
        </div>
        <div class="stat-grid">
          <div class="stat-card">
            <div class="stat-icon orange">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            </div>
            <div class="stat-body">
              <span class="stat-val" id="stat-curriculum">0</span>
              <span class="stat-lbl">หลักสูตรทั้งหมด</span>
            </div>
          </div>
          <div class="stat-card">
            <div class="stat-icon blue">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
            </div>
            <div class="stat-body">
              <span class="stat-val" id="stat-subject">0</span>
              <span class="stat-lbl">รายวิชาทั้งหมด</span>
            </div>
          </div>
          <div class="stat-card">
            <div class="stat-icon green">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div class="stat-body">
              <span class="stat-val" id="stat-student">0</span>
              <span class="stat-lbl">สมาชิกในระบบ</span>
            </div>
          </div>
        </div>
      </div>

      <div class="page" id="page-curriculum-list">
        <div class="page-header">
          <div><h1 class="page-title">รายการหลักสูตร</h1></div>
          <button class="btn-primary" data-goto="curriculum-add">เพิ่มหลักสูตร</button>
        </div>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>รหัส</th><th>ชื่อหลักสูตร</th><th>ระดับชั้น</th><th>สถานะ</th><th>จัดการ</th></tr></thead>
            <tbody id="curriculumBody"></tbody>
          </table>
        </div>
      </div>

      <div class="page" id="page-curriculum-add">
        <div class="page-header">
          <div><h1 class="page-title" id="curriculumFormTitle">เพิ่มหลักสูตรใหม่</h1></div>
          <button class="btn-ghost" data-goto="curriculum-list">← ย้อนกลับ</button>
        </div>
        <form class="form-card" id="curriculumForm" style="max-width: 800px;">
          <input type="hidden" id="cf-id" value="" />
          <div class="form-grid-2">
            <div class="form-field" id="field-cf-code">
              <label class="form-label">รหัสหลักสูตร <span class="req">*</span></label>
              <div class="input-wrap"><input type="text" id="cf-code" required /></div>
            </div>
            <div class="form-field" id="field-cf-level">
              <label class="form-label">ระดับชั้น <span class="req">*</span></label>
              <div class="input-wrap select-wrap">
                <select id="cf-level" required><option value="m4">ม.4</option><option value="m5">ม.5</option><option value="m6">ม.6</option></select>
                <svg class="select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
              </div>
            </div>
          </div>
          <div class="form-field" id="field-cf-name">
            <label class="form-label">ชื่อหลักสูตร <span class="req">*</span></label>
            <div class="input-wrap"><input type="text" id="cf-name" required /></div>
          </div>
          <div class="form-field">
            <label class="form-label">คำอธิบาย</label>
            <div class="input-wrap"><textarea id="cf-desc" rows="3"></textarea></div>
          </div>
          <div class="form-grid-2">
            <div class="form-field" id="field-cf-year">
              <label class="form-label">ปีการศึกษา <span class="req">*</span></label>
              <div class="input-wrap"><input type="text" id="cf-year" maxlength="4" required /></div>
            </div>
            <div class="form-field">
              <label class="form-label">สถานะ</label>
              <div class="input-wrap select-wrap">
                <select id="cf-status"><option value="active">เปิดใช้งาน</option><option value="draft">ร่าง</option><option value="inactive">ปิดใช้งาน</option></select>
                <svg class="select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
              </div>
            </div>
          </div>
          <div class="form-actions">
            <button type="button" class="btn-ghost" data-goto="curriculum-list">ยกเลิก</button>
            <button type="submit" class="btn-primary"><span class="btn-text">บันทึกข้อมูล</span></button>
          </div>
        </form>
      </div>

      <div class="page" id="page-subject-list">
        <div class="page-header">
          <div><h1 class="page-title">รายการรายวิชา</h1></div>
          <button class="btn-primary" data-goto="subject-add">เพิ่มรายวิชา</button>
        </div>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>รหัสวิชา</th><th>ชื่อวิชา</th><th>หน่วยกิต</th><th>ประเภท</th><th>จัดการ</th></tr></thead>
            <tbody id="subjectBody"></tbody>
          </table>
        </div>
      </div>

      <div class="page" id="page-subject-add">
        <div class="page-header">
          <div><h1 class="page-title" id="subjectFormTitle">เพิ่มรายวิชาใหม่</h1></div>
          <button class="btn-ghost" data-goto="subject-list">← ย้อนกลับ</button>
        </div>
        <form class="form-card" id="subjectForm" style="max-width: 800px;">
          <input type="hidden" id="sf-id" value="" />
          <div class="form-grid-2">
            <div class="form-field">
              <label class="form-label">รหัสวิชา <span class="req">*</span></label>
              <div class="input-wrap"><input type="text" id="sf-code" required /></div>
            </div>
            <div class="form-field">
              <label class="form-label">ชื่อวิชา <span class="req">*</span></label>
              <div class="input-wrap"><input type="text" id="sf-name" required /></div>
            </div>
          </div>
          <div class="form-grid-2">
            <div class="form-field">
              <label class="form-label">หน่วยกิต <span class="req">*</span></label>
              <div class="input-wrap"><input type="number" id="sf-credit" required /></div>
            </div>
            <div class="form-field">
              <label class="form-label">ประเภทวิชา</label>
              <div class="input-wrap select-wrap">
                <select id="sf-type"><option value="required">บังคับ</option><option value="elective">เลือก</option></select>
                <svg class="select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
              </div>
            </div>
          </div>
          <div class="form-actions">
            <button type="button" class="btn-ghost" data-goto="subject-list">ยกเลิก</button>
            <button type="submit" class="btn-primary"><span class="btn-text">บันทึกรายวิชา</span></button>
          </div>
        </form>
      </div>

      <div class="page" id="page-subject-detail">
        <div class="page-header">
          <div>
            <h1 class="page-title" id="detailSubjectTitle">จัดการบทเรียน</h1>
            <p class="page-sub">เพิ่ม แก้ไข และจัดเรียงเนื้อหาในรายวิชานี้</p>
          </div>
          <div style="display:flex; gap:10px;">
            <button class="btn-ghost" data-goto="subject-list">← กลับ</button>
            <button class="btn-primary" onclick="openAddLesson()">+ เพิ่มบทเรียน</button>
          </div>
        </div>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th style="width: 80px;">รูปภาพ</th><th>ชื่อบทเรียน</th><th>วิดีโอ</th><th style="width: 120px;">จัดการ</th></tr></thead>
            <tbody id="lessonBody"></tbody>
          </table>
        </div>
      </div>

      <div class="page" id="page-lesson-add">
        <div class="page-header">
          <div><h1 class="page-title" id="lessonFormTitle">เพิ่มบทเรียนใหม่</h1></div>
          <button class="btn-ghost" onclick="goTo('subject-detail')">← กลับ</button>
        </div>
        <form class="form-card" id="lessonForm" style="max-width: 800px;" enctype="multipart/form-data">
          <input type="hidden" id="lf-id" value="" />
          <div class="form-field">
            <label class="form-label">ชื่อบทเรียน <span class="req">*</span></label>
            <div class="input-wrap"><input type="text" id="lf-title" required /></div>
          </div>
          <div class="form-field">
            <label class="form-label">รายละเอียดเนื้อหา</label>
            <div class="input-wrap"><textarea id="lf-content" rows="4"></textarea></div>
          </div>
          <div class="form-grid-2">
            <div class="form-field">
              <label class="form-label">รูปภาพหน้าปก</label>
              <div class="input-wrap"><input type="file" id="lf-image" accept="image/*" /></div>
            </div>
            <div class="form-field">
              <label class="form-label">ลิงก์วิดีโอ (YouTube URL)</label>
              <div class="input-wrap"><input type="url" id="lf-video" placeholder="https://..." /></div>
            </div>
          </div>
          <div class="form-actions">
            <button type="button" class="btn-ghost" onclick="goTo('subject-detail')">ยกเลิก</button>
            <button type="submit" class="btn-primary"><span class="btn-text">บันทึกบทเรียน</span></button>
          </div>
        </form>
      </div>

      <div class="page" id="page-member-list">
        <div class="page-header">
          <div><h1 class="page-title">จัดการผู้ใช้งาน</h1></div>
        </div>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>ชื่อ-นามสกุล</th><th>อีเมล</th><th>บทบาท</th><th>สถานะ</th><th>จัดการ</th></tr></thead>
            <tbody id="memberBody"></tbody>
          </table>
        </div>
      </div>

      <div class="page" id="page-member-edit">
        <div class="page-header">
          <div><h1 class="page-title">แก้ไขข้อมูลสมาชิก</h1></div>
          <button class="btn-ghost" data-goto="member-list">← ย้อนกลับ</button>
        </div>
        <form class="form-card" id="memberForm" style="max-width: 800px;">
          <input type="hidden" id="mf-id" value="" />
          <div class="form-grid-2">
            <div class="form-field"><label class="form-label">ชื่อจริง</label><div class="input-wrap"><input type="text" id="mf-firstname" required /></div></div>
            <div class="form-field"><label class="form-label">นามสกุล</label><div class="input-wrap"><input type="text" id="mf-lastname" required /></div></div>
          </div>
          <div class="form-field"><label class="form-label">อีเมล</label><div class="input-wrap"><input type="email" id="mf-email" required /></div></div>
          <div class="form-grid-2">
            <div class="form-field"><label class="form-label">บทบาท</label>
              <div class="input-wrap select-wrap"><select id="mf-role"><option value="student">นักเรียน</option><option value="teacher">อาจารย์</option><option value="parent">ผู้ปกครอง</option><option value="staff">เจ้าหน้าที่</option></select><svg class="select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></div>
            </div>
            <div class="form-field"><label class="form-label">สถานะบัญชี</label>
              <div class="input-wrap select-wrap"><select id="mf-status"><option value="active">ปกติ</option><option value="inactive">ระงับบัญชี</option></select><svg class="select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></div>
            </div>
          </div>
          <div class="form-actions">
            <button type="button" class="btn-ghost" data-goto="member-list">ยกเลิก</button>
            <button type="submit" class="btn-primary"><span class="btn-text">บันทึกข้อมูลสมาชิก</span></button>
          </div>
        </form>
      </div>

      <div class="page" id="page-staff-add">
        <div class="page-header">
          <div>
            <h1 class="page-title">เพิ่มเจ้าหน้าที่</h1>
            <p class="page-sub">สร้างบัญชีเจ้าหน้าที่ใหม่สำหรับเข้าใช้งานระบบจัดการ</p>
          </div>
        </div>
        <form class="form-card" id="staffCreateForm" style="max-width: 800px;">
          <div class="form-grid-2">
            <div class="form-field">
              <label class="form-label">เลขบัตรประชาชน <span class="req">*</span></label>
              <div class="input-wrap"><input type="text" id="stf-user-id" inputmode="numeric" maxlength="13" placeholder="กรอก 13 หลัก" required /></div>
            </div>
            <div class="form-field">
              <label class="form-label">รหัสผ่าน <span class="req">*</span></label>
              <div class="input-wrap"><input type="password" id="stf-password" minlength="6" placeholder="อย่างน้อย 6 ตัวอักษร" required /></div>
            </div>
          </div>
          <div class="form-grid-2">
            <div class="form-field">
              <label class="form-label">ชื่อ <span class="req">*</span></label>
              <div class="input-wrap"><input type="text" id="stf-firstname" required /></div>
            </div>
            <div class="form-field">
              <label class="form-label">นามสกุล <span class="req">*</span></label>
              <div class="input-wrap"><input type="text" id="stf-lastname" required /></div>
            </div>
          </div>
          <div class="form-grid-2">
            <div class="form-field">
              <label class="form-label">ยืนยันรหัสผ่าน <span class="req">*</span></label>
              <div class="input-wrap"><input type="password" id="stf-password-confirm" minlength="6" required /></div>
            </div>
          </div>
          <div class="form-actions">
            <button type="button" class="btn-ghost" id="staffResetBtn">ล้างข้อมูล</button>
            <button type="submit" class="btn-primary"><span class="btn-text">บันทึกเจ้าหน้าที่</span></button>
          </div>
        </form>
      </div>

    </main>
  </div>

  <div class="modal-overlay" id="modalOverlay">
    <div class="modal">
      <div class="modal-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
      <h3 class="modal-title" id="modalTitle">ยืนยัน</h3>
      <p class="modal-body" id="modalBody">คุณต้องการดำเนินการนี้ใช่หรือไม่?</p>
      <div class="modal-actions">
        <button class="btn-ghost" id="modalCancel">ยกเลิก</button>
        <button class="btn-danger" id="modalConfirm">ตกลง</button>
      </div>
    </div>
  </div>

  <div class="toast" id="toast"></div>
  <script src="staffdash.js"></script>
</body>
</html>
