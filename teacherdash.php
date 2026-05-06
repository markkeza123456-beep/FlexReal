<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ตรวจสอบสิทธิ์การเข้าถึง
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db_connect.php';

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

// 0. ดึง avatar_url จากฐานข้อมูล
$avatar_url = '';
try {
    $stmtAv = $conn->prepare("SELECT avatar_url FROM public.teachers WHERE teachers_id = :uid");
    $stmtAv->execute(['uid' => $teacherId]);
    $rowAv = $stmtAv->fetch(PDO::FETCH_ASSOC);
    $avatar_url = $rowAv['avatar_url'] ?? '';
} catch (Exception $e) { $avatar_url = ''; }

// 1. ดึงข้อมูลอาจารย์
$teacherStmt = $conn->prepare('
    SELECT Teachers_ID, Teachers_Name 
    FROM public.teachers 
    WHERE Teachers_ID = :teacher_id LIMIT 1
');
$teacherStmt->execute([':teacher_id' => $teacherId]);
$teacherRow = $teacherStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$teacherName = trim((string) ($teacherRow['teachers_name'] ?? $_SESSION['name'] ?? $teacherId));
if ($teacherName === '') $teacherName = $teacherId;

// 2. ดึงข้อมูลวิชาที่อาจารย์คนนี้สอน
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
        'progress' => 0, 
        'status' => $lessonCount > 0 ? 'active' : 'draft',
        'lesson_count' => $lessonCount,
        'avg_score' => 0,
    ];

    if ($subjectId) $subjectIds[] = $subjectId;
    if ($subjectName) $subjectNames[] = $subjectName;
    $totalLessonCount += $lessonCount;
}

