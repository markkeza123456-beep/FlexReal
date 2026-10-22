<?php
session_start();
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function hubEscape($value): string { return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function hubCourseImagePath(array $course): ?string {
    $path = trim(str_replace('\\', '/', (string) ($course['image_path'] ?? '')));
    if ($path === '') return null;
    if (preg_match('/^data:(image\/(?:jpeg|png|webp|gif));base64,([A-Za-z0-9+\/=]+)$/s', $path, $matches)) {
        if (strlen($path) > 7 * 1024 * 1024) return null;
        $decoded = base64_decode($matches[2], true);
        $imageInfo = $decoded === false ? false : @getimagesizefromstring($decoded);
        return is_array($imageInfo) && ($imageInfo['mime'] ?? '') === $matches[1] ? $path : null;
    }
    if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $path) || str_starts_with($path, '/')) return null;
    $projectRoot = realpath(__DIR__);
    $imageFile = realpath(__DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
    if ($projectRoot === false || $imageFile === false || !is_file($imageFile)) return null;
    $rootPrefix = rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strncasecmp($imageFile, $rootPrefix, strlen($rootPrefix)) !== 0) return null;
    return $path;
}
function hubCard(array $course): string {
    $id = (string) ($course['course_id'] ?? '');
    $name = (string) ($course['name'] ?? '');
    $image = hubCourseImagePath($course);
    $type = in_array(($course['subject_type'] ?? ''), ['compulsory', 'required'], true) ? 'required' : (($course['subject_type'] ?? '') === 'elective' ? 'elective' : 'unknown');
    $tag = $type === 'required' ? 'วิชาบังคับ' : ($type === 'elective' ? 'วิชาเลือก' : 'ยังไม่ได้กำหนดประเภท');
    $subjectType = strtolower(trim((string) ($course['subject_type'] ?? '')));
    $type = in_array($subjectType, ['compulsory', 'required', 'mandatory', 'บังคับ', 'วิชาบังคับ'], true) ? 'required' : 'elective';
    $tag = $subjectType === '' ? '' : ($type === 'required' ? 'วิชาบังคับ' : 'วิชาเลือก');
    $status = !empty($course['is_enrolled']) ? 'ลงทะเบียนแล้ว' : 'ยังไม่ลงทะเบียน';
    $cover = $image !== null
        ? '<img src="' . hubEscape($image) . '" alt="ภาพปกวิชา ' . hubEscape($name) . '" loading="lazy">'
        : '<div class="course-cover-placeholder" role="img" aria-label="ไม่มีภาพปกวิชา"><i class="fa-solid fa-book-open" aria-hidden="true"></i></div>';
    return '<a class="card" href="index.php?subject_id=' . rawurlencode($id) . '">' . $cover . '<div class="card-content"><span class="card-tag ' . $type . '">' . $tag . '</span><h3>' . hubEscape($name) . '</h3><p>คลิกเพื่อดูรายละเอียด</p><div class="card-meta">รหัสวิชา: ' . hubEscape($id) . ' · ' . $status . '</div></div></a>';
}
function hubLessonList(array $lessons): string {
    if (!$lessons) return '<div class="assignment-empty-state">วิชานี้ยังไม่มีบทเรียน</div>';
    $html = '';
    foreach ($lessons as $index => $lesson) {
        $number = (int) ($lesson['position'] ?? ($index + 1));
        $title = hubEscape($lesson['title'] ?? '');
        $document = hubEscape($lesson['document_name'] ?: 'เปิดอ่านเอกสาร');
        $video = hubEscape($lesson['video_name'] ?: 'รับชมวิดีโอ');
        $html .= '<article class="lesson-accordion is-collapsed"><button type="button" class="lesson-header" onclick="toggleLesson(' . $number . ')" aria-expanded="false"><span>บทที่ ' . $number . ': ' . $title . '</span><span class="lesson-chevron">▾</span></button><div class="lesson-body"><div class="curriculum-list">';
        if (trim((string) ($lesson['content'] ?? '')) !== '') $html .= '<div style="margin:0 0 12px;padding:12px 14px;border:1px solid #f0d6c5;border-radius:10px;background:#fffaf7;white-space:pre-wrap"><b>รายละเอียดบทเรียน</b><p style="margin:6px 0 0">' . nl2br(hubEscape($lesson['content'])) . '</p></div>';
        $html .= '<div class="curriculum-item"><div class="curr-left"><span class="curr-icon"><i class="fa-solid fa-file-lines" aria-hidden="true"></i></span><div class="curr-text"><b>เอกสารประกอบบทเรียน</b><p>' . $document . '</p></div></div><button class="btn-orange" onclick="openCourseDocument(' . $number . ')">เปิดอ่าน</button></div>';
        $html .= '<div class="curriculum-item"><div class="curr-left"><span class="curr-icon"><i class="fa-solid fa-circle-play" aria-hidden="true"></i></span><div class="curr-text"><b>วิดีโอสรุปบทเรียน</b><p>' . $video . '</p></div></div><button class="btn-orange" onclick="openCourseVideo(' . $number . ')">ชมวิดีโอ</button></div>';
        $html .= '<div class="curriculum-item"><div class="curr-left"><span class="curr-icon"><i class="fa-solid fa-question" aria-hidden="true"></i></span><div class="curr-text"><b>แบบทดสอบประจำบท</b><p>ทดสอบความเข้าใจ</p></div></div><button class="btn-outline-orange" onclick="startQuiz(' . $number . ')">เริ่มทำ Quiz</button></div></div></div></article>';
    }
    return $html;
}

