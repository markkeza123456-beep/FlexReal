<?php
session_start();
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'student') {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แดชบอร์ดนักเรียน – Flexible Learning Hub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="student_dashboard.css">
</head>
<body>
<div class="dashboard-container">

    <!-- ════════════ SIDEBAR ════════════ -->
    <div class="sidebar">
        <div class="logo-section">
            <h2>FLEXIBLE</h2>
            <span>LEARNING HUB</span>
        </div>

        <div class="menu">
            <div class="menu-item active" id="btn-dashboard" onclick="showPage('dashboard')">
                <span>⊞</span> แดชบอร์ด
            </div>
            <div class="menu-item" id="btn-lessons" onclick="showPage('lessons')">
                <span>▣</span> บทเรียน
            </div>
            <div class="menu-item" id="btn-assignments" onclick="showPage('assignments')">
                <span>✎</span> งานที่มอบหมาย
            </div>
            <div class="menu-item" id="btn-settings" onclick="showPage('settings')">
                <span>⊟</span> รายงานผล
            </div>
        </div>

        <div>
            <a class="btn-logout" href="logout.php">
                <span>⇥</span> ออกจากระบบ
            </a>
            <div class="user-profile" onclick="showPage('settings')">
                <div class="avatar" id="sidebarAvatar">S</div>
                <div>
                    <div style="font-size:0.85rem;font-weight:600" id="sidebarName">กำลังโหลด...</div>
                    <div style="font-size:0.7rem;color:#888" id="sidebarRole">นักเรียน</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ════════════ MAIN CONTENT ════════════ -->
    <div class="main-content">

        <!-- ── PAGE: DASHBOARD ── -->
        <div id="dashboard-page">
            <div class="header">
                <div>
                    <h1>แดชบอร์ด</h1>
                    <p id="dashboardWelcome" style="color:#888;font-size:0.85rem;margin-top:4px">กำลังโหลดข้อมูลผู้ใช้...</p>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div style="font-size:0.8rem;color:#888">วิชาที่กำลังเรียน</div>
                    <div class="value" id="statCourseCount">0</div>
                    <div style="font-size:0.75rem;color:#888">จำนวนบทเรียนที่ลงทะเบียน</div>
                </div>
                <div class="stat-card blue">
                    <div style="font-size:0.8rem;color:#888">ความคืบหน้าเฉลี่ย</div>
                    <div class="value" id="statAvgProgress">0%</div>
                    <div style="font-size:0.75rem;color:#888">อัปเดตจากการเข้าเรียนล่าสุด</div>
                </div>
                <div class="stat-card green">
                    <div style="font-size:0.8rem;color:#888">คะแนนเฉลี่ย</div>
                    <div class="value" id="statAvgScore">0%</div>
                    <div style="font-size:0.75rem;color:#888">คิดจากแบบทดสอบที่ทำแล้ว</div>
                </div>
                <div class="stat-card purple">
                    <div style="font-size:0.8rem;color:#888">สถานะการเรียน</div>
                    <div class="value" style="font-size:1.4rem;padding-top:6px" id="statLearningState">เริ่มต้น</div>
                    <div style="font-size:0.75rem;color:#888">พร้อมติดตามทุกบทเรียน</div>
                </div>
            </div>

            <div class="content-card">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.2rem">
                    <h3 style="font-size:1rem">ความคืบหน้าการเรียน</h3>
                    <input id="courseSearch" placeholder="ค้นหาวิชาเรียน..."
                           style="background:#121212;border:1px solid #2d2d2d;border-radius:8px;padding:6px 14px;color:#fff;font-family:'Kanit',sans-serif;font-size:0.8rem;outline:none;width:200px">
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ลำดับ</th>
                            <th>วิชาเรียน</th>
                            <th>ความคืบหน้า</th>
                            <th>คะแนนสอบ</th>
                            <th>สถานะ</th>
                        </tr>
                    </thead>
                    <tbody id="courseTableBody">
                        <tr><td colspan="5" style="text-align:center;color:#888;padding:30px">กำลังโหลด...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ── PAGE: LESSONS ── -->
        <div id="lesson-page" style="display:none">
            <div class="header">
                <h1>บทเรียน</h1>
            </div>
            <div class="lessons-container" id="lessons-list">
                <div style="color:#888">กำลังโหลด...</div>
            </div>
        </div>

        <!-- ── PAGE: ASSIGNMENTS ── -->
        <div id="assignment-page" style="display:none">
            <div class="header">
                <h1>งานที่มอบหมาย</h1>
            </div>
            <div id="assignment-container"></div>
        </div>

        <!-- ── PAGE: SETTINGS ── -->
        <div id="settings-page" style="display:none">
            <div class="header">
                <h1>โปรไฟล์</h1>
            </div>

            <div class="settings-wrapper">
                <div class="content-card settings-card">

                    <!-- Avatar -->
                    <div class="avatar-section">
                        <div class="avatar-picker" onclick="document.getElementById('avatarFileInput').click()">
                            <div class="avatar-large" id="avatarInitial">S</div>
                            <img id="avatarImg" src="" alt="avatar"
                                 style="display:none;width:72px;height:72px;border-radius:50%;object-fit:cover;position:absolute;top:0;left:0">
                            <div class="avatar-edit-badge">✏️</div>
                        </div>
                        <div class="avatar-info">
                            <div class="avatar-name" id="displayName">กำลังโหลด...</div>
                            <div class="avatar-role" id="profileRole">นักเรียน</div>
                            <div class="avatar-hint" onclick="document.getElementById('avatarFileInput').click()">
                                คลิกที่รูปเพื่อเปลี่ยน
                            </div>
                        </div>
                    </div>
                    <input type="file" id="avatarFileInput" accept="image/*"
                           style="display:none" onchange="previewAvatar(this)">

                    <!-- Feedback -->
                    <div id="profileFeedback" class="profile-feedback" style="display:none"></div>

                    <!-- Form -->
                    <div class="settings-form">
                        <div class="settings-field">
                            <label class="settings-label">ชื่อ-นามสกุล</label>
                            <input class="settings-input" id="profileName" type="text" placeholder="ชื่อ-นามสกุล">
                        </div>
                        <div class="settings-field">
                            <label class="settings-label">อีเมล</label>
                            <input class="settings-input" id="profileEmail" type="email" placeholder="อีเมล">
                        </div>
                        <div class="settings-field">
                            <label class="settings-label">ชั้นเรียน</label>
                            <input class="settings-input" id="profileClass" type="text" disabled>
                        </div>
                        <div class="settings-field">
                            <label class="settings-label">เบอร์โทรศัพท์</label>
                            <input class="settings-input" id="profilePhone" type="tel" placeholder="กรอกเบอร์โทรศัพท์">
                        </div>

                        <!-- Password section -->
                        <div class="pwd-section">
                            <div class="pwd-section-title">🔒 เปลี่ยนรหัสผ่าน</div>

                            <div class="settings-field" style="margin-bottom:10px">
                                <label class="settings-label">รหัสผ่านปัจจุบัน</label>
                                <div class="pwd-input-wrap">
                                    <input class="settings-input" id="pwdCurrent" type="password" placeholder="ใส่รหัสผ่านปัจจุบัน">
                                    <button class="pwd-toggle-btn" type="button" onclick="togglePwd('pwdCurrent',this)">👁</button>
                                </div>
                            </div>

                            <div class="settings-field" style="margin-bottom:10px">
                                <label class="settings-label">รหัสผ่านใหม่</label>
                                <div class="pwd-input-wrap">
                                    <input class="settings-input" id="pwdNew" type="password"
                                           placeholder="อย่างน้อย 6 ตัวอักษร"
                                           oninput="checkPwdStrength(this.value)">
                                    <button class="pwd-toggle-btn" type="button" onclick="togglePwd('pwdNew',this)">👁</button>
                                </div>
                                <div id="pwdStrengthWrap" style="display:none;margin-top:6px">
                                    <div class="strength-bar-bg"><div class="strength-bar-fill" id="pwdStrengthBar"></div></div>
                                    <div class="strength-label" id="pwdStrengthLabel"></div>
                                </div>
                            </div>

                            <div class="settings-field">
                                <label class="settings-label">ยืนยันรหัสผ่านใหม่</label>
                                <div class="pwd-input-wrap">
                                    <input class="settings-input" id="pwdConfirm" type="password"
                                           placeholder="พิมพ์รหัสผ่านใหม่อีกครั้ง"
                                           oninput="checkPwdMatch()">
                                    <button class="pwd-toggle-btn" type="button" onclick="togglePwd('pwdConfirm',this)">👁</button>
                                </div>
                                <div class="pwd-match-msg" id="pwdMatchMsg"></div>
                            </div>
                        </div>

                        <button class="btn-save-profile" id="saveProfileBtn" onclick="saveProfile()">
                            💾 บันทึกข้อมูล
                        </button>
                    </div>

                </div>
            </div>
        </div>

    </div><!-- /main-content -->
</div><!-- /dashboard-container -->

<script src="student_dashboard.js"></script>
</body>
</html>