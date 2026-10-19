<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function transferResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    transferResponse(['status' => 'error', 'message' => 'รองรับเฉพาะคำขอ POST'], 405);
}

if (($_SESSION['role'] ?? '') !== 'student' || empty($_SESSION['user_id'])) {
    transferResponse(['status' => 'unauthorized', 'message' => 'กรุณาเข้าสู่ระบบนักเรียนก่อนส่งคำขอ'], 401);
}

// ปิด session หลังอ่านตัวตนแล้ว; อย่ารับ user_id จาก client เพราะระบุตัวตนจาก session เท่านั้น
$userId = trim((string) $_SESSION['user_id']);
session_write_close();

$courseId = trim((string) ($_POST['course_id'] ?? ''));
$documentUrl = trim((string) ($_POST['document_url'] ?? ''));

if ($courseId === '' || $documentUrl === '') {
    transferResponse(['status' => 'error', 'message' => 'กรุณาระบุรายวิชาและลิงก์เอกสาร'], 400);
}

if (filter_var($documentUrl, FILTER_VALIDATE_URL) === false || !in_array(strtolower((string) parse_url($documentUrl, PHP_URL_SCHEME)), ['http', 'https'], true)) {
    transferResponse(['status' => 'error', 'message' => 'ลิงก์เอกสารไม่ถูกต้อง'], 400);
}

try {
    require_once __DIR__ . '/db_connect.php';

    // Check that this course exists before creating a transfer request.
    $courseStmt = $conn->prepare('SELECT 1 FROM public.courses WHERE course_id = :course_id');
    $courseStmt->execute([':course_id' => $courseId]);
    if (!$courseStmt->fetchColumn()) {
        transferResponse(['status' => 'error', 'message' => 'ไม่พบรายวิชาที่ระบุ'], 404);
    }

    $conn->beginTransaction();

    // ยืนยัน role จากฐานข้อมูลและล็อกแถว เพื่อ serialize คำขอพร้อมกันของนักศึกษาคนเดียวกัน
    $studentStmt = $conn->prepare("SELECT user_id FROM public.users WHERE user_id = :user_id AND role = 'student' FOR UPDATE");
    $studentStmt->execute([':user_id' => $userId]);
    if (!$studentStmt->fetchColumn()) {
        $conn->rollBack();
        transferResponse(['status' => 'unauthorized', 'message' => 'ไม่พบบัญชีนักเรียน'], 401);
    }

    $duplicateStmt = $conn->prepare('SELECT 1 FROM public.credit_transfers WHERE user_id = :user_id AND course_id = :course_id LIMIT 1');
    $duplicateStmt->execute([':user_id' => $userId, ':course_id' => $courseId]);
    if ($duplicateStmt->fetchColumn()) {
        $conn->rollBack();
        transferResponse(['status' => 'error', 'message' => 'คุณได้ส่งคำขอเทียบโอนรายวิชานี้แล้ว'], 409);
    }

    $insertStmt = $conn->prepare(
        'INSERT INTO public.credit_transfers (user_id, course_id, document_url) '
        . 'VALUES (:user_id, :course_id, :document_url) RETURNING id'
    );
    $insertStmt->execute([
        ':user_id' => $userId,
        ':course_id' => $courseId,
        ':document_url' => $documentUrl,
    ]);
    $transferId = $insertStmt->fetchColumn();

    $conn->commit();
    transferResponse([
        'status' => 'success',
        'message' => 'ส่งคำขอเทียบโอนเรียบร้อยแล้ว',
        'transfer_id' => $transferId,
    ], 201);
} catch (PDOException $e) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }

    // Also handle a unique constraint added on (user_id, course_id).
    if ($e->getCode() === '23505') {
        transferResponse(['status' => 'error', 'message' => 'คุณได้ส่งคำขอเทียบโอนรายวิชานี้แล้ว'], 409);
    }

    error_log('Credit transfer submission failed: ' . $e->getMessage());
    transferResponse(['status' => 'error', 'message' => 'ไม่สามารถบันทึกคำขอได้ กรุณาลองใหม่ภายหลัง'], 500);
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Credit transfer submission failed: ' . $e->getMessage());
    transferResponse(['status' => 'error', 'message' => 'ไม่สามารถบันทึกคำขอได้ กรุณาลองใหม่ภายหลัง'], 500);
}