$hubRole = strtolower((string) ($_SESSION['role'] ?? ''));
$hubUserId = (string) ($_SESSION['user_id'] ?? '');
$hubCourses = [];
$hubCourse = null;
$hubLessons = [];
$hubInstructors = [];
$hubLoadError = false;
try {
    require_once __DIR__ . '/config/db.php';
    $courseSql = "SELECT c.course_id, c.code, c.name, c.description, c.image_path, CASE WHEN LOWER(TRIM(COALESCE(cc.requirement_type, c.subject_type, ''))) IN ('required', 'compulsory', 'mandatory', 'บังคับ', 'วิชาบังคับ') THEN 'compulsory' WHEN LOWER(TRIM(COALESCE(cc.requirement_type, c.subject_type, ''))) IN ('elective', 'optional', 'เลือก', 'วิชาเลือก') THEN 'elective' ELSE NULL END AS subject_type, (cc.course_id IS NOT NULL) AS in_curriculum, (sc.course_id IS NOT NULL) AS is_enrolled FROM public.courses c LEFT JOIN LATERAL (SELECT curriculum_id FROM public.student_curricula WHERE student_id = :student_id AND status = 'active' ORDER BY enrolled_at DESC LIMIT 1) active_curriculum ON true LEFT JOIN public.curriculum_courses cc ON cc.curriculum_id = active_curriculum.curriculum_id AND cc.course_id = c.course_id LEFT JOIN public.student_courses sc ON sc.course_id = c.course_id AND sc.student_id = :student_id AND sc.status = 'active' WHERE c.status = 'active' ORDER BY (cc.course_id IS NULL), c.name";
    $courseStatement = $conn->prepare($courseSql);
    $studentIdForQuery = $hubRole === 'student' ? $hubUserId : '';
    $courseStatement->execute([':student_id' => $studentIdForQuery]);
    $hubCourses = $courseStatement->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $requestedCourse = trim((string) ($_GET['subject_id'] ?? ''));
    if ($requestedCourse === '' && isset($_GET['course'])) $requestedCourse = trim((string) $_GET['course']);
    if ($requestedCourse !== '') {
        $detailStatement = $conn->prepare("SELECT c.course_id, c.code, c.name, c.description, c.image_path, c.credits, CASE WHEN LOWER(TRIM(COALESCE(cc.requirement_type, c.subject_type, ''))) IN ('required', 'compulsory', 'mandatory', 'บังคับ', 'วิชาบังคับ') THEN 'compulsory' WHEN LOWER(TRIM(COALESCE(cc.requirement_type, c.subject_type, ''))) IN ('elective', 'optional', 'เลือก', 'วิชาเลือก') THEN 'elective' ELSE NULL END AS subject_type, (sc.course_id IS NOT NULL) AS is_enrolled FROM public.courses c LEFT JOIN LATERAL (SELECT curriculum_id FROM public.student_curricula WHERE student_id = :student_id AND status = 'active' ORDER BY enrolled_at DESC LIMIT 1) active_curriculum ON true LEFT JOIN public.curriculum_courses cc ON cc.curriculum_id = active_curriculum.curriculum_id AND cc.course_id = c.course_id LEFT JOIN public.student_courses sc ON sc.course_id = c.course_id AND sc.student_id = :student_id AND sc.status = 'active' WHERE c.status = 'active' AND c.course_id::text = :id LIMIT 1");
        $detailStatement->execute([':student_id' => $studentIdForQuery, ':id' => $requestedCourse]);
        $hubCourse = $detailStatement->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($hubCourse) {
            $lessonStatement = $conn->prepare("SELECT l.lesson_id, l.course_id, l.title, l.content, l.position, COALESCE(video.url, '') AS video_url, COALESCE(video.url, '') AS video_path, COALESCE(video.title, '') AS video_name, COALESCE(document.url, '') AS document_path, COALESCE(document.title, '') AS document_name FROM public.lessons l LEFT JOIN LATERAL (SELECT url, title FROM public.lesson_resources WHERE lesson_id = l.lesson_id AND resource_type = 'video' ORDER BY position DESC, resource_id DESC LIMIT 1) video ON true LEFT JOIN LATERAL (SELECT url, title FROM public.lesson_resources WHERE lesson_id = l.lesson_id AND resource_type = 'document' ORDER BY position DESC, resource_id DESC LIMIT 1) document ON true WHERE l.course_id = :course_id ORDER BY l.position");
            $lessonStatement->execute([':course_id' => $hubCourse['course_id']]);
            $hubLessons = $lessonStatement->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $teacherStatement = $conn->prepare("SELECT t.user_id, t.full_name, t.email, t.phone, t.avatar_url FROM public.course_teachers ct JOIN public.teachers t ON t.user_id = ct.teacher_id WHERE ct.course_id = :id ORDER BY t.full_name");
            $teacherStatement->execute([':id' => $hubCourse['course_id']]);
            $hubInstructors = $teacherStatement->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $countStatement = $conn->prepare("SELECT COUNT(DISTINCT l.lesson_id) AS lesson_count, COUNT(DISTINCT l.lesson_id) FILTER (WHERE q.question_id IS NOT NULL) AS quiz_count FROM public.lessons l LEFT JOIN public.questions q ON q.lesson_id = l.lesson_id AND q.is_active = TRUE WHERE l.course_id = :id");
            $countStatement->execute([':id' => $hubCourse['course_id']]);
            $hubCounts = $countStatement->fetch(PDO::FETCH_ASSOC) ?: [];
            $hubCourse['lesson_count'] = (int) ($hubCounts['lesson_count'] ?? 0);
            $hubCourse['quiz_count'] = (int) ($hubCounts['quiz_count'] ?? 0);
            $hubCourse['teachers_name'] = implode(', ', array_filter(array_column($hubInstructors, 'full_name'))) ?: 'ไม่ระบุอาจารย์ผู้สอน';
            $hubCourse['subjects_id'] = $hubCourse['course_id'];
            $hubCourse['subjects_name'] = $hubCourse['name'];
            $hubCourse['subjects_description'] = $hubCourse['description'];
        }
    }
} catch (Throwable $error) {
    $hubLoadError = true;
    error_log('Learning Hub SSR query failed: ' . $error->getMessage());
}
$hubInstructor = $hubInstructors[0] ?? null;
$hubInstructorName = trim((string) ($hubInstructor['full_name'] ?? '')) ?: 'ยังไม่กำหนดอาจารย์ผู้สอน';
$hubInstructorContact = implode(' · ', array_filter([trim((string) ($hubInstructor['email'] ?? '')), trim((string) ($hubInstructor['phone'] ?? ''))]));
$hubInstructorInfo = 'อาจารย์ผู้สอนประจำรายวิชา' . ($hubInstructorContact !== '' ? ' · ' . $hubInstructorContact : '');
$hubDetailActive = $hubCourse !== null;
$hubClientCourses = array_map(static fn(array $course): array => [
    'course_id' => $course['course_id'] ?? '',
    'name' => $course['name'] ?? '',
    'subject_type' => $course['subject_type'] ?? null,
    'image_path' => $course['image_path'] ?? '',
    'is_enrolled' => $course['is_enrolled'] ?? false,
], $hubCourses);
$hubClientCourse = $hubCourse ? [
    'course_id' => $hubCourse['course_id'] ?? '',
    'name' => $hubCourse['name'] ?? '',
    'is_enrolled' => $hubCourse['is_enrolled'] ?? false,
] : null;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Flexible Learning Hub</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/css/web.css?v=20261022-pdf-reader-zoom-v1">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="assets/css/theme.css?v=20261022-mobile-layout-v2">
    <link rel="stylesheet" href="assets/css/responsive.css?v=20261022-mobile-search-fix">
