<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function rejectTranscriptJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$role = strtolower(trim((string) ($_SESSION['role'] ?? '')));
$reviewerId = (string) ($_SESSION['user_id'] ?? '');
if ($reviewerId === '' || !in_array($role, ['staff', 'teacher'], true)) {
    rejectTranscriptJson(['status' => 'unauthorized', 'message' => 'กรุณาเข้าสู่ระบบผู้ตรวจสอบ'], 401);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    rejectTranscriptJson(['status' => 'error', 'message' => 'รองรับเฉพาะคำขอ POST'], 405);
}
$expectedToken = (string) ($_SESSION['transcript_review_csrf'] ?? '');
if ($expectedToken === '' || !hash_equals($expectedToken, (string) ($_POST['csrf_token'] ?? ''))) {
    rejectTranscriptJson(['status' => 'error', 'message' => 'คำขอหมดอายุ กรุณาโหลดหน้าใหม่'], 419);
}
session_write_close();

$requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$requestId) rejectTranscriptJson(['status' => 'error', 'message' => 'รหัสคำขอไม่ถูกต้อง'], 400);
$reviewerNote = mb_substr(trim((string) ($_POST['reviewer_note'] ?? '')), 0, 2000, 'UTF-8');

try {
    require_once __DIR__ . '/db_connect.php';
    if ($role === 'staff') {
        $roleCheck = $conn->prepare("SELECT 1 FROM public.users WHERE user_id = :user_id AND role = 'staff'");
        $roleCheck->execute([':user_id' => $reviewerId]);
    } else {
        $roleCheck = $conn->prepare(
            "SELECT EXISTS (
                SELECT 1 FROM public.transfer_permissions p
                JOIN public.users u ON u.user_id = p.instructor_id
                WHERE p.instructor_id = :user_id AND p.is_active = TRUE AND u.role = 'teacher'
            )"
        );
        $roleCheck->execute([':user_id' => $reviewerId]);
    }
    if (!$roleCheck->fetchColumn()) rejectTranscriptJson(['status' => 'unauthorized', 'message' => 'คุณไม่มีสิทธิ์ตรวจสอบคำขอเทียบโอน'], 403);

    $conn->beginTransaction();
    $requestStmt = $conn->prepare(
        'SELECT status, file_path, storage_bucket, storage_object_path
         FROM public.transcript_requests WHERE request_id = :request_id FOR UPDATE'
    );
    $requestStmt->execute([':request_id' => $requestId]);
    $request = $requestStmt->fetch(PDO::FETCH_ASSOC);
    if (!$request) {
        $conn->rollBack();
        rejectTranscriptJson(['status' => 'error', 'message' => 'ไม่พบคำขอเทียบโอน'], 404);
    }
    if ($request['status'] !== 'pending') {
        $conn->rollBack();
        rejectTranscriptJson(['status' => 'error', 'message' => 'คำขอนี้ได้รับการตรวจสอบไปแล้ว'], 409);
    }

    if (!empty($request['storage_bucket']) && !empty($request['storage_object_path'])) {
        require_once __DIR__ . '/transcript_storage.php';
        $delete = transcriptStorageRequest('DELETE', (string) $request['storage_bucket'], (string) $request['storage_object_path']);
        // Missing objects are already deleted; any other failure aborts the rejection.
        if ($delete['status'] !== 404 && ($delete['status'] < 200 || $delete['status'] >= 300)) {
            throw new RuntimeException('Storage delete rejected with HTTP ' . $delete['status']);
        }
    } else {
        $relativePath = (string) $request['file_path'];
        if (str_starts_with($relativePath, 'uploads/transcripts/')) {
            $basePath = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'transcripts');
            $absolutePath = realpath(__DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
            if ($basePath !== false && $absolutePath !== false && str_starts_with($absolutePath, $basePath . DIRECTORY_SEPARATOR) && is_file($absolutePath) && !unlink($absolutePath)) {
                throw new RuntimeException('Unable to remove legacy transcript file');
            }
        }
    }

    $update = $conn->prepare(
        "UPDATE public.transcript_requests
         SET status = 'rejected', reviewed_by = :reviewer_id, reviewed_at = now(), reviewer_note = :reviewer_note
         WHERE request_id = :request_id AND status = 'pending'"
    );
    $update->execute([
        ':reviewer_id' => $reviewerId,
        ':reviewer_note' => $reviewerNote !== '' ? $reviewerNote : null,
        ':request_id' => $requestId,
    ]);
    if ($update->rowCount() !== 1) throw new RuntimeException('Unable to reject transcript request');
    $conn->commit();
    rejectTranscriptJson(['status' => 'success', 'message' => 'ไม่อนุมัติคำขอและลบไฟล์ทรานสคริปต์แล้ว']);
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) $conn->rollBack();
    error_log('Transcript rejection failed: ' . $e->getMessage());
    rejectTranscriptJson(['status' => 'error', 'message' => 'ปฏิเสธคำขอหรือลบไฟล์ไม่สำเร็จ กรุณาลองใหม่'], 500);
}
