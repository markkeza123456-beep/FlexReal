<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Flexible Learning Hub — แดชบอร์ดนักเรียน</title>
<link rel="stylesheet" href="regis.css">
<style>
  @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600&family=IBM+Plex+Sans+Thai:wght@300;400;500;600&display=swap');

</style>
</head>
<body>
<div class="layout">

  <!-- ===== SIDEBAR ===== -->
  <nav class="sidebar">
    <div class="sidebar-brand">
      <div class="brand-logo">
        <div class="brand-icon">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
          </svg>
        </div>
        <div>
          <div class="brand-name">Flexible Learning Hub</div>
          <div class="brand-sub">ระบบการเรียนทางเลือก</div>
        </div>
      </div>
      <div class="student-pill">
        <span class="student-pill-dot"></span>
        ผู้เรียน (Homeschool)
      </div>
    </div>

    <div class="nav-group">
      <div class="nav-label">หลัก</div>
      <a class="nav-item active" href="#">
        <svg fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        แดชบอร์ด
      </a>
      <a class="nav-item" href="#" onclick="showSection('schedule')">
        <svg fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        ตารางการเรียน
      </a>
    </div>

    <div class="nav-group">
      <div class="nav-label">การเรียน</div>
      <a class="nav-item" href="#" onclick="showSection('activities')">
        <svg fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        บันทึกกิจกรรม
      </a>
      <a class="nav-item" href="#" onclick="showSection('assessment')">
        <svg fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4"/><rect x="3" y="3" width="18" height="18" rx="3"/></svg>
        ผลการประเมิน
      </a>
      <a class="nav-item" href="#" onclick="showSection('subjects')">
        <svg fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
        รายวิชา
      </a>
      <a class="nav-item" href="#" onclick="showSection('tests')">
        <svg fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path d="M12 18v-5.25m0 0a6.01 6.01 0 001.5-.189m-1.5.189a6.01 6.01 0 01-1.5-.189m3.75 7.478a12.06 12.06 0 01-4.5 0m3.75 2.383a14.406 14.406 0 01-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 10-7.517 0c.85.493 1.509 1.333 1.509 2.316V18"/></svg>
        แบบทดสอบ
      </a>
    </div>

    <div class="nav-group">
      <div class="nav-label">เอกสาร</div>
      <a class="nav-item" href="#" onclick="showSection('portfolio')">
        <svg fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/></svg>
        แฟ้มสะสมงาน
      </a>
      <a class="nav-item" href="#" onclick="showSection('transfer')">
        <svg fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/></svg>
        เทียบโอนผลการเรียน
      </a>
    </div>

    <div class="sidebar-footer">
      <div class="avatar-sm">ส</div>
      <div>
        <div class="footer-name">สมชาย ใจดี</div>
        <div class="footer-role">S001 · Homeschool</div>
      </div>
      <button class="logout-btn" title="ออกจากระบบ">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
      </button>
    </div>
  </nav>

  <!-- ===== MAIN ===== -->
  <div class="main">
    <!-- Topbar -->
    <div class="topbar">
      <div class="topbar-left">
        <div>
          <div class="topbar-greeting">สวัสดีตอนเช้า, สมชาย</div>
          <div class="topbar-date" id="current-date"></div>
        </div>
      </div>
      <div class="topbar-right">
        <div class="notif-btn" onclick="openModal('notif-modal')">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
          <div class="notif-dot"></div>
        </div>
        <button class="btn" onclick="openModal('checkin-modal')">เช็คอินเรียน</button>
        <button class="btn btn-primary" onclick="openModal('activity-modal')">+ บันทึกกิจกรรม</button>
      </div>
    </div>

    <!-- Content -->
    <div class="content">

      <!-- Check-in status bar -->
      <div class="checkin-card">
        <div class="checkin-status">
          <div class="checkin-indicator" id="checkin-dot"></div>
          <div>
            <div class="checkin-label" id="checkin-label">กำลังเรียนอยู่ — คณิตศาสตร์</div>
            <div class="checkin-time" id="checkin-time">เช็คอินเมื่อ 09:00 น.</div>
          </div>
        </div>
        <div class="checkin-duration" id="timer">02:14:37</div>
        <div class="checkin-actions">
          <button class="btn" onclick="openModal('checkin-modal')">เปลี่ยนวิชา</button>
          <button class="btn btn-danger" onclick="checkOut()">เช็คเอาท์</button>
        </div>
      </div>

      <!-- Hero card -->
      <div class="hero-card">
        <div>
          <div class="hero-greeting">ความก้าวหน้าภาพรวม</div>
          <div class="hero-name">สมชาย ใจดี</div>
          <div class="hero-type">
            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            Homeschool · ปีการศึกษา 2569
          </div>
        </div>
        <div class="hero-right">
          <div class="ring-container">
            <svg width="72" height="72" viewBox="0 0 72 72">
              <circle cx="36" cy="36" r="29" fill="none" stroke="rgba(255,255,255,0.2)" stroke-width="6"/>
              <circle cx="36" cy="36" r="29" fill="none" stroke="#fff" stroke-width="6"
                stroke-dasharray="182" stroke-dashoffset="50" stroke-linecap="round"/>
            </svg>
            <div class="ring-text">
              <div class="ring-pct">72%</div>
              <div class="ring-sub">สำเร็จ</div>
            </div>
          </div>
          <div class="hero-progress-sub">อีก 28% ถึงเป้าหมาย</div>
        </div>
      </div>

      <!-- Stats -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon green">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
          </div>
          <div class="stat-label">รายวิชาที่เรียน</div>
          <div class="stat-value">5</div>
          <div class="stat-sub">กลุ่มสาระ 3 หมวด</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon blue">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          </div>
          <div class="stat-label">ชั่วโมงการเรียน</div>
          <div class="stat-value">148</div>
          <div class="stat-sub">เป้าหมาย 200 ชม./ปี</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon amber">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4"/><rect x="3" y="3" width="18" height="18" rx="3"/></svg>
          </div>
          <div class="stat-label">กิจกรรมสัปดาห์นี้</div>
          <div class="stat-value">7</div>
          <div class="stat-sub">เป้าหมาย 10 กิจกรรม</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon teal">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/></svg>
          </div>
          <div class="stat-label">คะแนนเฉลี่ย</div>
          <div class="stat-value">78<span style="font-size:14px;font-weight:400;color:var(--text3)">/100</span></div>
          <div class="stat-sub">ระดับ B+ (ดี)</div>
        </div>
      </div>

      <!-- Two columns: Subjects + Activities -->
      <div class="two-col">
        <!-- Subjects progress -->
        <div class="card">
          <div class="card-header">
            <div class="card-title">ความก้าวหน้ารายวิชา</div>
            <a class="card-action" href="#">ดูทั้งหมด →</a>
          </div>
          <div class="card-body">
            <div class="subject-item">
              <div class="subject-dot" style="background:#1D9E75"></div>
              <div class="subject-info">
                <div class="subject-name">คณิตศาสตร์</div>
                <div class="subject-meta">3 หน่วยกิต · อ.สุวิทย์</div>
              </div>
              <div class="subject-progress">
                <div class="prog-bar"><div class="prog-fill" style="width:85%;background:#1D9E75"></div></div>
                <div class="prog-val">85%</div>
              </div>
            </div>
            <div class="subject-item">
              <div class="subject-dot" style="background:#378ADD"></div>
              <div class="subject-info">
                <div class="subject-name">ภาษาไทย</div>
                <div class="subject-meta">3 หน่วยกิต · อ.ทิพาพร</div>
              </div>
              <div class="subject-progress">
                <div class="prog-bar"><div class="prog-fill" style="width:70%;background:#378ADD"></div></div>
                <div class="prog-val">70%</div>
              </div>
            </div>
            <div class="subject-item">
              <div class="subject-dot" style="background:#EF9F27"></div>
              <div class="subject-info">
                <div class="subject-name">ภาษาอังกฤษ</div>
                <div class="subject-meta">2 หน่วยกิต · อ.ลาวัณย์</div>
              </div>
              <div class="subject-progress">
                <div class="prog-bar"><div class="prog-fill" style="width:60%;background:#EF9F27"></div></div>
                <div class="prog-val">60%</div>
              </div>
            </div>
            <div class="subject-item">
              <div class="subject-dot" style="background:#D4537E"></div>
              <div class="subject-info">
                <div class="subject-name">วิทยาศาสตร์</div>
                <div class="subject-meta">3 หน่วยกิต · อ.สุวิทย์</div>
              </div>
              <div class="subject-progress">
                <div class="prog-bar"><div class="prog-fill" style="width:72%;background:#D4537E"></div></div>
                <div class="prog-val">72%</div>
              </div>
            </div>
            <div class="subject-item">
              <div class="subject-dot" style="background:#639922"></div>
              <div class="subject-info">
                <div class="subject-name">สังคมศึกษา</div>
                <div class="subject-meta">2 หน่วยกิต · อ.ทิพาพร</div>
              </div>
              <div class="subject-progress">
                <div class="prog-bar"><div class="prog-fill" style="width:55%;background:#639922"></div></div>
                <div class="prog-val">55%</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent activities -->
        <div class="card">
          <div class="card-header">
            <div class="card-title">กิจกรรมการเรียนล่าสุด</div>
            <a class="card-action" href="#">ดูทั้งหมด →</a>
          </div>
          <div class="card-body">
            <div class="activity-item">
              <div class="activity-time">
                <div class="activity-time-val">2</div>
                <div>ชม.</div>
              </div>
              <div class="activity-type at-read">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
              </div>
              <div class="activity-detail">
                <div class="activity-name">การอ่าน — ภาษาไทย</div>
                <div class="activity-sub">14 เม.ย. 2569 · บันทึกแล้ว</div>
              </div>
            </div>
            <div class="activity-item">
              <div class="activity-time">
                <div class="activity-time-val">3</div>
                <div>ชม.</div>
              </div>
              <div class="activity-type at-test">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              </div>
              <div class="activity-detail">
                <div class="activity-name">การทดลอง — วิทยาศาสตร์</div>
                <div class="activity-sub">13 เม.ย. 2569 · บันทึกแล้ว</div>
              </div>
            </div>
            <div class="activity-item">
              <div class="activity-time">
                <div class="activity-time-val">1.5</div>
                <div>ชม.</div>
              </div>
              <div class="activity-type at-vid">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 10l4.553-2.069A1 1 0 0121 8.82v6.36a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/></svg>
              </div>
              <div class="activity-detail">
                <div class="activity-name">ออนไลน์ — คณิตศาสตร์</div>
                <div class="activity-sub">12 เม.ย. 2569 · รอยืนยัน</div>
              </div>
            </div>
            <div class="activity-item">
              <div class="activity-time">
                <div class="activity-time-val">4</div>
                <div>ชม.</div>
              </div>
              <div class="activity-type at-proj">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
              </div>
              <div class="activity-detail">
                <div class="activity-name">โครงงาน — สังคมศึกษา</div>
                <div class="activity-sub">11 เม.ย. 2569 · บันทึกแล้ว</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Today's schedule + Assessment results -->
      <div class="two-col">
        <div class="card">
          <div class="card-header">
            <div class="card-title">ตารางเรียนวันนี้</div>
            <span class="pill pill-green">วันอังคาร</span>
          </div>
          <div class="card-body">
            <div class="schedule-item">
              <div class="schedule-time">09:00 – 11:00</div>
              <div class="schedule-bar" style="background:#1D9E75"></div>
              <div class="schedule-info">
                <div class="schedule-name">คณิตศาสตร์</div>
                <div class="schedule-meta">อ.สุวิทย์ · ออนไลน์</div>
              </div>
              <span class="pill pill-teal">กำลังเรียน</span>
            </div>
            <div class="schedule-item">
              <div class="schedule-time">11:30 – 13:00</div>
              <div class="schedule-bar" style="background:#378ADD"></div>
              <div class="schedule-info">
                <div class="schedule-name">ภาษาไทย</div>
                <div class="schedule-meta">อ.ทิพาพร · ห้องเรียน</div>
              </div>
              <span class="pill pill-blue">ถัดไป</span>
            </div>
            <div class="schedule-item">
              <div class="schedule-time">13:30 – 15:00</div>
              <div class="schedule-bar" style="background:#EF9F27"></div>
              <div class="schedule-info">
                <div class="schedule-name">ภาษาอังกฤษ</div>
                <div class="schedule-meta">อ.ลาวัณย์ · ออนไลน์</div>
              </div>
              <span class="pill pill-amber">รอ</span>
            </div>
            <div class="schedule-item">
              <div class="schedule-time">15:30 – 17:00</div>
              <div class="schedule-bar" style="background:#639922"></div>
              <div class="schedule-info">
                <div class="schedule-name">สังคมศึกษา</div>
                <div class="schedule-meta">อ.ทิพาพร · ห้องเรียน</div>
              </div>
              <span class="pill pill-amber">รอ</span>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <div class="card-title">ผลการประเมินล่าสุด</div>
            <a class="card-action" href="#">ดูทั้งหมด →</a>
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>วิชา</th>
                  <th>ประเภท</th>
                  <th>คะแนน</th>
                  <th>เกรด</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>คณิตศาสตร์</td>
                  <td><span class="pill pill-amber">แบบทดสอบ</span></td>
                  <td>78/100</td>
                  <td><span class="grade-circle g-b">B</span></td>
                </tr>
                <tr>
                  <td>ภาษาไทย</td>
                  <td><span class="pill pill-blue">ชิ้นงาน</span></td>
                  <td>92/100</td>
                  <td><span class="grade-circle g-a">A</span></td>
                </tr>
                <tr>
                  <td>วิทยาศาสตร์</td>
                  <td><span class="pill pill-amber">แบบทดสอบ</span></td>
                  <td>65/100</td>
                  <td><span class="grade-circle g-c">C</span></td>
                </tr>
                <tr>
                  <td>ภาษาอังกฤษ</td>
                  <td><span class="pill pill-amber">แบบทดสอบ</span></td>
                  <td>55/100</td>
                  <td><span class="grade-circle g-d">D</span></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Credit transfer status -->
      <div class="transfer-card">
        <div class="transfer-header">
          <div class="card-title">สถานะคำร้องเทียบโอน</div>
          <span class="pill pill-amber">รอพิจารณา</span>
        </div>
        <div style="font-size:12px;color:var(--text3);margin-bottom:14px">คำร้องเทียบโอน: ภาษาอังกฤษ Grade 5 → ENG301 (2 หน่วยกิต)</div>
        <div class="transfer-steps">
          <div class="step-item done">
            <div class="step-dot">✓</div>
            <div class="step-label">ยื่นคำร้อง</div>
          </div>
          <div class="step-item done">
            <div class="step-dot">✓</div>
            <div class="step-label">ตรวจเอกสาร</div>
          </div>
          <div class="step-item current">
            <div class="step-dot">3</div>
            <div class="step-label">พิจารณา</div>
          </div>
          <div class="step-item">
            <div class="step-dot">4</div>
            <div class="step-label">อนุมัติ</div>
          </div>
          <div class="step-item">
            <div class="step-dot">5</div>
            <div class="step-label">บันทึกผล</div>
          </div>
        </div>
      </div>

      <!-- Portfolio -->
      <div class="card" style="margin-bottom:20px">
        <div class="card-header">
          <div class="card-title">แฟ้มสะสมงาน (Portfolio)</div>
          <button class="btn btn-primary" style="padding:5px 12px;font-size:11px" onclick="openModal('portfolio-modal')">+ เพิ่มผลงาน</button>
        </div>
        <div class="card-body">
          <div class="portfolio-grid">
            <div class="portfolio-item">
              <div class="portfolio-icon" style="background:#E6F1FB;color:#185FA5">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
              </div>
              <div class="portfolio-name">โครงงานสังคมศึกษา</div>
              <div class="portfolio-date">14 เม.ย. 2569</div>
            </div>
            <div class="portfolio-item">
              <div class="portfolio-icon" style="background:var(--accent-light);color:var(--accent-dark)">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23-.693L5 14.5m14.8.8l1.402 1.402c1 1 .325 2.716-1.067 2.842l-1.322.12a11.067 11.067 0 01-1.813 0l-1.322-.12c-1.392-.126-2.067-1.842-1.067-2.842L5 14.5"/></svg>
              </div>
              <div class="portfolio-name">การทดลองวิทยาศาสตร์</div>
              <div class="portfolio-date">10 เม.ย. 2569</div>
            </div>
            <div class="portfolio-item">
              <div class="portfolio-icon" style="background:#FAEEDA;color:#854F0B">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 18v-5.25m0 0a6.01 6.01 0 001.5-.189m-1.5.189a6.01 6.01 0 01-1.5-.189m3.75 7.478a12.06 12.06 0 01-4.5 0m3.75 2.383a14.406 14.406 0 01-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 10-7.517 0c.85.493 1.509 1.333 1.509 2.316V18"/></svg>
              </div>
              <div class="portfolio-name">แบบทดสอบคณิตศาสตร์</div>
              <div class="portfolio-date">08 เม.ย. 2569</div>
            </div>
          </div>
        </div>
      </div>

    </div><!-- end content -->
  </div><!-- end main -->