</head>
<body class="landing-page">

<nav class="navbar">
    <div class="navbar-topline">
        <button type="button" class="nav-toggle" aria-label="เปิดเมนู" aria-controls="site-navigation" aria-expanded="false"><span></span><span></span><span></span></button>
        <a href="index.php" class="logo">Flexible Learning Hub</a>
        <div class="mobile-profile-slot"></div>
    </div>
    <div class="nav-links" id="site-navigation">
        <input type="text" id="searchInput" class="nav-search-input" onfocus="showPage('home')" onkeyup="filterCourses()" placeholder="ค้นหาบทเรียนที่คุณสนใจ...">
        <a href="index.php" class="nav-home-link">หน้าหลัก</a>
        
        <div class="dropdown">
            <button type="button" class="dropbtn" aria-expanded="false">หมวดหมู่ <span class="dropdown-arrow" aria-hidden="true"></span></button>
            <div class="dropdown-content" id="course-dropdown">
                <?php foreach ($hubCourses as $course): ?><a href="index.php?subject_id=<?= rawurlencode((string) $course['course_id']) ?>"><?= hubEscape($course['name']) ?></a><?php endforeach; ?>
                <?php if (!$hubCourses): ?><a href="#" onclick="return false;"><?= $hubLoadError ? 'ไม่สามารถโหลดรายวิชาได้' : 'ยังไม่มีรายวิชา' ?></a><?php endif; ?>
            </div>
        </div>

        <div class="dropdown">
            <button type="button" class="dropbtn" aria-expanded="false">คู่มือการเรียน <span class="dropdown-arrow" aria-hidden="true"></span></button>
            <div class="dropdown-content">
                <a href="#" onclick="showGuide('register')">ขั้นตอนการสมัคร</a>
                <a href="#" onclick="showGuide('certificate')">การรับใบประกาศ</a>
            </div> 
        </div>

        <div class="dropdown">
            <button type="button" class="dropbtn" aria-expanded="false">ติดต่อเรา <span class="dropdown-arrow" aria-hidden="true"></span></button>
            <div class="dropdown-content">
                <a href="#" onclick="showContact('channel')">ช่องทางการติดต่อ</a>
                <a href="#" onclick="showContact('location')">สถานที่ตั้ง</a>
            </div> 
        </div>

        <a href="login.php" id="loginBtn" class="btn-orange">Login</a>
