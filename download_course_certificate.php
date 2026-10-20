<?php
declare(strict_types=1);
session_start();
$studentId = (string) ($_SESSION['user_id'] ?? '');
$role = strtolower(trim((string) ($_SESSION['role'] ?? '')));
session_write_close();
if ($studentId === '' || $role !== 'student') { http_response_code(401); exit('กรุณาเข้าสู่ระบบนักเรียน'); }
$certificateId = filter_var($_GET['certificate_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$certificateId) { http_response_code(400); exit('รหัสใบประกาศไม่ถูกต้อง'); }
try {
    require_once __DIR__ . '/config/db.php';
    $stmt = $conn->prepare("SELECT certificate_id, certificate_number, encode(document_pdf, 'base64') AS document_pdf FROM public.course_certificates WHERE certificate_id = :id AND student_id = :student_id");
    $stmt->execute([':id' => $certificateId, ':student_id' => $studentId]);
    $certificate = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$certificate || !$certificate['document_pdf']) { http_response_code(404); exit('ไม่พบใบประกาศ'); }
    $pdf = base64_decode((string) $certificate['document_pdf'], true);
    if ($pdf === false || !str_starts_with($pdf, '%PDF-')) { http_response_code(500); exit('ไฟล์ใบประกาศไม่ถูกต้อง'); }
    header_remove('Content-Type');
    header('Content-Type: application/pdf');
    $disposition = isset($_GET['download']) ? 'attachment' : 'inline';
    header('Content-Disposition: ' . $disposition . '; filename="' . preg_replace('/[^A-Za-z0-9-]/', '', (string) $certificate['certificate_number']) . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    echo $pdf;
} catch (Throwable $e) {
    error_log('Course certificate download failed: ' . $e->getMessage());
    http_response_code(502);
    exit('เกิดข้อผิดพลาดในการดาวน์โหลดใบประกาศ');
}