</div><!-- end layout -->

<!-- ===== MODALS ===== -->

<!-- Check-in modal -->
<div class="modal-overlay" id="checkin-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">บันทึกเวลาเข้า-ออก</div>
      <button class="modal-close" onclick="closeModal('checkin-modal')">×</button>
    </div>
    <div class="form-group">
      <label class="form-label">รายวิชา</label>
      <select class="form-select">
        <option>คณิตศาสตร์</option>
        <option>ภาษาไทย</option>
        <option>ภาษาอังกฤษ</option>
        <option>วิทยาศาสตร์</option>
        <option>สังคมศึกษา</option>
      </select>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">เวลาเข้า</label>
        <input class="form-input" type="time" value="09:00"/>
      </div>
      <div class="form-group">
        <label class="form-label">เวลาออก</label>
        <input class="form-input" type="time" value="11:00"/>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">หมายเหตุ</label>
      <input class="form-input" placeholder="บันทึกเพิ่มเติม (ถ้ามี)"/>
    </div>
    <button class="btn btn-primary" style="width:100%" onclick="closeModal('checkin-modal')">บันทึกเวลา</button>
  </div>
</div>

<!-- Activity modal -->
<div class="modal-overlay" id="activity-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">บันทึกกิจกรรมการเรียน</div>
      <button class="modal-close" onclick="closeModal('activity-modal')">×</button>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">ประเภทกิจกรรม</label>
        <select class="form-select">
          <option>การอ่าน</option>
          <option>การทดลอง</option>
          <option>ออนไลน์</option>
          <option>โครงงาน</option>
          <option>การเขียน</option>
          <option>การนำเสนอ</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">รายวิชา</label>
        <select class="form-select">
          <option>คณิตศาสตร์</option>
          <option>ภาษาไทย</option>
          <option>ภาษาอังกฤษ</option>
          <option>วิทยาศาสตร์</option>
          <option>สังคมศึกษา</option>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">รายละเอียดกิจกรรม</label>
      <input class="form-input" placeholder="อธิบายสิ่งที่เรียน/ทำ"/>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">จำนวนชั่วโมง</label>
        <input class="form-input" type="number" step="0.5" min="0.5" value="1.5"/>
      </div>
      <div class="form-group">
        <label class="form-label">วันที่</label>
        <input class="form-input" type="date" id="activity-date"/>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">แนบไฟล์หลักฐาน (ชื่อไฟล์)</label>
      <input class="form-input" placeholder="เช่น lab-report.pdf"/>
    </div>
    <button class="btn btn-primary" style="width:100%" onclick="closeModal('activity-modal')">บันทึกกิจกรรม</button>
  </div>