// 3. ดึงข้อมูลบทย่อย (Lessons)
$subLessonsBySubject = [];
if (!empty($subjectIds)) {
    $lessonStmt = $conn->prepare('
        SELECT l.Lessons_ID, l.Lessons_Name, l.Study_Hours, l.Subjects_ID 
        FROM public.lessons l
        INNER JOIN public.subjects s ON s.Subjects_ID = l.Subjects_ID
        WHERE s.Teachers_ID = :teacher_id
        ORDER BY l.Lessons_ID ASC
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

// 4. ดึงข้อมูลนักเรียนและคะแนน
$studentStmt = $conn->prepare("
    SELECT 
        st.Student_ID,
        st.Student_Name,
        st.Student_Level AS class_name,
        (
            SELECT STRING_AGG(DISTINCT s3.Subjects_ID, ',')
            FROM public.student_subject ss3
            INNER JOIN public.subjects s3 ON s3.Subjects_ID = ss3.Subjects_ID
            WHERE ss3.Student_ID = st.Student_ID
              AND s3.Teachers_ID = :teacher_id
        ) AS subject_ids,
        (
            SELECT COALESCE(AVG(t.Score), 0)
            FROM public.test t
            WHERE t.Student_ID = st.Student_ID
        ) AS real_score,
        (
            SELECT COUNT(DISTINCT l.Lessons_ID)
            FROM public.lessons l
            INNER JOIN public.subjects s ON s.Subjects_ID = l.Subjects_ID
            INNER JOIN public.student_subject ss ON ss.Subjects_ID = s.Subjects_ID
            WHERE s.Teachers_ID = :teacher_id
              AND ss.Student_ID = st.Student_ID
        ) AS total_lessons,
        (
            SELECT COUNT(DISTINCT lr.Lessons_ID)
            FROM public.learning_records lr
            INNER JOIN public.lessons l ON lr.Lessons_ID = l.Lessons_ID
            INNER JOIN public.subjects s ON l.Subjects_ID = s.Subjects_ID
            WHERE lr.Student_ID = st.Student_ID
              AND s.Teachers_ID = :teacher_id
        ) AS completed_lessons,
        (
            SELECT STRING_AGG(DISTINCT l.Lessons_Name, '||')
            FROM public.learning_records lr
            INNER JOIN public.lessons l ON lr.Lessons_ID = l.Lessons_ID
            INNER JOIN public.subjects s ON l.Subjects_ID = s.Subjects_ID
            WHERE lr.Student_ID = st.Student_ID
              AND s.Teachers_ID = :teacher_id
        ) AS completed_lesson_names
    FROM public.student st
    WHERE EXISTS (
        SELECT 1
        FROM public.student_subject ss
        INNER JOIN public.subjects s ON s.Subjects_ID = ss.Subjects_ID
        WHERE ss.Student_ID = st.Student_ID
          AND s.Teachers_ID = :teacher_id
    )
    ORDER BY st.Student_Name ASC
");
$studentStmt->execute([':teacher_id' => $teacherId]);

$students = [];
$sumScore = 0;
foreach ($studentStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $realScore = round((float) $row['real_score'], 1); 
    $sumScore += $realScore;
    
    $totalLessons = (int) $row['total_lessons'];
    $completedLessons = (int) $row['completed_lessons'];
    $progressPct = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100) : 0;
    
    $completedNames = $row['completed_lesson_names'] ? explode('||', $row['completed_lesson_names']) : [];

    $students[] = [
        'id' => $row['student_id'],
        'name' => $row['student_name'] ?: 'ไม่ระบุชื่อ',
        'class' => $row['class_name'] ?: '-',
        'score' => $realScore,
        'status' => scoreStatus($realScore),
        'subject_ids' => $row['subject_ids'],
        'progress_pct' => $progressPct,
        'completed_lessons' => $completedLessons,
        'total_lessons' => $totalLessons,
        'completed_names_json' => htmlspecialchars(json_encode($completedNames), ENT_QUOTES, 'UTF-8')
    ];
}

$overallAvgScore = count($students) > 0 ? round($sumScore / count($students), 1) : 0;

// 5. ดึงคำถามข้อสอบของวิชานี้
$quizzes = [];
$defaultSubjectId = !empty($subjects) ? $subjects[0]['id'] : '';
if ($defaultSubjectId) {
    $quizStmt = $conn->prepare("
        SELECT tq.questions_id, tq.questions_text, tq.choice_a, tq.choice_b, tq.choice_c, tq.choice_d, tq.correct_answer, l.lessons_name 
        FROM public.test_questions tq
        INNER JOIN public.lessons l ON tq.lessons_id = l.lessons_id
        WHERE l.subjects_id = :subject_id
        ORDER BY tq.questions_id ASC
    ");
    $quizStmt->execute([':subject_id' => $defaultSubjectId]);
    $quizzes = $quizStmt->fetchAll(PDO::FETCH_ASSOC);
}

$teacherSubjectText = !empty($subjectNames) 
    ? implode(', ', array_slice($subjectNames, 0, 3)) . (count($subjectNames) > 3 ? ' +' . (count($subjectNames) - 3) : '')
    : 'ยังไม่มีบทเรียนที่ดูแล';

$firstNameParts = preg_split('/\s+/', $teacherName);
$teacherFirstName = $firstNameParts[0] ?? $teacherName;
$teacherAvatar = mb_substr($teacherName, 0, 1, 'UTF-8') ?: 'T';

$teacher = [
    'name'       => $teacherName,
    'subject'    => $teacherSubjectText,
    'avatar'     => $teacherAvatar,
    'avatar_url' => $avatar_url,
    'id'         => $teacherId,
];

$stats = [
    'students' => count($students),
    'lessons' => $totalLessonCount,
    'subjects' => count($subjects),
    'avg_score' => $overallAvgScore, 
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
    <style>
        .action-icon-btn {
            background: none; border: none; cursor: pointer; padding: 6px; 
            border-radius: 6px; font-size: 14px; transition: background 0.2s;
        }
        .action-icon-btn:hover { background: rgba(255,255,255,0.1); }
        .btn-open-add-quiz {
            background: rgba(249, 115, 22, 0.1); color: var(--orange); border: 1px solid rgba(249, 115, 22, 0.3);
            padding: 6px 12px; border-radius: 6px; font-size: 12px; cursor: pointer; transition: all 0.2s; font-family: 'Kanit', sans-serif;
            display: flex; align-items: center; gap: 4px;
        }
        .btn-open-add-quiz:hover { background: rgba(249, 115, 22, 0.2); transform: translateY(-1px); }
        
        /* สไตล์สำหรับ Radio เลือกเฉลย */
        .choice-row {
            display: flex; align-items: center; gap: 10px; margin-bottom: 8px;
        }
        input[type="radio"] {
            accent-color: var(--orange);
            width: 18px; height: 18px; cursor: pointer;
        }
        .choice-label { font-weight: 600; color: var(--text-dim); font-size: 14px; width: 16px; text-align: center; }
    </style>
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
        <a href="#" class="nav-item active" data-view="dashboard"><span class="nav-icon">⊞</span><span>แดชบอร์ด</span></a>
        <a href="#" class="nav-item" data-view="lessons"><span class="nav-icon">📘</span><span>บทเรียน</span></a>
        <a href="#" class="nav-item" data-view="reports"><span class="nav-icon">📊</span><span>ออกรายงาน</span></a>
    </nav>

    <a href="logout.php" class="sidebar-logout">
        <span>🚪</span><span>ออกจากระบบ</span>
    </a>

    <a href="#" class="sidebar-profile nav-item" data-view="settings" style="text-decoration:none">
        <div class="profile-avatar" id="sidebarAvatarWrap" style="overflow:hidden;padding:0">
            <?php if (!empty($teacher['avatar_url'])): ?>
            <img src="<?= h($teacher['avatar_url']) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%" id="sidebarAvatarImg">
            <?php else: ?>
            <span id="sidebarAvatarInitial"><?= h($teacher['avatar']) ?></span>
            <?php endif; ?>
        </div>
        <div class="profile-info">
            <div class="profile-name"><?= h($teacher['name']) ?></div>
            <div class="profile-role">อาจารย์ • <?= h($teacher['subject']) ?></div>
        </div>
    </a>
</aside>

<main class="main">
    <!-- ══ VIEW: DASHBOARD ══ -->
    <div id="view-dashboard" class="page-view">
        <header class="topbar">
            <div class="topbar-left">
                <h1 class="page-title">แดชบอร์ด</h1>
                <span class="page-sub">ยินดีต้อนรับกลับมา, <?= h($teacherFirstName) ?> 👋</span>
            </div>
        </header>

        <section class="stats-grid">
            <div class="stat-card" style="--accent:#f97316">
                <div class="stat-icon">👥</div>
                <div class="stat-value"><?= $stats['students'] ?></div>
                <div class="stat-label">นักเรียนที่ดูแล</div>
                <div class="stat-trend">เฉพาะที่ผูกกับวิชาของอาจารย์</div>
            </div>
            <div class="stat-card" style="--accent:#3b82f6">
                <div class="stat-icon">📘</div>
                <div class="stat-value"><?= $stats['lessons'] ?></div>
                <div class="stat-label">บทเรียนทั้งหมด</div>
                <div class="stat-trend">รวมบทเรียนในรายวิชาที่สอน</div>
            </div>
            <div class="stat-card" style="--accent:#10b981">
                <div class="stat-icon">🗂</div>
                <div class="stat-value"><?= $stats['subjects'] ?></div>
                <div class="stat-label">รายวิชาที่ดูแล</div>
                <div class="stat-trend">แสดงเฉพาะของอาจารย์ท่านนี้</div>
            </div>
            <div class="stat-card" style="--accent:#a855f7">
                <div class="stat-icon">🎯</div>
                <div class="stat-value"><?= number_format($stats['avg_score'], 1) ?>%</div>
                <div class="stat-label">คะแนนเฉลี่ย</div>
                <div class="stat-trend">จากแบบทดสอบของนักเรียนทั้งหมด</div>
            </div>
        </section>

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
                            <th>ระดับชั้น</th>
                            <th style="width: 25%">ความคืบหน้า</th>
                            <th>คะแนนเฉลี่ย</th>
                            <th>ระดับ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($students === []): ?>
                        <tr class="lesson-row">
                            <td colspan="6" style="text-align:center;color:var(--text-muted)">ยังไม่มีนักเรียนในความดูแล</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($students as $index => $student): ?>
                        <tr class="lesson-row">
                            <td class="mono"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></td>
                            <td>
                                <a href="#" class="student-progress-link" 
                                   data-name="<?= h($student['name']) ?>"
                                   data-completed="<?= h($student['completed_lessons']) ?>"
                                   data-total="<?= h($student['total_lessons']) ?>"
                                   data-json="<?= $student['completed_names_json'] ?>"
                                   onclick="showStudentProgress(this); return false;"
                                   style="color:#60a5fa; font-weight:500; text-decoration:none; display:flex; align-items:center; gap:6px;" title="คลิกเพื่อดูบทเรียนที่ผ่านแล้ว">
                                    <?= h($student['name']) ?> <span style="font-size:12px; color:var(--text-dim);">ℹ️</span>
                                </a>
                            </td>
                            <td><?= h($student['class']) ?></td>
                            <td>
                                <div class="progress-wrap">
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="--pct:<?= $student['progress_pct'] ?>%"></div>
                                    </div>
                                    <span class="progress-num"><?= $student['progress_pct'] ?>%</span>
                                </div>
                                <div style="font-size:10.5px; color:var(--text-muted); margin-top:4px;">
                                    สำเร็จ <?= $student['completed_lessons'] ?> / <?= $student['total_lessons'] ?> บทเรียน
                                </div>
                            </td>
                            <td class="mono score-cell"><?= number_format((float) $student['score'], 1) ?></td>
                            <td><span class="badge badge-<?= h($student['status']) ?>"><?= h(scoreLabel($student['status'])) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <!-- ══ VIEW: LESSONS (หน้ารายละเอียดหลัก) ══ -->
    <div id="view-lessons" class="page-view" style="display:none">
        <header class="topbar">
            <div class="topbar-left">
                <h1 class="page-title">บทเรียน</h1>
                <span class="page-sub">จัดการเนื้อหาและแบบทดสอบในวิชาของคุณ</span>
            </div>
        </header>

        <?php $defaultSubject = reset($subjects); ?>

        <div id="lessonDetailSection" style="display:flex;flex-direction:column;gap:20px">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px">
                <div style="display:flex;align-items:center;gap:12px">
                    <span style="font-size:13px;color:var(--text-muted)">รายละเอียดวิชา</span>
                </div>
                <!-- ปุ่มเพิ่มบทเรียนย่อย -->
                <button class="btn-add-lesson" id="openModalBtn" style="font-size:13px;padding:8px 14px" data-subject-id="<?= h($defaultSubjectId) ?>">
                    <span class="plus">+</span> เพิ่มบทเรียนย่อย
                </button>
            </div>

            <!-- ส่วนหัวของวิชา (Header) -->
            <div class="card" id="lessonDetailHeader" style="padding: 24px;">
                <?php if ($defaultSubject): ?>
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px">
                        <div>
                            <div style="font-size:24px;font-weight:700;color:#fff;margin-bottom:6px">▶ <?= h($defaultSubject['title']) ?></div>
                            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;font-size:13px;color:var(--text-dim)">
                                <span>📚 <?= h($defaultSubject['subject']) ?></span>
                                <span>👥 <?= (int)$defaultSubject['students'] ?> คน</span>
                                <span class="badge badge-<?= h($defaultSubject['status']) ?>"><?= $defaultSubject['status'] === 'active' ? 'เผยแพร่' : 'ฉบับร่าง' ?></span>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="color:var(--text-muted);">ยังไม่มีรายวิชาที่คุณดูแล</div>
                <?php endif; ?>
            </div>

            <!-- Tabs -->
            <div class="lesson-tabs" style="display:flex;gap:0;border:1px solid var(--border);border-radius:var(--radius-sm);overflow:hidden;background:var(--bg2)">
                <button class="lesson-tab-btn active" data-tab="overview" style="flex:1;padding:14px;background:var(--orange-dim);border:none;border-right:1px solid var(--border);color:var(--orange);font-family:'Kanit',sans-serif;font-size:14px;font-weight:600;cursor:pointer;transition:all .15s;">📋 ภาพรวมบทเรียน</button>
                <button class="lesson-tab-btn" data-tab="students" style="flex:1;padding:14px;background:none;border:none;border-right:1px solid var(--border);color:var(--text-dim);font-family:'Kanit',sans-serif;font-size:14px;font-weight:500;cursor:pointer;transition:all .15s">👥 รายชื่อนักเรียน</button>
                <button class="lesson-tab-btn" data-tab="quiz" style="flex:1;padding:14px;background:none;border:none;color:var(--text-dim);font-family:'Kanit',sans-serif;font-size:14px;font-weight:500;cursor:pointer;transition:all .15s">🧪 คลังแบบทดสอบ</button>
            </div>

            <!-- Tab Content: Overview -->
            <div class="lesson-tab-content card" id="lessonTab-overview">
                <div id="lessonOverviewBody">
                    <?php if ($defaultSubject): ?>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px; margin-bottom: 30px;">
                            <div>
                                <div style="font-size:11.5px;color:var(--text-muted);margin-bottom:6px;font-weight:500">ข้อมูลรายวิชา</div>
                                <div style="font-size:13.5px;color:var(--text);line-height:1.7">
                                    <div><span style="color:var(--text-dim)">ชื่อ:</span> <?= h($defaultSubject['title']) ?></div>
                                    <div><span style="color:var(--text-dim)">คำอธิบาย:</span> <?= h($defaultSubject['subject']) ?></div>
                                </div>
                            </div>
                            <div>
                                <div style="font-size:11.5px;color:var(--text-muted);margin-bottom:6px;font-weight:500">สถิติเบื้องต้น</div>
                                <div style="display:flex;flex-direction:column;gap:8px;font-size:13.5px">
                                    <div style="display:flex;justify-content:space-between">
                                        <span style="color:var(--text-dim)">นักเรียนลงทะเบียน</span>
                                        <span class="mono" style="color:#fff"><?= (int)$defaultSubject['students'] ?> คน</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ส่วนแสดงรายการบทเรียนย่อย -->
                        <div style="border-top:1px solid var(--border);padding-top:24px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                                <h3 style="font-size:16px;color:#fff;font-weight:600;">รายการบทเรียนย่อย</h3>
                            </div>
                            <div style="display:flex;flex-direction:column;gap:12px;">
                                <?php 
                                if ($defaultSubjectId && !empty($subLessonsBySubject[$defaultSubjectId])): 
                                    foreach ($subLessonsBySubject[$defaultSubjectId] as $i => $subLesson):
                                ?>
                                <div style="background:var(--bg3);padding:16px 20px;border-radius:var(--radius-sm);border:1px solid var(--border);display:flex;align-items:center;justify-content:space-between; flex-wrap:wrap; gap:12px;">
                                    <div style="display:flex;align-items:center;gap:16px;">
                                        <div style="width:36px;height:36px;background:var(--bg2);border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--orange);font-weight:600;font-size:14px;border:1px solid rgba(249,115,22,0.2);">
                                            <?= $i+1 ?>
                                        </div>
                                        <div>
                                            <div style="color:#fff;font-size:15px;font-weight:500;"><?= h($subLesson['title']) ?></div>
                                            <div style="color:var(--text-dim);font-size:12px;margin-top:2px;">⏱ <?= h($subLesson['duration']) ?></div>
                                        </div>
                                    </div>
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <span class="badge badge-<?= h($subLesson['status']) ?>" style="margin-right:8px;"><?= $subLesson['status'] === 'active' ? 'เผยแพร่' : 'ฉบับร่าง' ?></span>
                                        
                                        <!-- ปุ่มเพิ่มข้อสอบให้บทนี้โดยเฉพาะ -->
                                        <button class="btn-open-add-quiz" data-id="<?= h($subLesson['id']) ?>" data-name="<?= h($subLesson['title']) ?>">
                                            <span style="font-size:16px;line-height:1;">+</span> เพิ่มแบบทดสอบ
                                        </button>
                                        
                                        <!-- ปุ่มแก้ไข และ ลบ บทเรียน -->
                                        <button class="action-icon-btn btn-edit-lsn" data-id="<?= h($subLesson['id']) ?>" data-name="<?= h($subLesson['title']) ?>" title="แก้ไขบทเรียน">✏️</button>
                                        <button class="action-icon-btn btn-del-lsn" data-id="<?= h($subLesson['id']) ?>" title="ลบบทเรียน" style="color:#ef4444;">🗑</button>
                                    </div>
                                </div>
                                <?php 
                                    endforeach;
                                else: 
                                ?>
                                <div style="text-align:center;padding:40px;color:var(--text-muted);font-size:14px;background:var(--bg3);border-radius:var(--radius-sm);border:1px dashed var(--border);">
                                    <div style="font-size:32px;margin-bottom:10px;">📁</div>
                                    ยังไม่มีบทเรียนย่อย คลิกปุ่ม "+ เพิ่มบทเรียนย่อย" ด้านขวาบนเพื่อเริ่มต้น
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tab Content: Students -->
            <div class="lesson-tab-content card" id="lessonTab-students" style="display:none">
                <div class="card-header">
                    <h3 class="card-title" style="font-size:14px">นักเรียนในวิชานี้</h3>
                    <input class="search-input" type="text" id="detailStudentSearch" placeholder="🔍 ค้นหานักเรียน...">
                </div>
                <div class="table-wrap">
                    <table class="lessons-table" id="detailStudentTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>ชื่อ-นามสกุล</th>
                                <th>ระดับชั้น</th>
                                <th style="width: 25%">ความคืบหน้า</th>
                                <th>คะแนนเฉลี่ย</th>
                                <th>ระดับ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $index => $student): ?>
                            <tr class="lesson-row detail-student-row" data-subject-ids="<?= h($student['subject_ids']) ?>">
                                <td class="mono"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></td>
                                <td>
                                    <a href="#" class="student-progress-link" 
                                       data-name="<?= h($student['name']) ?>"
                                       data-completed="<?= h($student['completed_lessons']) ?>"
                                       data-total="<?= h($student['total_lessons']) ?>"
                                       data-json="<?= $student['completed_names_json'] ?>"
                                       onclick="showStudentProgress(this); return false;"
                                       style="color:#60a5fa; font-weight:500; text-decoration:none; display:flex; align-items:center; gap:6px;" title="คลิกเพื่อดูบทเรียนที่ผ่านแล้ว">
                                        <?= h($student['name']) ?> <span style="font-size:12px; color:var(--text-dim);">ℹ️</span>
                                    </a>
                                </td>
                                <td><?= h($student['class']) ?></td>
                                <td>
                                    <div class="progress-wrap">
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="--pct:<?= $student['progress_pct'] ?>%"></div>
                                        </div>
                                        <span class="progress-num"><?= $student['progress_pct'] ?>%</span>
                                    </div>
                                    <div style="font-size:10.5px; color:var(--text-muted); margin-top:4px;">
                                        สำเร็จ <?= $student['completed_lessons'] ?> / <?= $student['total_lessons'] ?> บทเรียน
                                    </div>
                                </td>
                                <td class="mono score-cell"><?= number_format((float) $student['score'], 1) ?></td>
                                <td><span class="badge badge-<?= h($student['status']) ?>"><?= h(scoreLabel($student['status'])) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab Content: Quiz -->
            <div class="lesson-tab-content card" id="lessonTab-quiz" style="display:none">
                <div class="card-header">
                    <h3 class="card-title" style="font-size:14px">คลังแบบทดสอบทั้งหมดในวิชานี้</h3>
                </div>
                <div id="quizList" style="display:flex;flex-direction:column;gap:12px;">
                    <?php if (empty($quizzes)): ?>
                        <div id="quizEmpty" style="text-align:center;padding:40px;color:var(--text-muted);font-size:13px; border: 1px dashed var(--border); border-radius: var(--radius-sm);">
                            <div style="font-size:32px;margin-bottom:10px;">🧪</div>
                            ยังไม่มีคำถามในระบบ — กรุณาไปที่แท็บ "ภาพรวม" แล้วกด "+ เพิ่มแบบทดสอบ" ในบทเรียนย่อยที่ต้องการ
                        </div>
                    <?php else: ?>
                        <?php foreach($quizzes as $i => $q): 
                            // จำแนกประเภทคำถามเพื่อแสดงผลให้ถูกต้อง
                            $qType = 'choice';
                            $qLabel = 'ปรนัย (4 ตัวเลือก)';
                            if ($q['correct_answer'] === '-') {
                                $qType = 'essay';
                                $qLabel = 'อัตนัย (ข้อเขียน)';
                            } elseif ($q['choice_a'] === 'ถูก' || $q['choice_a'] === 'ถูก (True)') {
                                $qType = 'truefalse';
                                $qLabel = 'ถูก/ผิด';
                            }
                        ?>
                        <div class="quiz-item" style="background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius-sm);padding:16px;display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
                            <div style="display:flex; gap:12px; flex:1; min-width:0;">
                                <div class="mono" style="font-size:12px;color:var(--orange);width:24px;flex-shrink:0;padding-top:2px;font-weight:600;">Q<?= $i+1 ?></div>
                                <div>
                                    <div style="font-size:14px;color:var(--text);line-height:1.5;margin-bottom:6px;"><?= h($q['questions_text']) ?></div>
                                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                                        <span class="badge badge-draft" style="font-size:10.5px;"><?= $qLabel ?></span>
                                        <span style="font-size:11.5px;color:var(--text-muted)">จาก: <?= h($q['lessons_name']) ?></span>
                                        
                                        <?php if($qType === 'choice' || $qType === 'truefalse'): ?>
                                            <span style="font-size:11.5px;color:var(--text-dim); background:rgba(255,255,255,0.05); padding: 2px 8px; border-radius:4px;">
                                                เฉลย: <span style="color:#10b981;font-weight:600;">
                                                    <?php 
                                                        $ans = $q['correct_answer'];
                                                        if($ans === 'A') echo 'A. ' . h($q['choice_a']);
                                                        elseif($ans === 'B') echo 'B. ' . h($q['choice_b']);
                                                        elseif($ans === 'C') echo 'C. ' . h($q['choice_c']);
                                                        elseif($ans === 'D') echo 'D. ' . h($q['choice_d']);
                                                        else echo $ans;
                                                    ?>
                                                </span>
                                            </span>
                                        <?php elseif($qType === 'essay'): ?>
                                            <span style="font-size:11.5px;color:#facc15; background:rgba(234,179,8,0.1); padding: 2px 8px; border-radius:4px;">รออาจารย์ตรวจ</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div style="display:flex;gap:4px;flex-shrink:0;">
                                <!-- ปุ่มแก้ไขและลบแบบทดสอบ -->
                                <button class="action-icon-btn btn-edit-quiz" 
                                    data-id="<?= h($q['questions_id']) ?>" 
                                    data-type="<?= $qType ?>"
                                    data-question="<?= h($q['questions_text']) ?>"
                                    data-ca="<?= h($q['choice_a']) ?>"
                                    data-cb="<?= h($q['choice_b']) ?>"
                                    data-cc="<?= h($q['choice_c']) ?>"
                                    data-cd="<?= h($q['choice_d']) ?>"
                                    data-answer="<?= h($q['correct_answer']) ?>"
                                    title="แก้ไขคำถาม">✏️</button>
                                <button class="action-icon-btn btn-del-quiz" data-id="<?= h($q['questions_id']) ?>" title="ลบคำถาม" style="color:#ef4444;">🗑</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ═ Modal 1: เพิ่มบทเรียนย่อย ═ -->
        <div class="modal-overlay" id="modalOverlay">
            <div class="modal">
                <div class="modal-header">
                    <h3 class="modal-title">➕ เพิ่มบทเรียนย่อย</h3>
                    <button class="modal-close" onclick="document.getElementById('modalOverlay').classList.remove('open')">✕</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>ชื่อบทเรียนย่อย</label>
                        <input type="text" class="form-input" id="lessonNameInput" placeholder="เช่น 1.1 ตรรกศาสตร์เบื้องต้น">
                    </div>
                    <input type="hidden" id="lessonSubjectId" value="<?= h($defaultSubjectId) ?>">
                </div>
                <div class="modal-footer">
                    <button class="btn-cancel" onclick="document.getElementById('modalOverlay').classList.remove('open')">ยกเลิก</button>
                    <button class="btn-save" id="saveLessonBtn">💾 บันทึกบทเรียน</button>
                </div>
            </div>
        </div>

        <!-- ═ Modal 2: แก้ไขบทเรียนย่อย ═ -->
        <div class="modal-overlay" id="editLessonModal">
            <div class="modal">
                <div class="modal-header">
                    <h3 class="modal-title">✏️ แก้ไขชื่อบทเรียน</h3>
                    <button class="modal-close" onclick="document.getElementById('editLessonModal').classList.remove('open')">✕</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>ชื่อบทเรียนใหม่</label>
                        <input type="text" class="form-input" id="editLsnNameInput">
                    </div>
                    <input type="hidden" id="editLsnIdInput">
                </div>
                <div class="modal-footer">
                    <button class="btn-cancel" onclick="document.getElementById('editLessonModal').classList.remove('open')">ยกเลิก</button>
                    <button class="btn-save" id="saveEditLsnBtn">💾 บันทึกการแก้ไข</button>
                </div>
            </div>
        </div>

        <!-- ═ Modal 3: เพิ่มแบบทดสอบ ═ -->
        <div class="modal-overlay" id="quizModalOverlay">
            <div class="modal" style="max-width: 520px;">
                <div class="modal-header">
                    <h3 class="modal-title">🧪 เพิ่มคำถามลงในบทเรียน</h3>
                    <button class="modal-close" onclick="document.getElementById('quizModalOverlay').classList.remove('open')">×</button>
                </div>
                <div class="modal-body">
                    <div style="background:var(--orange-dim); color:var(--orange); padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:10px; border:1px solid rgba(249,115,22,0.3);">
                        <b>เพิ่มลงใน:</b> <span id="quizTargetLessonName"></span>
                    </div>
                    <input type="hidden" id="quizTargetLessonId" value="">

                    <div class="form-group">
                        <label>รูปแบบข้อสอบ</label>
                        <select class="form-input" id="quizTypeAdd">
                            <option value="choice">ปรนัย (4 ตัวเลือก)</option>
                            <option value="truefalse">ถูก/ผิด</option>
                            <option value="essay">อัตนัย (ข้อเขียน รออาจารย์ตรวจ)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>คำถาม</label>
                        <textarea class="form-input" id="quizQuestionAdd" rows="3" placeholder="พิมพ์คำถาม..."></textarea>
                    </div>

                    <!-- ส่วนกรอกตัวเลือก ปรนัย -->
                    <div class="form-group" id="quizChoiceGroupAdd">
                        <label>ตัวเลือก (เลือกวงกลมหน้าข้อที่เป็นเฉลย)</label>
                        <div style="display:flex; flex-direction:column; gap:8px;">
                            <div class="choice-row"><input type="radio" name="correctAdd" value="A" checked><span class="choice-label">A.</span><input type="text" class="form-input" id="chA_Add" placeholder="ตัวเลือก ก"></div>
                            <div class="choice-row"><input type="radio" name="correctAdd" value="B"><span class="choice-label">B.</span><input type="text" class="form-input" id="chB_Add" placeholder="ตัวเลือก ข"></div>
                            <div class="choice-row"><input type="radio" name="correctAdd" value="C"><span class="choice-label">C.</span><input type="text" class="form-input" id="chC_Add" placeholder="ตัวเลือก ค"></div>
                            <div class="choice-row"><input type="radio" name="correctAdd" value="D"><span class="choice-label">D.</span><input type="text" class="form-input" id="chD_Add" placeholder="ตัวเลือก ง"></div>
                        </div>
                    </div>

                    <!-- ส่วนกรอกตัวเลือก ถูก/ผิด -->
                    <div class="form-group" id="quizTFGroupAdd" style="display:none;">
                        <label>เฉลย (เลือกข้อที่ถูกต้อง)</label>
                        <div style="display:flex; gap:20px; margin-top:8px;">
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;color:var(--text);"><input type="radio" name="tfAdd" value="A" checked> ถูก (True)</label>
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;color:var(--text);"><input type="radio" name="tfAdd" value="B"> ผิด (False)</label>
                        </div>
                    </div>

                    <!-- ส่วนแสดงสำหรับอัตนัย -->
                    <div class="form-group" id="quizEssayGroupAdd" style="display:none;">
                        <div style="padding:12px; background:rgba(234,179,8,0.1); color:#facc15; border-radius:6px; font-size:12px; border:1px dashed rgba(234,179,8,0.3);">
                            ✏️ <b>โหมดข้อเขียน:</b> นักเรียนจะต้องพิมพ์คำตอบส่งมา และคุณจะต้องเป็นผู้ตรวจให้คะแนนเองในภายหลัง
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn-cancel" onclick="document.getElementById('quizModalOverlay').classList.remove('open')">ยกเลิก</button>
                    <button class="btn-save" id="saveQuizBtn">➕ เพิ่มคำถาม</button>
                </div>
            </div>
        </div>

        <!-- ═ Modal 4: แก้ไขแบบทดสอบ ═ -->
        <div class="modal-overlay" id="editQuizModal">
            <div class="modal" style="max-width: 520px;">
                <div class="modal-header">
                    <h3 class="modal-title">✏️ แก้ไขคำถาม</h3>
                    <button class="modal-close" onclick="document.getElementById('editQuizModal').classList.remove('open')">×</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="editQuizIdInput" value="">
                    <input type="hidden" id="editQuizTypeHidden" value="choice">

                    <div class="form-group">
                        <label>คำถาม</label>
                        <textarea class="form-input" id="editQuizQuestion" rows="3"></textarea>
                    </div>

                    <!-- ส่วนแก้ไข ปรนัย -->
                    <div class="form-group" id="quizChoiceGroupEdit">
                        <label>ตัวเลือก (เลือกวงกลมหน้าข้อที่เป็นเฉลย)</label>
                        <div style="display:flex; flex-direction:column; gap:8px;">
                            <div class="choice-row"><input type="radio" name="correctEdit" value="A"><span class="choice-label">A.</span><input type="text" class="form-input" id="chA_Edit"></div>
                            <div class="choice-row"><input type="radio" name="correctEdit" value="B"><span class="choice-label">B.</span><input type="text" class="form-input" id="chB_Edit"></div>
                            <div class="choice-row"><input type="radio" name="correctEdit" value="C"><span class="choice-label">C.</span><input type="text" class="form-input" id="chC_Edit"></div>
                            <div class="choice-row"><input type="radio" name="correctEdit" value="D"><span class="choice-label">D.</span><input type="text" class="form-input" id="chD_Edit"></div>
                        </div>
                    </div>

                    <!-- ส่วนแก้ไข ถูก/ผิด -->
                    <div class="form-group" id="quizTFGroupEdit" style="display:none;">
                        <label>เฉลย (เลือกข้อที่ถูกต้อง)</label>
                        <div style="display:flex; gap:20px; margin-top:8px;">
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;color:var(--text);"><input type="radio" name="tfEdit" value="A"> ถูก (True)</label>
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;color:var(--text);"><input type="radio" name="tfEdit" value="B"> ผิด (False)</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn-cancel" onclick="document.getElementById('editQuizModal').classList.remove('open')">ยกเลิก</button>
                    <button class="btn-save" id="saveEditQuizBtn">💾 บันทึกการแก้ไข</button>
                </div>
            </div>
        </div>

    </div>
    
    <!-- ══ VIEW: REPORTS ══ -->
    <div id="view-reports" class="page-view" style="display:none">
        <header class="topbar">
            <div class="topbar-left">
                <h1 class="page-title">ออกรายงาน</h1>
            </div>
        </header>
        <div class="content-grid" style="grid-template-columns: 1fr;">
            <div class="card" style="text-align: center; padding: 60px 20px;">
                <div style="font-size: 48px; margin-bottom: 20px;">📊</div>
                <h2 style="color: #fff; margin-bottom: 10px;">ระบบออกรายงานกำลังอยู่ระหว่างการพัฒนา</h2>
                <button class="btn-add-lesson" style="margin: 0 auto; background: var(--bg3); color: var(--text-muted); cursor: not-allowed;">📥 ส่งออกรายงาน (เร็วๆ นี้)</button>
            </div>
        </div>
    </div>
</main>

<script src="teacherdash.js"></script>
</body>
</html>