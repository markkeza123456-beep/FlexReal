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

function staffGraduationSafeError(Throwable $error): string
{
    global $env;
    $message = trim($error->getMessage());
    foreach (['SUPABASE_SERVICE_ROLE_KEY', 'SUPABASE_SERVICE_KEY'] as $keyName) {
        $serviceKey = (string) ($env[$keyName] ?? '');
        if ($serviceKey !== '') $message = str_replace($serviceKey, '[ซ่อน Service Role Key]', $message);
    }
    if ($message === '') $message = 'ไม่มีรายละเอียดจากระบบ';
    return function_exists('mb_substr') ? mb_substr($message, 0, 700, 'UTF-8') : substr($message, 0, 700);
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
    require_once __DIR__ . '/config/db.php';
    require_once __DIR__ . '/graduation_document_lib.php';

    if ($method === 'GET') {
        $stmt = $conn->query(
            "SELECT gr.request_id, gr.student_id, gr.total_credits, gr.submitted_at, gr.status,
                    gr.document_bucket, gr.document_object_path,
                    s.full_name AS student_name
             FROM public.graduation_requests gr
             JOIN public.students s ON s.user_id = gr.student_id
             WHERE gr.status = 'pending'
                OR (gr.status = 'approved' AND (gr.document_bucket IS NULL OR gr.document_object_path IS NULL))
             ORDER BY CASE WHEN gr.status = 'pending' THEN 0 ELSE 1 END, gr.submitted_at ASC"
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
    $action = (string) ($_POST['action'] ?? '');
    if (!in_array($action, ['approve', 'retry_pdf'], true)) {
        staffGraduationJson(['status' => 'error', 'message' => 'ไม่รองรับคำสั่งนี้'], 400);
    }
    $requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$requestId) staffGraduationJson(['status' => 'error', 'message' => 'รหัสคำขอไม่ถูกต้อง'], 400);

    $conn->beginTransaction();
    $requestStatus = $action === 'approve' ? 'pending' : 'approved';
    $retryGuard = $action === 'retry_pdf'
        ? 'AND (gr.document_bucket IS NULL OR gr.document_object_path IS NULL)'
        : '';
    $requestStmt = $conn->prepare(
        "SELECT gr.request_id, gr.student_id, s.full_name, s.email, gr.status,
                gr.document_bucket, gr.document_object_path
         FROM public.graduation_requests gr
         JOIN public.students s ON s.user_id = gr.student_id
         WHERE gr.request_id = :request_id AND gr.status = :status
           {$retryGuard}
         FOR UPDATE OF gr"
    );
    $requestStmt->execute([':request_id' => $requestId, ':status' => $requestStatus]);
    $request = $requestStmt->fetch(PDO::FETCH_ASSOC);
    if (!$request) {
        $conn->rollBack();
        staffGraduationJson(['status' => 'error', 'message' => 'ไม่พบคำขอที่รออนุมัติ'], 404);
    }
    $courses = graduationTranscriptRows($conn, (string) $request['student_id']);
    if ($action === 'approve' && !graduationTranscriptEligible($courses)) {
        $conn->rollBack();
        staffGraduationJson(['status' => 'error', 'message' => 'นักเรียนมีรายวิชาที่ยังไม่ผ่าน ระบบจึงยังอนุมัติจบไม่ได้'], 409);
    }
    $credits = array_sum(array_map(static fn(array $row): float => (float) $row['credits'], $courses));
    if ($action === 'approve') {
        $updateStmt = $conn->prepare(
            "UPDATE public.graduation_requests
             SET status = 'approved', approved_by = :approved_by, reviewed_at = NOW(),
                 total_credits = :total_credits, updated_at = NOW()
             WHERE request_id = :request_id AND status = 'pending'"
        );
        $updateStmt->execute([':approved_by' => $staffId, ':total_credits' => $credits, ':request_id' => $requestId]);
        if ($updateStmt->rowCount() !== 1) throw new RuntimeException('คำขอเปลี่ยนสถานะระหว่างอนุมัติ');
    }
    $conn->commit();

    $phase = 'สร้าง PDF';
    $uploadedBucket = null;
    $uploadedPath = null;
    try {
        require_once __DIR__ . '/transcript_storage.php';
        $pdf = generateGraduationTranscriptPdf($request, $courses, $credits, $staffName);

        $phase = 'ตรวจสอบการตั้งค่า Supabase Storage';
        [, , $defaultBucket] = transcriptStorageConfig();
        $phase = 'อัปโหลด PDF ไปยัง Supabase Storage';
        $uploadedBucket = $defaultBucket;
        $uploadedPath = 'graduation/' . (int) $requestId . '/' . bin2hex(random_bytes(16)) . '.pdf';
        transcriptStorageUploadBytes($uploadedBucket, $uploadedPath, $pdf, 'application/pdf');

        $phase = 'บันทึกตำแหน่ง PDF ในฐานข้อมูล';
        $documentStmt = $conn->prepare(
            "UPDATE public.graduation_requests
             SET document_bucket = :bucket, document_object_path = :object_path, updated_at = NOW()
             WHERE request_id = :request_id AND status = 'approved'
               AND (document_bucket IS NULL OR document_object_path IS NULL)"
        );
        $documentStmt->execute([':bucket' => $uploadedBucket, ':object_path' => $uploadedPath, ':request_id' => $requestId]);
        if ($documentStmt->rowCount() !== 1) {
            throw new RuntimeException('ไม่สามารถผูกไฟล์ที่อัปโหลดเข้ากับคำขอที่อนุมัติแล้วได้');
        }
        staffGraduationJson(['status' => 'success', 'message' => 'อนุมัติการจบและออกใบสรุปผลการเรียนแล้ว']);
    } catch (Throwable $e) {
        if ($uploadedBucket !== null && $uploadedPath !== null) {
            try { transcriptStorageDelete($uploadedBucket, $uploadedPath); }
            catch (Throwable $cleanupError) { error_log('Graduation PDF cleanup failed: ' . $cleanupError->getMessage()); }
        }
        $detail = staffGraduationSafeError($e);
        error_log('Graduation PDF phase failed [' . $phase . ']: ' . $e->getMessage());
        staffGraduationJson([
            'status' => 'approved_pdf_failed',
            'approved' => true,
            'phase' => $phase,
            'message' => 'บันทึกอนุมัติจบการศึกษาแล้ว แต่' . $phase . 'ไม่สำเร็จ: ' . $detail . ' สามารถกดลองสร้าง PDF อีกครั้งได้',
        ], 502);
    }
} catch (PDOException $e) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) $conn->rollBack();
    error_log('Staff graduation database error: ' . $e->getMessage());
    staffGraduationJson(['status' => 'error', 'message' => 'ไม่สามารถบันทึกผลการอนุมัติได้: ' . staffGraduationSafeError($e)], 500);
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) $conn->rollBack();
    error_log('Staff graduation approval failed: ' . $e->getMessage());
    staffGraduationJson(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดระหว่างบันทึกการอนุมัติ: ' . staffGraduationSafeError($e)], 500);
}
