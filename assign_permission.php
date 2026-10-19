<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function permissionResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    permissionResponse(['status' => 'error', 'message' => 'รองรับเฉพาะคำขอ POST'], 405);
}

if (($_SESSION['role'] ?? '') !== 'staff' || empty($_SESSION['user_id'])) {
    permissionResponse(['status' => 'unauthorized', 'message' => 'เฉพาะเจ้าหน้าที่เท่านั้นที่ให้สิทธิ์ได้'], 403);
}
$staffId = (string) $_SESSION['user_id'];
session_write_close();

$teacherId = trim((string) ($_POST['instructor_id'] ?? $_POST['teacher_id'] ?? ''));
if ($teacherId === '') {
    permissionResponse(['status' => 'error', 'message' => 'กรุณาระบุรหัสอาจารย์'], 400);
}

try {
    require_once __DIR__ . '/db_connect.php';
    $stmt = $conn->prepare(
        "INSERT INTO public.transfer_permissions (instructor_id, granted_by)
         SELECT teacher.user_id, staff.user_id
         FROM public.users AS teacher
         JOIN public.users AS staff ON staff.user_id = :staff_id
         WHERE teacher.user_id = :teacher_id
           AND teacher.role IN ('teacher', 'instructor')
           AND staff.role = 'staff'
         RETURNING permission_id"
    );
    $stmt->execute([':staff_id' => $staffId, ':teacher_id' => $teacherId]);
    $permissionId = $stmt->fetchColumn();

    if ($permissionId === false) {
        permissionResponse(['status' => 'error', 'message' => 'ไม่พบอาจารย์ หรืออาจารย์มีสิทธิ์ใช้งานอยู่แล้ว'], 409);
    }

    permissionResponse([
        'status' => 'success',
        'message' => 'ให้สิทธิ์อาจารย์เรียบร้อยแล้ว',
        'permission_id' => $permissionId,
    ], 201);
} catch (PDOException $e) {
    if ($e->getCode() === '23505') {
        permissionResponse(['status' => 'error', 'message' => 'อาจารย์ท่านนี้มีสิทธิ์ที่ใช้งานอยู่แล้ว'], 409);
    }
    error_log('Transfer permission assignment failed: ' . $e->getMessage());
    permissionResponse(['status' => 'error', 'message' => 'ไม่สามารถบันทึกสิทธิ์ได้ กรุณาลองใหม่ภายหลัง'], 500);
} catch (Throwable $e) {
    error_log('Transfer permission assignment failed: ' . $e->getMessage());
    permissionResponse(['status' => 'error', 'message' => 'ไม่สามารถบันทึกสิทธิ์ได้ กรุณาลองใหม่ภายหลัง'], 500);
}
