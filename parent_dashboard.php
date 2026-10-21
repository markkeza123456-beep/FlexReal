<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$profileRole = strtolower((string) ($_SESSION['role'] ?? ''));
$profileContactReadonly = $profileRole === 'staff' ? '' : 'readonly';
if (empty($_SESSION['user_id']) || $profileRole !== 'parent') { header('Location: login.php'); exit; }
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>หน้าผู้ปกครอง - Flexible Learning Hub</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&family=Orbitron:wght@600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" />
  <link rel="stylesheet" href="assets/css/parent_dashboard.css?v=20261021-profile-two-column-fields" />
  <style>

    .settings-wrapper { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1.2rem; align-items: start; max-width: 620px; width: 100%; margin-inline: auto; }
    @media (max-width: 860px) { .settings-wrapper { grid-template-columns: 1fr; } }

    .settings-card { padding: 28px; }
    #page-settings .settings-card > .card-title { font-family: 'Kanit', sans-serif; font-size: 15px; font-weight: 600; }

    #page-settings > .page-header { width: min(100%, 620px); margin-inline: auto; text-align: left; }
    #page-settings > .page-header h1 { font-family: 'Kanit', sans-serif; font-size: 26px; font-weight: 700; line-height: 1; }
    #page-settings > .page-header p { font-family: 'Kanit', sans-serif; font-size: 13px; line-height: 1.5; margin-top: 4px; }

    .avatar-section {
      display: flex; align-items: center; gap: 1.2rem;
      margin-bottom: 1.4rem; padding-bottom: 1.2rem;
      border-bottom: 1px solid rgba(255,255,255,0.07);
    }
    .avatar-picker { position: relative; flex-shrink: 0; }
    .avatar-large {
      width: 80px; height: 80px; border-radius: 50%;
      background: rgba(92, 107, 192,0.2); color: var(--text-primary);
      display: flex; align-items: center; justify-content: center;
      font-size: 1.6rem; font-weight: 600;
      border: 2px solid rgba(92, 107, 192,0.4);
      overflow: hidden;
    }
    .avatar-edit-badge {
      position: absolute; bottom: 0; right: 0;
      width: 22px; height: 22px; border-radius: 50%;
      background: var(--accent); color: #fff;
      display: flex; align-items: center; justify-content: center;
      font-size: 0.7rem; border: 2px solid var(--bg);
    }
    .avatar-name { font-size: 17px; font-weight: 700; color: var(--text); }
    .avatar-role { font-size: 12px; color: var(--text-primary); margin: 3px 0 0; }
    .avatar-hint { font-size: 12px; color: var(--text-muted); margin-top: 2px; }

    .settings-form { display: flex; flex-direction: column; gap: 0.85rem; margin-bottom: 1.2rem; }
    .settings-row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
    .settings-field { display: flex; flex-direction: column; gap: 5px; }
    .settings-label { font-size: 12px; color: var(--text-muted); text-transform: none; letter-spacing: 0; font-weight: 500; }
    .settings-input {
      box-sizing: border-box; background: var(--bg3); border: 1px solid var(--border);
      border-radius: var(--radius-sm); padding: 9px 12px; color: var(--text-primary);
      font-family: 'Kanit', sans-serif; font-size: 14px; line-height: normal; outline: none;
      transition: border-color .2s;
    }
    .settings-input:focus { border-color: rgba(92, 107, 192,0.5); }
    @media (max-width: 640px) { .settings-row { grid-template-columns: 1fr; } }

    .pwd-section { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.07); }
    .password-settings-card .pwd-section { margin-top: 0; padding-top: 0; border-top: 0; }
    .pwd-section-title { font-size: 0.85rem; font-weight: 500; color: var(--text-muted); margin-bottom: 0.9rem; display: flex; align-items: center; gap: 6px; }
    .pwd-input-wrap { position: relative; }
    .pwd-input-wrap .settings-input { width: 100%; box-sizing: border-box; padding-right: 40px; }
    .pwd-toggle-btn {
      position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
      background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.1rem; padding: 4px; line-height: 1;
    }
    .pwd-toggle-btn:hover, .pwd-toggle-btn:focus-visible { color: var(--text-primary); }
    .pwd-toggle-btn svg { display:block; width:20px; height:20px; fill:none; stroke:currentColor; stroke-width:1.8; stroke-linecap:round; stroke-linejoin:round; }

    .strength-bar-bg { height: 5px; background: rgba(255,255,255,0.08); border-radius: 10px; overflow: hidden; }
    .strength-bar-fill { height: 100%; border-radius: 10px; transition: width .3s, background .3s; width: 0; }
    .strength-label { font-size: 0.72rem; margin-top: 4px; }
    .pwd-match-msg { font-size: 0.72rem; margin-top: 4px; }

    .btn-save-profile {
      width: 100%; padding: 11px; margin-top: 1.2rem;
      background: #31578f; border: 1px solid #31578f;
      border-radius: 8px; color: #fff;
      font-family: 'Kanit', sans-serif; font-size: 0.9rem; font-weight: 500;
      cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px;
      transition: background .2s, transform .2s;
    }
    .btn-save-profile:hover { background: #284a80; border-color: #284a80; transform: translateY(-1px); }
    .btn-save-profile:disabled { opacity: .5; cursor: not-allowed; }
    .btn-logout { display:flex; align-items:center; gap:10px; margin:0 12px 12px; padding:10px 12px; border-radius:8px; color:var(--text-muted); text-decoration:none; font-size:13px; transition:background .18s,color .18s; }
    .btn-logout:hover { background:rgba(239,68,68,.08); color:#ef4444; }
    .btn-logout:focus-visible { outline:3px solid rgba(var(--brand-400-rgb),.35); outline-offset:2px; }

    .parent-profile { transition: background .2s; padding: 8px; margin: -8px; border-radius: 10px; }
  </style>
  <link rel="stylesheet" href="assets/css/theme.css?v=20261021-sidebar-header-fullbleed" />
    <link rel="stylesheet" href="assets/css/responsive.css">
  <link rel="stylesheet" href="assets/css/profile-layout.css?v=20261021-centered-profile-layout" />
</head>
<body>

<div class="wrap">

  <aside class="sidebar">
    <a class="logo logo-section sidebar-brand-link" href="index.html" title="กลับหน้าหลักและรายวิชา">
      <img class="sidebar-brand-logo" src="images/flexible-learning-hub-logo-transparent.png" alt="Flexible Learning Hub">
    </a>

    <nav class="menu">
      <div class="menu-item active" onclick="showPage('overview', this)">
        <i class="ti ti-layout-dashboard"></i> ภาพรวม
      </div>
      <div class="menu-item" onclick="showPage('grades', this)">
        <i class="ti ti-chart-bar"></i> ผลการเรียน
      </div>
    </nav>

    <a href="logout.php" class="btn-logout" onclick="return confirm('ต้องการออกจากระบบหรือไม่?')">
      <span aria-hidden="true"></span> ออกจากระบบ
    </a>

    <div class="sidebar-footer">
      <div class="parent-profile" id="btn-settings" onclick="showPage('settings', this)" title="ตั้งค่าโปรไฟล์" style="cursor:pointer;">
        <div class="avatar" id="sidebarAvatar">ผป</div>
        <div class="profile-info">
          <div class="profile-name" id="sidebarName">ผู้ปกครอง</div>
          <div class="profile-role" id="sidebarRole">ผู้ปกครอง</div>
        </div>
        <i class="ti ti-settings" style="color: var(--text-primary); font-size: 16px;"></i>
      </div>
    </div>
  </aside>


  <main class="main">


    <div id="page-overview" class="page active">
      <div class="page-header">
        <h1>ภาพรวมการเรียน</h1>
        <p id="overviewSubtitle">ข้อมูลผลการเรียนและความคืบหน้าของบุตรหลาน</p><p id="overviewLastUpdated" class="live-update-status" aria-live="polite">กำลังเชื่อมต่อฐานข้อมูล...</p>
      </div>

      <div class="child-tabs" id="childTabsOverview">

      </div>

      <section class="stats-row overview-student-stats" aria-label="สรุปการเรียนของนักเรียน">
        <div class="stat-card orange">
          <div class="stat-label">วิชาที่ลงทะเบียน</div>
          <div class="stat-value" id="overviewCourseCount">0</div>
          <div class="stat-sub">รายวิชาที่เปิดเรียนอยู่</div>
        </div>
        <div class="stat-card blue">
          <div class="stat-label">แบบทดสอบที่ทำแล้ว</div>
          <div class="stat-value" id="overviewQuizCount">0/0</div>
          <div class="stat-sub">รวมบทเรียนของวิชาที่ลงทะเบียน</div>
        </div>
        <div class="stat-card green">
          <div class="stat-label">คะแนนสะสม</div>
          <div class="stat-value" id="overviewScoreTotal">0/0</div>
          <div class="stat-sub">รวมคะแนนจากแบบทดสอบ</div>
        </div>
      </section>

      <section class="card overview-courses-card" aria-labelledby="overviewCoursesTitle">
        <div class="overview-courses-heading">
          <h2 id="overviewCoursesTitle">ความคืบหน้าการเรียน</h2>
          <label class="overview-course-search"><i class="ti ti-search" aria-hidden="true"></i><input id="overviewCourseSearch" type="search" placeholder="ค้นหาวิชาเรียน..." aria-label="ค้นหาวิชาเรียน"></label>
        </div>
        <div class="overview-course-table-wrap">
          <table class="overview-course-table">
            <thead><tr><th>ลำดับ</th><th>วิชาเรียน</th><th>แบบทดสอบในรายวิชา</th><th>คะแนนสะสม</th><th>รายละเอียด</th></tr></thead>
            <tbody id="overviewCourseBody"><tr><td colspan="5" class="overview-course-empty">กำลังโหลดข้อมูลรายวิชา...</td></tr></tbody>
          </table>
        </div>
      </section>

      <section class="card overview-chart-card" aria-labelledby="courseChartTitle">
        <div class="overview-chart-heading">
          <div>
            <div class="card-title" id="courseChartTitle"><i class="ti ti-chart-bar"></i> ภาพรวมความคืบหน้ารายวิชา</div>
            <p class="overview-chart-note">จำนวนบทเรียนที่ทำแบบทดสอบแล้วเทียบกับบทเรียนทั้งหมด</p>
          </div>
        </div>
        <div id="courseChart" class="course-chart" aria-live="polite"></div>
      </section>

    </div>


    <div id="page-grades" class="page">
      <div class="page-header"><h1>ผลการเรียน</h1><p>สรุปคะแนนแบบทดสอบล่าสุดของแต่ละรายวิชา · ดึงข้อมูลจากฐานข้อมูลทุก 10 วินาที</p><p id="gradesLastUpdated" class="live-update-status" aria-live="polite">กำลังเชื่อมต่อฐานข้อมูล...</p></div>

      <div class="child-tabs" id="childTabsGrades" style="margin-bottom: 1.5rem;">

      </div>

      <div style="margin-bottom: 1.2rem;" class="stats-row grade-summary">
        <div class="stat-card purple">
          <div class="stat-label">บทเรียนที่ทำแบบทดสอบแล้ว</div>
          <div class="stat-value" id="gradeLessonsDone" style="color: var(--purple);">0</div>
          <div class="stat-sub" id="gradeLessonsTotal">จาก 0 บท</div>
        </div>
        <div class="stat-card blue">
          <div class="stat-label">คะแนนสูงสุด</div>
          <div class="stat-value" id="topScore" style="color: var(--blue);">—</div>
          <div class="stat-sub" id="topSubject">ยังไม่มีคะแนน</div>
        </div>
      </div>

      <div class="card">
        <div class="card-title"><i class="ti ti-list-details"></i> สถานะผลการเรียนและคะแนนรายวิชา</div>
        <table class="grade-table">
          <thead>
            <tr>
              <th>รายวิชา</th>
              <th>คะแนนที่ทำได้</th>
              <th>ทำแล้ว</th>
              <th>สถานะ</th>
            </tr>
          </thead>
          <tbody id="gradeBody"></tbody>
        </table>
      </div>

      <div class="card" style="margin-top:1.2rem;">
        <div class="card-title"><i class="ti ti-chart-bar"></i> สถานะรายบทและข้อสอบเขียน</div>
        <div id="progressList" class="progress-list"></div>
      </div>

    </div>


    <div id="page-messages" class="page">
      <div class="page-header"><h1>ข้อความ</h1><p>การสื่อสารระหว่างผู้ปกครองและอาจารย์</p></div>

      <div class="grid-2">
        <div class="card">
          <div class="card-title"><i class="ti ti-inbox"></i> ข้อความที่ได้รับ</div>
          <div class="msg-item" onclick="openMsg(0)">
            <div class="msg-top"><div class="msg-sender"><div class="unread-dot"></div>อ.สมชาย วิชาการ</div><div class="msg-time">เมื่อวาน</div></div>
            <div class="msg-subject">วิทยาศาสตร์ ม.4/2</div>
            <div class="msg-preview">กานต์ทำได้ดีมากในการทดสอบกลางภาค ขอแนะนำให้ฝึกเรื่องสมการเพิ่มเติมก่อนปลายภาคครับ</div>
          </div>
          <div class="msg-item" onclick="openMsg(1)">
            <div class="msg-top"><div class="msg-sender"><div class="unread-dot"></div>อ.วราภรณ์ ภาษาไทย</div><div class="msg-time">23 พ.ค.</div></div>
            <div class="msg-subject">ภาษาไทย ม.4/2</div>
            <div class="msg-preview">เรื่องการส่งงานเขียนเรียงความ กรุณาแจ้งให้กานต์ส่งงานภายในศุกร์นี้</div>
          </div>
          <div class="msg-item" onclick="openMsg(2)">
            <div class="msg-top"><div class="msg-sender" style="color: var(--text-muted);">อ.ประเสริฐ คณิตศาสตร์</div><div class="msg-time">20 พ.ค.</div></div>
            <div class="msg-subject">คณิตศาสตร์ ม.4/2</div>
            <div class="msg-preview">แจ้งผลสอบกลางภาค: กานต์ได้ 88 คะแนน อยู่ในเกณฑ์ดีมาก</div>
          </div>
        </div>

        <div class="card">
          <div class="card-title"><i class="ti ti-send"></i> ส่งข้อความหาอาจารย์</div>
          <div style="display: flex; flex-direction: column; gap: 10px;">
            <div>
              <div style="font-size: 0.72rem; color: var(--text-muted); margin-bottom: 5px;">เลือกอาจารย์</div>
              <select>
                <option>อ.สมชาย วิชาการ (วิทยาศาสตร์)</option>
                <option>อ.วราภรณ์ ภาษาไทย</option>
                <option>อ.ประเสริฐ คณิตศาสตร์</option>
                <option>อ.อรุณ ภาษาอังกฤษ</option>
              </select>
            </div>
            <div>
              <div style="font-size: 0.72rem; color: var(--text-muted); margin-bottom: 5px;">หัวข้อ</div>
              <input type="text" placeholder="เช่น สอบถามเรื่องการบ้าน" />
            </div>
            <div>
              <div style="font-size: 0.72rem; color: var(--text-muted); margin-bottom: 5px;">ข้อความ</div>
              <textarea rows="5" placeholder="พิมพ์ข้อความ..." class="reply-input"></textarea>
            </div>
            <button class="reply-btn"><i class="ti ti-send"></i> ส่งข้อความ</button>
          </div>
        </div>
      </div>
    </div>


    <div id="page-settings" class="page">
      <div class="page-header profile-settings-header">
        <h1>ตั้งค่าโปรไฟล์</h1>
        <p>จัดการข้อมูลส่วนตัวและรหัสผ่านของคุณ</p>
      </div>

      <div class="settings-wrapper profile-settings-layout">


        <div class="card settings-card profile-settings-card">
          <div class="card-title"><i class="ti ti-user-circle"></i> โปรไฟล์ผู้ปกครอง</div>


          <div class="avatar-section">
            <div class="avatar-picker" title="รูปโปรไฟล์">
              <div class="avatar-large" id="avatarDisplay">
                <img id="avatarImg" src="" alt="" style="display:none;width:100%;height:100%;object-fit:cover;border-radius:50%">
                <span id="avatarInitial">สม</span>
              </div>
              <input type="file" id="avatarFileInput" accept="image/*" hidden onchange="previewAvatar(this)">
              <button type="button" class="avatar-change-button" onclick="document.getElementById('avatarFileInput').click()" aria-label="เปลี่ยนรูปโปรไฟล์" title="เปลี่ยนรูปโปรไฟล์"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 4h-4l-2 3H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3z"/><circle cx="12" cy="13" r="3"/></svg></button>
            </div>
            <div class="avatar-info">
        <div class="avatar-name" id="displayName">ผู้ปกครอง</div>
              <div class="avatar-role" id="profileRole">ผู้ปกครอง</div>
              <div class="avatar-hint">รูปโปรไฟล์</div>
            </div>
          </div>


          <div class="settings-form">
            <div class="settings-row">
              <div class="settings-field"><label class="settings-label">ชื่อ</label><input type="text" class="settings-input" id="profileFirstName" autocomplete="given-name"></div>
              <div class="settings-field"><label class="settings-label">นามสกุล</label><input type="text" class="settings-input" id="profileLastName" autocomplete="family-name"></div>
            </div>
            <div class="settings-row">
              <div class="settings-field"><label class="settings-label">เลขบัตรประจำตัวประชาชน</label><input type="text" class="settings-input profile-readonly" id="profileNationalId" <?= $profileContactReadonly ?>></div>
              <div class="settings-field"><label class="settings-label">อีเมล</label><input type="email" class="settings-input profile-readonly" id="profileEmail" <?= $profileContactReadonly ?>></div>
            </div>
            <div class="settings-row">
              <div class="settings-field"><label class="settings-label">เบอร์โทรศัพท์</label><input type="tel" class="settings-input profile-readonly" id="profilePhone" <?= $profileContactReadonly ?>></div>
              <div class="settings-field"><label class="settings-label">บ้านเลขที่ / ถนน</label><input type="text" class="settings-input profile-readonly" id="profileAddressLine" <?= $profileContactReadonly ?>></div>
            </div>
            <div class="settings-row">
              <div class="settings-field"><label class="settings-label">ตำบล / แขวง</label><input type="text" class="settings-input profile-readonly" id="profileSubdistrict" <?= $profileContactReadonly ?>></div>
              <div class="settings-field"><label class="settings-label">อำเภอ / เขต</label><input type="text" class="settings-input profile-readonly" id="profileDistrict" <?= $profileContactReadonly ?>></div>
            </div>
            <div class="settings-row">
              <div class="settings-field"><label class="settings-label">จังหวัด</label><input type="text" class="settings-input profile-readonly" id="profileProvince" <?= $profileContactReadonly ?>></div>
              <div class="settings-field"><label class="settings-label">รหัสไปรษณีย์</label><input type="text" class="settings-input profile-readonly" id="profilePostalCode" <?= $profileContactReadonly ?>></div>
            </div>
            <div class="settings-field"><label class="settings-label">ความสัมพันธ์</label><input type="text" class="settings-input profile-readonly" id="profileRelation" value="ผู้ปกครอง" readonly></div>
          </div>


          <div id="profileFeedback" style="display:none;padding:10px 14px;border-radius:8px;margin-top:12px;font-size:0.82rem;"></div>
          <button class="btn-save-profile" id="saveProfileBtn" onclick="saveProfile()">
            <i class="ti ti-device-floppy"></i> บันทึกข้อมูล
          </button>
        </div>

        <div class="card settings-card password-settings-card">
          <div class="card-title"><i class="ti ti-lock"></i> เปลี่ยนรหัสผ่าน</div>
          <div class="pwd-section">
            <div class="settings-form">
              <div class="settings-field">
                <label class="settings-label">รหัสผ่านปัจจุบัน</label>
                <div class="pwd-input-wrap">
                  <input type="password" class="settings-input" id="pwdCurrent" placeholder="ใส่รหัสผ่านปัจจุบัน">
                  <button type="button" onclick="togglePwd('pwdCurrent',this)" class="pwd-toggle-btn" aria-label="แสดงรหัสผ่าน" title="แสดงรหัสผ่าน"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button>
                </div>
              </div>
              <div class="settings-field">
                <label class="settings-label">รหัสผ่านใหม่</label>
                <div class="pwd-input-wrap">
                  <input type="password" class="settings-input" id="pwdNew" placeholder="อย่างน้อย 6 ตัวอักษร" oninput="checkPwdStrength(this.value)">
                  <button type="button" onclick="togglePwd('pwdNew',this)" class="pwd-toggle-btn" aria-label="แสดงรหัสผ่าน" title="แสดงรหัสผ่าน"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button>
                </div>
                <div id="pwdStrengthWrap" style="display:none;margin-top:8px">
                  <div class="strength-bar-bg"><div id="pwdStrengthBar" class="strength-bar-fill"></div></div>
                  <div id="pwdStrengthLabel" class="strength-label"></div>
                </div>
              </div>
              <div class="settings-field">
                <label class="settings-label">ยืนยันรหัสผ่านใหม่</label>
                <div class="pwd-input-wrap">
                  <input type="password" class="settings-input" id="pwdConfirm" placeholder="พิมพ์รหัสผ่านใหม่อีกครั้ง" oninput="checkPwdMatch()">
                  <button type="button" onclick="togglePwd('pwdConfirm',this)" class="pwd-toggle-btn" aria-label="แสดงรหัสผ่าน" title="แสดงรหัสผ่าน"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button>
                </div>
                <div id="pwdMatchMsg" class="pwd-match-msg"></div>
              </div>
            </div>
          </div>

          <div id="passwordFeedback" style="display:none;padding:10px 14px;border-radius:8px;margin-top:12px;font-size:0.82rem;"></div>
          <button class="btn-save-profile" id="changePasswordBtn" onclick="changePassword()">
            <i class="ti ti-lock"></i> เปลี่ยนรหัสผ่าน
          </button>
        </div>


      </div>
    </div>

  </main>
</div>


<div class="overlay quiz-detail-overlay" id="quizDetailOverlay" aria-hidden="true">
  <section class="quiz-detail-modal" role="dialog" aria-modal="true" aria-labelledby="quizDetailTitle">
    <header class="quiz-detail-header">
      <div><span class="quiz-detail-eyebrow">ผลการทำแบบทดสอบ</span><h2 id="quizDetailTitle">รายละเอียดคำตอบ</h2><p id="quizDetailSubtitle">กำลังโหลดข้อมูล...</p></div>
      <button type="button" class="msg-detail-close" data-close-quiz-details aria-label="ปิดหน้าต่าง">×</button>
    </header>
    <div class="quiz-detail-content" id="quizDetailContent"><div class="quiz-detail-loading">กำลังโหลดรายละเอียดคำตอบ...</div></div>
    <footer class="quiz-detail-footer"><span><i class="ti ti-lock" aria-hidden="true"></i> แสดงผลแบบอ่านอย่างเดียว</span><button type="button" class="quiz-detail-close-button" data-close-quiz-details>ปิด</button></footer>
  </section>
</div>


<div class="overlay" id="msgOverlay" onclick="closeMsg(event)">
  <div class="msg-detail" id="msgDetail">
    <div class="msg-detail-header">
      <div>
        <div style="font-weight: 600; font-size: 1rem;" id="detailSender"></div>
        <div style="font-size: 0.75rem; color: var(--text-primary); margin-top: 2px;" id="detailSubject"></div>
        <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 1px;" id="detailTime"></div>
      </div>
      <button class="msg-detail-close" onclick="document.getElementById('msgOverlay').classList.remove('open')">×</button>
    </div>
    <div class="msg-detail-body" id="detailBody"></div>
    <div class="reply-box">
      <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 6px;">ตอบกลับ</div>
      <textarea class="reply-input" rows="3" placeholder="พิมพ์ข้อความตอบกลับ..."></textarea>
      <button class="reply-btn"><i class="ti ti-send"></i> ส่ง</button>
    </div>
  </div>
</div>

  <script src="assets/js/parent_dashboard.js?v=20261021-subject-type-badges"></script>
<script src="assets/js/responsive.js?v=20261021-responsive" defer></script>
</body>
</html>
