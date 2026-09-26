<?php
session_start();
if (!isset($_SESSION['user_id']) || strtolower((string) ($_SESSION['role'] ?? '')) !== 'student') {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flexible Learning Hub - Student Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="theme.css">
    <link rel="stylesheet" href="student_dashboard.css?v=20260926-no-dashboard-notifications">
</head>
<body>
    <div class="dashboard-container">

        <aside class="sidebar">
            <a class="logo-section" href="index.html" title="กลับหน้าหลักและรายวิชา">
                <h2>FLEXIBLE</h2>
                <span>LEARNING HUB</span>
            </a>
            <nav class="menu">
                <button type="button" class="menu-item active" id="btn-dashboard">
                    <span class="icon">⊞</span> แดชบอร์ด
                </button>
                <button type="button" class="menu-item" id="btn-lessons">
                    <span class="icon"></span> บทเรียน
                </button>
                <button type="button" class="menu-item" id="btn-reports">
                    <span class="icon"></span> รายงานผล
                </button>
            </nav>
            <a href="logout.php" class="btn-logout" onclick="return confirm('ต้องการออกจากระบบหรือไม่?')">
                <span></span> ออกจากระบบ
            </a>
            <button type="button" class="user-profile" id="btn-settings" title="แก้ไขโปรไฟล์">
                <div class="avatar" id="sidebarAvatar">-</div>
                <div class="user-info">
                    <p class="name" id="sidebarName">กำลังโหลด...</p>
                    <p class="role" id="sidebarRole">-</p>
                </div>
                <span style="margin-left:auto;font-size:0.8rem;color:var(--accent-orange);">️</span>
            </button>
        </aside>


        <main class="main-content">


            <section id="dashboard-page" class="content-section">
                <header class="header">
                    <div class="welcome">
                        <h1>แดชบอร์ด</h1>
                        <p id="dashboardOwnerName"></p>
                        <p id="dashboardLoadError" class="dashboard-load-error" role="status" hidden></p>
                    </div>
                </header>

                <section class="stats-grid student-stats-grid">
                    <div class="stat-card orange">
                        <p class="label">วิชาที่ลงทะเบียน</p>
                        <p class="value" id="statCourseCount">0</p>
                        <span class="sub-value">รายวิชาที่เปิดเรียนอยู่</span>
                    </div>
                    <div class="stat-card blue">
                        <p class="label">แบบทดสอบที่ทำแล้ว</p>
                        <p class="value" id="statAvgProgress">0/0</p>
                        <span class="sub-value">รวมบทเรียนของวิชาที่ลงทะเบียน</span>
                    </div>
                    <div class="stat-card green">
                        <p class="label">คะแนนสะสม</p>
                        <p class="value" id="statAvgScore">0/0</p>
                        <span class="sub-value">ข้อเขียนจะเพิ่มเมื่อครูตรวจผ่าน</span>
                    </div>
                </section>

                <section class="content-card">
                    <div class="card-header">
                        <h2>ความคืบหน้าการเรียน</h2>
                        <input type="text" id="courseSearch" placeholder="ค้นหาวิชาเรียน...">
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ลำดับ</th>
                                    <th>วิชาเรียน</th>
                                    <th>แบบทดสอบในรายวิชา</th>
                                    <th>คะแนนสะสม</th>
                                </tr>
                            </thead>
                            <tbody id="courseTableBody"></tbody>
                        </table>
                    </div>
                </section>
            </section>


            <section id="lesson-page" class="content-section" style="display: none;">
                <header class="header">
                    <div class="welcome">
                        <h1>บทเรียนของฉัน</h1>
                        <p>บทเรียนที่คุณลงทะเบียนและมีความคืบหน้าจากระบบจริง</p>
                    </div>
                </header>
                <div class="lessons-container" id="lessons-list"></div>
            </section>

            <section id="reports-page" class="content-section" style="display: none;">
                <header class="header">
                    <div class="welcome"><h1>รายงานผลการเรียน</h1><p id="reportStudent"></p></div>
                    <div class="report-actions">
                        <label class="sr-only" for="reportCourseFilter">เลือกวิชา</label>
                        <select id="reportCourseFilter" aria-label="กรองรายงานตามรายวิชา"><option value="all">ทุกรายวิชา</option></select>
                        <button type="button" class="report-action-btn secondary" id="reportPrintBtn">พิมพ์รายงาน</button>
                    </div>
                </header>
                <div class="report-summary" id="reportSummary"></div>
                <section class="content-card report-card">
                    <div class="card-header"><h2>สรุปรายวิชา</h2></div>
                    <div class="table-responsive"><table class="data-table report-table course-report-table"><thead><tr><th>รายวิชา</th><th>ประเภท</th><th>แบบทดสอบ</th><th>ความคืบหน้า</th><th>คะแนนรวม</th><th>ครั้งที่ทำ</th><th>ข้อเขียนรอตรวจ</th><th>ทำล่าสุด</th></tr></thead><tbody id="courseReportBody"></tbody></table></div>
                </section>
                <section class="content-card report-card">
                    <div class="card-header"><h2>ผลคะแนนแยกตามบทเรียน</h2><span id="reportLessonCount" class="report-meta"></span></div>
                    <div class="table-responsive"><table class="data-table report-table lesson-report-table"><thead><tr><th>รายวิชา / บทเรียน</th><th>สถานะแบบทดสอบ</th><th>คะแนนรวม</th><th>ปรนัย</th><th>ข้อเขียน</th><th>จำนวนครั้ง</th><th>ทำล่าสุด</th><th>ข้อเสนอแนะจากครู</th></tr></thead><tbody id="lessonReportBody"></tbody></table></div>
                </section>
            </section>


            <section id="settings-page" class="content-section" style="display: none;">
                <header class="header">
                    <div class="welcome">
                        <h1>ตั้งค่าโปรไฟล์</h1>
                        <p>จัดการข้อมูลส่วนตัวและรหัสผ่านของคุณ</p>
                    </div>
                </header>

                <div class="settings-wrapper">


                    <div class="content-card settings-card">
                        <h2 class="settings-card-title">&#128100; โปรไฟล์</h2>

                        <div class="avatar-section">
                            <div class="avatar-picker" onclick="document.getElementById('avatarInput').click()" title="คลิกเพื่อเปลี่ยนรูปโปรไฟล์">
                                <div class="avatar-large" id="avatarDisplay">
                                    <img id="avatarImg" src="" alt="" style="display:none;width:100%;height:100%;object-fit:cover;border-radius:50%">
                                    <span id="avatarInitial">-</span>
                                </div>
                                <div class="avatar-edit-badge">&#9999;&#65039;</div>
                                <input type="file" id="avatarInput" accept="image/*" style="display:none" onchange="previewAvatar(this)">
                            </div>
                            <div class="avatar-info">
                                <div class="avatar-name" id="displayName">กำลังโหลด...</div>
                                <div class="avatar-role" id="profileRole">-</div>
                                <div class="avatar-hint">คลิกที่รูปเพื่อเปลี่ยน</div>
                            </div>
                        </div>

                        <div class="settings-form">
                            <div class="settings-field">
                                <label class="settings-label">ชื่อ-นามสกุล</label>
                                <input type="text" class="settings-input" id="profileName" value="">
                            </div>
                            <div class="settings-field">
                                <label class="settings-label">อีเมล</label>
                                <input type="email" class="settings-input" id="profileEmail" value="">
                            </div>
                            <div class="settings-field">
                                <label class="settings-label">ชั้นเรียน</label>
                                <input type="text" class="settings-input" id="profileClass" value="-" readonly style="opacity:.5;cursor:not-allowed">
                            </div>
                            <div class="settings-field">
                                <label class="settings-label">เบอร์โทรศัพท์</label>
                                <input type="tel" class="settings-input" id="profilePhone" placeholder="กรอกเบอร์โทรศัพท์">
                            </div>
                        </div>

                        <div class="pwd-section">
                            <div class="pwd-section-title">&#128272; เปลี่ยนรหัสผ่าน</div>
                            <div class="settings-form">
                                <div class="settings-field">
                                    <label class="settings-label">รหัสผ่านปัจจุบัน</label>
                                    <div class="pwd-input-wrap">
                                        <input type="password" class="settings-input" id="pwdCurrent" placeholder="ใส่รหัสผ่านปัจจุบัน">
                                        <button type="button" onclick="togglePwd('pwdCurrent',this)" class="pwd-toggle-btn">แสดง</button>
                                    </div>
                                </div>
                                <div class="settings-field">
                                    <label class="settings-label">รหัสผ่านใหม่</label>
                                    <div class="pwd-input-wrap">
                                        <input type="password" class="settings-input" id="pwdNew" placeholder="อย่างน้อย 6 ตัวอักษร" oninput="checkPwdStrength(this.value)">
                                        <button type="button" onclick="togglePwd('pwdNew',this)" class="pwd-toggle-btn">แสดง</button>
                                    </div>
                                    <div id="pwdStrengthWrap" style="display:none;margin-top:8px">
                                        <div class="strength-bar-bg">
                                            <div id="pwdStrengthBar" class="strength-bar-fill"></div>
                                        </div>
                                        <div id="pwdStrengthLabel" class="strength-label"></div>
                                    </div>
                                </div>
                                <div class="settings-field">
                                    <label class="settings-label">ยืนยันรหัสผ่านใหม่</label>
                                    <div class="pwd-input-wrap">
                                        <input type="password" class="settings-input" id="pwdConfirm" placeholder="พิมพ์รหัสผ่านใหม่อีกครั้ง" oninput="checkPwdMatch()">
                                        <button type="button" onclick="togglePwd('pwdConfirm',this)" class="pwd-toggle-btn">แสดง</button>
                                    </div>
                                    <div id="pwdMatchMsg" class="pwd-match-msg"></div>
                                </div>
                            </div>
                        </div>

                        <div id="profileFeedback" class="profile-feedback" style="display:none;"></div>
                        <button class="btn-save-profile" id="saveProfileBtn" onclick="saveProfile()">&#128190; บันทึกข้อมูล</button>
                    </div>


                </div>
            </section>

        </main>
    </div>
    <script src="student_dashboard.js?v=20260926-no-dashboard-notifications"></script>
</body>
</html>
