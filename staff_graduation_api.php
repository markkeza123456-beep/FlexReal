<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

function staffGraduationJson(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (strtolower(trim((string) ($_SESSION['role'] ?? ''))) !== 'staff' || empty($_SESSION['user_id'])) {
    staffGraduationJson(['status' => 'unauthorized', 'message' => 'กรุณาเข้าสู่ระบบเจ้าหน้าที่'], 401);
}
$staffId = (string) $_SESSION['user_id'];
$staffName = (string) ($_SESSION['name'] ?? 'เจ้าหน้าที่');
if (empty($_SESSION['graduation_review_csrf'])) {
    $_SESSION['graduation_review_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = (string) $_SESSION['graduation_review_csrf'];
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
session_write_close();

try {
    require_once __DIR__ . '/db_connect.php';
    require_once __DIR__ . '/graduation_document_lib.php';

    if ($method === 'GET') {
        $stmt = $conn->query(
            "SELECT gr.request_id, gr.student_id, gr.total_credits, gr.submitted_at,
                    s.full_name AS student_name
             FROM public.graduation_requests gr
             JOIN public.students s ON s.user_id = gr.student_id
             WHERE gr.status = 'pending'
             ORDER BY gr.submitted_at ASC"
        );
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($requests as &$request) {
            $request['courses'] = graduationTranscriptRows($conn, (string) $request['student_id']);
            $request['eligible'] = graduationTranscriptEligible($request['courses']);
        }
        unset($request);
        staffGraduationJson(['status' => 'success', 'csrf_token' => $csrfToken, 'requests' => $requests]);
    }

    if ($method !== 'POST') {
        header('Allow: GET, POST');
        staffGraduationJson(['status' => 'error', 'message' => 'รูปแบบคำขอไม่ถูกต้อง'], 405);
    }
    $providedToken = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrfToken, $providedToken)) {
        staffGraduationJson(['status' => 'error', 'message' => 'คำขอหมดอายุ กรุณาโหลดหน้าใหม่'], 419);
    }
    if (($_POST['action'] ?? '') !== 'approve') {
        staffGraduationJson(['status' => 'error', 'message' => 'ไม่รองรับคำสั่งนี้'], 400);
    }
    $requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$requestId) staffGraduationJson(['status' => 'error', 'message' => 'รหัสคำขอไม่ถูกต้อง'], 400);

    require_once __DIR__ . '/transcript_storage.php';
    $uploadedBucket = null;
    $uploadedPath = null;
    $conn->beginTransaction();
    $requestStmt = $conn->prepare(
        "SELECT gr.request_id, gr.student_id, s.full_name, s.email
         FROM public.graduation_requests gr
         JOIN public.students s ON s.user_id = gr.student_id
         WHERE gr.request_id = :request_id AND gr.status = 'pending'
         FOR UPDATE OF gr"
    );
    $requestStmt->execute([':request_id' => $requestId]);
    $request = $requestStmt->fetch(PDO::FETCH_ASSOC);
    if (!$request) {
        $conn->rollBack();
        staffGraduationJson(['status' => 'error', 'message' => 'ไม่พบคำขอที่รออนุมัติ'], 404);
    }
    $courses = graduationTranscriptRows($conn, (string) $request['student_id']);
    if (!graduationTranscriptEligible($courses)) {
        $conn->rollBack();
        staffGraduationJson(['status' => 'error', 'message' => 'นักเรียนมีรายวิชาที่ยังไม่ผ่าน ระบบจึงยังอนุมัติจบไม่ได้'], 409);
    }
    $credits = array_sum(array_map(static fn(array $row): float => (float) $row['credits'], $courses));
    $pdf = generateGraduationTranscriptPdf($request, $courses, $credits, $staffName);
    [$storageUrl, $serviceKey, $defaultBucket] = transcriptStorageConfig();
    unset($storageUrl, $serviceKey);
    $uploadedBucket = $defaultBucket;
    $uploadedPath = 'graduation/' . (int) $requestId . '/' . bin2hex(random_bytes(16)) . '.pdf';
    transcriptStorageUploadBytes($uploadedBucket, $uploadedPath, $pdf, 'application/pdf');

    $updateStmt = $conn->prepare(
        "UPDATE public.graduation_requests
         SET status = 'approved', approved_by = :approved_by, reviewed_at = NOW(),
             total_credits = :total_credits, document_bucket = :document_bucket,
             document_object_path = :document_object_path
         WHERE request_id = :request_id AND status = 'pending'"
    );
    $updateStmt->execute([
        ':approved_by' => $staffId,
        ':total_credits' => $credits,
        ':document_bucket' => $uploadedBucket,
        ':document_object_path' => $uploadedPath,
        ':request_id' => $requestId,
    ]);
    if ($updateStmt->rowCount() !== 1) throw new RuntimeException('คำขอเปลี่ยนสถานะระหว่างอนุมัติ');
    $conn->commit();
    staffGraduationJson(['status' => 'success', 'message' => 'อนุมัติการจบและออกใบสรุปผลการเรียนแล้ว']);
} catch (PDOException $e) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) $conn->rollBack();
    if (isset($uploadedBucket, $uploadedPath) && $uploadedBucket !== null && $uploadedPath !== null) {
        try { transcriptStorageDelete($uploadedBucket, $uploadedPath); } catch (Throwable $cleanupError) { error_log('Graduation PDF cleanup failed: ' . $cleanupError->getMessage()); }
    }
    error_log('Staff graduation database error: ' . $e->getMessage());
    staffGraduationJson(['status' => 'error', 'message' => 'ไม่สามารถบันทึกผลการอนุมัติได้'], 500);
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) $conn->rollBack();
    if (isset($uploadedBucket, $uploadedPath) && $uploadedBucket !== null && $uploadedPath !== null) {
        try { transcriptStorageDelete($uploadedBucket, $uploadedPath); } catch (Throwable $cleanupError) { error_log('Graduation PDF cleanup failed: ' . $cleanupError->getMessage()); }
    }
    error_log('Staff graduation approval failed: ' . $e->getMessage());
    staffGraduationJson(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดระหว่างอนุมัติ กรุณาตรวจสอบการตั้งค่า PDF และ Storage'], 500);
}
