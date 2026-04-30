<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Flexible Learning Hub</title>
    <link rel="stylesheet" href="style.css">
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
                <div class="menu-item">
                    <span class="icon">📝</span> งานที่มอบหมาย
                </div>
                <div class="menu-item">
                    <span class="icon">📊</span> รายงานผล
                </div>
            </nav>
            <div class="user-profile">
                <div class="avatar">S</div>
                <div class="user-info">
                    <p class="name">สมชาย ตั้งใจเรียน</p>
                    <p class="role">นักเรียน - ม.5/1</p>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="main-content">
            
            <!-- SECTION: Dashboard Page -->
            <section id="dashboard-page">
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
                            <tbody id="courseTableBody">
                                <!-- JS rendering data here -->
                            </tbody>
                        </table>
                    </div>
                </section>
            </section>

            <!-- SECTION: Lessons Page (Hidden by default) -->
            <section id="lesson-page" style="display: none;">
                <header class="header">
                    <div class="welcome">
                        <h1>บทเรียนของฉัน</h1>
                        <p>ติดตามความคืบหน้าของคอร์สที่คุณลงทะเบียนเรียน</p>
                    </div>
                </header>
                
                <div class="lessons-container" id="lessons-list">
                    <!-- JS rendering lessons cards here -->
                </div>
            </section>

        </main>
    </div>
    <script src="script.js"></script>
</body>
</html>