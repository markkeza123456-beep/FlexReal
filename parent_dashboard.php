<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>หน้าผู้ปกครอง - FLEXIBLE LEARNING HUB</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&family=Orbitron:wght@600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" />
  <link rel="stylesheet" href="parent_dashboard.css" />
  <style>
    /* ── Settings page ── */
    .settings-wrapper { display: grid; grid-template-columns: 1fr 1fr; gap: 1.2rem; align-items: start; }
    @media (max-width: 860px) { .settings-wrapper { grid-template-columns: 1fr; } }

    .settings-card { padding: 1.4rem 1.6rem; }

    .avatar-section {
      display: flex; align-items: center; gap: 1.2rem;
      margin-bottom: 1.4rem; padding-bottom: 1.2rem;
      border-bottom: 1px solid rgba(255,255,255,0.07);
    }
    .avatar-picker { position: relative; cursor: pointer; flex-shrink: 0; }
    .avatar-large {
      width: 72px; height: 72px; border-radius: 50%;
      background: rgba(255,122,0,0.2); color: var(--accent);
      display: flex; align-items: center; justify-content: center;
      font-size: 1.6rem; font-weight: 600;
      border: 2px solid rgba(255,122,0,0.4);
      overflow: hidden;
    }
    .avatar-edit-badge {
      position: absolute; bottom: 0; right: 0;
      width: 22px; height: 22px; border-radius: 50%;
      background: var(--accent); color: #fff;
      display: flex; align-items: center; justify-content: center;
      font-size: 0.7rem; border: 2px solid var(--bg);
    }
    .avatar-name { font-size: 1rem; font-weight: 600; color: var(--text); }
    .avatar-role { font-size: 0.78rem; color: var(--accent); margin: 2px 0; }
    .avatar-hint { font-size: 0.7rem; color: var(--text-muted); }

    .settings-form { display: flex; flex-direction: column; gap: 0.85rem; margin-bottom: 1.2rem; }
    .settings-field { display: flex; flex-direction: column; gap: 5px; }
    .settings-label { font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: .04em; }
    .settings-input {
      background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1);
      border-radius: 8px; padding: 9px 12px; color: var(--text);
      font-family: 'Kanit', sans-serif; font-size: 0.87rem; outline: none;
      transition: border-color .2s;
    }
    .settings-input:focus { border-color: rgba(255,122,0,0.5); }

    .pwd-section { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.07); }
    .pwd-section-title { font-size: 0.85rem; font-weight: 500; color: var(--text-muted); margin-bottom: 0.9rem; display: flex; align-items: center; gap: 6px; }
    .pwd-input-wrap { position: relative; }
    .pwd-input-wrap .settings-input { width: 100%; box-sizing: border-box; padding-right: 40px; }
    .pwd-toggle-btn {
      position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
      background: none; border: none; cursor: pointer; font-size: 1rem; padding: 0; line-height: 1;
    }

    .strength-bar-bg { height: 5px; background: rgba(255,255,255,0.08); border-radius: 10px; overflow: hidden; }
    .strength-bar-fill { height: 100%; border-radius: 10px; transition: width .3s, background .3s; width: 0; }
    .strength-label { font-size: 0.72rem; margin-top: 4px; }
    .pwd-match-msg { font-size: 0.72rem; margin-top: 4px; }

    .btn-save-profile {
      width: 100%; padding: 11px; margin-top: 1.2rem;
      background: rgba(255,122,0,0.15); border: 1px solid rgba(255,122,0,0.35);
      border-radius: 10px; color: var(--accent);
      font-family: 'Kanit', sans-serif; font-size: 0.9rem; font-weight: 500;
      cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px;
      transition: background .2s;
    }
    .btn-save-profile:hover { background: rgba(255,122,0,0.25); }
    .btn-save-profile:disabled { opacity: .5; cursor: not-allowed; }

    .notif-list { display: flex; flex-direction: column; gap: 0.85rem; }
    .notif-toggle {
      display: flex; align-items: center; gap: 10px;
      font-size: 0.85rem; color: var(--text); cursor: pointer;
      padding: 8px 10px; border-radius: 8px;
      transition: background .15s;
    }
    .notif-toggle:hover { background: rgba(255,255,255,0.04); }

    /* sidebar active state for settings */
    .parent-profile.active { background: rgba(255,122,0,0.1); border-radius: 10px; }
    .parent-profile { transition: background .2s; padding: 8px; margin: -8px; border-radius: 10px; }
    .parent-profile:hover { background: rgba(255,255,255,0.05); }
  </style>
