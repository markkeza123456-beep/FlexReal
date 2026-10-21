<?php require_once __DIR__ . '/includes/language.php'; ?>
<?php require __DIR__ . '/includes/transcript_review_data.php'; ?>
<?php
$pageTitle = 'ตรวจสอบทรานสคริปต์';
$pageScripts = ['assets/js/review.js?v=20261020-search-modal-center'];
require __DIR__ . '/includes/header.php';
?>
<main class="review-page">
    <header class="review-header">
        <div><a class="back-link" href="<?= $role === 'staff' ? 'staffdash.php' : 'teacherdash.php' ?>">← กลับแดชบอร์ด</a><h1>ตรวจสอบทรานสคริปต์</h1><p>เลือกบทเรียนที่เทียบโอนผ่านจากเอกสารของนักเรียน</p></div>
        <div class="current-request-picker">
            <?php if ($selectedRequest): ?>
                <?php
                    $requestStatus = (string) $selectedRequest['status'];
                    $requestStatusLabel = $requestStatus === 'pending' ? 'รอตรวจสอบ' : ($requestStatus === 'approved' ? 'อนุมัติแล้ว' : 'ปฏิเสธ');
                ?>
                <div class="current-request-card">
                    <span class="current-request-number">คำขอ #<?= (int) $selectedRequest['request_id'] ?></span>
                    <strong><?= hReview((string) $selectedRequest['student_name']) ?></strong>
                    <span>รหัส <?= hReview((string) $selectedRequest['student_id']) ?></span>
                    <span class="request-status-pill status-<?= hReview($requestStatus) ?>"><?= hReview($requestStatusLabel) ?></span>
                </div>
            <?php else: ?>
                <div class="current-request-card current-request-empty">ยังไม่มีคำขอเทียบโอน</div>
            <?php endif; ?>
            <button type="button" class="open-request-modal" id="openRequestModal" <?= !$requests ? 'disabled' : '' ?>>🔍 ค้นหาคำขอ / เปลี่ยนคน</button>
        </div>
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
                    <?php elseif ($fileViewerUrl !== ''): ?>
                        <p class="transcript-file-error">ไม่รองรับชนิดไฟล์นี้</p>
                    <?php else: ?>
                        <p class="transcript-file-error">ไม่พบไฟล์เอกสารทรานสคริปต์</p>
                    <?php endif; ?>
                </div>
                <a class="transcript-open-link" <?= $isBase64Document ? 'id="transcriptOpenLink" href="#" aria-disabled="true"' : 'href="' . hReview($fileViewerUrl) . '"' ?> target="_blank" rel="noopener noreferrer">เปิดไฟล์ในแท็บใหม่</a>
            </section>
            <section class="checklist-pane audit-pane" aria-label="ประวัติและสถานะการตรวจสอบ">
                <div class="pane-title">ประวัติและสถานะการตรวจสอบ</div>
                <?php if ($selectedRequest['status'] === 'approved'): ?>
                    <div class="audit-status audit-status-approved">อนุมัติผลเทียบโอนเรียบร้อยแล้ว</div>
                <?php elseif ($selectedRequest['status'] === 'pending'): ?>
                    <div class="audit-status audit-status-pending">รอเจ้าหน้าที่ตรวจสอบ</div>
                <?php else: ?>
                    <div class="audit-status audit-status-rejected">ปฏิเสธคำขอเทียบโอน</div>
                <?php endif; ?>
                <dl class="audit-details">
                    <div><dt>วันที่นักเรียนอัปโหลด</dt><dd><?= hReview(formatReviewDate((string) ($selectedRequest['created_at'] ?? $selectedRequest['submitted_at'] ?? ''))) ?></dd></div>
                    <?php if ($user_role === 'staff' && $selectedRequest['status'] === 'approved'): ?>
                        <div><dt>ผู้อนุมัติ</dt><dd><?= hReview((string) ($selectedRequest['approver_display'] ?? '-')) ?></dd></div>
                        <div><dt>วันที่อนุมัติ</dt><dd><?= hReview(formatReviewDate((string) ($selectedRequest['approved_at'] ?? $selectedRequest['reviewed_at'] ?? ''))) ?></dd></div>
                    <?php endif; ?>
                </dl>
                <?php if ($selectedRequest['status'] === 'approved'): ?>
                    <div class="approved-course-list">
                        <strong>รายวิชา/บทเรียนที่ได้รับการเทียบโอนเกรด TR</strong>
                        <?php if (!empty($selectedRequest['approved_courses_list'])): ?>
                            <ul>
                                <?php foreach ($selectedRequest['approved_courses_list'] as $approvedCourse): ?>
                                    <?php if (is_array($approvedCourse)): ?>
                                        <li>
                                            <strong><?= hReview((string) ($approvedCourse['course_name'] ?? 'รายวิชา')) ?><?= !empty($approvedCourse['course_code']) ? ' (' . hReview((string) $approvedCourse['course_code']) . ')' : '' ?></strong>
                                            <?php if (!empty($approvedCourse['lessons']) && is_array($approvedCourse['lessons'])): ?>
                                                <ul><?php foreach ($approvedCourse['lessons'] as $approvedLesson): ?><li><?= hReview((string) (is_array($approvedLesson) ? ($approvedLesson['lesson_title'] ?? 'บทเรียน') : $approvedLesson)) ?></li><?php endforeach; ?></ul>
                                            <?php endif; ?>
                                        </li>
                                    <?php else: ?>
                                        <li><?= hReview((string) $approvedCourse) ?></li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="muted">ไม่พบรายละเอียดบทเรียนที่บันทึกไว้</p>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($selectedRequest['reviewer_note'])): ?>
                        <p class="audit-note"><strong>หมายเหตุการตรวจสอบ:</strong> <?= hReview((string) $selectedRequest['reviewer_note']) ?></p>
                    <?php endif; ?>
                <?php elseif ($selectedRequest['status'] === 'pending'): ?>
                    <form class="review-checklist-form" id="transcriptReviewForm" action="process_transcript.php" method="post">
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
                <?php elseif (!empty($selectedRequest['reviewer_note'])): ?>
                    <p class="audit-note"><strong>หมายเหตุการตรวจสอบ:</strong> <?= hReview((string) $selectedRequest['reviewer_note']) ?></p>
                <?php endif; ?>
            </section>
        </div>
    <?php endif; ?>
