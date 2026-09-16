<?php
declare(strict_types=1);

$subjectId = strtoupper(trim((string)($_GET['id'] ?? '')));
if (!preg_match('/^[A-Z0-9_-]+$/', $subjectId)) {
    http_response_code(404);
    exit;
}

$baseDir = __DIR__ . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'subjects' . DIRECTORY_SEPARATOR . $subjectId;
if (!is_dir($baseDir)) {
    http_response_code(404);
    exit;
}

$allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
$files = @scandir($baseDir) ?: [];
$picked = null;

foreach ($files as $file) {
    if ($file === '.' || $file === '..') continue;
    $path = $baseDir . DIRECTORY_SEPARATOR . $file;
    if (!is_file($path)) continue;
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (in_array($ext, $allowedExt, true)) {
        $picked = $path;
        break;
    }
}

if ($picked === null) {
    http_response_code(404);
    exit;
}

$mime = mime_content_type($picked) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
readfile($picked);
exit;

