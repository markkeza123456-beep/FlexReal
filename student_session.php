<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

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

$avatarUrl = '';
try {
    require_once __DIR__ . '/config/db.php';
    $profileTable = match ($role) {
        'student' => 'students',
        'teacher' => 'teachers',
        'staff' => 'staff',
        'parent' => 'parents',
        default => null,
    };
    if ($profileTable !== null) {
        $avatarStmt = $conn->prepare("SELECT avatar_url FROM public.{$profileTable} WHERE user_id = :user_id LIMIT 1");
        $avatarStmt->execute([':user_id' => $userId]);
        $avatarUrl = (string) ($avatarStmt->fetchColumn() ?: '');
    }
} catch (Throwable $error) {
    error_log('Session avatar lookup failed: ' . $error->getMessage());
}

$payload = [
    'logged_in' => true,
    'name' => $name,
    'role' => $role,
    'user_id' => $userId,
    'avatar_text' => $firstChar,
    'avatar_url' => $avatarUrl,
    'dashboard_url' => $dashboardUrlMap[$role] ?? 'index.html',
];

echo json_encode($payload, JSON_UNESCAPED_UNICODE);
?>