<div id="userProfile" class="user-profile-wrap">
            <button type="button" id="userProfileBtn" class="user-profile">
                <div class="user-avatar" id="userAvatar">U</div>
                <span id="userName">ผู้ใช้งาน</span>
            </button>
            <div id="userMenu" class="user-menu">
                <a href="student_dashboard.php#settings">ข้อมูลส่วนตัว</a>
                <a href="logout.php?return=index.php">ออกจากระบบ</a>
            </div>
        </div>
    </div>
    </nav>

<div id="home" class="page<?= $hubDetailActive ? '' : ' active' ?>">
    <div class="hero">
        <h1>Learning Hub</h1>
        <p class="hero-subtitle">ค้นพบรายวิชาออนไลน์ที่ตอบโจทย์ชีวิตคุณ</p>
    </div>

    <div class="container">
        <div id="public-course-section"<?= $hubRole === 'student' ? ' style="display:none"' : '' ?>>
            <h2 class="section-title">รายวิชาทั้งหมด</h2>
            <div class="grid" id="course-grid">
                <?php if (!$hubCourses): ?><p class="course-loading"><?= $hubLoadError ? 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้' : 'ยังไม่มีรายวิชาในระบบ' ?></p><?php else: ?><?php foreach ($hubCourses as $course) echo hubCard($course); ?><?php endif; ?>
            </div>
        </div>

        <div id="student-course-sections" style="display:<?= $hubRole === 'student' ? 'block' : 'none' ?>">
            <div class="course-section-block">
                <div class="section-head-row">
                    <h2 class="section-title">วิชาที่เรียน</h2>
                </div>
                <div class="course-carousel">
                    <button class="carousel-control" type="button" aria-label="เลื่อนวิชาที่เรียนไปทางซ้าย" onclick="scrollCourseCarousel('required-course-grid', -1)">‹</button>
                    <div class="grid" id="required-course-grid"><?php foreach ($hubCourses as $course) if (!empty($course['is_enrolled'])) echo hubCard($course); ?></div>
                    <button class="carousel-control" type="button" aria-label="เลื่อนวิชาที่เรียนไปทางขวา" onclick="scrollCourseCarousel('required-course-grid', 1)">›</button>
                </div>
            </div>

            <div class="course-section-block">
                <div class="section-head-row">
                    <h2 class="section-title">วิชาที่ยังไม่เรียน</h2>
                </div>
                <div class="course-carousel">
                    <button class="carousel-control" type="button" aria-label="เลื่อนวิชาที่ยังไม่เรียนไปทางซ้าย" onclick="scrollCourseCarousel('elective-course-grid', -1)">‹</button>
                    <div class="grid" id="elective-course-grid"><?php foreach ($hubCourses as $course) if (empty($course['is_enrolled'])) echo hubCard($course); ?></div>
                    <button class="carousel-control" type="button" aria-label="เลื่อนวิชาที่ยังไม่เรียนไปทางขวา" onclick="scrollCourseCarousel('elective-course-grid', 1)">›</button>
                </div>
            </div>
        </div>
    </div>

        

    <div class="steps-bg">
        <h2 class="section-title steps-title">เรียนรู้ง่ายๆ ใน 5 ขั้นตอน</h2>
        <div class="steps-grid">
            <div><div class="step-icon">1</div><h4>สมัครสมาชิก</h4></div>
            <div><div class="step-icon">2</div><h4>ค้นหารายวิชา</h4></div>
            <div><div class="step-icon">3</div><h4>เรียนออนไลน์</h4></div>
            <div><div class="step-icon">4</div><h4>ทำแบบทดสอบ</h4></div>
            <div><div class="step-icon">5</div><h4>รับใบประกาศ</h4></div>
        </div>
    </div>
