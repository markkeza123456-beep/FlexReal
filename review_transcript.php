<?php require __DIR__ . '/includes/transcript_review_data.php'; ?>
<?php
$pageTitle = 'ตรวจสอบทรานสคริปต์';
$pageScripts = ['assets/js/review.js'];
require __DIR__ . '/includes/header.php';
?>
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
                    // New requests store a Base64 data URI; retain fallback for older local-file requests.
                    $fileViewerUrl = (string) ($selectedRequest['document_data'] ?? '');
                    $fileMimeType = strtolower((string) ($selectedRequest['mime_type'] ?? ''));
                    $isTranscriptImage = str_starts_with($fileViewerUrl, 'data:image/')
                        || in_array($fileMimeType, ['image/jpeg', 'image/png', 'image/webp'], true);
                    $isTranscriptPdf = str_starts_with($fileViewerUrl, 'data:application/pdf;')
                        || $fileMimeType === 'application/pdf'
                        || strtolower(pathinfo(parse_url($fileViewerUrl, PHP_URL_PATH) ?: $fileViewerUrl, PATHINFO_EXTENSION)) === 'pdf';
                    $isBase64Pdf = str_starts_with($fileViewerUrl, 'data:application/pdf;');
                    $isBase64Image = str_starts_with($fileViewerUrl, 'data:image/');
                    $isBase64Document = $isBase64Pdf || $isBase64Image;
                ?>
                <div class="transcript-viewer">
                    <?php if ($isTranscriptImage): ?>
                        <img <?= $isBase64Image ? 'id="transcriptImageViewer"' : '' ?> src="<?= hReview($fileViewerUrl) ?>" alt="เอกสารทรานสคริปต์ของ <?= hReview((string) $selectedRequest['student_name']) ?>">
                    <?php elseif ($isTranscriptPdf): ?>
                        <iframe id="transcriptPdfViewer" title="เอกสารทรานสคริปต์ของนักเรียน" <?= $isBase64Pdf ? 'data-document="' . hReview($fileViewerUrl) . '"' : 'src="' . hReview($fileViewerUrl) . '"' ?>></iframe>
                    <?php else: ?>
                        <p class="transcript-file-error">ไม่รองรับชนิดไฟล์นี้</p>
                    <?php endif; ?>
                </div>
                <a class="transcript-open-link" <?= $isBase64Document ? 'id="transcriptOpenLink" href="#" aria-disabled="true"' : 'href="' . hReview($fileViewerUrl) . '"' ?> target="_blank" rel="noopener noreferrer">เปิดไฟล์ในแท็บใหม่</a>
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
<?php require __DIR__ . '/includes/footer.php'; ?>
