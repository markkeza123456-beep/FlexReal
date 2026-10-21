<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'คำขอไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (empty($_SESSION['user_id']) || strtolower((string) ($_SESSION['role'] ?? '')) !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'กรุณาล็อกอินใหม่'], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(403);
echo json_encode(['success' => false, 'message' => 'แก้ไขรูปโปรไฟล์ได้เฉพาะเจ้าหน้าที่'], JSON_UNESCAPED_UNICODE);