</div>

<div id="course-detail" class="page<?= $hubDetailActive ? ' active' : '' ?>">
    <div class="container">
        <div class="course-layout-grid">
            
            <div class="course-main-content">
    <h1 id="detail-title" class="course-page-title"><?= hubEscape($hubCourse['name'] ?? 'ชื่อวิชา') ?></h1>
    
    <p id="detail-desc-top" class="course-page-subtitle"><?= $hubDetailActive ? 'รายละเอียดเบื้องต้น' : 'รายละเอียดเบื้องต้นของวิชา...' ?></p>
                <div class="tabs">
                    <button class="tab-btn active" onclick="openTab(event, 'overview')">รายละเอียด</button>
                    <button class="tab-btn" onclick="openTab(event, 'curriculum')">เนื้อหาในคอร์ส</button>
                    <button class="tab-btn" onclick="openTab(event, 'instructor')">ผู้สอน</button>
                </div>

                <div id="overview" class="tab-content active">
                    <div class="info-card section-info-card">
                        <h3 class="section-title subsection-title">คำอธิบายรายวิชา</h3>
                        <p id="detail-desc"><?= hubEscape(trim((string) ($hubCourse['description'] ?? '')) ?: 'ยังไม่มีคำอธิบาย') ?></p>
                    </div>
                </div>

    <div id="curriculum" class="tab-content">
    <div class="info-card">
        <p id="curriculum-locked-msg" class="curriculum-locked-message"<?= $hubDetailActive && !empty($hubCourse['is_enrolled']) ? ' style="display:none"' : '' ?>>กรุณาลงทะเบียนรายวิชาก่อน เพื่อปลดล็อกสื่อการสอนและแบบทดสอบ</p>
        <div id="course-curriculum-lesson-list" class="lesson-accordion-list"<?= $hubDetailActive && !empty($hubCourse['is_enrolled']) ? '' : ' style="display:none"' ?>><?= $hubDetailActive ? hubLessonList($hubLessons) : '' ?></div>
    </div>
