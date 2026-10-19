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
$uploadSucceeded = false;
$uploadDebug = null;
$maxUploadBytes = 2 * 1024 * 1024;
$uploadTooLargeMessage = 'ไฟล์ใหญ่เกินไป กรุณาใช้อัปโหลดไฟล์ขนาดไม่เกิน 2MB';
$allowedMimes = [
    'application/pdf' => 'pdf',
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];

function transcriptIniBytes(string $setting): int
{
    $setting = trim($setting);
    if ($setting === '' || $setting === '0') return 0;
    if (!preg_match('/^(\d+(?:\.\d+)?)\s*([KMG])?$/i', $setting, $matches)) return 0;
    $power = match (strtoupper($matches[2] ?? '')) { 'K' => 1, 'M' => 2, 'G' => 3, default => 0 };
    return (int) ((float) $matches[1] * (1024 ** $power));
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $postLimit = transcriptIniBytes((string) ini_get('post_max_size'));
    $requestSize = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($postLimit > 0 && $requestSize > $postLimit && !isset($_POST['csrf_token'])) {
        $message = $uploadTooLargeMessage;
        $uploadDebug = ['content_length_bytes' => $requestSize, 'post_max_size' => (string) ini_get('post_max_size')];
    } elseif (!hash_equals($csrfToken, (string) ($_POST['csrf_token'] ?? ''))) {
        $message = 'แบบฟอร์มหมดอายุ กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง';
    } elseif (!isset($_FILES['transcript']) || !is_array($_FILES['transcript'])) {
        $message = 'กรุณาเลือกไฟล์ทรานสคริปต์';
    } else {
        $file = $_FILES['transcript'];
        $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_OK) {
            $message = match ($uploadError) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => $uploadTooLargeMessage,
                UPLOAD_ERR_NO_FILE => 'กรุณาเลือกไฟล์ทรานสคริปต์',
                UPLOAD_ERR_PARTIAL => 'อัปโหลดไฟล์ไม่ครบ กรุณาลองใหม่',
                UPLOAD_ERR_NO_TMP_DIR => 'เซิร์ฟเวอร์ไม่มีโฟลเดอร์ชั่วคราวสำหรับรับไฟล์ (upload_tmp_dir)',
                UPLOAD_ERR_CANT_WRITE => 'เซิร์ฟเวอร์เขียนไฟล์ลงโฟลเดอร์ชั่วคราวไม่ได้ ตรวจสอบพื้นที่ดิสก์และสิทธิ์เขียน',
                UPLOAD_ERR_EXTENSION => 'PHP Extension หยุดการอัปโหลดไฟล์',
                default => 'ไม่สามารถรับไฟล์ได้ กรุณาลองใหม่',
            };
            $uploadDebug = ['upload_error_code' => $uploadError];
        } elseif ((int) ($file['size'] ?? 0) < 1 || (int) $file['size'] > $maxUploadBytes) {
            $message = $uploadTooLargeMessage;
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file((string) $file['tmp_name']);
            if (!is_string($mimeType) || !isset($allowedMimes[$mimeType])) {
                $message = 'รองรับเฉพาะไฟล์ PDF, JPG, PNG หรือ WEBP';
            } elseif (!is_uploaded_file((string) $file['tmp_name'])) {
                $message = 'ไฟล์อัปโหลดไม่ถูกต้อง กรุณาลองใหม่';
            } else {
                $extension = $allowedMimes[$mimeType];
                $originalName = trim((string) ($file['name'] ?? 'transcript'));
                $originalName = mb_substr(basename($originalName), 0, 255, 'UTF-8');
                if ($originalName === '') $originalName = 'transcript.' . $extension;

                try {
                    $binaryData = file_get_contents((string) $file['tmp_name']);
                    if ($binaryData === false || $binaryData === '') {
                        throw new RuntimeException('อ่านข้อมูลไฟล์ที่อัปโหลดไม่สำเร็จ');
                    }
                    $dataUri = 'data:' . $mimeType . ';base64,' . base64_encode($binaryData);
                    unset($binaryData);

                    $insert = $conn->prepare(
                        "INSERT INTO public.transcript_requests
                            (student_id, file_path, document_url, original_file_name, mime_type, file_size)
                         VALUES (:student_id, :file_path, :document_url, :original_file_name, :mime_type, :file_size)"
                    );
                    $insert->execute([
                        ':student_id' => $studentId,
                        // Keep file_path populated for existing NOT NULL schema constraints.
                        ':file_path' => $dataUri,
                        ':document_url' => $dataUri,
                        ':original_file_name' => $originalName,
                        ':mime_type' => $mimeType,
                        ':file_size' => (int) $file['size'],
                    ]);
                    $message = 'ส่งทรานสคริปต์เรียบร้อยแล้ว กรุณารอผู้ตรวจสอบพิจารณา';
                    $messageType = 'success';
                    $uploadSucceeded = true;
                } catch (Throwable $e) {
                    error_log('Transcript upload failed: ' . $e->getMessage());
                    $message = $e->getMessage();
                }
            }
        }
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && (str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
        || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    echo json_encode([
        'status' => $uploadSucceeded ? 'success' : 'error',
        'message' => $message !== '' ? $message : 'ไม่สามารถรับไฟล์ได้ กรุณาลองใหม่',
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
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
        <p class="intro">อัปโหลดทรานสคริปต์ฉบับที่อ่านได้ชัดเจน รองรับ PDF หรือรูปภาพ ขนาดไม่เกิน 2 MB</p>
        <?php if ($message !== ''): ?>
            <p class="notice <?= $messageType === 'success' ? 'notice-success' : 'notice-error' ?>" role="status"><?= hTranscript($message) ?></p>
        <?php endif; ?>
        <form id="transcriptUploadForm" method="post" enctype="multipart/form-data" class="transcript-form">
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
<script>
document.getElementById('transcriptUploadForm')?.addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    const fileInput = form.querySelector('input[type="file"]');
    if (fileInput?.files?.[0]?.size > 2 * 1024 * 1024) {
        window.alert('ไฟล์ใหญ่เกินไป กรุณาใช้อัปโหลดไฟล์ขนาดไม่เกิน 2MB');
        fileInput.value = '';
        return;
    }
    const button = form.querySelector('button[type="submit"]');
    if (button) button.disabled = true;
    try {
        const response = await fetch(form.action || window.location.href, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const result = await response.json();
        if (!response.ok || result.status !== 'success') {
            const debug = result.debug ? '\nรายละเอียด: ' + JSON.stringify(result.debug) : '';
            window.alert((result.message || 'อัปโหลดไม่สำเร็จ') + debug);
            return;
        }
        window.alert(result.message || 'ส่งทรานสคริปต์เรียบร้อยแล้ว');
        window.location.reload();
    } catch (error) {
        window.alert('ส่งข้อมูลไม่สำเร็จ: ' + (error?.message || 'ตรวจสอบการเชื่อมต่อแล้วลองใหม่'));
    } finally {
        if (button) button.disabled = false;
    }
});
</script>
</body>
</html>
