<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flexible Learning Hub - Student Portal</title>
    <link rel="stylesheet" href="student_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo-section">
                <h2>FLEXIBLE</h2>
                <span>LEARNING HUB</span>
            </div>
            <nav class="menu">
                <div class="menu-item active" id="btn-dashboard">
                    <span class="icon">⊞</span> แดชบอร์ด
                </div>
                <div class="menu-item" id="btn-lessons">
                    <span class="icon">📘</span> บทเรียน
                </div>
                <div class="menu-item" id="btn-assignments">
                    <span class="icon">📝</span> งานที่มอบหมาย
                </div>
                <div class="menu-item">
                    <span class="icon">📊</span> รายงานผล
                </div>
            </nav>
            <div class="user-profile" id="btn-settings" style="cursor:pointer" title="แก้ไขโปรไฟล์">
                <div class="avatar" id="sidebarAvatar">S</div>
                <div class="user-info">
                    <p class="name" id="sidebarName">สมชาย ตั้งใจเรียน</p>
                    <p class="role">นักเรียน - ม.5/1</p>
                </div>
                <span style="margin-left:auto;font-size:0.75rem;color:#888;">⚙️</span>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="main-content">
            
            <!-- SECTION: Dashboard -->
            <section id="dashboard-page" class="content-section">
                <header class="header">
                    <div class="welcome">
                        <h1>แดชบอร์ด</h1>
                        <p>ยินดีต้อนรับกลับมา, สมชาย 👋</p>
                    </div>
                    <div class="notif-icon">🔔</div>
                </header>

                <section class="stats-grid">
                    <div class="stat-card orange">
                        <p class="label">วิชาที่กำลังเรียน</p>
                        <p class="value">5</p>
                        <span class="sub-value">↑ +1 สัปดาห์นี้</span>
                    </div>
                    <div class="stat-card blue">
                        <p class="label">งานที่ค้างส่ง</p>
                        <p class="value">3</p>
                        <span class="sub-value">รอส่ง 2 งาน</span>
                    </div>
                    <div class="stat-card green">
                        <p class="label">ชั่วโมงสะสม</p>
                        <p class="value">42.5</p>
                        <span class="sub-value">↑ +5.2 ชม.</span>
                    </div>
                    <div class="stat-card purple">
                        <p class="label">คะแนนเฉลี่ย</p>
                        <p class="value">88.2%</p>
                        <span class="sub-value">↑ +2.3%</span>
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
                                    <th>ความคืบหน้า</th>
                                    <th>คะแนนสะสม</th>
                                    <th>สถานะ</th>
                                </tr>
                            </thead>
                            <tbody id="courseTableBody"></tbody>
                        </table>
                    </div>
                </section>
            </section>

            <!-- SECTION: Lessons -->
            <section id="lesson-page" class="content-section" style="display: none;">
                <header class="header">
                    <div class="welcome">
                        <h1>บทเรียนของฉัน</h1>
                        <p>คอร์สที่คุณลงทะเบียนเรียนทั้งหมด</p>
                    </div>
                </header>
                <div class="lessons-container" id="lessons-list"></div>
            </section>

            <!-- SECTION: Assignments -->
            <section id="assignment-page" class="content-section" style="display: none;">
                <header class="header">
                    <div class="welcome">
                        <h1>งานที่มอบหมาย</h1>
                        <p>รายการงานและแบบฝึกหัดที่ต้องส่ง</p>
                    </div>
                </header>
                <div class="assignment-list" id="assignment-container"></div>
            </section>

            <!-- SECTION: Settings / Profile -->
            <section id="settings-page" class="content-section" style="display: none;">
                <header class="header">
                    <div class="welcome">
                        <h1>ตั้งค่าโปรไฟล์</h1>
                        <p>จัดการข้อมูลส่วนตัวและรหัสผ่านของคุณ</p>
                    </div>
                    <div class="notif-icon">🔔</div>
                </header>

                <div class="settings-wrapper">

                    <!-- Profile Card -->
                    <div class="content-card settings-card">
                        <h2 class="settings-card-title">&#128100; โปรไฟล์</h2>

                        <div class="avatar-section">
                            <div class="avatar-picker" onclick="document.getElementById('avatarInput').click()" title="คลิกเพื่อเปลี่ยนรูปโปรไฟล์">
                                <div class="avatar-large" id="avatarDisplay">
                                    <img id="avatarImg" src="" alt="" style="display:none;width:100%;height:100%;object-fit:cover;border-radius:50%">
                                    <span id="avatarInitial">S</span>
                                </div>
                                <div class="avatar-edit-badge">&#9999;&#65039;</div>
                                <input type="file" id="avatarInput" accept="image/*" style="display:none" onchange="previewAvatar(this)">
                            </div>
                            <div class="avatar-info">
                                <div class="avatar-name" id="displayName">สมชาย ตั้งใจเรียน</div>
                                <div class="avatar-role">นักเรียน &#183; ม.5/1</div>
                                <div class="avatar-hint">คลิกที่รูปเพื่อเปลี่ยน</div>
                            </div>
                        </div>

                        <div class="settings-form">
                            <div class="settings-field">
                                <label class="settings-label">ชื่อ-นามสกุล</label>
                                <input type="text" class="settings-input" id="profileName" value="สมชาย ตั้งใจเรียน">
                            </div>
                            <div class="settings-field">
                                <label class="settings-label">อีเมล</label>
                                <input type="email" class="settings-input" id="profileEmail" value="somchai@student.school.ac.th">
                            </div>
                            <div class="settings-field">
                                <label class="settings-label">ชั้นเรียน</label>
                                <input type="text" class="settings-input" value="ม.5/1" readonly style="opacity:.5;cursor:not-allowed">
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
                                        <button type="button" onclick="togglePwd('pwdCurrent',this)" class="pwd-toggle-btn">&#128065;</button>
                                    </div>
                                </div>
                                <div class="settings-field">
                                    <label class="settings-label">รหัสผ่านใหม่</label>
                                    <div class="pwd-input-wrap">
                                        <input type="password" class="settings-input" id="pwdNew" placeholder="อย่างน้อย 6 ตัวอักษร" oninput="checkPwdStrength(this.value)">
                                        <button type="button" onclick="togglePwd('pwdNew',this)" class="pwd-toggle-btn">&#128065;</button>
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
                                        <button type="button" onclick="togglePwd('pwdConfirm',this)" class="pwd-toggle-btn">&#128065;</button>
                                    </div>
                                    <div id="pwdMatchMsg" class="pwd-match-msg"></div>
                                </div>
                            </div>
                        </div>

                        <div id="profileFeedback" class="profile-feedback" style="display:none;"></div>
                        <button class="btn-save-profile" id="saveProfileBtn" onclick="saveProfile()">&#128190; บันทึกข้อมูล</button>
                    </div>

                    <!-- Notification Card -->
                    <div class="content-card settings-card">
                        <h2 class="settings-card-title">&#128276; การแจ้งเตือน</h2>
                        <div class="notif-list">
                            <label class="notif-toggle">
                                <input type="checkbox" checked style="accent-color:var(--accent-orange);width:16px;height:16px">
                                <span>แจ้งเตือนงานใกล้ครบกำหนด</span>
                            </label>
                            <label class="notif-toggle">
                                <input type="checkbox" checked style="accent-color:var(--accent-orange);width:16px;height:16px">
                                <span>แจ้งเตือนบทเรียนใหม่</span>
                            </label>
                            <label class="notif-toggle">
                                <input type="checkbox" style="accent-color:var(--accent-orange);width:16px;height:16px">
                                <span>แจ้งเตือนผลคะแนน</span>
                            </label>
                        </div>
                    </div>

                </div>
            </section>

        </main>
    </div>
    <script src="student_dashboard.js"></script>
</body>
</html>