<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'student' || empty($_SESSION['user_id'])) {
    $_SESSION['after_login_return'] = 'upload_transcript.php';
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db_connect.php';
if (empty($_SESSION['transcript_csrf'])) {
    $_SESSION['transcript_csrf'] = bin2hex(random_bytes(32));
}

$studentId = (string) $_SESSION['user_id'];
$csrfToken = (string) $_SESSION['transcript_csrf'];
$message = '';
$messageType = 'error';
$maxUploadBytes = 10 * 1024 * 1024;
$allowedMimes = [
    'application/pdf' => 'pdf',
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!hash_equals($csrfToken, (string) ($_POST['csrf_token'] ?? ''))) {
        $message = 'แบบฟอร์มหมดอายุ กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง';
    } elseif (!isset($_FILES['transcript']) || !is_array($_FILES['transcript'])) {
        $message = 'กรุณาเลือกไฟล์ทรานสคริปต์';
    } else {
        $file = $_FILES['transcript'];
        $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_OK) {
            $message = match ($uploadError) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'ไฟล์มีขนาดเกินกำหนด (สูงสุด 10 MB)',
                UPLOAD_ERR_NO_FILE => 'กรุณาเลือกไฟล์ทรานสคริปต์',
                UPLOAD_ERR_PARTIAL => 'อัปโหลดไฟล์ไม่ครบ กรุณาลองใหม่',
                default => 'ไม่สามารถรับไฟล์ได้ กรุณาลองใหม่',
            };
        } elseif ((int) ($file['size'] ?? 0) < 1 || (int) $file['size'] > $maxUploadBytes) {
            $message = 'ไฟล์ต้องมีขนาดไม่เกิน 10 MB';
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file((string) $file['tmp_name']);
            if (!is_string($mimeType) || !isset($allowedMimes[$mimeType])) {
                $message = 'รองรับเฉพาะไฟล์ PDF, JPG, PNG หรือ WEBP';
            } elseif (!is_uploaded_file((string) $file['tmp_name'])) {
                $message = 'ไฟล์อัปโหลดไม่ถูกต้อง กรุณาลองใหม่';
            } else {
                $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'transcripts';
                $storedName = bin2hex(random_bytes(16)) . '.' . $allowedMimes[$mimeType];
                $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $storedName;
                $relativePath = 'uploads/transcripts/' . $storedName;
                $originalName = trim((string) ($file['name'] ?? 'transcript'));
                $originalName = mb_substr(basename($originalName), 0, 255, 'UTF-8');
                if ($originalName === '') $originalName = 'transcript.' . $allowedMimes[$mimeType];

                try {
                    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0750, true) && !is_dir($uploadDir)) {
                        throw new RuntimeException('ไม่สามารถเตรียมพื้นที่จัดเก็บไฟล์ได้');
                    }
                    if (!move_uploaded_file((string) $file['tmp_name'], $targetPath)) {
                        throw new RuntimeException('ไม่สามารถบันทึกไฟล์ได้');
                    }

                    $insert = $conn->prepare(
                        "INSERT INTO public.transcript_requests
                            (student_id, file_path, original_file_name, mime_type, file_size)
                         VALUES (:student_id, :file_path, :original_file_name, :mime_type, :file_size)"
                    );
                    $insert->execute([
                        ':student_id' => $studentId,
                        ':file_path' => $relativePath,
                        ':original_file_name' => $originalName,
                        ':mime_type' => $mimeType,
                        ':file_size' => (int) $file['size'],
                    ]);
                    $message = 'ส่งทรานสคริปต์เรียบร้อยแล้ว กรุณารอผู้ตรวจสอบพิจารณา';
                    $messageType = 'success';
                } catch (Throwable $e) {
                    if (is_file($targetPath)) @unlink($targetPath);
                    error_log('Transcript upload failed: ' . $e->getMessage());
                    $message = 'บันทึกคำขอไม่สำเร็จ กรุณาลองใหม่ภายหลัง';
                }
            }
        }
    }
}

try {
    $requestsStmt = $conn->prepare(
        'SELECT request_id, status, submitted_at, reviewed_at, reviewer_note
         FROM public.transcript_requests
         WHERE student_id = :student_id
         ORDER BY submitted_at DESC'
    );
    $requestsStmt->execute([':student_id' => $studentId]);
    $requests = $requestsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Transcript request history load failed: ' . $e->getMessage());
    $requests = null;
}

function hTranscript(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ส่งทรานสคริปต์เพื่อขอเทียบโอน</title>
    <link rel="stylesheet" href="theme.css">
    <link rel="stylesheet" href="transcript.css">
</head>
<body>
<main class="transcript-page">
    <a class="back-link" href="index.html">← กลับหน้าหลัก</a>
    <section class="transcript-card">
        <h1>ขอเทียบโอนผลการเรียน</h1>
        <p class="intro">อัปโหลดทรานสคริปต์ฉบับที่อ่านได้ชัดเจน รองรับ PDF หรือรูปภาพ ขนาดไม่เกิน 10 MB</p>
        <?php if ($message !== ''): ?>
            <p class="notice <?= $messageType === 'success' ? 'notice-success' : 'notice-error' ?>" role="status"><?= hTranscript($message) ?></p>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="transcript-form">
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
</body>
</html>