</head>
<body>

<div class="wrap">
  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="logo">
      <div class="logo-icon">
        <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
          <polygon points="20,2 38,12 38,28 20,38 2,28 2,12" fill="none" stroke="currentColor" stroke-width="2"/>
          <polygon points="20,10 30,16 30,24 20,30 10,24 10,16" fill="currentColor" opacity="0.4"/>
          <circle cx="20" cy="20" r="4" fill="currentColor"/>
        </svg>
      </div>
      <div>
        <div class="logo-text">FLEXIBLE</div>
        <div class="logo-sub">LEARNING HUB</div>
      </div>
    </div>

    <nav class="menu">
      <div class="menu-item active" onclick="showPage('overview', this)">
        <i class="ti ti-layout-dashboard"></i> ภาพรวม
      </div>
      <div class="menu-item" onclick="showPage('grades', this)">
        <i class="ti ti-chart-bar"></i> ผลการเรียน
      </div>
      <div class="menu-item" onclick="showPage('attendance', this)">
        <i class="ti ti-calendar-check"></i> การเข้าเรียน
      </div>
      <div class="menu-item" onclick="showPage('messages', this)">
        <i class="ti ti-message-2"></i> ข้อความ
        <span class="notif-badge">3</span>
      </div>
      <div class="menu-item" onclick="showPage('notifications', this)">
        <i class="ti ti-bell"></i> การแจ้งเตือน
        <span class="notif-badge">2</span>
      </div>
    </nav>

    <div class="sidebar-footer">
      <div class="parent-profile" id="btn-settings" onclick="showPage('settings', this)" title="ตั้งค่าโปรไฟล์" style="cursor:pointer;">
        <div class="avatar" id="sidebarAvatar">สม</div>
        <div class="profile-info">
          <div class="profile-name" id="sidebarName">คุณสมหญิง ใจดี</div>
          <div class="profile-role" id="sidebarRole">ผู้ปกครอง</div>
        </div>
        <i class="ti ti-settings" style="color: var(--accent); font-size: 16px;"></i>
      </div>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <main class="main">

    <!-- OVERVIEW PAGE -->
    <div id="page-overview" class="page active">
      <div class="page-header">
        <h1>สวัสดี คุณสมหญิง 👋</h1>
        <p>ภาพรวมความคืบหน้าของบุตรหลาน · อัปเดตล่าสุด 26 พ.ค. 2569</p>
      </div>

      <div class="child-tabs" id="childTabsOverview">
        <!-- render โดย JS จาก API -->
      </div>

      <div class="stats-row" id="statsRow">
        <div class="stat-card">
          <div class="stat-label">เกรดเฉลี่ย</div>
          <div class="stat-value" id="stat-gpa" style="color: var(--accent);">3.75</div>
          <div class="stat-sub">ภาคเรียนที่ 2/2568</div>
        </div>
        <div class="stat-card blue">
          <div class="stat-label">เข้าเรียน</div>
          <div class="stat-value" id="stat-attend" style="color: var(--blue);">96%</div>
          <div class="stat-sub" id="stat-attend-sub">48/50 วัน</div>
        </div>
        <div class="stat-card green">
          <div class="stat-label">งานที่ส่ง</div>
          <div class="stat-value" id="stat-works" style="color: var(--green);">18/20</div>
          <div class="stat-sub">ส่งครบ 90%</div>
        </div>
        <div class="stat-card purple">
          <div class="stat-label">กิจกรรม</div>
          <div class="stat-value" id="stat-activities" style="color: var(--purple);">5</div>
          <div class="stat-sub">รายการที่เข้าร่วม</div>
        </div>
      </div>

      <div class="grid-3-1">
        <div class="card">
          <div class="card-title"><i class="ti ti-book"></i> คะแนนรายวิชา</div>
          <div id="subjectList"></div>
        </div>

        <div class="card">
          <div class="card-title"><i class="ti ti-bell-ringing"></i> แจ้งเตือนล่าสุด</div>
          <div class="notif-item">
            <div class="notif-icon warn"><i class="ti ti-alert-triangle"></i></div>
            <div class="notif-text"><p>งานส่งช้า: รายงานวิทย์ ม.4</p><span>2 ชั่วโมงที่แล้ว</span></div>
          </div>
          <div class="notif-item">
            <div class="notif-icon info"><i class="ti ti-message-2"></i></div>
            <div class="notif-text"><p>อาจารย์สมชายส่งข้อความถึงคุณ</p><span>เมื่อวาน</span></div>
          </div>
          <div class="notif-item">
            <div class="notif-icon ok"><i class="ti ti-trophy"></i></div>
            <div class="notif-text"><p>กานต์ได้คะแนนสูงสุดในชั้น วิชาวิทย์</p><span>3 วันที่แล้ว</span></div>
          </div>
        </div>
      </div>

      <div style="margin-top: 1.2rem;">
        <div class="card">
          <div class="card-title"><i class="ti ti-inbox"></i> ข้อความจากอาจารย์</div>
          <div class="msg-item" onclick="openMsg(0)">
            <div class="msg-top">
              <div class="msg-sender"><div class="unread-dot"></div> อ.สมชาย วิชาการ</div>
              <div class="msg-time">เมื่อวาน 14:30</div>
            </div>
            <div class="msg-subject">วิทยาศาสตร์ ม.4/2</div>
            <div class="msg-preview">กานต์ทำได้ดีมากในการทดสอบกลางภาค ขอแนะนำให้ฝึกเรื่องสมการเพิ่มเติมก่อนปลายภาคครับ</div>
          </div>
          <div class="msg-item" onclick="openMsg(1)">
            <div class="msg-top">
              <div class="msg-sender"><div class="unread-dot"></div> อ.วราภรณ์ ภาษาไทย</div>
              <div class="msg-time">23 พ.ค.</div>
            </div>
            <div class="msg-subject">ภาษาไทย ม.4/2</div>
            <div class="msg-preview">เรื่องการส่งงานเขียนเรียงความ กรุณาแจ้งให้กานต์ส่งงานภายในศุกร์นี้ด้วยนะคะ</div>
          </div>
          <div class="msg-item" onclick="openMsg(2)">
            <div class="msg-top">
              <div class="msg-sender" style="color: var(--text-muted);">อ.ประเสริฐ คณิตศาสตร์</div>
              <div class="msg-time">20 พ.ค.</div>
            </div>
            <div class="msg-subject">คณิตศาสตร์ ม.4/2</div>
            <div class="msg-preview">แจ้งผลสอบกลางภาค: กานต์ได้ 88 คะแนน อยู่ในเกณฑ์ดีมาก ยังมีจุดที่ควรพัฒนาเรื่องสถิติครับ</div>
          </div>
        </div>
      </div>
    </div>

    <!-- GRADES PAGE -->
    <div id="page-grades" class="page">
      <div class="page-header"><h1>ผลการเรียน</h1><p>รายละเอียดเกรดทุกรายวิชา</p></div>

      <div class="child-tabs" id="childTabsGrades" style="margin-bottom: 1.5rem;">
        <!-- render โดย JS จาก API -->
      </div>

      <div class="card">
        <div class="card-title"><i class="ti ti-list-details"></i> ผลการเรียนภาคเรียน 2/2568</div>
        <table class="grade-table">
          <thead>
            <tr>
              <th>รายวิชา</th>
              <th>คะแนนกลางภาค</th>
              <th>คะแนนปลายภาค</th>
              <th>คะแนนรวม</th>
              <th>เกรด</th>
              <th>สถานะ</th>
            </tr>
          </thead>
          <tbody id="gradeBody"></tbody>
        </table>
      </div>

      <div style="margin-top: 1.2rem;" class="stats-row">
        <div class="stat-card">
          <div class="stat-label">เกรดเฉลี่ย GPA</div>
          <div class="stat-value" id="gpaVal" style="color: var(--accent);">3.75</div>
          <div class="stat-sub">ภาคเรียน 2/2568</div>
        </div>
        <div class="stat-card green">
          <div class="stat-label">วิชาที่ได้ A</div>
          <div class="stat-value" id="gradeACount" style="color: var(--green);">3</div>
          <div class="stat-sub">จาก 7 วิชา</div>
        </div>
        <div class="stat-card blue">
          <div class="stat-label">คะแนนสูงสุด</div>
          <div class="stat-value" id="topScore" style="color: var(--blue);">95</div>
          <div class="stat-sub" id="topSubject">วิชาพลศึกษา</div>
        </div>
        <div class="stat-card purple">
          <div class="stat-label">อันดับในชั้น</div>
          <div class="stat-value" id="rankVal" style="color: var(--purple);">5</div>
          <div class="stat-sub">จาก 40 คน</div>
        </div>
      </div>
    </div>

    <!-- ATTENDANCE PAGE -->
    <div id="page-attendance" class="page">
      <div class="page-header"><h1>การเข้าเรียน</h1><p>สถิติการเข้าเรียนรายวิชา</p></div>

      <div class="child-tabs" id="childTabsAttendance" style="margin-bottom: 1.5rem;">
        <!-- render โดย JS จาก API -->
      </div>

      <div class="grid-2">
        <div class="card">
          <div class="card-title"><i class="ti ti-calendar-stats"></i> สรุปการเข้าเรียน</div>
          <div class="attend-row"><div class="attend-dot" style="background: var(--green)"></div><div class="attend-label">เข้าเรียนปกติ</div><div class="attend-count">46 วัน</div><div class="attend-bar-wrap"><div class="attend-bar" style="width: 92%; background: var(--green)"></div></div></div>
          <div class="attend-row"><div class="attend-dot" style="background: var(--accent)"></div><div class="attend-label">ลาป่วย</div><div class="attend-count">2 วัน</div><div class="attend-bar-wrap"><div class="attend-bar" style="width: 4%; background: var(--accent)"></div></div></div>
          <div class="attend-row"><div class="attend-dot" style="background: var(--blue)"></div><div class="attend-label">ลากิจ</div><div class="attend-count">1 วัน</div><div class="attend-bar-wrap"><div class="attend-bar" style="width: 2%; background: var(--blue)"></div></div></div>
          <div class="attend-row"><div class="attend-dot" style="background: var(--red)"></div><div class="attend-label">ขาดเรียน</div><div class="attend-count">1 วัน</div><div class="attend-bar-wrap"><div class="attend-bar" style="width: 2%; background: var(--red)"></div></div></div>
        </div>

        <div class="card">
          <div class="card-title"><i class="ti ti-percentage"></i> อัตราการเข้าเรียน</div>
          <div style="display: flex; align-items: center; justify-content: center; height: 120px;">
            <div style="text-align: center;">
              <div style="font-size: 3rem; font-weight: 700; color: var(--green);">96%</div>
              <div style="font-size: 0.8rem; color: var(--text-muted);">ของวันทั้งหมด 50 วัน</div>
            </div>
          </div>
          <div style="background: #161616; border-radius: 10px; padding: 0.8rem 1rem; font-size: 0.78rem;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 6px;"><span style="color: var(--text-muted)">เป้าหมายขั้นต่ำ</span><span>80%</span></div>
            <div class="prog-bg"><div class="prog-fill green" style="width: 96%"></div></div>
          </div>
        </div>
      </div>

      <div class="card" style="margin-top: 1.2rem;">
        <div class="card-title"><i class="ti ti-table"></i> รายวิชาที่ขาดเรียน</div>
        <table class="grade-table">
          <thead><tr><th>วันที่</th><th>รายวิชา</th><th>สถานะ</th><th>หมายเหตุ</th></tr></thead>
          <tbody>
            <tr><td>12 เม.ย. 2569</td><td>คณิตศาสตร์</td><td><span class="grade-pill grade-d">ขาด</span></td><td style="color: var(--text-muted);">-</td></tr>
            <tr><td>3 พ.ค. 2569</td><td>ภาษาไทย</td><td><span class="grade-pill grade-c">ลาป่วย</span></td><td style="color: var(--text-muted);">ใบรับรองแพทย์</td></tr>
            <tr><td>18 พ.ค. 2569</td><td>สังคมศึกษา</td><td><span class="grade-pill grade-b">ลากิจ</span></td><td style="color: var(--text-muted);">ธุระครอบครัว</td></tr>
            <tr><td>22 พ.ค. 2569</td><td>วิทยาศาสตร์</td><td><span class="grade-pill grade-c">ลาป่วย</span></td><td style="color: var(--text-muted);">ไข้หวัด</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- MESSAGES PAGE -->
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

    <!-- NOTIFICATIONS PAGE -->
    <div id="page-notifications" class="page">
      <div class="page-header"><h1>การแจ้งเตือน</h1><p>การแจ้งเตือนทั้งหมดจากระบบ</p></div>
      <div class="card">
        <div class="card-title"><i class="ti ti-bell"></i> การแจ้งเตือนทั้งหมด</div>
        <div class="notif-item"><div class="notif-icon warn"><i class="ti ti-clock-exclamation"></i></div><div class="notif-text"><p>กานต์มีงานค้างส่ง: รายงานวิทยาศาสตร์ ม.4 (ครบกำหนด 25 พ.ค.)</p><span>2 ชั่วโมงที่แล้ว · กานต์ ใจดี</span></div></div>
        <div class="notif-item"><div class="notif-icon warn"><i class="ti ti-alert-circle"></i></div><div class="notif-text"><p>พิมขาดเรียนวิชาคณิตศาสตร์ โดยไม่มีใบลา วันที่ 24 พ.ค.</p><span>เมื่อวาน · พิม ใจดี</span></div></div>
        <div class="notif-item"><div class="notif-icon info"><i class="ti ti-message-2"></i></div><div class="notif-text"><p>อ.สมชาย ส่งข้อความใหม่เกี่ยวกับผลการเรียนของกานต์</p><span>เมื่อวาน · กานต์ ใจดี</span></div></div>
        <div class="notif-item"><div class="notif-icon ok"><i class="ti ti-trophy"></i></div><div class="notif-text"><p>กานต์ได้รับรางวัลคะแนนสูงสุดในชั้น วิชาวิทยาศาสตร์ เดือนพฤษภาคม</p><span>3 วันที่แล้ว · กานต์ ใจดี</span></div></div>
        <div class="notif-item"><div class="notif-icon info"><i class="ti ti-calendar-event"></i></div><div class="notif-text"><p>แจ้งเตือนกำหนดการสอบปลายภาค: 10–20 มิ.ย. 2569</p><span>5 วันที่แล้ว · ทุกคน</span></div></div>
        <div class="notif-item"><div class="notif-icon ok"><i class="ti ti-star"></i></div><div class="notif-text"><p>พิมได้คะแนนทดสอบภาษาอังกฤษ 90/100 ดีเยี่ยม!</p><span>1 สัปดาห์ที่แล้ว · พิม ใจดี</span></div></div>
      </div>
    </div>

    <!-- SETTINGS PAGE -->
    <div id="page-settings" class="page">
      <div class="page-header">
        <h1>ตั้งค่าโปรไฟล์</h1>
        <p>จัดการข้อมูลส่วนตัวและรหัสผ่านของคุณ</p>
      </div>

      <div class="settings-wrapper">

        <!-- Profile Card -->
        <div class="card settings-card">
          <div class="card-title"><i class="ti ti-user-circle"></i> โปรไฟล์ผู้ปกครอง</div>

          <!-- Avatar section -->
          <div class="avatar-section">
            <div class="avatar-picker" onclick="document.getElementById('avatarInput').click()" title="คลิกเพื่อเปลี่ยนรูปโปรไฟล์">
              <div class="avatar-large" id="avatarDisplay">
                <img id="avatarImg" src="" alt="" style="display:none;width:100%;height:100%;object-fit:cover;border-radius:50%">
                <span id="avatarInitial">สม</span>
              </div>
              <div class="avatar-edit-badge"><i class="ti ti-pencil"></i></div>
              <input type="file" id="avatarInput" accept="image/*" style="display:none" onchange="previewAvatar(this)">
            </div>
            <div class="avatar-info">
              <div class="avatar-name" id="displayName">คุณสมหญิง ใจดี</div>
              <div class="avatar-role" id="profileRole">ผู้ปกครอง</div>
              <div class="avatar-hint">คลิกที่รูปเพื่อเปลี่ยน</div>
            </div>
          </div>

          <!-- Form fields -->
          <div class="settings-form">
            <div class="settings-field">
              <label class="settings-label">ชื่อ-นามสกุล</label>
              <input type="text" class="settings-input" id="profileName" placeholder="กรอกชื่อ-นามสกุล">
            </div>
            <div class="settings-field">
              <label class="settings-label">อีเมล</label>
              <input type="email" class="settings-input" id="profileEmail" placeholder="กรอกอีเมล">
            </div>
            <div class="settings-field">
              <label class="settings-label">เบอร์โทรศัพท์</label>
              <input type="tel" class="settings-input" id="profilePhone" placeholder="กรอกเบอร์โทรศัพท์">
            </div>
            <div class="settings-field">
              <label class="settings-label">ความสัมพันธ์</label>
              <input type="text" class="settings-input" id="profileRelation" value="ผู้ปกครอง" readonly style="opacity:.5;cursor:not-allowed">
            </div>
          </div>

          <!-- Password section -->
          <div class="pwd-section">
            <div class="pwd-section-title"><i class="ti ti-lock"></i> เปลี่ยนรหัสผ่าน</div>
            <div class="settings-form">
              <div class="settings-field">
                <label class="settings-label">รหัสผ่านปัจจุบัน</label>
                <div class="pwd-input-wrap">
                  <input type="password" class="settings-input" id="pwdCurrent" placeholder="ใส่รหัสผ่านปัจจุบัน">
                  <button type="button" onclick="togglePwd('pwdCurrent',this)" class="pwd-toggle-btn">👁</button>
                </div>
              </div>
              <div class="settings-field">
                <label class="settings-label">รหัสผ่านใหม่</label>
                <div class="pwd-input-wrap">
                  <input type="password" class="settings-input" id="pwdNew" placeholder="อย่างน้อย 6 ตัวอักษร" oninput="checkPwdStrength(this.value)">
                  <button type="button" onclick="togglePwd('pwdNew',this)" class="pwd-toggle-btn">👁</button>
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
                  <button type="button" onclick="togglePwd('pwdConfirm',this)" class="pwd-toggle-btn">👁</button>
                </div>
                <div id="pwdMatchMsg" class="pwd-match-msg"></div>
              </div>
            </div>
          </div>

          <div id="profileFeedback" style="display:none;padding:10px 14px;border-radius:8px;margin-top:12px;font-size:0.82rem;"></div>
          <button class="btn-save-profile" id="saveProfileBtn" onclick="saveProfile()">
            <i class="ti ti-device-floppy"></i> บันทึกข้อมูล
          </button>
        </div>

        <!-- Notification Card -->
        <div class="card settings-card">
          <div class="card-title"><i class="ti ti-bell"></i> การแจ้งเตือน</div>
          <div class="notif-list">
            <label class="notif-toggle">
              <input type="checkbox" checked style="accent-color:var(--accent);width:16px;height:16px">
              <span>แจ้งเตือนงานค้างส่งของบุตรหลาน</span>
            </label>
            <label class="notif-toggle">
              <input type="checkbox" checked style="accent-color:var(--accent);width:16px;height:16px">
              <span>แจ้งเตือนข้อความจากอาจารย์</span>
            </label>
            <label class="notif-toggle">
              <input type="checkbox" checked style="accent-color:var(--accent);width:16px;height:16px">
              <span>แจ้งเตือนการขาดเรียน</span>
            </label>
            <label class="notif-toggle">
              <input type="checkbox" style="accent-color:var(--accent);width:16px;height:16px">
              <span>แจ้งเตือนผลคะแนนสอบ</span>
            </label>
            <label class="notif-toggle">
              <input type="checkbox" style="accent-color:var(--accent);width:16px;height:16px">
              <span>แจ้งเตือนกิจกรรมและข่าวสาร</span>
            </label>
          </div>
        </div>

      </div>
    </div>

  </main>
</div>

<!-- MESSAGE DETAIL OVERLAY -->
<div class="overlay" id="msgOverlay" onclick="closeMsg(event)">
  <div class="msg-detail" id="msgDetail">
    <div class="msg-detail-header">
      <div>
        <div style="font-weight: 600; font-size: 1rem;" id="detailSender"></div>
        <div style="font-size: 0.75rem; color: var(--accent); margin-top: 2px;" id="detailSubject"></div>
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

<script src="parent_dashboard.js"></script>
</body>
</html>