</main>
<div class="search-modal-overlay" id="searchModal" aria-hidden="true">
    <section class="search-modal-box" role="dialog" aria-modal="true" aria-labelledby="requestModalTitle">
        <header class="request-modal-header">
            <div><h2 id="requestModalTitle">ค้นหาคำขอเทียบโอน</h2><p>ค้นหาจากชื่อ รหัสนักเรียน เลขคำขอ หรือสถานะ</p></div>
            <button type="button" class="close-btn" id="closeRequestModal" aria-label="ปิดหน้าต่าง">✕</button>
        </header>
        <input type="search" id="requestModalSearch" class="request-modal-search" placeholder="พิมพ์คำค้นหา..." autocomplete="off" aria-label="ค้นหาคำขอเทียบโอน">
        <div class="search-modal-content request-modal-table-wrap">
            <table class="request-modal-table">
                <thead><tr><th>เลขคำขอ</th><th>ชื่อ-นามสกุล</th><th>รหัสนักเรียน</th><th>สถานะ</th><th></th></tr></thead>
                <tbody id="requestModalRows">
                    <?php foreach ($requests as $request): ?>
                        <?php
                            $rowStatus = (string) $request['status'];
                            $rowStatusLabel = $rowStatus === 'pending' ? 'รอตรวจสอบ' : ($rowStatus === 'approved' ? 'อนุมัติแล้ว' : 'ปฏิเสธ');
                            $searchText = '#' . (int) $request['request_id'] . ' ' . (string) $request['student_name'] . ' ' . (string) $request['student_id'] . ' ' . $rowStatus . ' ' . $rowStatusLabel;
                        ?>
                        <tr data-search-text="<?= hReview($searchText) ?>">
                            <td>#<?= (int) $request['request_id'] ?></td>
                            <td><?= hReview((string) $request['student_name']) ?></td>
                            <td><?= hReview((string) $request['student_id']) ?></td>
                            <td><span class="request-status-pill status-<?= hReview($rowStatus) ?>"><?= hReview($rowStatusLabel) ?></span></td>
                            <td><a class="select-request-button" href="review_transcript.php?id=<?= (int) $request['request_id'] ?>">เลือกตรวจ</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr id="requestModalEmpty" hidden><td colspan="5">ไม่พบคำขอที่ตรงกับคำค้นหา</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
