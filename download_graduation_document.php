<?php
declare(strict_types=1);

session_start();
$studentId = (string) ($_SESSION['user_id'] ?? '');
$role = strtolower(trim((string) ($_SESSION['role'] ?? '')));
session_write_close();
if ($studentId === '' || $role !== 'student') {
    http_response_code(401);
    exit('กรุณาเข้าสู่ระบบนักเรียน');
}
$requestId = filter_var($_GET['request_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$requestId) {
    http_response_code(400);
    exit('รหัสคำขอไม่ถูกต้อง');
}

try {
    require_once __DIR__ . '/config/db.php';
    require_once __DIR__ . '/transcript_storage.php';
    $stmt = $conn->prepare(
        "SELECT request_id, document_bucket, document_object_path
         FROM public.graduation_requests
         WHERE request_id = :request_id AND student_id = :student_id AND status = 'approved'"
    );
    $stmt->execute([':request_id' => $requestId, ':student_id' => $studentId]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$document || empty($document['document_bucket']) || empty($document['document_object_path'])) {
        http_response_code(404);
        exit('ยังไม่มีเอกสารที่อนุมัติแล้ว');
    }
    $response = transcriptStorageRequest('GET', (string) $document['document_bucket'], (string) $document['document_object_path']);
    if ($response['status'] !== 200) {
        http_response_code($response['status'] === 404 ? 404 : 502);
        exit($response['status'] === 404 ? 'ไม่พบไฟล์เอกสาร' : 'ไม่สามารถดาวน์โหลดเอกสารได้');
    }
    header_remove('Content-Type');
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="credit-bank-transcript-' . (int) $requestId . '.pdf"');
    header('Content-Length: ' . strlen($response['body']));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    echo $response['body'];
} catch (Throwable $e) {
    error_log('Graduation document download failed: ' . $e->getMessage());
    http_response_code(502);
    exit('เกิดข้อผิดพลาดในการดาวน์โหลดเอกสาร');
}