</div>

                <div id="instructor" class="tab-content">
                    <div class="info-card section-info-card">
                        <h3 class="section-title subsection-title">อาจารย์ผู้สอน</h3>
                        <div class="instructor-profile">
                            <?php if (!empty($hubInstructor['avatar_url'])): ?><img id="detail-instructor-avatar" class="instructor-avatar" src="<?= hubEscape($hubInstructor['avatar_url']) ?>" alt="รูปโปรไฟล์ <?= hubEscape($hubInstructorName) ?>" style="object-fit:cover"><?php else: ?><div id="detail-instructor-avatar" class="instructor-avatar"><?= hubEscape(mb_substr($hubInstructorName, 0, 1, 'UTF-8')) ?></div><?php endif; ?>
                            <div><b id="detail-instructor-name"><?= hubEscape($hubInstructorName) ?></b><p id="detail-instructor-role" class="instructor-role"><?= hubEscape($hubInstructorInfo) ?></p></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="course-sidebar">
                <div class="sidebar-card">
                    <?php if (!empty($hubCourse['is_enrolled'])): ?>
                        <a class="btn-enroll is-enrolled" id="enroll-course-btn" href="#lessons" onclick="goToCourseLearning(event)">กำลังเรียน</a>
                    <?php else: ?>
                        <button class="btn-enroll" id="enroll-course-btn" type="button" onclick="enrollCourseAndOpenLearning()"<?= !$hubDetailActive ? ' disabled' : '' ?>>เข้าเรียน</button>
                    <?php endif; ?>
                    
                    <div class="meta-list">
                        <div class="meta-item"><span> Lessons</span> <b id="detail-lesson-count"><?= (int) ($hubCourse['lesson_count'] ?? 0) ?> บทเรียน</b></div>
                        <div class="meta-item"><span> Quizzes</span> <b id="detail-quiz-count"><?= (int) ($hubCourse['quiz_count'] ?? 0) ?> ชุด</b></div>
                        <div class="meta-item"><span> Language</span> <b>ภาษาไทย</b></div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<div id="course-learning" class="page">
    <div class="container">
        <div class="course-layout-grid">
            <div class="course-main-content">
                <h1 id="learning-title" class="course-page-title"><?= hubEscape($hubCourse['name'] ?? 'ชื่อวิชา') ?></h1>
                <p id="learning-desc-top" class="course-page-subtitle">เริ่มเรียนรายวิชา</p>

                <div class="tabs">
                    <button class="tab-btn" onclick="openLearningTab(event, 'learning-overview')">รายละเอียด</button>
                    <button class="tab-btn active" onclick="openLearningTab(event, 'learning-curriculum')">เนื้อหาในคอร์ส</button>
                    <button class="tab-btn" onclick="openLearningTab(event, 'learning-instructor')">ผู้สอน</button>
                </div>

                <div id="learning-overview" class="learning-tab-content">
                    <div class="info-card section-info-card">
                        <h3 class="section-title subsection-title">คำอธิบายรายวิชา</h3>
                        <p id="learning-desc"><?= hubEscape(trim((string) ($hubCourse['description'] ?? '')) ?: 'ยังไม่มีคำอธิบาย') ?></p>
                    </div>
                </div>

                <div id="learning-curriculum" class="learning-tab-content active">
                    <div class="info-card">
                        <div id="lessons"><div id="learning-lesson-text-list" class="lesson-accordion-list"><?= $hubDetailActive ? hubLessonList($hubLessons) : '' ?></div></div>
                    </div>
                </div>

                <div id="learning-instructor" class="learning-tab-content">
                    <div class="info-card section-info-card">
                        <h3 class="section-title subsection-title">อาจารย์ผู้สอน</h3>
                        <div class="instructor-profile">
                            <?php if (!empty($hubInstructor['avatar_url'])): ?><img id="learning-instructor-avatar" class="instructor-avatar" src="<?= hubEscape($hubInstructor['avatar_url']) ?>" alt="รูปโปรไฟล์ <?= hubEscape($hubInstructorName) ?>" style="object-fit:cover"><?php else: ?><div id="learning-instructor-avatar" class="instructor-avatar"><?= hubEscape(mb_substr($hubInstructorName, 0, 1, 'UTF-8')) ?></div><?php endif; ?>
                            <div><b id="learning-instructor-name"><?= hubEscape($hubInstructorName) ?></b><p id="learning-instructor-role" class="instructor-role"><?= hubEscape($hubInstructorInfo) ?></p></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="course-sidebar">
                <div class="sidebar-card">
                    <button class="btn-enroll is-enrolled">กำลังเรียน</button>

                    <div class="meta-list">
                    <div class="meta-item"><span> Lessons</span> <b id="learning-lesson-count"><?= (int) ($hubCourse['lesson_count'] ?? 0) ?> บทเรียน</b></div>
                    <div class="meta-item"><span> Quizzes</span> <b id="learning-quiz-count"><?= (int) ($hubCourse['quiz_count'] ?? 0) ?> ชุด</b></div>
                        <div class="meta-item"><span> Language</span> <b>ภาษาไทย</b></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="modal-overlay" class="modal-overlay">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal()">&times;</span>
        <div id="modal-body"></div>
    </div>
</div>

<div id="page-guide" class="page">
    <div class="content-header">
        <h1 id="guide-title">คู่มือการเรียน</h1>
    </div>
    <div class="container" id="guide-content">
        </div>
</div>

<div id="page-contact" class="page">
    <div class="content-header">
        <h1 id="contact-title">ติดต่อเรา</h1>
    </div>
    <div class="container" id="contact-content">
        </div>
</div>

<footer class="footer">
    <p><b>Flexible Learning Hub</b></p>
    <p>© 2026 Flow Learning. All Rights Reserved.</p>
</footer>

<script>window.FLEXREAL_INITIAL = <?= json_encode(['courses' => $hubClientCourses, 'course' => $hubClientCourse, 'lessons' => $hubLessons, 'instructors' => $hubInstructors], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;</script>
<script src="assets/js/web.js?v=20261022-course-type-labels"></script>

</body>
</html>
