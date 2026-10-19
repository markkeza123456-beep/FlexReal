<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

function transcriptUploadResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    transcriptUploadResponse(['status' => 'error', 'message' => 'รองรับเฉพาะคำขอ POST'], 405);
}
if (($_SESSION['role'] ?? '') !== 'student' || empty($_SESSION['user_id'])) {
    transcriptUploadResponse(['status' => 'unauthorized', 'message' => 'กรุณาเข้าสู่ระบบนักเรียน'], 401);
}

$studentId = (string) $_SESSION['user_id'];
$maxUploadBytes = 2 * 1024 * 1024;
$tooLargeMessage = 'ไฟล์ใหญ่เกินไป กรุณาใช้อัปโหลดไฟล์ขนาดไม่เกิน 2MB';
$postLimit = trim((string) ini_get('post_max_size'));
if (preg_match('/^(\d+(?:\.\d+)?)\s*([KMG])?$/i', $postLimit, $limitParts)) {
    $power = ['' => 0, 'K' => 1, 'M' => 2, 'G' => 3][strtoupper($limitParts[2] ?? '')] ?? 0;
    $postLimitBytes = (int) ((float) $limitParts[1] * (1024 ** $power));
    if ($postLimitBytes > 0 && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $postLimitBytes && !isset($_POST['csrf_token'])) {
        transcriptUploadResponse(['status' => 'error', 'message' => $tooLargeMessage], 413);
    }
}

$expectedToken = (string) ($_SESSION['transcript_csrf'] ?? '');
if ($expectedToken === '' || !hash_equals($expectedToken, (string) ($_POST['csrf_token'] ?? ''))) {
    transcriptUploadResponse(['status' => 'error', 'message' => 'แบบฟอร์มหมดอายุ กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง'], 419);
}
session_write_close();

$allowedMimes = [
    'application/pdf' => 'pdf',
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];

$file = $_FILES['transcript'] ?? null;
if (!is_array($file)) transcriptUploadResponse(['status' => 'error', 'message' => 'กรุณาเลือกไฟล์ทรานสคริปต์'], 400);
$uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
if ($uploadError !== UPLOAD_ERR_OK) {
    $message = match ($uploadError) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => $tooLargeMessage,
        UPLOAD_ERR_NO_FILE => 'กรุณาเลือกไฟล์ทรานสคริปต์',
        UPLOAD_ERR_PARTIAL => 'อัปโหลดไฟล์ไม่ครบ กรุณาลองใหม่',
        UPLOAD_ERR_NO_TMP_DIR => 'เซิร์ฟเวอร์ไม่มีโฟลเดอร์ชั่วคราวสำหรับรับไฟล์',
        UPLOAD_ERR_CANT_WRITE => 'เซิร์ฟเวอร์เขียนไฟล์ชั่วคราวไม่ได้',
        UPLOAD_ERR_EXTENSION => 'PHP Extension หยุดการอัปโหลดไฟล์',
        default => 'ไม่สามารถรับไฟล์ได้ กรุณาลองใหม่',
    };
    transcriptUploadResponse(['status' => 'error', 'message' => $message], 400);
}
if ((int) ($file['size'] ?? 0) < 1 || (int) $file['size'] > $maxUploadBytes) {
    transcriptUploadResponse(['status' => 'error', 'message' => $tooLargeMessage], 413);
}
if (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
    transcriptUploadResponse(['status' => 'error', 'message' => 'ไฟล์อัปโหลดไม่ถูกต้อง กรุณาลองใหม่'], 400);
}

$mimeType = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
if (!is_string($mimeType) || !isset($allowedMimes[$mimeType])) {
    transcriptUploadResponse(['status' => 'error', 'message' => 'รองรับเฉพาะไฟล์ PDF, JPG, PNG หรือ WEBP'], 415);
}
$binaryData = file_get_contents((string) $file['tmp_name']);
if ($binaryData === false || $binaryData === '') {
    transcriptUploadResponse(['status' => 'error', 'message' => 'อ่านไฟล์ที่อัปโหลดไม่สำเร็จ'], 400);
}
$dataUri = 'data:' . $mimeType . ';base64,' . base64_encode($binaryData);
unset($binaryData);
$originalName = mb_substr(basename(trim((string) ($file['name'] ?? 'transcript'))), 0, 255, 'UTF-8');
if ($originalName === '') $originalName = 'transcript.' . $allowedMimes[$mimeType];

try {
    require_once dirname(__DIR__) . '/config/db.php';
    $insert = $conn->prepare(
        'INSERT INTO public.transcript_requests
            (student_id, file_path, document_url, original_file_name, mime_type, file_size)
         VALUES (:student_id, :file_path, :document_url, :original_file_name, :mime_type, :file_size)'
    );
    $insert->execute([
        ':student_id' => $studentId,
        ':file_path' => $dataUri,
        ':document_url' => $dataUri,
        ':original_file_name' => $originalName,
        ':mime_type' => $mimeType,
        ':file_size' => (int) $file['size'],
    ]);
    transcriptUploadResponse(['status' => 'success', 'message' => 'ส่งทรานสคริปต์เรียบร้อยแล้ว กรุณารอผู้ตรวจสอบพิจารณา']);
} catch (Throwable $error) {
    error_log('Transcript upload failed: ' . $error->getMessage());
    transcriptUploadResponse(['status' => 'error', 'message' => 'บันทึกคำขอไม่สำเร็จ กรุณาลองใหม่'], 500);
}
