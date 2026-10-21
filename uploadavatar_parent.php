<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function parentAvatarResponse(bool $success, string $message, int $status): never
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    parentAvatarResponse(false, 'คำขอไม่ถูกต้อง', 405);
}
if (empty($_SESSION['user_id']) || strtolower((string) ($_SESSION['role'] ?? '')) !== 'parent') {
    parentAvatarResponse(false, 'กรุณาล็อกอินใหม่', 401);
}

parentAvatarResponse(false, 'แก้ไขรูปโปรไฟล์ได้เฉพาะเจ้าหน้าที่', 403);
