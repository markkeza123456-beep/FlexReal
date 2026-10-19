<?php
session_start();
$role = strtolower(trim((string) ($_SESSION['role'] ?? '')));
$userId = (string) ($_SESSION['user_id'] ?? '');
if ($userId === '' || !in_array($role, ['staff', 'teacher'], true)) {
    http_response_code(401);
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db_connect.php';
if ($role === 'teacher') {
    $access = $conn->prepare(
        "SELECT EXISTS (
            SELECT 1 FROM public.transfer_permissions p
            JOIN public.users u ON u.user_id = p.instructor_id
            WHERE p.instructor_id = :user_id AND p.is_active = TRUE AND u.role = 'teacher'
        )"
    );
    $access->execute([':user_id' => $userId]);
    if (!$access->fetchColumn()) {
        http_response_code(403);
        exit('คุณไม่มีสิทธิ์ตรวจสอบคำขอเทียบโอน');
    }
}

if (empty($_SESSION['transcript_review_csrf'])) {
    $_SESSION['transcript_review_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = (string) $_SESSION['transcript_review_csrf'];
$requestsStmt = $conn->query(
    "SELECT r.request_id, r.student_id, r.original_file_name, r.submitted_at,
            COALESCE(s.full_name, r.student_id) AS student_name
     FROM public.transcript_requests r
     LEFT JOIN public.students s ON s.user_id = r.student_id
     WHERE r.status = 'pending'
     ORDER BY r.submitted_at ASC"
);
$requests = $requestsStmt->fetchAll(PDO::FETCH_ASSOC);
$selectedRequestId = trim((string) ($_GET['request_id'] ?? ''));
$selectedRequest = null;
foreach ($requests as $request) {
    if ((string) $request['request_id'] === $selectedRequestId) {
        $selectedRequest = $request;
        break;
    }
}
if (!$selectedRequest && $requests) {
    $selectedRequest = $requests[0];
    $selectedRequestId = (string) $selectedRequest['request_id'];
}

$lessonsByCourse = [];
if ($selectedRequest) {
    $lessonsStmt = $conn->query(
        'SELECT c.course_id, c.code AS course_code, c.name AS course_name,
                l.lesson_id, l.position, l.title, l.content
         FROM public.courses c
         INNER JOIN public.lessons l ON l.course_id = c.course_id
         ORDER BY c.name ASC, l.position ASC'
    );
    foreach ($lessonsStmt->fetchAll(PDO::FETCH_ASSOC) as $lesson) {
        $courseId = (string) $lesson['course_id'];
        if (!isset($lessonsByCourse[$courseId])) {
            $lessonsByCourse[$courseId] = [
                'code' => (string) ($lesson['course_code'] ?? ''),
                'name' => (string) $lesson['course_name'],
                'lessons' => [],
            ];
        }
        $lessonsByCourse[$courseId]['lessons'][] = $lesson;
    }
}

function hReview(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ตรวจสอบทรานสคริปต์</title>
    <link rel="stylesheet" href="theme.css">
    <link rel="stylesheet" href="transcript.css">
    <link rel="stylesheet" href="review_transcript.css">
    <script src="review_transcript.js" defer></script>
</head>
<body>
<main class="review-page">
    <header class="review-header">
        <div><a class="back-link" href="<?= $role === 'staff' ? 'staffdash.php' : 'teacherdash.php' ?>">← กลับแดชบอร์ด</a><h1>ตรวจสอบทรานสคริปต์</h1><p>เลือกบทเรียนที่เทียบโอนผ่านจากเอกสารของนักเรียน</p></div>
        <label class="request-picker">คำขอที่รอตรวจ
            <select id="requestPicker" <?= !$requests ? 'disabled' : '' ?>>
                <?php foreach ($requests as $request): ?>
                    <option value="<?= (int) $request['request_id'] ?>" <?= (string) $request['request_id'] === $selectedRequestId ? 'selected' : '' ?>>
                        #<?= (int) $request['request_id'] ?> · <?= hReview((string) $request['student_name']) ?> (<?= hReview((string) $request['student_id']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </header>

    <?php if (!$selectedRequest): ?>
        <section class="review-empty">ไม่มีคำขอทรานสคริปต์ที่รอตรวจสอบ</section>
    <?php else: ?>
        <div class="review-meta">
            <strong><?= hReview((string) $selectedRequest['student_name']) ?></strong>
            <span>รหัสนักเรียน <?= hReview((string) $selectedRequest['student_id']) ?></span>
            <span>ไฟล์: <?= hReview((string) $selectedRequest['original_file_name']) ?></span>
        </div>
        <div class="review-columns">
            <section class="document-pane" aria-label="เอกสารทรานสคริปต์">
                <div class="pane-title">เอกสารทรานสคริปต์</div>
                <iframe title="ทรานสคริปต์ของนักเรียน" src="transcript_file.php?request_id=<?= (int) $selectedRequest['request_id'] ?>"></iframe>
            </section>
            <form class="checklist-pane" id="transcriptReviewForm" action="process_transcript.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= hReview($csrfToken) ?>">
                <input type="hidden" name="request_id" value="<?= (int) $selectedRequest['request_id'] ?>">
                <div class="pane-title">Checklist บทเรียนที่ให้ผ่าน</div>
                <p class="checklist-help">เลือกเฉพาะบทเรียนที่เนื้อหาตรงกับผลการเรียนในทรานสคริปต์</p>
                <div class="lesson-checklist">
                    <?php if (!$lessonsByCourse): ?>
                        <p class="muted">ยังไม่มีบทเรียนในระบบ</p>
                    <?php else: foreach ($lessonsByCourse as $courseId => $course): ?>
                        <fieldset class="course-group">
                            <legend><?= hReview($course['name']) ?><?= $course['code'] !== '' ? ' · ' . hReview($course['code']) : '' ?></legend>
                            <?php foreach ($course['lessons'] as $lesson): ?>
                                <label class="lesson-option">
                                    <input type="checkbox" name="lesson_ids[]" value="<?= (int) $lesson['lesson_id'] ?>">
                                    <span><strong>บทที่ <?= (int) $lesson['position'] ?>: <?= hReview((string) $lesson['title']) ?></strong>
                                        <?php if (trim((string) ($lesson['content'] ?? '')) !== ''): ?><small><?= hReview(mb_strimwidth(strip_tags((string) $lesson['content']), 0, 180, '…', 'UTF-8')) ?></small><?php endif; ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                    <?php endforeach; endif; ?>
                </div>
                <label class="review-note-label" for="reviewerNote">หมายเหตุ (ถ้ามี)</label>
                <textarea id="reviewerNote" name="reviewer_note" rows="3" maxlength="2000" placeholder="บันทึกข้อสังเกตถึงนักเรียน"></textarea>
                <button class="approve-button" type="submit" <?= !$lessonsByCourse ? 'disabled' : '' ?>>อนุมัติผลเทียบโอน</button>
                <p class="review-message" id="reviewMessage" role="status" aria-live="polite"></p>
            </form>
        </div>
    <?php endif; ?>
</main>
<script>window.transcriptReviewCsrf = <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
</body>
</html>
