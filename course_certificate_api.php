<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

function courseCertificateJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$studentId = (string) ($_SESSION['user_id'] ?? '');
if ($studentId === '' || strtolower(trim((string) ($_SESSION['role'] ?? ''))) !== 'student') {
    courseCertificateJson(['status' => 'unauthorized', 'message' => 'กรุณาเข้าสู่ระบบนักเรียน'], 401);
}
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (empty($_SESSION['course_certificate_csrf'])) $_SESSION['course_certificate_csrf'] = bin2hex(random_bytes(32));
$csrfToken = (string) $_SESSION['course_certificate_csrf'];
if ($method === 'POST' && !hash_equals($csrfToken, (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
    courseCertificateJson(['status' => 'error', 'message' => 'คำขอหมดอายุ กรุณาโหลดหน้าใหม่'], 419);
}
session_write_close();
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    courseCertificateJson(['status' => 'error', 'message' => 'รูปแบบคำขอไม่ถูกต้อง'], 405);
}

try {
    require_once __DIR__ . '/config/db.php';
    require_once __DIR__ . '/course_certificate_lib.php';

    if ($method === 'GET') {
        $courses = courseCertificateProgress($conn, $studentId);
        $issuedStmt = $conn->prepare('SELECT course_id, certificate_id, certificate_number, issued_at FROM public.course_certificates WHERE student_id = :student_id');
        $issuedStmt->execute([':student_id' => $studentId]);
        $issued = [];
        foreach ($issuedStmt->fetchAll(PDO::FETCH_ASSOC) as $certificate) $issued[(string) $certificate['course_id']] = $certificate;
        foreach ($courses as &$course) $course['certificate'] = $issued[(string) $course['course_id']] ?? null;
        unset($course);
        courseCertificateJson(['status' => 'success', 'csrf_token' => $csrfToken, 'courses' => $courses]);
    }

    $body = json_decode((string) file_get_contents('php://input'), true);
    $courseId = filter_var($body['course_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$courseId) courseCertificateJson(['status' => 'error', 'message' => 'รหัสวิชาไม่ถูกต้อง'], 400);

    $conn->beginTransaction();
    $lockStmt = $conn->prepare('SELECT pg_advisory_xact_lock(hashtextextended(:lock_key, 0))');
    $lockStmt->execute([':lock_key' => $studentId . ':' . (int) $courseId]);
    $course = null;
    foreach (courseCertificateProgress($conn, $studentId) as $candidate) {
        if ((int) $candidate['course_id'] === (int) $courseId) { $course = $candidate; break; }
    }
    if (!$course) {
        $conn->rollBack();
        courseCertificateJson(['status' => 'error', 'message' => 'ไม่พบรายวิชาที่ลงทะเบียนในบัญชีนี้'], 404);
    }
    if (!$course['is_eligible']) {
        $conn->rollBack();
        courseCertificateJson(['status' => 'ineligible', 'message' => 'ยังเรียนไม่ครบหรือยังไม่ผ่านแบบทดสอบตามเกณฑ์ของรายวิชา', 'course' => $course], 409);
    }
    $studentStmt = $conn->prepare('SELECT full_name FROM public.students WHERE user_id = :student_id');
    $studentStmt->execute([':student_id' => $studentId]);
    $student = $studentStmt->fetch(PDO::FETCH_ASSOC);
    if (!$student) { $conn->rollBack(); courseCertificateJson(['status' => 'error', 'message' => 'ไม่พบข้อมูลผู้เรียน'], 404); }

    $existingStmt = $conn->prepare('SELECT certificate_id, certificate_number, issued_at, (document_pdf IS NOT NULL) AS has_document FROM public.course_certificates WHERE student_id = :student_id AND course_id = :course_id FOR UPDATE');
    $existingStmt->execute([':student_id' => $studentId, ':course_id' => $courseId]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
    if ($existing && in_array($existing['has_document'], [true, 1, '1', 't', 'true'], true)) {
        $conn->commit();
        courseCertificateJson(['status' => 'success', 'message' => 'มีใบประกาศของรายวิชานี้แล้ว', 'certificate' => $existing]);
    }

    $number = 'FLH-' . strtoupper(bin2hex(random_bytes(8)));
    $issuedAt = gmdate('Y-m-d H:i:s') . '+00';
    $pdf = generateCourseCertificatePdf($student, $course, $number, $issuedAt);
    $pdfBase64 = base64_encode($pdf);

    if ($existing) {
        $save = $conn->prepare("UPDATE public.course_certificates SET certificate_number = :number, issued_at = NOW(), document_pdf = decode(:pdf, 'base64') WHERE certificate_id = :id RETURNING certificate_id, certificate_number, issued_at");
        $save->execute([':number' => $number, ':pdf' => $pdfBase64, ':id' => $existing['certificate_id']]);
    } else {
        $save = $conn->prepare("INSERT INTO public.course_certificates (student_id, course_id, certificate_number, document_pdf) VALUES (:student_id, :course_id, :number, decode(:pdf, 'base64')) ON CONFLICT (student_id, course_id) DO NOTHING RETURNING certificate_id, certificate_number, issued_at");
        $save->execute([':student_id' => $studentId, ':course_id' => $courseId, ':number' => $number, ':pdf' => $pdfBase64]);
        if (!$save->rowCount()) {
            $existingStmt->execute([':student_id' => $studentId, ':course_id' => $courseId]);
            $certificate = $existingStmt->fetch(PDO::FETCH_ASSOC);
            $conn->commit();
            courseCertificateJson(['status' => 'success', 'message' => 'มีใบประกาศของรายวิชานี้แล้ว', 'certificate' => $certificate]);
        }
    }
    $certificate = $save->fetch(PDO::FETCH_ASSOC);
    $conn->commit();
    courseCertificateJson(['status' => 'success', 'message' => 'ออกใบประกาศเรียบร้อยแล้ว', 'certificate' => $certificate]);
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) $conn->rollBack();
    error_log('Course certificate error: ' . $e->getMessage());
    courseCertificateJson(['status' => 'error', 'message' => 'ดำเนินการใบประกาศไม่สำเร็จ กรุณาลองอีกครั้ง'], 500);
}
