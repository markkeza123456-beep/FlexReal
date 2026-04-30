<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || strtolower((string)($_SESSION['role'] ?? '')) !== 'student') {
    echo json_encode([
        'logged_in' => false,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$name = trim((string)($_SESSION['name'] ?? 'ผู้ใช้งาน'));
if ($name === '') {
    $name = 'ผู้ใช้งาน';
}

$firstChar = mb_substr($name, 0, 1, 'UTF-8');
if ($firstChar === '') {
    $firstChar = 'U';
}

echo json_encode([
    'logged_in' => true,
    'name' => $name,
    'role' => (string)($_SESSION['role'] ?? ''),
    'avatar_text' => $firstChar,
], JSON_UNESCAPED_UNICODE);
?>
