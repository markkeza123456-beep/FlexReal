<?php
/**
 * parent_login_api.php
 * รับ POST: id_card + password → ตรวจสอบกับตาราง parents → สร้าง session
 *
 * เรียกจาก login.js เมื่อ role = 'parent'
 * POST body: { "id_card": "...", "password": "..." }
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

define('SB_URL', 'https://gwunrmptlmfpvidrxwdf.supabase.co');
define('SB_KEY', 'YOUR_SUPABASE_ANON_KEY');   // ← ใส่ anon key ของคุณ

/* ── รับ input ── */
$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$idCard   = trim($body['id_card']  ?? '');
$password = trim($body['password'] ?? '');

if (!$idCard || !$password) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกข้อมูลให้ครบ']);
    exit;
}

/* ── ค้นหาผู้ปกครองจาก parents_id (รหัสบัตร) ── */
$url = SB_URL . '/rest/v1/parents?' . http_build_query([
    'parents_id' => 'eq.' . $idCard,
    'select'     => 'parents_id,parents_name,email,tel,pin,password',
    'limit'      => 1,
]);

$ctx = stream_context_create(['http' => [
    'method'  => 'GET',
    'header'  => "apikey: " . SB_KEY . "\r\nAuthorization: Bearer " . SB_KEY . "\r\nAccept: application/json",
    'timeout' => 10,
]]);

$raw  = @file_get_contents($url, false, $ctx);
$rows = $raw ? json_decode($raw, true) : [];

if (empty($rows)) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบบัญชีผู้ปกครองนี้']);
    exit;
}

$parent = $rows[0];

/* ── ตรวจสอบรหัสผ่าน ──
   รองรับทั้ง password_hash() และ plain text (ปรับตามระบบที่ใช้อยู่)
   ถ้ารหัสผ่านใน DB เป็น bcrypt ให้ใช้ password_verify
   ถ้าเป็น plain text ให้ใช้ === เปรียบตรงๆ ไปก่อน */
$storedPwd = $parent['password'] ?? '';
$valid = false;

if (str_starts_with($storedPwd, '$2y$') || str_starts_with($storedPwd, '$2b$')) {
    // bcrypt hash
    $valid = password_verify($password, $storedPwd);
} else {
    // plain text (ควรเปลี่ยนเป็น hash ในอนาคต)
    $valid = ($password === $storedPwd);
}

if (!$valid) {
    echo json_encode(['status' => 'error', 'message' => 'รหัสผ่านไม่ถูกต้อง']);
    exit;
}

/* ── สร้าง session ── */
session_regenerate_id(true);
$_SESSION['parent_id']   = $parent['parents_id'];
$_SESSION['parent_name'] = $parent['parents_name'];
$_SESSION['role']        = 'parent';

echo json_encode([
    'status'   => 'success',
    'redirect' => 'parent_dashboard.php',
    'name'     => $parent['parents_name'],
]);