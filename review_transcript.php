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
    "SELECT r.request_id, r.student_id, r.file_path, r.original_file_name, r.mime_type, r.submitted_at,
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
                <?php
                    $fileMimeType = strtolower(trim((string) ($selectedRequest['mime_type'] ?? '')));
                    $fileExtension = strtolower(pathinfo((string) $selectedRequest['original_file_name'], PATHINFO_EXTENSION));
                    if ($fileExtension === '') {
                        $fileExtension = strtolower(pathinfo((string) $selectedRequest['file_path'], PATHINFO_EXTENSION));
                    }
                    $databaseFilePath = (string) $selectedRequest['file_path'];
                    // Resolve the relative DB path beneath the same absolute folder used by upload_transcript.php.
                    $target_dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'transcripts' . DIRECTORY_SEPARATOR;
                    $transcriptRoot = realpath(rtrim($target_dir, DIRECTORY_SEPARATOR));
                    $relativeFileName = str_starts_with($databaseFilePath, 'uploads/transcripts/')
                        ? substr($databaseFilePath, strlen('uploads/transcripts/'))
                        : '';
                    $safeFileName = $relativeFileName !== ''
                        && basename(str_replace('\\', '/', $relativeFileName)) === $relativeFileName
                        && !str_contains($relativeFileName, '/')
                        && !str_contains($relativeFileName, '\\');
                    $candidateFilePath = $safeFileName ? $target_dir . $relativeFileName : $target_dir . '__invalid_path__';
                    $resolvedFilePath = $safeFileName ? realpath($candidateFilePath) : false;
                    $fileExists = $resolvedFilePath !== false && is_file($resolvedFilePath);
                    $fileIsInsideTranscriptDirectory = $fileExists && $transcriptRoot !== false
                        && str_starts_with($resolvedFilePath, rtrim($transcriptRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
                    $isLocalTranscriptPath = $safeFileName && $fileIsInsideTranscriptDirectory;
                    $debugAbsolutePath = $resolvedFilePath !== false ? $resolvedFilePath : $candidateFilePath;
                    if ($fileMimeType === '' || $fileMimeType === 'application/octet-stream') {
                        $fileMimeType = match ($fileExtension) {
                            'pdf' => 'application/pdf',
                            'jpg', 'jpeg' => 'image/jpeg',
                            'png' => 'image/png',
                            'webp' => 'image/webp',
                            default => '',
                        };
                    }
                    $fileViewerUrl = 'transcript_file.php?request_id=' . (int) $selectedRequest['request_id'];
                ?>
                <div class="transcript-file-error" id="transcriptViewerError" role="status" hidden></div>
                <details class="transcript-path-debug" open>
                    <summary>Debug Path ไฟล์ (ชั่วคราว)</summary>
                    <div><strong>Path จากฐานข้อมูล:</strong> <code><?= hReview($databaseFilePath) ?></code></div>
                    <div><strong>Path ที่ตรวจบนเซิร์ฟเวอร์:</strong> <code><?= hReview($debugAbsolutePath) ?></code></div>
                    <div><strong>โฟลเดอร์ uploads/transcripts:</strong> <code><?= hReview($transcriptRoot !== false ? $transcriptRoot : 'ไม่พบโฟลเดอร์') ?></code></div>
                    <div><strong>พบไฟล์จริง:</strong> <?= $fileIsInsideTranscriptDirectory ? 'ใช่' : 'ไม่พบหรืออยู่นอกโฟลเดอร์ที่อนุญาต' ?></div>
                </details>
                <?php if (!$isLocalTranscriptPath): ?>
                    <div class="transcript-file-error" role="status">ไม่พบไฟล์ที่ Path ข้างต้นบนเซิร์ฟเวอร์ กรุณาเทียบกับ Path จากฐานข้อมูล หรือให้นักเรียนอัปโหลดไฟล์อีกครั้ง</div>
                <?php elseif ($fileMimeType === 'application/pdf'): ?>
                    <iframe class="transcript-pdf-viewer" title="ทรานสคริปต์ PDF ของนักเรียน" src="<?= hReview($fileViewerUrl) ?>"></iframe>
                <?php elseif (in_array($fileMimeType, ['image/jpeg', 'image/png', 'image/webp'], true)): ?>
                    <div class="transcript-image-viewer"><img src="<?= hReview($fileViewerUrl) ?>" alt="ทรานสคริปต์ของ <?= hReview((string) $selectedRequest['student_name']) ?>" loading="lazy"></div>
                <?php else: ?>
                    <div class="transcript-file-error" role="status">ไม่รองรับชนิดไฟล์นี้ กรุณาติดต่อเจ้าหน้าที่</div>
                <?php endif; ?>
                <?php if ($isLocalTranscriptPath): ?><a class="transcript-open-link" href="<?= hReview($fileViewerUrl) ?>" target="_blank" rel="noopener noreferrer">เปิดไฟล์ในแท็บใหม่</a><?php endif; ?>
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
                <div class="review-actions">
                    <button class="approve-button" type="submit" <?= !$lessonsByCourse ? 'disabled' : '' ?>>อนุมัติผลเทียบโอน</button>
                    <button class="reject-button" id="rejectTranscriptButton" type="button">ไม่อนุมัติและลบไฟล์</button>
                </div>
                <p class="review-message" id="reviewMessage" role="status" aria-live="polite"></p>
            </form>
        </div>
    <?php endif; ?>
</main>
<script>window.transcriptReviewCsrf = <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
</body>
</html>
