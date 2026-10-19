<?php
session_start();
header('Cache-Control: no-store, private');

$requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($requestMethod, ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    exit('รองรับเฉพาะการเปิดดูไฟล์');
}

$role = strtolower(trim((string) ($_SESSION['role'] ?? '')));
$userId = (string) ($_SESSION['user_id'] ?? '');
if ($userId === '' || !in_array($role, ['student', 'teacher', 'staff'], true)) {
    http_response_code(401);
    exit('กรุณาเข้าสู่ระบบ');
}

try {
    require_once __DIR__ . '/db_connect.php';
    $requestId = filter_var($_GET['request_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$requestId) {
        http_response_code(400);
        exit('รหัสคำขอไม่ถูกต้อง');
    }
    $requestStmt = $conn->prepare(
        'SELECT student_id, file_path, storage_bucket, storage_object_path, original_file_name, mime_type, file_size
         FROM public.transcript_requests WHERE request_id = :request_id'
    );
    $requestStmt->execute([':request_id' => $requestId]);
    $request = $requestStmt->fetch(PDO::FETCH_ASSOC);
    if (!$request) {
        http_response_code(404);
        exit('ไม่พบไฟล์ทรานสคริปต์');
    }

    $authorized = false;
    if ($role === 'student') {
        $authorized = (string) $request['student_id'] === $userId;
    } elseif ($role === 'staff') {
        $staffStmt = $conn->prepare("SELECT 1 FROM public.users WHERE user_id = :user_id AND role = 'staff'");
        $staffStmt->execute([':user_id' => $userId]);
        $authorized = (bool) $staffStmt->fetchColumn();
    } else {
        $teacherStmt = $conn->prepare(
            "SELECT EXISTS (
                SELECT 1 FROM public.transfer_permissions p
                JOIN public.users u ON u.user_id = p.instructor_id
                WHERE p.instructor_id = :user_id AND p.is_active = TRUE AND u.role = 'teacher'
            )"
        );
        $teacherStmt->execute([':user_id' => $userId]);
        $authorized = (bool) $teacherStmt->fetchColumn();
    }
    if (!$authorized) {
        http_response_code(403);
        exit('ไม่มีสิทธิ์เปิดไฟล์นี้');
    }

    $mimeType = (string) $request['mime_type'];
    if (!in_array($mimeType, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true)) {
        throw new RuntimeException('Unsupported transcript MIME type');
    }
    $filename = rawurlencode((string) $request['original_file_name']);
    header('Content-Type: ' . $mimeType);
    header("Content-Disposition: inline; filename*=UTF-8''{$filename}");
    header('X-Content-Type-Options: nosniff');

    if (!empty($request['storage_bucket']) && !empty($request['storage_object_path'])) {
        require_once __DIR__ . '/transcript_storage.php';
        $storageResponse = transcriptStorageRequest($requestMethod, (string) $request['storage_bucket'], (string) $request['storage_object_path']);
        if ($storageResponse['status'] === 404) {
            http_response_code(404);
            exit('ไม่พบไฟล์ทรานสคริปต์ใน Storage');
        }
        if ($storageResponse['status'] < 200 || $storageResponse['status'] >= 300) {
            error_log('Transcript Storage delivery returned HTTP ' . $storageResponse['status']);
            http_response_code(502);
            exit('ไม่สามารถเปิดไฟล์ได้');
        }
        $contentLength = $storageResponse['headers']['content-length'] ?? (string) ($request['file_size'] ?? '');
        if ($contentLength !== '') header('Content-Length: ' . (string) (int) $contentLength);
        if ($requestMethod !== 'HEAD') echo $storageResponse['body'];
        exit;
    }

    $relativePath = (string) $request['file_path'];
    if (!str_starts_with($relativePath, 'uploads/transcripts/')) {
        throw new RuntimeException('Invalid transcript storage path');
    }
    $basePath = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'transcripts');
    $filePath = realpath(__DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
    if ($basePath === false || $filePath === false || !str_starts_with($filePath, $basePath . DIRECTORY_SEPARATOR) || !is_file($filePath)) {
        http_response_code(404);
        exit('ไม่พบไฟล์ทรานสคริปต์');
    }

    // Legacy local uploads are served from the old transcript directory.
    header('Content-Length: ' . (string) filesize($filePath));
    if ($requestMethod !== 'HEAD') {
        readfile($filePath);
    }
} catch (Throwable $e) {
    error_log('Transcript file delivery failed: ' . $e->getMessage());
    http_response_code(500);
    exit('ไม่สามารถเปิดไฟล์ได้');
}
