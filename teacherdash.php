<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php');
    exit;
}

// ดึงชื่ออาจารย์จาก session แทน mock data
$teacher = [
    'name'    => $_SESSION['name'],
    'subject' => 'คณิตศาสตร์',
    'avatar'  => mb_substr($_SESSION['name'], 0, 1),
    'id'      => $_SESSION['user_id'],
];


$stats = [
    'students'    => 148,
    'lessons'     => 32,
    'assignments' => 12,
    'avg_score'   => 78.5,
];

$lessons = [
    ['id'=>1,'title'=>'พีชคณิตเบื้องต้น',  'subject'=>'คณิตศาสตร์ ม.4','students'=>45,'progress'=>80,'status'=>'active'],
    ['id'=>2,'title'=>'สมการเชิงเส้น',      'subject'=>'คณิตศาสตร์ ม.4','students'=>38,'progress'=>60,'status'=>'active'],
    ['id'=>3,'title'=>'ตรีโกณมิติ',         'subject'=>'คณิตศาสตร์ ม.5','students'=>42,'progress'=>45,'status'=>'draft'],
    ['id'=>4,'title'=>'แคลคูลัสเบื้องต้น', 'subject'=>'คณิตศาสตร์ ม.6','students'=>23,'progress'=>20,'status'=>'draft'],
];

$students = [
    ['name'=>'นายกิตติ วงค์ดี',       'class'=>'ม.4/1','score'=>92,'status'=>'excellent'],
    ['name'=>'นางสาวมินา ทองใส',       'class'=>'ม.4/2','score'=>85,'status'=>'good'],
    ['name'=>'นายภูมิ สุขสันต์',       'class'=>'ม.5/1','score'=>71,'status'=>'average'],
    ['name'=>'นางสาวใบบุญ แสงจันทร์', 'class'=>'ม.5/2','score'=>58,'status'=>'needs-help'],
    ['name'=>'นายธนพล รุ่งเรือง',      'class'=>'ม.6/1','score'=>88,'status'=>'good'],
];

$activities = [
    ['icon'=>'📘','text'=>'เพิ่มบทเรียน "ตรีโกณมิติ" สำเร็จ',   'time'=>'10 นาทีที่แล้ว'],
    ['icon'=>'✅','text'=>'ตรวจงาน 15 ชิ้นเสร็จแล้ว',             'time'=>'1 ชั่วโมงที่แล้ว'],
    ['icon'=>'👥','text'=>'นักเรียนใหม่เข้าร่วม 3 คน',             'time'=>'3 ชั่วโมงที่แล้ว'],
    ['icon'=>'📊','text'=>'รายงานผลการเรียนถูกสร้างแล้ว',          'time'=>'เมื่อวาน'],
];

