<?php


header('Content-Type: application/json; charset=utf-8');
session_start();

define('SB_URL', 'https://gwunrmptlmfpvidrxwdf.supabase.co');
define('SB_KEY', 'YOUR_SUPABASE_ANON_KEY');


$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$idCard   = trim($body['id_card']  ?? '');
$password = trim($body['password'] ?? '');

if (!$idCard || !$password) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกข้อมูลให้ครบ']);
    exit;
}


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


$storedPwd = $parent['password'] ?? '';
$valid = false;

if (str_starts_with($storedPwd, '$2y$') || str_starts_with($storedPwd, '$2b$')) {

    $valid = password_verify($password, $storedPwd);
} else {

    $valid = ($password === $storedPwd);
}

if (!$valid) {
    echo json_encode(['status' => 'error', 'message' => 'รหัสผ่านไม่ถูกต้อง']);
    exit;
}


session_regenerate_id(true);
$_SESSION['parent_id']   = $parent['parents_id'];
$_SESSION['parent_name'] = $parent['parents_name'];
$_SESSION['role']        = 'parent';

echo json_encode([
    'status'   => 'success',
    'redirect' => 'parent_dashboard.php',
    'name'     => $parent['parents_name'],
]);