</div>

<!-- Portfolio modal -->
<div class="modal-overlay" id="portfolio-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">เพิ่มผลงานในแฟ้มสะสมงาน</div>
      <button class="modal-close" onclick="closeModal('portfolio-modal')">×</button>
    </div>
    <div class="form-group">
      <label class="form-label">ชื่อผลงาน</label>
      <input class="form-input" placeholder="ชื่อผลงาน/โครงงาน"/>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">รายวิชา</label>
        <select class="form-select">
          <option>คณิตศาสตร์</option>
          <option>ภาษาไทย</option>
          <option>วิทยาศาสตร์</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">ประเภทผลงาน</label>
        <select class="form-select">
          <option>เอกสาร</option>
          <option>การทดลอง</option>
          <option>วิดีโอ</option>
          <option>ภาพถ่าย</option>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">คำอธิบาย</label>
      <input class="form-input" placeholder="อธิบายผลงานโดยย่อ"/>
    </div>
    <button class="btn btn-primary" style="width:100%" onclick="closeModal('portfolio-modal')">บันทึกผลงาน</button>
  </div>
</div>

<!-- Notification modal -->
<div class="modal-overlay" id="notif-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">การแจ้งเตือน</div>
      <button class="modal-close" onclick="closeModal('notif-modal')">×</button>
    </div>
    <div style="display:flex;flex-direction:column;gap:0">
      <div style="padding:12px 0;border-bottom:1px solid var(--border)">
        <div style="display:flex;gap:10px;align-items:flex-start">
          <div style="width:8px;height:8px;background:var(--accent);border-radius:50%;margin-top:5px;flex-shrink:0"></div>
          <div>
            <div style="font-size:13px;font-weight:500;color:var(--text)">กิจกรรมวิทยาศาสตร์รอยืนยัน</div>
            <div style="font-size:11px;color:var(--text3);margin-top:2px">อ.สุวิทย์ ต้องยืนยันกิจกรรมการทดลอง 12 เม.ย.</div>
          </div>
        </div>
      </div>
      <div style="padding:12px 0;border-bottom:1px solid var(--border)">
        <div style="display:flex;gap:10px;align-items:flex-start">
          <div style="width:8px;height:8px;background:#EF9F27;border-radius:50%;margin-top:5px;flex-shrink:0"></div>
          <div>
            <div style="font-size:13px;font-weight:500;color:var(--text)">แบบทดสอบภาษาอังกฤษ</div>
            <div style="font-size:11px;color:var(--text3);margin-top:2px">มีแบบทดสอบใหม่จาก อ.ลาวัณย์ ภายใน 3 วัน</div>
          </div>
        </div>
      </div>
      <div style="padding:12px 0">
        <div style="display:flex;gap:10px;align-items:flex-start">
          <div style="width:8px;height:8px;background:#378ADD;border-radius:50%;margin-top:5px;flex-shrink:0"></div>
          <div>
            <div style="font-size:13px;font-weight:500;color:var(--text)">คำร้องเทียบโอนอัปเดต</div>
            <div style="font-size:11px;color:var(--text3);margin-top:2px">คำร้อง ENG301 กำลังอยู่ระหว่างการพิจารณา</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  // Date
  var days = ['อาทิตย์','จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์'];
  var months = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
  var now = new Date();
  document.getElementById('current-date').textContent = 'วัน' + days[now.getDay()] + 'ที่ ' + now.getDate() + ' ' + months[now.getMonth()] + ' ' + (now.getFullYear() + 543);
  document.getElementById('activity-date').value = now.toISOString().split('T')[0];

  // Timer
  var start = new Date();
  start.setHours(9,0,0,0);
  function updateTimer() {
    var diff = Math.floor((Date.now() - start.getTime()) / 1000);
    if(diff < 0) diff = 0;
    var h = Math.floor(diff/3600);
    var m = Math.floor((diff%3600)/60);
    var s = diff%60;
    document.getElementById('timer').textContent = String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
  }
  updateTimer();
  setInterval(updateTimer, 1000);

  // Modal
  function openModal(id) { document.getElementById(id).classList.add('open'); }
  function closeModal(id) { document.getElementById(id).classList.remove('open'); }
  document.querySelectorAll('.modal-overlay').forEach(function(el) {
    el.addEventListener('click', function(e) {
      if(e.target === el) el.classList.remove('open');
    });
  });

  // Check out
  function checkOut() {
    document.getElementById('checkin-dot').style.background = '#888';
    document.getElementById('checkin-dot').style.boxShadow = 'none';
    document.getElementById('checkin-label').textContent = 'ออกจากการเรียนแล้ว';
    document.getElementById('checkin-time').textContent = 'เช็คเอาท์เมื่อ ' + new Date().getHours() + ':' + String(new Date().getMinutes()).padStart(2,'0') + ' น.';
  }

  // Nav highlight
  function showSection(name) {
    document.querySelectorAll('.nav-item').forEach(function(el){ el.classList.remove('active'); });
    event.target.closest('.nav-item').classList.add('active');
  }
</script>
</body>
</html>