$score_labels = ['excellent'=>'ดีเยี่ยม','good'=>'ดี','average'=>'ปานกลาง','needs-help'=>'ต้องดูแล'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แดชบอร์ดอาจารย์ – Flexible Learning Hub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="teacherdash.css">
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon">
            <svg width="28" height="28" viewBox="0 0 32 32" fill="none">
                <polygon points="16,2 30,10 30,22 16,30 2,22 2,10" fill="none" stroke="#f97316" stroke-width="2.5"/>
                <polygon points="16,8 24,13 24,20 16,25 8,20 8,13" fill="#f97316" opacity="0.35"/>
            </svg>
        </div>
        <div>
            <div class="logo-name">FLEXIBLE</div>
            <div class="logo-sub">LEARNING HUB</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <a href="#" class="nav-item active" data-view="dashboard">
            <span class="nav-icon">⊞</span><span>แดชบอร์ด</span>
        </a>
        <a href="#" class="nav-item" data-view="lessons">
            <span class="nav-icon">📘</span><span>บทเรียน</span>
        </a>
        <a href="#" class="nav-item" data-section="students">
            <span class="nav-icon">👥</span><span>นักเรียน</span>
        </a>
        <a href="#" class="nav-item" data-section="assignments">
            <span class="nav-icon">📝</span><span>งานที่มอบหมาย</span>
        </a>
        <a href="#" class="nav-item" data-section="reports">
            <span class="nav-icon">📊</span><span>รายงาน</span>
        </a>
        <a href="#" class="nav-item" data-section="settings">
            <span class="nav-icon">⚙️</span><span>ตั้งค่า</span>
        </a>
    </nav>

    <a href="logout.php" class="sidebar-logout">
        <span>🚪</span><span>ออกจากระบบ</span>
    </a>

    <div class="sidebar-profile">
        <div class="profile-avatar"><?= $teacher['avatar'] ?></div>
        <div class="profile-info">
            <div class="profile-name"><?= htmlspecialchars($teacher['name']) ?></div>
            <div class="profile-role">อาจารย์ · <?= htmlspecialchars($teacher['subject']) ?></div>
        </div>
    </div>
</aside>

<!-- Main Content -->
<main class="main">

    <!-- ══ VIEW: DASHBOARD ══ -->
    <div id="view-dashboard" class="page-view">

    <!-- Topbar -->
    <header class="topbar">
        <div class="topbar-left">
            <h1 class="page-title">แดชบอร์ด</h1>
            <span class="page-sub">ยินดีต้อนรับกลับมา, <?= explode(' ', $teacher['name'])[1] ?> 👋</span>
        </div>
        <div class="topbar-right">
            <div class="notif-btn" id="notifBtn">
                🔔<span class="notif-dot"></span>
            </div>
        </div>
    </header>

    <!-- Stats -->
    <section class="stats-grid">
        <div class="stat-card" style="--accent:#f97316">
            <div class="stat-icon">👥</div>
            <div class="stat-value counter" data-target="<?= $stats['students'] ?>">0</div>
            <div class="stat-label">นักเรียนทั้งหมด</div>
            <div class="stat-trend up">↑ +3 สัปดาห์นี้</div>
        </div>
        <div class="stat-card" style="--accent:#3b82f6">
            <div class="stat-icon">📘</div>
            <div class="stat-value counter" data-target="<?= $stats['lessons'] ?>">0</div>
            <div class="stat-label">บทเรียนทั้งหมด</div>
            <div class="stat-trend up">↑ +2 เดือนนี้</div>
        </div>
        <div class="stat-card" style="--accent:#10b981">
            <div class="stat-icon">📝</div>
            <div class="stat-value counter" data-target="<?= $stats['assignments'] ?>">0</div>
            <div class="stat-label">งานที่มอบหมาย</div>
            <div class="stat-trend">รอตรวจ 4 ชิ้น</div>
        </div>
        <div class="stat-card" style="--accent:#a855f7">
            <div class="stat-icon">🎯</div>
            <div class="stat-value"><?= $stats['avg_score'] ?>%</div>
            <div class="stat-label">คะแนนเฉลี่ย</div>
            <div class="stat-trend up">↑ +2.3% จากเดิม</div>
        </div>
    </section>

    <!-- Students (dashboard only) -->
    <section class="card students-card">
        <div class="card-header">
            <h2 class="card-title">นักเรียนในความดูแล</h2>
            <input class="search-input" type="text" id="studentSearch" placeholder="🔍 ค้นหานักเรียน...">
        </div>
        <div class="table-wrap">
            <table class="lessons-table" id="studentTable">
                <thead>
                    <tr>
                        <th> </th>
                        <th>ชื่อ-นามสกุล</th>
                        <th>ชั้น</th>
                        <th>คะแนน</th>
                        <th>ระดับ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $i => $s): ?>
                    <tr class="lesson-row">
                        <td class="mono"><?= str_pad($i+1, 2, '0', STR_PAD_LEFT) ?></td>
                        <td><?= htmlspecialchars($s['name']) ?></td>
                        <td><?= htmlspecialchars($s['class']) ?></td>
                        <td class="mono score-cell"><?= $s['score'] ?></td>
                        <td><span class="badge badge-<?= $s['status'] ?>"><?= $score_labels[$s['status']] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>


    <!-- Lessons summary (dashboard only – no filter, no add button) -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">บทเรียนของฉัน</h2>
            <input class="search-input" type="text" id="dashLessonSearch" placeholder="🔍 ค้นหาบทเรียน...">
        </div>
        <div class="table-wrap">
            <table class="lessons-table" id="dashLessonsTable">
                <thead>
                    <tr>
                        <th>ชื่อบทเรียน</th>
                        <th>วิชา / ระดับชั้น</th>
                        <th>นักเรียน</th>
                        <th>ความคืบหน้า</th>
                        <th>สถานะ</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lessons as $lesson): ?>
                    <tr class="lesson-row dash-lesson-row" data-id="<?= $lesson['id'] ?>">
                        <td class="lesson-title-cell"><?= htmlspecialchars($lesson['title']) ?></td>
                        <td class="lesson-subject"><?= htmlspecialchars($lesson['subject']) ?></td>
                        <td><?= $lesson['students'] ?> คน</td>
                        <td>
                            <div class="progress-wrap">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="--pct:<?= $lesson['progress'] ?>%"></div>
                                </div>
                                <span class="progress-num"><?= $lesson['progress'] ?>%</span>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-<?= $lesson['status'] ?>">
                                <?= $lesson['status'] === 'active' ? 'เผยแพร่' : 'ฉบับร่าง' ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-btns">
                                <button class="btn-icon btn-dash-view-lesson" title="ดูรายละเอียด" data-id="<?= $lesson['id'] ?>">👁</button>
                                <button class="btn-icon btn-dash-edit-lesson" title="แก้ไข" data-id="<?= $lesson['id'] ?>">✏️</button>
                                <button class="btn-icon btn-del" title="ลบ">🗑</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div id="dashLessonNoResult" style="display:none;text-align:center;padding:28px;color:var(--text-muted);font-size:13px">
                ไม่พบบทเรียนที่ตรงกับการค้นหา
            </div>
        </div>
    </div>

    </div><!-- /view-dashboard -->

    <!-- ══ VIEW: LESSONS ══ -->
    <div id="view-lessons" class="page-view" style="display:none">

    <!-- Topbar -->
    <header class="topbar">
        <div class="topbar-left">
            <h1 class="page-title" id="lessonsPageTitle">บทเรียน</h1>
            <span class="page-sub" id="lessonsPageSub">จัดการบทเรียนทั้งหมดของคุณ</span>
        </div>
        <div class="topbar-right">
            <div class="notif-btn">🔔</div>
        </div>
    </header>

    <!-- Lessons List -->
    <div id="lessonsSection">
        <div class="card lessons-card">
            <div class="card-header" style="flex-wrap:wrap;gap:10px">
                <h2 class="card-title">บทเรียนของฉัน</h2>
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <input class="search-input" type="text" id="lessonSearch" placeholder="🔍 ค้นหาบทเรียน...">
                    <select class="search-input" id="lessonFilterStatus" style="width:140px">
                        <option value="">สถานะทั้งหมด</option>
                        <option value="active">เผยแพร่แล้ว</option>
                        <option value="draft">ฉบับร่าง</option>
                    </select>
                    <button class="btn-add-lesson" id="openModalBtn">
                        <span class="plus">+</span> เพิ่มบทเรียน
                    </button>
                </div>
            </div>

            <div class="table-wrap">
                <table class="lessons-table" id="lessonsTable">
                    <thead>
                        <tr>
                            <th>ชื่อบทเรียน</th>
                            <th>วิชา / ระดับชั้น</th>
                            <th>นักเรียน</th>
                            <th>ความคืบหน้า</th>
                            <th>สถานะ</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="lessonsTableBody">
                        <?php foreach ($lessons as $lesson): ?>
                        <tr class="lesson-row" data-id="<?= $lesson['id'] ?>" data-status="<?= $lesson['status'] ?>">
                            <td>
                                <div class="lesson-title-cell"><?= htmlspecialchars($lesson['title']) ?></div>
                            </td>
                            <td class="lesson-subject"><?= htmlspecialchars($lesson['subject']) ?></td>
                            <td><?= $lesson['students'] ?> คน</td>
                            <td>
                                <div class="progress-wrap">
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="--pct:<?= $lesson['progress'] ?>%"></div>
                                    </div>
                                    <span class="progress-num"><?= $lesson['progress'] ?>%</span>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-<?= $lesson['status'] ?>">
                                    <?= $lesson['status'] === 'active' ? 'เผยแพร่' : 'ฉบับร่าง' ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn-icon btn-view-lesson" title="ดูรายละเอียด" data-id="<?= $lesson['id'] ?>">👁</button>
                                    <button class="btn-icon btn-edit-lesson" title="แก้ไข" data-id="<?= $lesson['id'] ?>">✏️</button>
                                    <button class="btn-icon btn-del" title="ลบ">🗑</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div id="lessonNoResult" style="display:none;text-align:center;padding:32px;color:var(--text-muted);font-size:13px">
                    ไม่พบบทเรียนที่ตรงกับการค้นหา
                </div>
            </div>
        </div>
    </div>

    <!-- Lesson Detail View (hidden by default) -->
    <div id="lessonDetailSection" style="display:none;flex-direction:column;gap:20px">

        <!-- Back + Detail Header -->
        <div style="display:flex;align-items:center;gap:12px">
            <button class="btn-add-lesson" id="backToLessonsBtn" style="background:var(--bg2);border:1px solid var(--border);color:var(--text-dim)">
                ← กลับ
            </button>
            <span style="font-size:13px;color:var(--text-muted)">บทเรียนของฉัน</span>
        </div>

        <div class="card" id="lessonDetailHeader"></div>

        <!-- Tabs -->
        <div class="lesson-tabs" style="display:flex;gap:0;border:1px solid var(--border);border-radius:var(--radius-sm);overflow:hidden;background:var(--bg2)">
            <button class="lesson-tab-btn active" data-tab="overview" style="flex:1;padding:11px;background:none;border:none;border-right:1px solid var(--border);color:var(--text-dim);font-family:'Kanit',sans-serif;font-size:13px;font-weight:500;cursor:pointer;transition:all .15s">
                📋 ภาพรวม
            </button>
            <button class="lesson-tab-btn" data-tab="students" style="flex:1;padding:11px;background:none;border:none;border-right:1px solid var(--border);color:var(--text-dim);font-family:'Kanit',sans-serif;font-size:13px;font-weight:500;cursor:pointer;transition:all .15s">
                👥 นักเรียน
            </button>
            <button class="lesson-tab-btn" data-tab="quiz" style="flex:1;padding:11px;background:none;border:none;color:var(--text-dim);font-family:'Kanit',sans-serif;font-size:13px;font-weight:500;cursor:pointer;transition:all .15s">
                🧪 แบบทดสอบ
            </button>
        </div>

        <!-- Tab: Overview -->
        <div class="lesson-tab-content card" id="lessonTab-overview">
            <div id="lessonOverviewBody"></div>
        </div>

        <!-- Tab: Students -->
        <div class="lesson-tab-content card" id="lessonTab-students" style="display:none">
            <div class="card-header">
                <h3 class="card-title" style="font-size:14px">นักเรียนในบทเรียนนี้</h3>
                <input class="search-input" type="text" id="detailStudentSearch" placeholder="🔍 ค้นหานักเรียน...">
            </div>
            <div class="table-wrap">
                <table class="lessons-table" id="detailStudentTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th>ชั้น</th>
                            <th>คะแนน</th>
                            <th>ระดับ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $i => $s): ?>
                        <tr class="lesson-row detail-student-row">
                            <td class="mono"><?= str_pad($i+1, 2, '0', STR_PAD_LEFT) ?></td>
                            <td><?= htmlspecialchars($s['name']) ?></td>
                            <td><?= htmlspecialchars($s['class']) ?></td>
                            <td class="mono score-cell"><?= $s['score'] ?></td>
                            <td><span class="badge badge-<?= $s['status'] ?>"><?= $score_labels[$s['status']] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab: Quiz -->
        <div class="lesson-tab-content card" id="lessonTab-quiz" style="display:none">
            <div class="card-header">
                <h3 class="card-title" style="font-size:14px">แบบทดสอบ</h3>
                <button class="btn-add-lesson" id="openQuizModalBtn" style="font-size:12px;padding:8px 14px">
                    <span class="plus">+</span> เพิ่มคำถาม
                </button>
            </div>
            <div id="quizList" style="display:flex;flex-direction:column;gap:10px">
                <div id="quizEmpty" style="text-align:center;padding:36px;color:var(--text-muted);font-size:13px">
                    ยังไม่มีแบบทดสอบ — กด "เพิ่มคำถาม" เพื่อเริ่มต้น
                </div>
            </div>
        </div>

    </div>

    <!-- Edit Lesson Modal -->
    <div class="modal-overlay" id="editLessonOverlay">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title" id="editLessonTitle">✏️ แก้ไขบทเรียน</h3>
                <button class="modal-close" id="closeEditLessonBtn">✕</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editLessonId">
                <div class="form-group">
                    <label>ชื่อบทเรียน</label>
                    <input type="text" class="form-input" id="editLessonName" placeholder="เช่น สมการกำลังสอง">
                </div>
                <div class="form-group">
                    <label>วิชา / ระดับชั้น</label>
                    <input type="text" class="form-input" id="editLessonSubject" placeholder="เช่น คณิตศาสตร์ ม.4">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>ความคืบหน้า (%)</label>
                        <input type="number" class="form-input" id="editLessonProgress" min="0" max="100" placeholder="0–100">
                    </div>
                    <div class="form-group">
                        <label>สถานะ</label>
                        <select class="form-input" id="editLessonStatus">
                            <option value="draft">ฉบับร่าง</option>
                            <option value="active">เผยแพร่</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" id="closeEditLessonBtn2">ยกเลิก</button>
                <button class="btn-save" id="saveEditLessonBtn">💾 บันทึกการแก้ไข</button>
            </div>
        </div>
    </div>

    <!-- Add Quiz Modal -->
    <div class="modal-overlay" id="quizModalOverlay">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">🧪 เพิ่มคำถามแบบทดสอบ</h3>
                <button class="modal-close" id="closeQuizModalBtn">✕</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>คำถาม</label>
                    <textarea class="form-input" id="quizQuestion" rows="3" placeholder="พิมพ์คำถาม..."></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>ประเภทคำถาม</label>
                        <select class="form-input" id="quizType">
                            <option value="choice">ตัวเลือก (MCQ)</option>
                            <option value="truefalse">ถูก / ผิด</option>
                            <option value="short">เติมคำสั้น</option>
                            <option value="essay">อัตนัย</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>คะแนน</label>
                        <input type="number" class="form-input" id="quizScore" value="1" min="1" max="100">
                    </div>
                </div>
                <div class="form-group" id="quizChoicesGroup">
                    <label>ตัวเลือก (คั่นด้วย Enter)</label>
                    <textarea class="form-input" id="quizChoices" rows="4" placeholder="ตัวเลือก ก&#10;ตัวเลือก ข&#10;ตัวเลือก ค&#10;ตัวเลือก ง"></textarea>
                </div>
                <div class="form-group">
                    <label>เฉลย (ถ้ามี)</label>
                    <input type="text" class="form-input" id="quizAnswer" placeholder="คำตอบที่ถูกต้อง">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" id="closeQuizModalBtn2">ยกเลิก</button>
                <button class="btn-save" id="saveQuizBtn">➕ เพิ่มคำถาม</button>
            </div>
        </div>
    </div>

    </div><!-- /view-lessons -->

</main><!-- /main -->

<!-- Add Lesson Modal -->
<div class="modal-overlay" id="modalOverlay">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">➕ เพิ่มบทเรียนใหม่</h3>
            <button class="modal-close" id="closeModalBtn">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>ชื่อบทเรียน</label>
                <input type="text" class="form-input" placeholder="เช่น สมการกำลังสอง">
            </div>
            <div class="form-group">
                <label>วิชา / ระดับชั้น</label>
                <input type="text" class="form-input" placeholder="เช่น คณิตศาสตร์ ม.4">
            </div>
            <div class="form-group">
                <label>คำอธิบาย</label>
                <textarea class="form-input" rows="3" placeholder="รายละเอียดบทเรียน..."></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>วันที่เริ่มต้น</label>
                    <input type="date" class="form-input">
                </div>
                <div class="form-group">
                    <label>สถานะ</label>
                    <select class="form-input">
                        <option value="draft">ฉบับร่าง</option>
                        <option value="active">เผยแพร่</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" id="closeModalBtn2">ยกเลิก</button>
            <button class="btn-save">💾 บันทึกบทเรียน</button>
        </div>
    </div>
</div>

<script src="teacherdash.js"></script>
</body>
</html>