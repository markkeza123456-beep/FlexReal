<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ตรวจสอบสิทธิ์การเข้าถึง
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php');
    exit;
}

<<<<<<< HEAD
require_once __DIR__ . '/db_connect.php';
=======
// ดึงชื่ออาจารย์จาก session แทน mock data
// ดึง avatar_url จากฐานข้อมูล
$avatar_url = '';
try {
    require_once 'db_connect.php';
    $stmtAv = $conn->prepare("SELECT avatar_url FROM public.teachers WHERE teachers_id = :uid");
    $stmtAv->execute(['uid' => $_SESSION['user_id']]);
    $rowAv = $stmtAv->fetch(PDO::FETCH_ASSOC);
    $avatar_url = $rowAv['avatar_url'] ?? '';
} catch (Exception $e) { $avatar_url = ''; }

$teacher = [
    'name'       => $_SESSION['name'],
    'subject'    => 'คณิตศาสตร์',
    'avatar'     => mb_substr($_SESSION['name'], 0, 1),
    'avatar_url' => $avatar_url,
    'id'         => $_SESSION['user_id'],
];
>>>>>>> 52849ed3e843d8c643645e10466ce145d3336c4f

// ฟังก์ชันป้องกัน XSS
function h(?string $value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function scoreStatus(float $score): string {
    if ($score >= 85) return 'excellent';
    if ($score >= 70) return 'good';
    if ($score >= 50) return 'average';
    return 'needs-help';
}

function scoreLabel(string $status): string {
    $labels = ['excellent' => 'ดีเยี่ยม', 'good' => 'ดี', 'average' => 'ปานกลาง', 'needs-help' => 'ต้องดูแล'];
    return $labels[$status] ?? 'ยังไม่มีข้อมูล';
}

$teacherId = (string) $_SESSION['user_id'];

// 1. ดึงข้อมูลอาจารย์ (จากตาราง Teachers)
$teacherStmt = $conn->prepare('
    SELECT Teachers_ID, Teachers_Name 
    FROM public.teachers 
    WHERE Teachers_ID = :teacher_id LIMIT 1
');
$teacherStmt->execute([':teacher_id' => $teacherId]);
$teacherRow = $teacherStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$teacherName = trim((string) ($teacherRow['teachers_name'] ?? $_SESSION['name'] ?? $teacherId));
if ($teacherName === '') $teacherName = $teacherId;

// 2. ดึงข้อมูลวิชาที่อาจารย์คนนี้สอน (จากตาราง Subjects)
$subjectStmt = $conn->prepare('
    SELECT 
        s.Subjects_ID AS subjects_id, 
        s.Subjects_Name AS subjects_name, 
        s.Subjects_Description AS subjects_description,
        (SELECT COUNT(*) FROM public.lessons l WHERE l.Subjects_ID = s.Subjects_ID) AS lesson_count,
        (SELECT COUNT(DISTINCT ss.Student_ID) FROM public.student_subject ss WHERE ss.Subjects_ID = s.Subjects_ID) AS student_count
    FROM public.subjects s
    WHERE s.Teachers_ID = :teacher_id
    ORDER BY s.Subjects_Name ASC
');
$subjectStmt->execute([':teacher_id' => $teacherId]);
$subjectRows = $subjectStmt->fetchAll(PDO::FETCH_ASSOC);

$subjects = [];
$subjectIds = [];
$subjectNames = [];
$totalLessonCount = 0;

foreach ($subjectRows as $row) {
    $subjectId = (string) $row['subjects_id'];
    $subjectName = trim((string) $row['subjects_name']);
    $lessonCount = (int) $row['lesson_count'];
    $studentCount = (int) $row['student_count'];

    $subjects[] = [
        'id' => $subjectId,
        'title' => $subjectName !== '' ? $subjectName : $subjectId,
        'subject' => trim((string) $row['subjects_description']) ?: 'ยังไม่มีคำอธิบายรายวิชา',
        'students' => $studentCount,
        'progress' => $lessonCount > 0 ? rand(40, 90) : 0, // จำลอง % ความคืบหน้าไปก่อน
        'status' => $lessonCount > 0 ? 'active' : 'draft',
        'lesson_count' => $lessonCount,
        'avg_score' => 0,
    ];

    if ($subjectId) $subjectIds[] = $subjectId;
    if ($subjectName) $subjectNames[] = $subjectName;
    $totalLessonCount += $lessonCount;
}

// 3. ดึงข้อมูลบทย่อย (จากตาราง Lessons)
$subLessonsBySubject = [];
if (!empty($subjectIds)) {
    $lessonStmt = $conn->prepare('
        SELECT l.Lessons_ID, l.Lessons_Name, l.Study_Hours, l.Subjects_ID 
        FROM public.lessons l
        INNER JOIN public.subjects s ON s.Subjects_ID = l.Subjects_ID
        WHERE s.Teachers_ID = :teacher_id
        ORDER BY l.Subjects_ID ASC, l.Lessons_Name ASC
    ');
    $lessonStmt->execute([':teacher_id' => $teacherId]);
    foreach ($lessonStmt->fetchAll(PDO::FETCH_ASSOC) as $lessonRow) {
        $subLessonsBySubject[$lessonRow['subjects_id']][] = [
            'id' => $lessonRow['lessons_id'],
            'title' => $lessonRow['lessons_name'],
            'duration' => $lessonRow['study_hours'] > 0 ? $lessonRow['study_hours'] . ' ชั่วโมง' : 'ไม่ระบุเวลา',
            'status' => 'active',
        ];
    }
}

// 4. ดึงข้อมูลนักเรียน (หัวใจสำคัญ: ดึงเฉพาะเด็กที่ลงเรียนวิชาของอาจารย์คนนี้!)
$studentStmt = $conn->prepare('
    SELECT 
        st.Student_ID,
        st.Student_Name,
        st.Student_Level AS class_name,
        (
            SELECT STRING_AGG(DISTINCT s3.Subjects_ID, \',\')
            FROM public.student_subject ss3
            INNER JOIN public.subjects s3 ON s3.Subjects_ID = ss3.Subjects_ID
            WHERE ss3.Student_ID = st.Student_ID
              AND s3.Teachers_ID = :teacher_id
        ) AS subject_ids
    FROM public.student st
    WHERE EXISTS (
        SELECT 1
        FROM public.student_subject ss
        INNER JOIN public.subjects s ON s.Subjects_ID = ss.Subjects_ID
        WHERE ss.Student_ID = st.Student_ID
          AND s.Teachers_ID = :teacher_id
    )
    ORDER BY st.Student_Name ASC
');
$studentStmt->execute([':teacher_id' => $teacherId]);

$students = [];
foreach ($studentStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    // กำหนดค่าสมมติให้คะแนนไปก่อน จนกว่าจะเชื่อมระบบทำข้อสอบ
    $mockScore = rand(50, 95); 
    $students[] = [
        'id' => $row['student_id'],
        'name' => $row['student_name'] ?: 'ไม่ระบุชื่อ',
        'class' => $row['class_name'] ?: '-',
        'score' => $mockScore,
        'status' => scoreStatus($mockScore),
        'subject_ids' => $row['subject_ids'],
    ];
}

// 5. ดึงประวัติการเข้าเรียนของนักเรียน (จากตาราง Learning_Records)
$recentActivities = [];
$activityStmt = $conn->prepare('
    SELECT 
        lr.Study_Time AS created_at,
        st.Student_Name AS student_name,
        s.Subjects_Name AS subjects_name,
        l.Lessons_Name AS activity_detail
    FROM public.learning_records lr
    INNER JOIN public.student st ON st.Student_ID = lr.Student_ID
    INNER JOIN public.lessons l ON l.Lessons_ID = lr.Lessons_ID
    INNER JOIN public.subjects s ON s.Subjects_ID = l.Subjects_ID
    WHERE s.Teachers_ID = :teacher_id
    ORDER BY lr.Study_Time DESC
    LIMIT 8
');
$activityStmt->execute([':teacher_id' => $teacherId]);
foreach ($activityStmt->fetchAll(PDO::FETCH_ASSOC) as $act) {
    $recentActivities[] = [
        'activity_type' => 'lesson_open',
        'student_name' => $act['student_name'],
        'subjects_name' => $act['subjects_name'],
        'activity_detail' => $act['activity_detail'],
        'created_at' => date('d/m/Y H:i', strtotime($act['created_at']))
    ];
}

// เตรียมข้อมูลแสดงผลบน UI
$teacherSubjectText = !empty($subjectNames) 
    ? implode(', ', array_slice($subjectNames, 0, 3)) . (count($subjectNames) > 3 ? ' +' . (count($subjectNames) - 3) : '')
    : 'ยังไม่มีบทเรียนที่ดูแล';

$firstNameParts = preg_split('/\s+/', $teacherName);
$teacherFirstName = $firstNameParts[0] ?? $teacherName;
$teacherAvatar = mb_substr($teacherName, 0, 1, 'UTF-8') ?: 'T';

$stats = [
    'students' => count($students),
    'lessons' => $totalLessonCount,
    'subjects' => count($subjects),
    'avg_score' => 0, 
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แดชบอร์ดอาจารย์ - Flexible Learning Hub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="teacherdash.css">
</head>
<body>
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
        <a href="#" class="nav-item active" data-view="dashboard"><span class="nav-icon">⌘</span><span>แดชบอร์ด</span></a>
        <a href="#" class="nav-item" data-view="lessons"><span class="nav-icon">📘</span><span>บทเรียน</span></a>
    </nav>

    <a href="logout.php" class="sidebar-logout">
        <span>↩</span><span>ออกจากระบบ</span>
    </a>

    <a href="#" class="sidebar-profile nav-item" data-view="settings" style="text-decoration:none">
<<<<<<< HEAD
        <div class="profile-avatar"><?= h($teacherAvatar) ?></div>
=======
        <div class="profile-avatar" id="sidebarAvatarWrap" style="overflow:hidden;padding:0">
            <?php if (!empty($teacher['avatar_url'])): ?>
            <img src="<?= htmlspecialchars($teacher['avatar_url']) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%" id="sidebarAvatarImg">
            <?php else: ?>
            <span id="sidebarAvatarInitial"><?= $teacher['avatar'] ?></span>
            <?php endif; ?>
        </div>
>>>>>>> 52849ed3e843d8c643645e10466ce145d3336c4f
        <div class="profile-info">
            <div class="profile-name"><?= h($teacherName) ?></div>
            <div class="profile-role">อาจารย์ • <?= h($teacherSubjectText) ?></div>
        </div>
    </a>
</aside>

<main class="main">
    <div id="view-dashboard" class="page-view">
        <header class="topbar">
            <div class="topbar-left">
                <h1 class="page-title">แดชบอร์ด</h1>
                <span class="page-sub">ยินดีต้อนรับกลับมา, <?= h($teacherFirstName) ?></span>
            </div>
            <div class="topbar-right">
                <div class="notif-btn" id="notifBtn">🔔</div>
            </div>
        </header>

        <section class="stats-grid">
            <div class="stat-card" style="--accent:#f97316">
                <div class="stat-icon">👥</div>
                <div class="stat-value counter" data-target="<?= $stats['students'] ?>">0</div>
                <div class="stat-label">นักเรียนที่ดูแล</div>
                <div class="stat-trend">เฉพาะที่ผูกกับวิชาหรือครูคนนี้</div>
            </div>
            <div class="stat-card" style="--accent:#3b82f6">
                <div class="stat-icon">📘</div>
                <div class="stat-value counter" data-target="<?= $stats['lessons'] ?>">0</div>
                <div class="stat-label">บทเรียนทั้งหมด</div>
                <div class="stat-trend">รวมบทเรียนในรายวิชาที่สอน</div>
            </div>
            <div class="stat-card" style="--accent:#10b981">
                <div class="stat-icon">🗂</div>
                <div class="stat-value counter" data-target="<?= $stats['subjects'] ?>">0</div>
                <div class="stat-label">บทเรียนที่ดูแล</div>
                <div class="stat-trend">แสดงเฉพาะของอาจารย์คนนี้</div>
            </div>
            <div class="stat-card" style="--accent:#a855f7">
                <div class="stat-icon">🎯</div>
                <div class="stat-value"><?= number_format($stats['avg_score'], 1) ?>%</div>
                <div class="stat-label">คะแนนเฉลี่ย</div>
                <div class="stat-trend">จากแบบทดสอบในวิชาที่สอน</div>
            </div>
        </section>

        <section class="card">
            <div class="card-header">
                <h2 class="card-title">การเข้าเรียนล่าสุด</h2>
            </div>
            <div style="display:flex;flex-direction:column;gap:12px;">
                <?php if ($recentActivities === []): ?>
                <div style="color:var(--text-muted);padding:8px 0;">ยังไม่มีการเข้าเรียนจากนักเรียนในบทเรียนที่คุณดูแล</div>
                <?php else: ?>
                <?php foreach ($recentActivities as $activity): ?>
                <?php
                    $activityType = (string) ($activity['activity_type'] ?? '');
                    $activityText = match ($activityType) {
                        'course_enter' => 'เข้าเรียน',
                        'lesson_open' => 'เปิดบทเรียน',
                        'video_open' => 'ดูวิดีโอ',
                        'quiz_submit' => 'ส่งแบบทดสอบ',
                        default => 'มีกิจกรรม'
                    };
                ?>
                <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;border-bottom:1px solid var(--border);padding-bottom:10px;">
                    <div>
                        <div style="color:var(--text);font-weight:600;"><?= h((string) ($activity['student_name'] ?? 'นักเรียน')) ?> • <?= h((string) ($activity['subjects_name'] ?? '-')) ?></div>
                        <div style="color:var(--text-dim);font-size:13px;"><?= h($activityText) ?><?= !empty($activity['activity_detail']) ? ' - ' . h((string) $activity['activity_detail']) : '' ?></div>
                    </div>
                    <div style="color:var(--text-muted);font-size:12px;white-space:nowrap;"><?= h((string) ($activity['created_at'] ?? '')) ?></div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="card students-card">
            <div class="card-header">
                <h2 class="card-title">นักเรียนในความดูแล</h2>
                <input class="search-input" type="text" id="studentSearch" placeholder="ค้นหานักเรียน...">
            </div>
            <div class="table-wrap">
                <table class="lessons-table" id="studentTable">
                    <thead>
                        <tr>
                            <th></th>
                            <th>ชื่อ-นามสกุล</th>
                            <th>ระดับชั้น</th>
                            <th>คะแนนเฉลี่ย</th>
                            <th>ระดับ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($students === []): ?>
                        <tr class="lesson-row">
                            <td colspan="5" style="text-align:center;color:var(--text-muted)">ยังไม่มีนักเรียนในความดูแล</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($students as $index => $student): ?>
                        <tr class="lesson-row">
                            <td class="mono"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></td>
                            <td><?= h($student['name']) ?></td>
                            <td><?= h($student['class']) ?></td>
                            <td class="mono score-cell"><?= number_format((float) $student['score'], 1) ?></td>
                            <td><span class="badge badge-<?= h($student['status']) ?>"><?= h(scoreLabel($student['status'])) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">บทเรียนของฉัน</h2>
                <input class="search-input" type="text" id="dashLessonSearch" placeholder="ค้นหาบทเรียน...">
            </div>
            <div class="table-wrap">
                <table class="lessons-table" id="dashLessonsTable">
                    <thead>
                        <tr>
                            <th>บทเรียน</th>
                            <th>รายละเอียด</th>
                            <th>นักเรียน</th>
                            <th>ความคืบหน้า</th>
                            <th>สถานะ</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subjects as $subject): ?>
                        <tr class="lesson-row dash-lesson-row" data-id="<?= h($subject['id']) ?>" data-subject-id="<?= h($subject['id']) ?>" data-student-count="<?= (int) $subject['students'] ?>">
                            <td class="lesson-title-cell"><?= h($subject['title']) ?></td>
                            <td class="lesson-subject"><?= h($subject['subject']) ?></td>
                            <td><?= (int) $subject['students'] ?> คน</td>
                            <td>
                                <div class="progress-wrap">
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="--pct:<?= (int) $subject['progress'] ?>%"></div>
                                    </div>
                                    <span class="progress-num"><?= (int) $subject['progress'] ?>%</span>
                                </div>
                            </td>
                            <td><span class="badge badge-<?= h($subject['status']) ?>"><?= $subject['status'] === 'active' ? 'เผยแพร่' : 'ฉบับร่าง' ?></span></td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn-icon btn-dash-view-lesson" title="ดูรายละเอียด" data-id="<?= h($subject['id']) ?>">👁</button>
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
    </div>

<<<<<<< HEAD
=======
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
<?php
 
// $teacher already defined above



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

$sub_lessons = [
    1 => [
        ['id'=>'1-1','title'=>'จำนวนจริงและพีชคณิต',     'duration'=>'45 นาที','status'=>'active'],
        ['id'=>'1-2','title'=>'นิพจน์และสมการ',           'duration'=>'60 นาที','status'=>'active'],
        ['id'=>'1-3','title'=>'อสมการและค่าสัมบูรณ์',     'duration'=>'50 นาที','status'=>'draft'],
    ],
    2 => [
        ['id'=>'2-1','title'=>'สมการเชิงเส้นตัวแปรเดียว', 'duration'=>'40 นาที','status'=>'active'],
        ['id'=>'2-2','title'=>'ระบบสมการเชิงเส้น',         'duration'=>'55 นาที','status'=>'active'],
    ],
    3 => [
        ['id'=>'3-1','title'=>'อัตราส่วนตรีโกณมิติ',       'duration'=>'50 นาที','status'=>'active'],
        ['id'=>'3-2','title'=>'กฎของไซน์และโคไซน์',       'duration'=>'60 นาที','status'=>'draft'],
        ['id'=>'3-3','title'=>'ฟังก์ชันตรีโกณมิติ',        'duration'=>'65 นาที','status'=>'draft'],
    ],
    4 => [
        ['id'=>'4-1','title'=>'ลิมิตและความต่อเนื่อง',    'duration'=>'60 นาที','status'=>'draft'],
        ['id'=>'4-2','title'=>'อนุพันธ์เบื้องต้น',         'duration'=>'70 นาที','status'=>'draft'],
    ],
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
    </nav>

    <a href="logout.php" class="sidebar-logout">
        <span>🚪</span><span>ออกจากระบบ</span>
    </a>

    <a href="#" class="sidebar-profile nav-item" data-view="settings" style="text-decoration:none">
        <div class="profile-avatar" id="sidebarAvatarWrap" style="overflow:hidden;padding:0">
            <?php if (!empty($teacher['avatar_url'])): ?>
            <img src="<?= htmlspecialchars($teacher['avatar_url']) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%" id="sidebarAvatarImg">
            <?php else: ?>
            <span id="sidebarAvatarInitial"><?= $teacher['avatar'] ?></span>
            <?php endif; ?>
        </div>
        <div class="profile-info">
            <div class="profile-name"><?= htmlspecialchars($teacher['name']) ?></div>
            <div class="profile-role">อาจารย์ · <?= htmlspecialchars($teacher['subject']) ?></div>
        </div>
    </a>
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
>>>>>>> 52849ed3e843d8c643645e10466ce145d3336c4f
    <div id="view-lessons" class="page-view" style="display:none">
        <header class="topbar">
            <div class="topbar-left">
                <h1 class="page-title" id="lessonsPageTitle">บทเรียน</h1>
                <span class="page-sub" id="lessonsPageSub">จัดการบทเรียนที่อยู่ในความดูแลของคุณ</span>
            </div>
            <div class="topbar-right">
                <div class="notif-btn">🔔</div>
            </div>
        </header>

        <div id="lessonsSection">
            <div class="card lessons-card">
                <div class="card-header" style="flex-wrap:wrap;gap:10px">
                    <h2 class="card-title">บทเรียนของฉัน</h2>
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                        <input class="search-input" type="text" id="lessonSearch" placeholder="ค้นหาบทเรียน...">
                        <select class="search-input" id="lessonFilterStatus" style="width:140px">
                            <option value="">สถานะทั้งหมด</option>
                            <option value="active">เผยแพร่แล้ว</option>
                            <option value="draft">ฉบับร่าง</option>
                        </select>
                    </div>
                </div>

                <div class="table-wrap">
                    <table class="lessons-table" id="lessonsTable">
                        <thead>
                            <tr>
                                <th>บทเรียน</th>
                                <th>รายละเอียด</th>
                                <th>นักเรียน</th>
                                <th>ความคืบหน้า</th>
                                <th>สถานะ</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="lessonsTableBody">
                            <?php foreach ($subjects as $subject): ?>
                            <?php $subLessons = $subLessonsBySubject[$subject['id']] ?? []; ?>
                            <tr class="lesson-row lesson-main-row" data-id="<?= h($subject['id']) ?>" data-subject-id="<?= h($subject['id']) ?>" data-student-count="<?= (int) $subject['students'] ?>" data-status="<?= h($subject['status']) ?>" data-expanded="false">
                                <td class="lesson-title-cell">
                                    <div style="display:flex;align-items:center;gap:8px">
                                        <?php if ($subLessons !== []): ?>
                                        <button class="btn-expand-sub" data-id="<?= h($subject['id']) ?>" title="ดูบทย่อย" style="background:none;border:none;color:var(--text-muted);font-size:11px;cursor:pointer;padding:2px 4px;transition:transform .2s;line-height:1">▸</button>
                                        <?php else: ?>
                                        <span style="display:inline-block;width:20px"></span>
                                        <?php endif; ?>
                                        <span><?= h($subject['title']) ?></span>
                                    </div>
                                </td>
                                <td class="lesson-subject"><?= h($subject['subject']) ?></td>
                                <td><?= (int) $subject['students'] ?> คน</td>
                                <td>
                                    <div class="progress-wrap">
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="--pct:<?= (int) $subject['progress'] ?>%"></div>
                                        </div>
                                        <span class="progress-num"><?= (int) $subject['progress'] ?>%</span>
                                    </div>
                                </td>
                                <td><span class="badge badge-<?= h($subject['status']) ?>"><?= $subject['status'] === 'active' ? 'เผยแพร่' : 'ฉบับร่าง' ?></span></td>
                                <td>
                                    <div class="action-btns">
                                        <button class="btn-icon btn-view-lesson" title="ดูรายละเอียด" data-id="<?= h($subject['id']) ?>">👁</button>
                                    </div>
                                </td>
                            </tr>
                            <?php foreach ($subLessons as $subLesson): ?>
                            <tr class="sub-lesson-row" data-parent="<?= h($subject['id']) ?>" style="display:none">
                                <td style="padding-left:48px">
                                    <div style="display:flex;align-items:center;gap:6px">
                                        <span style="color:var(--text-muted);font-size:11px">└</span>
                                        <span style="font-size:13px;color:var(--text-dim)"><?= h($subLesson['title']) ?></span>
                                    </div>
                                </td>
                                <td style="font-size:12px;color:var(--text-muted)">⏱ <?= h($subLesson['duration']) ?></td>
                                <td>-</td>
                                <td>-</td>
                                <td><span class="badge badge-<?= h($subLesson['status']) ?>"><?= $subLesson['status'] === 'active' ? 'เผยแพร่' : 'ฉบับร่าง' ?></span></td>
                                <td></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div id="lessonNoResult" style="display:none;text-align:center;padding:32px;color:var(--text-muted);font-size:13px">
                        ไม่พบบทเรียนที่ตรงกับการค้นหา
                    </div>
                </div>
            </div>
        </div>

        <div id="lessonDetailSection" style="display:none;flex-direction:column;gap:20px">
            <div style="display:flex;align-items:center;gap:12px">
                <button class="btn-add-lesson" id="backToLessonsBtn" style="background:var(--bg2);border:1px solid var(--border);color:var(--text-dim)">← กลับ</button>
                <span style="font-size:13px;color:var(--text-muted)">รายละเอียดบทเรียน</span>
            </div>

            <div class="card" id="lessonDetailHeader"></div>

            <div class="lesson-tabs" style="display:flex;gap:0;border:1px solid var(--border);border-radius:var(--radius-sm);overflow:hidden;background:var(--bg2)">
                <button class="lesson-tab-btn active" data-tab="overview" style="flex:1;padding:11px;background:none;border:none;border-right:1px solid var(--border);color:var(--text-dim);font-family:'Kanit',sans-serif;font-size:13px;font-weight:500;cursor:pointer;transition:all .15s">ภาพรวม</button>
                <button class="lesson-tab-btn" data-tab="students" style="flex:1;padding:11px;background:none;border:none;border-right:1px solid var(--border);color:var(--text-dim);font-family:'Kanit',sans-serif;font-size:13px;font-weight:500;cursor:pointer;transition:all .15s">นักเรียน</button>
                <button class="lesson-tab-btn" data-tab="quiz" style="flex:1;padding:11px;background:none;border:none;color:var(--text-dim);font-family:'Kanit',sans-serif;font-size:13px;font-weight:500;cursor:pointer;transition:all .15s">แบบทดสอบ</button>
            </div>

            <div class="lesson-tab-content card" id="lessonTab-overview">
                <div id="lessonOverviewBody"></div>
            </div>

            <div class="lesson-tab-content card" id="lessonTab-students" style="display:none">
                <div class="card-header">
                    <h3 class="card-title" style="font-size:14px">นักเรียนในบทเรียนนี้</h3>
                    <input class="search-input" type="text" id="detailStudentSearch" placeholder="ค้นหานักเรียน...">
                </div>
                <div class="table-wrap">
                    <table class="lessons-table" id="detailStudentTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>ชื่อ-นามสกุล</th>
                                <th>ระดับชั้น</th>
                                <th>คะแนนเฉลี่ย</th>
                                <th>ระดับ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $index => $student): ?>
                            <tr class="lesson-row detail-student-row" data-subject-ids="<?= h($student['subject_ids']) ?>">
                                <td class="mono"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></td>
                                <td><?= h($student['name']) ?></td>
                                <td><?= h($student['class']) ?></td>
                                <td class="mono score-cell"><?= number_format((float) $student['score'], 1) ?></td>
                                <td><span class="badge badge-<?= h($student['status']) ?>"><?= h(scoreLabel($student['status'])) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="lesson-tab-content card" id="lessonTab-quiz" style="display:none">
                <div class="card-header">
                    <h3 class="card-title" style="font-size:14px">แบบทดสอบ</h3>
                    <button class="btn-add-lesson" id="openQuizModalBtn" style="font-size:12px;padding:8px 14px">
                        <span class="plus">+</span> เพิ่มคำถาม
                    </button>
                </div>
                <div id="quizList" style="display:flex;flex-direction:column;gap:10px">
                    <div id="quizEmpty" style="text-align:center;padding:36px;color:var(--text-muted);font-size:13px">
                        ยังไม่มีแบบทดสอบในมุมมองนี้
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-overlay" id="quizModalOverlay">
            <div class="modal">
                <div class="modal-header">
                    <h3 class="modal-title">เพิ่มคำถามแบบทดสอบ</h3>
                    <button class="modal-close" id="closeQuizModalBtn">×</button>
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
                        <label>ตัวเลือก</label>
                        <textarea class="form-input" id="quizChoices" rows="4"></textarea>
                    </div>
                    <div class="form-group">
                        <label>เฉลย</label>
                        <input type="text" class="form-input" id="quizAnswer" placeholder="คำตอบที่ถูกต้อง">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn-cancel" id="closeQuizModalBtn2">ยกเลิก</button>
                    <button class="btn-save" id="saveQuizBtn">เพิ่มคำถาม</button>
                </div>
            </div>
        </div>
    </div>

    <div id="view-settings" class="page-view" style="display:none">
        <header class="topbar">
            <div class="topbar-left">
                <h1 class="page-title">ตั้งค่า</h1>
                <span class="page-sub">จัดการโปรไฟล์และรหัสผ่านของคุณ</span>
            </div>
            <div class="topbar-right">
                <div class="notif-btn">🔔</div>
            </div>
        </header>

<<<<<<< HEAD
        <div style="display:flex;flex-direction:column;gap:20px;max-width:600px;width:100%">
            <div class="card" style="margin:0">
                <div class="card-header">
                    <h2 class="card-title">โปรไฟล์</h2>
=======
    <header class="topbar">
        <div class="topbar-left">
            <h1 class="page-title">ตั้งค่า</h1>
            <span class="page-sub">จัดการโปรไฟล์และการตั้งค่าของคุณ</span>
        </div>
        <div class="topbar-right">
            <div class="notif-btn">🔔</div>
        </div>
    </header>

    <div style="display:flex;flex-direction:column;gap:20px;max-width:600px;width:100%">

    <!-- Profile Card -->
    <div class="card" style="margin:0">
        <div class="card-header">
            <h2 class="card-title">👤 โปรไฟล์</h2>
        </div>
        <div style="display:flex;align-items:center;gap:20px;padding:8px 0 24px">
            <!-- Avatar picker -->
            <div style="position:relative;flex-shrink:0;cursor:pointer" onclick="document.getElementById('avatarInput').click()" title="คลิกเพื่อเปลี่ยนรูปโปรไฟล์">
                <div id="avatarDisplay" class="profile-avatar" style="width:72px;height:72px;font-size:28px;overflow:hidden;padding:0">
                    <img id="avatarImg" src="<?= htmlspecialchars($teacher['avatar_url']) ?>" alt="" style="<?= !empty($teacher['avatar_url']) ? 'display:block' : 'display:none' ?>;width:100%;height:100%;object-fit:cover;border-radius:50%">
                    <span id="avatarInitial" style="<?= !empty($teacher['avatar_url']) ? 'display:none' : '' ?>"><?= $teacher['avatar'] ?></span>
>>>>>>> 52849ed3e843d8c643645e10466ce145d3336c4f
                </div>
                <div style="display:flex;align-items:center;gap:20px;padding:8px 0 24px">
                    <div style="position:relative;flex-shrink:0;cursor:pointer" onclick="document.getElementById('avatarInput').click()" title="คลิกเพื่อเปลี่ยนรูปโปรไฟล์">
                        <div id="avatarDisplay" class="profile-avatar" style="width:72px;height:72px;font-size:28px;overflow:hidden;padding:0">
                            <img id="avatarImg" src="" alt="" style="display:none;width:100%;height:100%;object-fit:cover;border-radius:50%">
                            <span id="avatarInitial"><?= h($teacherAvatar) ?></span>
                        </div>
                        <div style="position:absolute;bottom:0;right:0;width:22px;height:22px;background:var(--orange);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;border:2px solid var(--bg2)">✏</div>
                        <input type="file" id="avatarInput" accept="image/*" style="display:none" onchange="previewAvatar(this)">
                    </div>
                    <div>
                        <div style="font-size:18px;font-weight:600;color:var(--text)"><?= h($teacherName) ?></div>
                        <div style="font-size:13px;color:var(--text-dim);margin-top:2px">อาจารย์ • <?= h($teacherSubjectText) ?></div>
                    </div>
                </div>
                <div style="display:flex;flex-direction:column;gap:14px">
                    <div class="form-group" style="margin:0">
                        <label style="font-size:12px;color:var(--text-muted);display:block;margin-bottom:6px">ชื่อ-นามสกุล</label>
                        <input type="text" class="form-input" id="profileName" value="<?= h($teacherName) ?>">
                    </div>
                    <div class="form-group" style="margin:0">
                        <label style="font-size:12px;color:var(--text-muted);display:block;margin-bottom:6px">บทเรียนที่ดูแล</label>
                        <input type="text" class="form-input" value="<?= h($teacherSubjectText) ?>" readonly style="opacity:.5;cursor:not-allowed">
                    </div>
                    <div style="border-top:1px solid var(--border);padding-top:16px;margin-top:4px">
                        <div style="font-size:12px;color:var(--text-muted);font-weight:600;margin-bottom:14px;letter-spacing:.04em">เปลี่ยนรหัสผ่าน</div>
                        <div style="display:flex;flex-direction:column;gap:12px">
                            <div class="form-group" style="margin:0">
                                <label style="font-size:12px;color:var(--text-muted);display:block;margin-bottom:6px">รหัสผ่านปัจจุบัน</label>
                                <div style="position:relative">
                                    <input type="password" class="form-input" id="pwdCurrent" style="padding-right:42px">
                                    <button type="button" onclick="togglePwd('pwdCurrent',this)" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:16px;color:var(--text-muted);line-height:1">👁</button>
                                </div>
                            </div>
                            <div class="form-group" style="margin:0">
                                <label style="font-size:12px;color:var(--text-muted);display:block;margin-bottom:6px">รหัสผ่านใหม่</label>
                                <div style="position:relative">
                                    <input type="password" class="form-input" id="pwdNew" style="padding-right:42px" oninput="checkPwdStrength(this.value)">
                                    <button type="button" onclick="togglePwd('pwdNew',this)" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:16px;color:var(--text-muted);line-height:1">👁</button>
                                </div>
                                <div id="pwdStrengthWrap" style="display:none;margin-top:8px">
                                    <div style="height:4px;border-radius:2px;background:var(--bg3);overflow:hidden">
                                        <div id="pwdStrengthBar" style="height:100%;width:0%;transition:width .3s,background .3s;border-radius:2px"></div>
                                    </div>
                                    <div id="pwdStrengthLabel" style="font-size:11px;color:var(--text-muted);margin-top:4px"></div>
                                </div>
                            </div>
                            <div class="form-group" style="margin:0">
                                <label style="font-size:12px;color:var(--text-muted);display:block;margin-bottom:6px">ยืนยันรหัสผ่านใหม่</label>
                                <div style="position:relative">
                                    <input type="password" class="form-input" id="pwdConfirm" style="padding-right:42px" oninput="checkPwdMatch()">
                                    <button type="button" onclick="togglePwd('pwdConfirm',this)" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:16px;color:var(--text-muted);line-height:1">👁</button>
                                </div>
                                <div id="pwdMatchMsg" style="font-size:11px;margin-top:4px"></div>
                            </div>
                        </div>
                    </div>
                    <div id="profileFeedback" style="font-size:13px;display:none;padding:10px 14px;border-radius:var(--radius-sm)"></div>
                    <div style="padding-top:4px">
                        <button class="btn-add-lesson" id="saveProfileBtn" onclick="saveProfile()">บันทึกข้อมูล</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="teacherdash.js"></script>
<script>
<<<<<<< HEAD
=======
// ── Avatar preview → open crop modal ───────────────
>>>>>>> 52849ed3e843d8c643645e10466ce145d3336c4f
function previewAvatar(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    if (!file.type.startsWith('image/')) return;
    const reader = new FileReader();
<<<<<<< HEAD
    reader.onload = e => {
        const img = document.getElementById('avatarImg');
        const initial = document.getElementById('avatarInitial');
        img.src = e.target.result;
        img.style.display = 'block';
        initial.style.display = 'none';
        const sideAvatar = document.querySelector('.sidebar-profile .profile-avatar');
        if (sideAvatar) {
            sideAvatar.style.background = 'none';
            sideAvatar.style.padding = '0';
            sideAvatar.style.overflow = 'hidden';
            sideAvatar.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;border-radius:50%">`;
        }
    };
=======
    reader.onload = e => openCropModal(e.target.result);
>>>>>>> 52849ed3e843d8c643645e10466ce145d3336c4f
    reader.readAsDataURL(file);
    // reset input so same file can be re-selected
    input.value = '';
}

function togglePwd(id, btn) {
    const inp = document.getElementById(id);
    if (!inp) return;
    const show = inp.type === 'password';
    inp.type = show ? 'text' : 'password';
    btn.textContent = show ? '🙈' : '👁';
}

function checkPwdStrength(val) {
    const wrap = document.getElementById('pwdStrengthWrap');
    const bar = document.getElementById('pwdStrengthBar');
    const label = document.getElementById('pwdStrengthLabel');
    if (!val) {
        wrap.style.display = 'none';
        return;
    }
    wrap.style.display = 'block';

    let score = 0;
    if (val.length >= 8) score++;
    if (val.length >= 12) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const levels = [
        { pct:'20%', color:'#ef4444', text:'อ่อนมาก' },
        { pct:'40%', color:'#f97316', text:'อ่อน' },
        { pct:'60%', color:'#eab308', text:'ปานกลาง' },
        { pct:'80%', color:'#3b82f6', text:'ดี' },
        { pct:'100%', color:'#10b981', text:'แข็งแรงมาก' },
    ];
    const lv = levels[Math.min(score - 1, 4)] || levels[0];
    bar.style.width = lv.pct;
    bar.style.background = lv.color;
    label.style.color = lv.color;
    label.textContent = `ความแข็งแกร่ง: ${lv.text}`;
    checkPwdMatch();
}

function checkPwdMatch() {
    const newPwd = document.getElementById('pwdNew')?.value;
    const confirm = document.getElementById('pwdConfirm')?.value;
    const msg = document.getElementById('pwdMatchMsg');
    if (!msg || !confirm) return;
    if (newPwd === confirm) {
        msg.style.color = '#10b981';
        msg.textContent = 'รหัสผ่านตรงกัน';
    } else {
        msg.style.color = '#ef4444';
        msg.textContent = 'รหัสผ่านไม่ตรงกัน';
    }
}

// ═══════════════════════════════════════════════════
// CROP MODAL
// ═══════════════════════════════════════════════════
(function () {
    // ---------- state ----------
    let srcDataUrl = '';
    let imgNatW = 0, imgNatH = 0;
    // rendered image position & scale inside the crop-area
    let posX = 0, posY = 0, scale = 1;
    let dragging = false, startX = 0, startY = 0, startPX = 0, startPY = 0;

    const CROP_SIZE = 240; // px – the visible circular window

    // ---------- inject modal HTML once ----------
    const modalHTML = `
<div id="cropOverlay" style="display:none;position:fixed;inset:0;z-index:9999;
     background:rgba(0,0,0,.72);display:none;align-items:center;justify-content:center">
  <div style="background:var(--bg2,#1e2130);border-radius:16px;padding:28px 24px 22px;
       width:min(92vw,360px);box-shadow:0 24px 60px rgba(0,0,0,.5);text-align:center">
    <div style="font-size:15px;font-weight:700;color:var(--text,#f1f5f9);margin-bottom:18px">
      ✂️ ครอปรูปโปรไฟล์
    </div>

    <!-- crop viewport -->
    <div id="cropViewport" style="
         position:relative;width:${CROP_SIZE}px;height:${CROP_SIZE}px;margin:0 auto 14px;
         border-radius:50%;overflow:hidden;cursor:grab;
         box-shadow:0 0 0 4px var(--orange,#f97316),0 0 0 7px rgba(249,115,22,.2);
         background:#000;touch-action:none">
      <img id="cropImg" draggable="false" style="
           position:absolute;transform-origin:top left;user-select:none;
           -webkit-user-select:none;pointer-events:none">
    </div>

    <!-- zoom slider -->
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px">
      <span style="font-size:13px;color:var(--text-muted,#94a3b8)">🔍</span>
      <input type="range" id="cropZoom" min="1" max="3" step="0.01" value="1"
             style="flex:1;accent-color:var(--orange,#f97316)">
      <span style="font-size:13px;color:var(--text-muted,#94a3b8)">🔎</span>
    </div>

    <div style="display:flex;gap:10px">
      <button id="cropCancelBtn" style="
              flex:1;padding:10px;border-radius:8px;border:1px solid var(--border,#334155);
              background:transparent;color:var(--text,#f1f5f9);cursor:pointer;font-size:13px">
        ยกเลิก
      </button>
      <button id="cropConfirmBtn" style="
              flex:1;padding:10px;border-radius:8px;border:none;
              background:var(--orange,#f97316);color:#fff;cursor:pointer;
              font-size:13px;font-weight:600">
        ✓ ใช้รูปนี้
      </button>
    </div>
    <div id="cropUploadMsg" style="font-size:12px;margin-top:10px;color:var(--text-muted,#94a3b8);
         min-height:18px"></div>
  </div>
</div>`;

    document.body.insertAdjacentHTML('beforeend', modalHTML);

    // ---------- elements ----------
    const overlay     = document.getElementById('cropOverlay');
    const viewport    = document.getElementById('cropViewport');
    const cropImg     = document.getElementById('cropImg');
    const zoomSlider  = document.getElementById('cropZoom');
    const cancelBtn   = document.getElementById('cropCancelBtn');
    const confirmBtn  = document.getElementById('cropConfirmBtn');
    const uploadMsg   = document.getElementById('cropUploadMsg');

    // ---------- open ----------
    window.openCropModal = function (dataUrl) {
        srcDataUrl = dataUrl;
        scale = 1;
        zoomSlider.value = 1;
        uploadMsg.textContent = '';

        const tmp = new Image();
        tmp.onload = () => {
            imgNatW = tmp.naturalWidth;
            imgNatH = tmp.naturalHeight;

            // initial scale: fill the circle
            const minFit = CROP_SIZE / Math.min(imgNatW, imgNatH);
            scale = minFit;
            zoomSlider.min = minFit.toFixed(3);
            zoomSlider.value = minFit;

            cropImg.src = dataUrl;
            applyTransform();
            centerImage();
            overlay.style.display = 'flex';
        };
        tmp.src = dataUrl;
    };

    // ---------- transform helpers ----------
    function applyTransform() {
        cropImg.style.width  = (imgNatW * scale) + 'px';
        cropImg.style.height = (imgNatH * scale) + 'px';
        cropImg.style.transform = `translate(${posX}px, ${posY}px)`;
    }

    function centerImage() {
        posX = (CROP_SIZE - imgNatW * scale) / 2;
        posY = (CROP_SIZE - imgNatH * scale) / 2;
        applyTransform();
    }

    function clamp() {
        const w = imgNatW * scale;
        const h = imgNatH * scale;
        // image must fully cover the crop circle
        if (posX > 0) posX = 0;
        if (posY > 0) posY = 0;
        if (posX + w < CROP_SIZE) posX = CROP_SIZE - w;
        if (posY + h < CROP_SIZE) posY = CROP_SIZE - h;
    }

    // ---------- zoom slider ----------
    zoomSlider.addEventListener('input', () => {
        const newScale = parseFloat(zoomSlider.value);
        // zoom toward center of crop area
        const cx = CROP_SIZE / 2;
        const cy = CROP_SIZE / 2;
        const ratio = newScale / scale;
        posX = cx - ratio * (cx - posX);
        posY = cy - ratio * (cy - posY);
        scale = newScale;
        clamp();
        applyTransform();
    });

    // ---------- drag (mouse) ----------
    viewport.addEventListener('mousedown', e => {
        dragging = true;
        startX = e.clientX; startY = e.clientY;
        startPX = posX;     startPY = posY;
        viewport.style.cursor = 'grabbing';
        e.preventDefault();
    });
    document.addEventListener('mousemove', e => {
        if (!dragging) return;
        posX = startPX + (e.clientX - startX);
        posY = startPY + (e.clientY - startY);
        clamp();
        applyTransform();
    });
    document.addEventListener('mouseup', () => {
        dragging = false;
        viewport.style.cursor = 'grab';
    });

    // ---------- drag (touch) ----------
    viewport.addEventListener('touchstart', e => {
        if (e.touches.length !== 1) return;
        dragging = true;
        startX = e.touches[0].clientX; startY = e.touches[0].clientY;
        startPX = posX; startPY = posY;
        e.preventDefault();
    }, { passive: false });
    document.addEventListener('touchmove', e => {
        if (!dragging || e.touches.length !== 1) return;
        posX = startPX + (e.touches[0].clientX - startX);
        posY = startPY + (e.touches[0].clientY - startY);
        clamp();
        applyTransform();
        e.preventDefault();
    }, { passive: false });
    document.addEventListener('touchend', () => { dragging = false; });

    // ---------- cancel ----------
    cancelBtn.addEventListener('click', () => {
        overlay.style.display = 'none';
    });

    // ---------- confirm → canvas crop → upload ----------
    confirmBtn.addEventListener('click', () => {
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = CROP_SIZE;
        const ctx = canvas.getContext('2d');

        // draw only the visible region
        ctx.beginPath();
        ctx.arc(CROP_SIZE/2, CROP_SIZE/2, CROP_SIZE/2, 0, Math.PI*2);
        ctx.clip();

        const tmp = new Image();
        tmp.onload = () => {
            ctx.drawImage(tmp, posX, posY, imgNatW * scale, imgNatH * scale);
            const croppedDataUrl = canvas.toDataURL('image/png');

            // update profile page avatar immediately
            const avatarImg     = document.getElementById('avatarImg');
            const avatarInitial = document.getElementById('avatarInitial');
            if (avatarImg) {
                avatarImg.src = croppedDataUrl;
                avatarImg.style.display = 'block';
            }
            if (avatarInitial) avatarInitial.style.display = 'none';

            // update sidebar avatars
            document.querySelectorAll('.sidebar-profile .profile-avatar').forEach(el => {
                el.style.background = 'none';
                el.style.padding    = '0';
                el.style.overflow   = 'hidden';
                el.innerHTML = `<img src="${croppedDataUrl}" style="width:100%;height:100%;object-fit:cover;border-radius:50%">`;
            });

            overlay.style.display = 'none';

            // upload to server
            uploadMsg.textContent = '⏳ กำลังอัปโหลด...';
            overlay.style.display = 'flex';
            confirmBtn.disabled = true;
            cancelBtn.disabled  = true;

            fetch('uploadavatar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ image: croppedDataUrl })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    uploadMsg.style.color = '#10b981';
                    uploadMsg.textContent = '✓ อัปโหลดสำเร็จ!';
                    // update with real CDN URL
                    if (data.url) {
                        if (avatarImg) avatarImg.src = data.url;
                        document.querySelectorAll('.sidebar-profile .profile-avatar img').forEach(img => img.src = data.url);
                    }
                    setTimeout(() => { overlay.style.display = 'none'; }, 1000);
                } else {
                    uploadMsg.style.color = '#ef4444';
                    uploadMsg.textContent = '✗ ' + (data.message || 'อัปโหลดล้มเหลว');
                }
            })
            .catch(() => {
                uploadMsg.style.color = '#ef4444';
                uploadMsg.textContent = '✗ เกิดข้อผิดพลาด กรุณาลองใหม่';
            })
            .finally(() => {
                confirmBtn.disabled = false;
                cancelBtn.disabled  = false;
            });
        };
        tmp.src = srcDataUrl;
    });
})();
</script>

<!-- ── Crop Modal ── inserted by JS above ── -->
</body>
</html>