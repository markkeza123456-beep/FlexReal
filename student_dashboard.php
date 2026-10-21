<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$profileRole = strtolower((string) ($_SESSION['role'] ?? ''));
$profileContactReadonly = $profileRole === 'staff' ? '' : 'readonly';
if (!isset($_SESSION['user_id']) || $profileRole !== 'student') {
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
    <link rel="stylesheet" href="assets/css/student_dashboard.css?v=20261021-sidebar-profile-text-states">
    <link rel="stylesheet" href="assets/css/theme.css?v=20261021-login-shared-background">
    <link rel="stylesheet" href="assets/css/responsive.css">
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
                <a class="menu-item" href="upload_transcript.php" style="text-decoration:none;">
                    <span class="icon">⇧</span> ขอเทียบโอนหน่วยกิต
                </a>
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
                <span style="margin-left:auto;font-size:0.8rem;color:var(--text-primary);">️</span>
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

                <section class="content-card graduation-tracker" aria-labelledby="graduationTrackerTitle">
                    <div class="graduation-tracker-heading">
                        <div>
                            <h2 id="graduationTrackerTitle">ความคืบหน้าหลักสูตร</h2>
                            <p id="graduationTrackerMessage" class="graduation-tracker-message" role="status" aria-live="polite">กำลังคำนวณความคืบหน้า...</p>
                        </div>
                        <strong id="graduationCoursesLabel" class="graduation-credits-label">— / — วิชา</strong>
                    </div>
                    <div class="graduation-progress-track" role="progressbar" aria-label="ความคืบหน้ารายวิชาตามหลักสูตร" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                        <div id="graduationProgressFill" class="graduation-progress-fill"></div>
                    </div>
                    <div class="graduation-tracker-footer">
                        <span id="graduationProgressPercent">0%</span>
                        <button type="button" id="graduationRequestBtn" class="btn-graduation-request" hidden>ยื่นขอจบการศึกษา</button>
                        <a id="graduationDownloadLink" class="btn-graduation-download" href="#" hidden>ดาวน์โหลดใบสรุปผลการเรียน PDF</a>
                    </div>
                </section>

                <section class="stats-grid student-stats-grid">
                    <div class="stat-card orange">
                        <p class="label">วิชาที่ลงทะเบียน</p>
                        <p class="value" id="statCourseCount">0</p>
                        <span class="sub-value">รายวิชาที่เปิดเรียนอยู่</span>
                    </div>
                    <div class="stat-card blue">
                        <p class="label">ครั้งที่ส่งข้อสอบ</p>
                        <p class="value" id="statAvgProgress">0/0</p>
                        <span class="sub-value" id="statQuizAttemptCoverage">รวมบทเรียนของวิชาที่ลงทะเบียน</span>
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
                                    <th>รายวิชาตามหลักสูตร</th>
                                    <th>ความคืบหน้า</th>
                                    <th>สถานะผลการเรียน</th>
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
                    <div class="table-responsive"><table class="data-table report-table course-report-table"><thead><tr><th>รายวิชา</th><th>ประเภท</th><th>แบบทดสอบ</th><th>ความคืบหน้า</th><th>คะแนนรวม</th><th>ข้อเขียนรอตรวจ</th></tr></thead><tbody id="courseReportBody"></tbody></table></div>
                </section>
                <section class="content-card report-card">
                    <div class="card-header"><h2>ผลคะแนนแยกตามบทเรียน</h2><span id="reportLessonCount" class="report-meta"></span></div>
                    <div class="table-responsive">
                        <table class="data-table report-table lesson-report-table">
                            <colgroup>
                                <col style="width:22%"><col style="width:24%"><col style="width:15%">
                                <col style="width:13%"><col style="width:12%"><col style="width:14%">
                            </colgroup>
                            <thead><tr><th>รายวิชา</th><th>บทเรียน</th><th class="report-center">คะแนนปรนัย (กากบาท)</th><th class="report-center">คะแนนข้อเขียน</th><th class="report-center">คะแนนรวม</th><th class="report-center">สถานะผลการเรียน</th></tr></thead>
                            <tbody id="lessonReportBody"></tbody>
                        </table>
                    </div>
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
                            <div class="avatar-picker" title="รูปโปรไฟล์">
                                <div class="avatar-large" id="avatarDisplay">
                                    <img id="avatarImg" src="" alt="" style="display:none;width:100%;height:100%;object-fit:cover;border-radius:50%">
                                    <span id="avatarInitial">-</span>
                                </div>
                                <input type="file" id="avatarFileInput" accept="image/*" hidden onchange="previewAvatar(this)">
                                <button type="button" class="avatar-change-button" onclick="document.getElementById('avatarFileInput').click()" aria-label="เปลี่ยนรูปโปรไฟล์" title="เปลี่ยนรูปโปรไฟล์"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 4h-4l-2 3H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3z"/><circle cx="12" cy="13" r="3"/></svg></button>
                            </div>
                            <div class="avatar-info">
                                <div class="avatar-name" id="displayName">กำลังโหลด...</div>
                                <div class="avatar-role" id="profileRole">-</div>
                                <div class="avatar-hint">รูปโปรไฟล์</div>
                            </div>
                        </div>

                        <div class="settings-form">
                            <div class="settings-field"><label class="settings-label">ชื่อ</label><input type="text" class="settings-input" id="profileFirstName" autocomplete="given-name"></div>
                            <div class="settings-field"><label class="settings-label">นามสกุล</label><input type="text" class="settings-input" id="profileLastName" autocomplete="family-name"></div>
                            <div class="settings-field"><label class="settings-label">เลขบัตรประจำตัวประชาชน</label><input type="text" class="settings-input profile-readonly" id="profileNationalId" <?= $profileContactReadonly ?>></div>
                            <div class="settings-field"><label class="settings-label">อีเมล</label><input type="email" class="settings-input profile-readonly" id="profileEmail" <?= $profileContactReadonly ?>></div>
                            <div class="settings-field"><label class="settings-label">เบอร์โทรศัพท์</label><input type="tel" class="settings-input profile-readonly" id="profilePhone" <?= $profileContactReadonly ?>></div>
                            <div class="settings-field"><label class="settings-label">บ้านเลขที่ / ถนน</label><input type="text" class="settings-input profile-readonly" id="profileAddressLine" <?= $profileContactReadonly ?>></div>
                            <div class="settings-field"><label class="settings-label">ตำบล / แขวง</label><input type="text" class="settings-input profile-readonly" id="profileSubdistrict" <?= $profileContactReadonly ?>></div>
                            <div class="settings-field"><label class="settings-label">อำเภอ / เขต</label><input type="text" class="settings-input profile-readonly" id="profileDistrict" <?= $profileContactReadonly ?>></div>
                            <div class="settings-field"><label class="settings-label">จังหวัด</label><input type="text" class="settings-input profile-readonly" id="profileProvince" <?= $profileContactReadonly ?>></div>
                            <div class="settings-field"><label class="settings-label">รหัสไปรษณีย์</label><input type="text" class="settings-input profile-readonly" id="profilePostalCode" <?= $profileContactReadonly ?>></div>
                            <div class="settings-field"><label class="settings-label">ชั้นเรียน</label><input type="text" class="settings-input profile-readonly" id="profileClass" readonly></div>
                        </div>

                        <div class="pwd-section">
                            <div class="pwd-section-title">&#128272; เปลี่ยนรหัสผ่าน</div>
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
                                        <button type="button" onclick="togglePwd('pwdConfirm',this)" class="pwd-toggle-btn" aria-label="แสดงรหัสผ่าน" title="แสดงรหัสผ่าน"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button>
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
    <script src="assets/js/student_dashboard.js?v=20261021-profile-avatar-control"></script>
    <script src="assets/js/responsive.js?v=20261021-responsive" defer></script>
</body>
</html>
