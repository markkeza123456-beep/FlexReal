<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (($_SESSION['role'] ?? '') !== 'teacher' || empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit('กรุณาเข้าสู่ระบบอาจารย์');
}

require_once __DIR__ . '/config/db.php';

try {
    $permissionStmt = $conn->prepare(
        "SELECT EXISTS (
            SELECT 1
            FROM public.transfer_permissions p
            JOIN public.users u ON u.user_id = p.instructor_id
            WHERE p.instructor_id = :user_id
              AND p.is_active = TRUE
              AND u.role IN ('teacher', 'instructor')
        )"
    );
    $permissionStmt->execute([':user_id' => (string) $_SESSION['user_id']]);

    if (!$permissionStmt->fetchColumn()) {
        http_response_code(403);
        exit('คุณไม่มีสิทธิ์เข้าถึงหน้าระบบเทียบโอนหน่วยกิต หรือสิทธิ์ถูกยกเลิกแล้ว');
    }
} catch (Throwable $e) {
    error_log('Credit transfer authorization failed: ' . $e->getMessage());
    http_response_code(503);
    exit('ไม่สามารถตรวจสอบสิทธิ์ได้ กรุณาลองใหม่ภายหลัง');
}
