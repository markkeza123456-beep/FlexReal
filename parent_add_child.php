<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function addChildResponse(string $message, bool $success = false, int $status = 200): never
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (strtolower((string) ($_SESSION['role'] ?? '')) !== 'parent' || empty($_SESSION['user_id'])) {
    addChildResponse('กรุณาเข้าสู่ระบบใหม่', false, 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') addChildResponse('ไม่รองรับคำขอนี้', false, 405);
$token = (string) ($_POST['csrf_token'] ?? '');
if ($token === '' || !hash_equals((string) ($_SESSION['parent_csrf_token'] ?? ''), $token)) {
    addChildResponse('เซสชันหมดอายุ กรุณาโหลดหน้าใหม่', false, 403);
}
$studentId = preg_replace('/\D+/', '', (string) ($_POST['student_id'] ?? ''));
$pin = trim((string) ($_POST['student_pin'] ?? ''));
if (!preg_match('/^[0-9]{13}$/', $studentId)) addChildResponse('กรุณากรอกรหัสบัตรประชาชนนักเรียน 13 หลัก', false, 422);
if (!preg_match('/^[0-9]{6}$/', $pin)) addChildResponse('กรุณากรอก PIN นักเรียน 6 หลัก', false, 422);

require_once __DIR__ . '/config/db.php';
try {
    $conn->exec('ALTER TABLE public.students ADD COLUMN IF NOT EXISTS pin_hash TEXT');
    $conn->beginTransaction();
    $stmt = $conn->prepare('SELECT pin_hash, parent_user_id FROM public.students WHERE user_id = :id FOR UPDATE');
    $stmt->execute([':id' => $studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        $conn->rollBack();
        addChildResponse('ไม่พบนักเรียนจากเลขบัตรประชาชนนี้', false, 404);
    }
    if (empty($student['pin_hash']) || !password_verify($pin, (string) $student['pin_hash'])) {
        $conn->rollBack();
        addChildResponse('PIN นักเรียนไม่ถูกต้อง หรือยังไม่ได้ตั้ง PIN', false, 422);
    }
    if (!empty($student['parent_user_id']) && (string) $student['parent_user_id'] !== (string) $_SESSION['user_id']) {
        $conn->rollBack();
        addChildResponse('นักเรียนคนนี้เชื่อมโยงกับผู้ปกครองบัญชีอื่นแล้ว', false, 409);
    }
    $conn->prepare('UPDATE public.students SET parent_user_id = :parent_id, updated_at = NOW() WHERE user_id = :student_id')
        ->execute([':parent_id' => (string) $_SESSION['user_id'], ':student_id' => $studentId]);
    $conn->commit();
    addChildResponse('เพิ่มนักเรียนในบัญชีสำเร็จ', true);
} catch (Throwable $error) {
    if ($conn->inTransaction()) $conn->rollBack();
    error_log('Parent add child failed: ' . $error->getMessage());
    addChildResponse('เพิ่มนักเรียนไม่สำเร็จ กรุณาลองใหม่', false, 500);
}
