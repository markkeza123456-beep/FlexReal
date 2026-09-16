<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'logged_in' => false,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$role = strtolower(trim((string) ($_SESSION['role'] ?? '')));
$userId = trim((string) ($_SESSION['user_id'] ?? ''));
$name = trim((string) ($_SESSION['name'] ?? 'ผู้ใช้งาน'));
if ($name === '') {
    $name = 'ผู้ใช้งาน';
}

$firstChar = mb_substr($name, 0, 1, 'UTF-8');
if ($firstChar === '') {
    $firstChar = 'U';
}

$dashboardUrlMap = [
    'student' => 'student_dashboard.php',
    'teacher' => 'teacherdash.php',
    'staff' => 'staffdash.php',
    'parent' => 'parent_dashboard.php',
];

$payload = [
    'logged_in' => true,
    'name' => $name,
    'role' => $role,
    'user_id' => $userId,
    'avatar_text' => $firstChar,
    'dashboard_url' => $dashboardUrlMap[$role] ?? 'web.html',
];

echo json_encode($payload, JSON_UNESCAPED_UNICODE);
?>
