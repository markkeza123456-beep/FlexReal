<?php require __DIR__ . '/includes/transcript_upload_data.php'; ?>
<?php
$pageTitle = 'ส่งทรานสคริปต์เพื่อขอเทียบโอน';
$pageScripts = ['assets/js/upload.js'];
require __DIR__ . '/includes/header.php';
?>
<main class="transcript-page">
    <a class="back-link" href="index.html">← กลับหน้าหลัก</a>
    <section class="transcript-card">
        <h1>ขอเทียบโอนผลการเรียน</h1>
        <p class="intro">อัปโหลดทรานสคริปต์ฉบับที่อ่านได้ชัดเจน รองรับ PDF หรือรูปภาพ ขนาดไม่เกิน 2 MB</p>
        <form id="transcriptUploadForm" action="api/upload_transcript.php" method="post" enctype="multipart/form-data" class="transcript-form">
            <input type="hidden" name="csrf_token" value="<?= hTranscript($csrfToken) ?>">
            <label for="transcriptFile">ไฟล์ทรานสคริปต์</label>
            <input id="transcriptFile" name="transcript" type="file" accept="application/pdf,image/jpeg,image/png,image/webp" required>
            <button type="submit">ส่งคำขอเทียบโอน</button>
        </form>
    </section>

    <section class="transcript-card history-card">
        <h2>คำขอของฉัน</h2>
        <?php if ($requests === null): ?>
            <p class="muted">โหลดประวัติคำขอไม่สำเร็จ</p>
        <?php elseif (!$requests): ?>
            <p class="muted">ยังไม่มีคำขอเทียบโอน</p>
        <?php else: ?>
            <div class="history-list">
                <?php foreach ($requests as $request): ?>
                    <article class="history-row">
                        <div><strong>คำขอ #<?= (int) $request['request_id'] ?></strong><span><?= hTranscript((string) $request['submitted_at']) ?></span></div>
                        <span class="request-status status-<?= hTranscript((string) $request['status']) ?>">
                            <?= $request['status'] === 'pending' ? 'รอตรวจสอบ' : ($request['status'] === 'approved' ? 'อนุมัติแล้ว' : 'ไม่อนุมัติ') ?>
                        </span>
                        <?php if (!empty($request['reviewer_note'])): ?><p class="review-note"><?= hTranscript((string) $request['reviewer_note']) ?></p><?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
