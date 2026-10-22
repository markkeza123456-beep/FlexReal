<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
session_start();
header('Content-Type: application/json; charset=utf-8');

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'กรุณาล็อกอินใหม่'], JSON_UNESCAPED_UNICODE);
    exit;
}

function phpSizeToBytes(string $size): int
{
    $size = trim($size);
    if ($size === '') return 0;
    $unit = strtolower(substr($size, -1));
    $value = (float) $size;
    return match ($unit) {
        'g' => (int) round($value * 1024 * 1024 * 1024),
        'm' => (int) round($value * 1024 * 1024),
        'k' => (int) round($value * 1024),
        default => (int) round($value),
    };
}

$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
$postMaxBytes = phpSizeToBytes((string) ini_get('post_max_size'));
if ($postMaxBytes > 0 && $contentLength > $postMaxBytes) {
    http_response_code(413);
    $limitMegabytes = round($postMaxBytes / 1024 / 1024);
    echo json_encode(['success' => false, 'message' => "ไฟล์ที่ส่งมามีขนาดเกิน {$limitMegabytes} MB ที่เซิร์ฟเวอร์รองรับ"], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    require_once __DIR__ . '/config/db.php';
    require_once __DIR__ . '/learning_progress_lib.php';
} catch (Throwable $e) {
    error_log('Teacher API startup failed: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'เชื่อมต่อฐานข้อมูลไม่สำเร็จ กรุณาลองใหม่อีกครั้ง'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_POST['action'] ?? '';
$teacherId = (string) $_SESSION['user_id'];

function ensureLessonMediaColumns(PDO $conn): void
{

}

function sanitizePathSegment(string $value, string $fallback = 'unknown'): string
{
    $value = trim($value);
    if ($value === '') {
        return $fallback;
    }

    $value = preg_replace('/[^A-Za-z0-9._-]+/', '_', $value);
    $value = trim((string) $value, "._-");
    return $value !== '' ? $value : $fallback;
}

function generateLessonId(PDO $conn): string
{
    throw new LogicException('public.lessons generates lesson_id automatically');
}

function buildLessonMediaSegments(string $teacherId, string $subjectId, string $lessonId, string $mediaType): array
{
    return [
        'teachers',
        sanitizePathSegment($teacherId, 'unassigned'),
        'subjects',
        sanitizePathSegment($subjectId, 'subject'),
        'lessons',
        sanitizePathSegment($lessonId, 'lesson'),
        sanitizePathSegment($mediaType, 'files'),
    ];
}

function buildVideoMediaSegments(string $teacherId, string $subjectId): array
{
    return ['teachers', sanitizePathSegment($teacherId), 'subjects', sanitizePathSegment($subjectId), 'videos'];
}

function ensureEssaySubmissionTable(PDO $conn): void
{
    $conn->exec("CREATE TABLE IF NOT EXISTS public.essay_submissions (
        submission_id BIGSERIAL PRIMARY KEY,
        test_id INTEGER NOT NULL,
        student_id VARCHAR(50) NOT NULL,
        subjects_id VARCHAR(50) NOT NULL,
        lessons_id VARCHAR(50) NOT NULL,
        questions_id INTEGER NOT NULL,
        answer_text TEXT NOT NULL,
        review_status VARCHAR(20) NOT NULL DEFAULT 'pending',
        teacher_id VARCHAR(50),
        teacher_comment TEXT,
        reviewed_at TIMESTAMP,
        submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
}

function ensureCourseWorkTables(PDO $conn): void
{
    $conn->exec("CREATE TABLE IF NOT EXISTS public.course_assignments (
        assignment_id BIGSERIAL PRIMARY KEY, course_id bigint NOT NULL REFERENCES public.courses(course_id) ON DELETE CASCADE,
        teacher_id varchar(50) NOT NULL REFERENCES public.teachers(user_id), title varchar(255) NOT NULL, description text NOT NULL DEFAULT '',
        due_at timestamptz NOT NULL, max_score numeric(7,2) NOT NULL DEFAULT 10, created_at timestamptz NOT NULL DEFAULT now()
    )");
    $conn->exec("CREATE TABLE IF NOT EXISTS public.course_announcements (
        announcement_id BIGSERIAL PRIMARY KEY, course_id bigint NOT NULL REFERENCES public.courses(course_id) ON DELETE CASCADE,
        teacher_id varchar(50) NOT NULL REFERENCES public.teachers(user_id), title varchar(255) NOT NULL, body text NOT NULL DEFAULT '', created_at timestamptz NOT NULL DEFAULT now()
    )");
}

function uploadLessonFile(string $fieldName, array $segments, string $prefix, bool $required = false, ?array $allowedExtensions = null): array
{
    if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) {
        if ($required) throw new Exception('กรุณาเลือกไฟล์วิดีโอ');
        return ['path' => '', 'name' => ''];
    }

    $file = $_FILES[$fieldName];
    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode !== UPLOAD_ERR_OK) {
        if ($errorCode === UPLOAD_ERR_NO_FILE && !$required) return ['path' => '', 'name' => ''];
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'ไฟล์วิดีโอมีขนาดเกินค่าที่ PHP อนุญาต',
            UPLOAD_ERR_FORM_SIZE => 'ไฟล์วิดีโอมีขนาดเกินค่าที่แบบฟอร์มอนุญาต',
            UPLOAD_ERR_PARTIAL => 'อัปโหลดไฟล์ไม่สมบูรณ์ กรุณาลองใหม่',
            UPLOAD_ERR_NO_FILE => 'กรุณาเลือกไฟล์วิดีโอ',
            UPLOAD_ERR_NO_TMP_DIR => 'ไม่พบโฟลเดอร์ชั่วคราวสำหรับอัปโหลด',
            UPLOAD_ERR_CANT_WRITE => 'เซิร์ฟเวอร์ไม่สามารถบันทึกไฟล์วิดีโอได้',
            UPLOAD_ERR_EXTENSION => 'การตั้งค่า PHP หยุดการอัปโหลดไฟล์',
        ];
        throw new Exception($errors[$errorCode] ?? 'เกิดข้อผิดพลาดระหว่างอัปโหลดไฟล์วิดีโอ');
    }

    $temporaryPath = (string) ($file['tmp_name'] ?? '');
    if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
        throw new Exception('ไฟล์อัปโหลดชั่วคราวไม่ถูกต้อง กรุณาเลือกไฟล์ใหม่');
    }

    $relativeDir = 'uploads/' . implode('/', $segments);
    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('เซิร์ฟเวอร์สร้างโฟลเดอร์สำหรับเก็บวิดีโอไม่ได้');
    }
    if (!is_writable($uploadDir)) {
        throw new RuntimeException('ไม่มีสิทธิ์เขียนไฟล์ในโฟลเดอร์เก็บวิดีโอ');
    }

    $originalName = trim((string) ($file['name'] ?? ''));
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($allowedExtensions !== null && !in_array($extension, $allowedExtensions, true)) {
        throw new Exception($prefix === 'video' ? 'รองรับไฟล์วิดีโอ MP4 เท่านั้น' : 'เอกสารบทเรียนต้องเป็นไฟล์ PDF เท่านั้น');
    }
    if ($prefix === 'video' && (int) ($file['size'] ?? 0) <= 0) {
        throw new Exception('ไฟล์ที่เลือกว่างเปล่าหรืออ่านไม่ได้');
    }
    $uploadLimit = phpSizeToBytes((string) ini_get('upload_max_filesize'));
    if ($uploadLimit > 0 && (int) ($file['size'] ?? 0) > $uploadLimit) {
        throw new Exception('ไฟล์มีขนาดเกิน upload_max_filesize ของ PHP (' . ini_get('upload_max_filesize') . ')');
    }
    if ($prefix !== 'video' && (int) ($file['size'] ?? 0) <= 0) throw new Exception('ไฟล์เอกสารว่างเปล่าหรืออ่านไม่ได้');
    if ($prefix !== 'video') {
        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        if ($mimeType !== 'application/pdf') throw new Exception('ไฟล์เอกสารไม่ใช่ PDF ที่ถูกต้อง');
    }
    if ($prefix === 'video') {
        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        if (!in_array($mimeType, ['video/mp4', 'application/mp4', 'application/octet-stream'], true)) {
            throw new Exception('ไฟล์ที่เลือกไม่ใช่วิดีโอ MP4');
        }
    }
    $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($originalName));
    $safeName = $safeName !== '' ? $safeName : ($prefix . '_' . time());
    $targetName = $prefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '_' . $safeName;
    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $targetName;

    if (!move_uploaded_file($temporaryPath, $targetPath)) {
        throw new Exception('เซิร์ฟเวอร์ไม่สามารถย้ายไฟล์วิดีโอไปยังโฟลเดอร์จัดเก็บได้');
    }
    if (!is_file($targetPath) || filesize($targetPath) !== (int) $file['size']) {
        @unlink($targetPath);
        throw new RuntimeException('ตรวจสอบไฟล์วิดีโอหลังบันทึกไม่ผ่าน');
    }

    $localPath = $relativeDir . '/' . $targetName;
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($targetPath) ?: 'application/octet-stream';
    try {
        $sharedUrl = publishLessonMedia($localPath, $mime);
    } catch (Throwable $e) {
        @unlink($targetPath);
        throw $e;
    }
    @unlink($targetPath);
    return [
        'path' => $sharedUrl,
        'name' => $originalName !== '' ? $originalName : $targetName,
    ];
}

/** Publish teacher uploads to shared Supabase Storage so every app server can serve them. */
function publishLessonMedia(string $localPath, string $mimeType): string
{
    global $env, $supabaseUrl;
    static $bucketReady = false;
    $url = rtrim((string) ($env['SUPABASE_URL'] ?? $supabaseUrl ?? ''), '/');
    $key = trim((string) ($env['SUPABASE_SERVICE_ROLE_KEY'] ?? ''));
    $bucket = trim((string) ($env['SUPABASE_LESSON_BUCKET'] ?? 'lesson-media'));
    if ($url === '' || str_contains($url, 'your_project_ref')) throw new RuntimeException('ไม่พบ SUPABASE_URL ที่ใช้งานได้ในค่าตั้งค่าฝั่งเซิร์ฟเวอร์ กรุณาตรวจสอบ flexreal.env และ FLEXREAL_ENV_FILE');
    if ($key === '' || str_starts_with($key, 'replace_with_')) throw new RuntimeException('PHP อ่าน flexreal.env ได้ แต่ไม่พบ SUPABASE_SERVICE_ROLE_KEY ในค่าตั้งค่าที่มีผล กรุณาเพิ่ม Secret/Service Role Key จริงใน C:\\xampp\\config\\flexreal.env (หรือ FLEXREAL_ENV_FILE); ถ้าตั้งผ่านไฟล์จะมีผลในคำขอถัดไป ถ้าตั้งผ่าน Apache config ให้รีสตาร์ต Apache; ห้ามใส่คีย์ในหน้าเว็บ');
    // New Supabase secret keys (sb_secret_...) are API keys, not JWTs, so send
    // them only through the apikey header. Legacy service_role JWTs need both.
    $headers = ['apikey: ' . $key, 'Content-Type: application/json'];
    if (!str_starts_with($key, 'sb_secret_')) $headers[] = 'Authorization: Bearer ' . $key;
    $storageErrorDetail = static function ($body): string {
        if (!is_string($body) || $body === '') return '';
        $decoded = json_decode($body, true);
        $detail = is_array($decoded) ? ($decoded['message'] ?? $decoded['error'] ?? $decoded['msg'] ?? '') : '';
        if (!is_string($detail) || $detail === '') $detail = $body;
        $detail = trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $detail));
        return $detail !== '' ? ': ' . substr($detail, 0, 220) : '';
    };
    if (!$bucketReady) {
        $check = curl_init($url . '/storage/v1/bucket/' . rawurlencode($bucket));
        curl_setopt_array($check, [CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 20]);
        $checkBody = curl_exec($check); $checkStatus = (int) curl_getinfo($check, CURLINFO_RESPONSE_CODE); curl_close($check);
        $bucketMissing = $checkStatus === 404 || (is_string($checkBody) && stripos($checkBody, 'bucket not found') !== false);
        if ($bucketMissing) {
            $create = curl_init($url . '/storage/v1/bucket');
            curl_setopt_array($create, [CURLOPT_POST => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => json_encode(['id' => $bucket, 'name' => $bucket, 'public' => true]), CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 20]);
            $createBody = curl_exec($create); $createStatus = (int) curl_getinfo($create, CURLINFO_RESPONSE_CODE); curl_close($create);
            if ($createStatus < 200 || $createStatus >= 300) throw new RuntimeException('สร้างพื้นที่เก็บไฟล์บทเรียนไม่สำเร็จ (HTTP ' . $createStatus . ')' . $storageErrorDetail($createBody));
        } elseif ($checkStatus < 200 || $checkStatus >= 300) {
            throw new RuntimeException('ตรวจสอบพื้นที่เก็บไฟล์บทเรียนไม่สำเร็จ (HTTP ' . $checkStatus . ')' . $storageErrorDetail($checkBody));
        }
        $makePublic = curl_init($url . '/storage/v1/bucket/' . rawurlencode($bucket));
        curl_setopt_array($makePublic, [CURLOPT_CUSTOMREQUEST => 'PUT', CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => json_encode(['public' => true]), CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 20]);
        $publicBody = curl_exec($makePublic); $publicStatus = (int) curl_getinfo($makePublic, CURLINFO_RESPONSE_CODE); curl_close($makePublic);
        if ($publicStatus < 200 || $publicStatus >= 300) throw new RuntimeException('ตั้งค่าการอ่านไฟล์สำหรับผู้เรียนไม่สำเร็จ (HTTP ' . $publicStatus . ')' . $storageErrorDetail($publicBody));
        $bucketReady = true;
    }
    $objectPath = preg_replace('/^uploads\//', '', str_replace('\\', '/', $localPath));
    $body = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $localPath));
    if ($body === false) throw new RuntimeException('อ่านไฟล์อัปโหลดไม่สำเร็จ');
    $upload = curl_init($url . '/storage/v1/object/' . rawurlencode($bucket) . '/' . implode('/', array_map('rawurlencode', explode('/', $objectPath))));
    $uploadHeaders = ['apikey: ' . $key, 'Content-Type: ' . $mimeType, 'x-upsert: true'];
    if (!str_starts_with($key, 'sb_secret_')) $uploadHeaders[] = 'Authorization: Bearer ' . $key;
    curl_setopt_array($upload, [CURLOPT_POST => true, CURLOPT_HTTPHEADER => $uploadHeaders, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 300]);
    $uploadBody = curl_exec($upload); $uploadStatus = (int) curl_getinfo($upload, CURLINFO_RESPONSE_CODE); $uploadError = curl_error($upload); curl_close($upload);
    if ($uploadBody === false || $uploadStatus < 200 || $uploadStatus >= 300) throw new RuntimeException('บันทึกไฟล์ส่วนกลางไม่สำเร็จ' . ($uploadStatus ? ' (HTTP ' . $uploadStatus . ')' . $storageErrorDetail($uploadBody) : ': ' . $uploadError));
    return $url . '/storage/v1/object/public/' . rawurlencode($bucket) . '/' . implode('/', array_map('rawurlencode', explode('/', $objectPath)));
}

function shareLegacyLessonMedia(string $storedPath): string
{
    if (!str_starts_with($storedPath, 'uploads/')) return $storedPath;
    $relativePath = strtok($storedPath, '?');
    $root = realpath(__DIR__ . '/uploads');
    $localPath = realpath(__DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
    if ($root === false || $localPath === false || !str_starts_with($localPath, $root . DIRECTORY_SEPARATOR) || !is_file($localPath)) {
        throw new RuntimeException('ไม่พบไฟล์เดิม กรุณาอัปโหลดไฟล์นี้ใหม่');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($localPath) ?: 'application/octet-stream';
    return publishLessonMedia($relativePath, $mime);
}

function teacherOwnsSubject(PDO $conn, string $teacherId, string $subjectId): bool
{
    $stmt = $conn->prepare(
        'SELECT 1
         FROM public.course_teachers
         WHERE course_id = :subject_id
           AND teacher_id = :teacher_id
         LIMIT 1'
    );
    $stmt->execute([
        ':subject_id' => $subjectId,
        ':teacher_id' => $teacherId,
    ]);

    return (bool) $stmt->fetchColumn();
}

function teacherOwnsLesson(PDO $conn, string $teacherId, string $lessonId): bool
{
    $stmt = $conn->prepare(
        'SELECT 1
         FROM public.lessons l
         INNER JOIN public.course_teachers ct ON ct.course_id = l.course_id
         WHERE l.lesson_id = :lesson_id
           AND ct.teacher_id = :teacher_id
         LIMIT 1'
    );
    $stmt->execute([
        ':lesson_id' => $lessonId,
        ':teacher_id' => $teacherId,
    ]);

    return (bool) $stmt->fetchColumn();
}

function teacherOwnsQuiz(PDO $conn, string $teacherId, string $quizId): bool
{
    $stmt = $conn->prepare(
        'SELECT 1
         FROM public.questions q
         INNER JOIN public.lessons l ON l.lesson_id = q.lesson_id
         INNER JOIN public.course_teachers ct ON ct.course_id = l.course_id
         WHERE q.question_id = :quiz_id
           AND ct.teacher_id = :teacher_id
         LIMIT 1'
    );
    $stmt->execute([
        ':quiz_id' => $quizId,
        ':teacher_id' => $teacherId,
    ]);

    return (bool) $stmt->fetchColumn();
}

try {
    if ($action === 'check_storage_config') {
        $storageKey = trim((string) ($env['SUPABASE_SERVICE_ROLE_KEY'] ?? ''));
        $storageUrl = rtrim((string) ($env['SUPABASE_URL'] ?? $supabaseUrl ?? ''), '/');
        $bucket = trim((string) ($env['SUPABASE_LESSON_BUCKET'] ?? 'lesson-media'));
        $keyConfigured = $storageKey !== '' && !str_starts_with($storageKey, 'replace_with_');
        $bucketStmt = $conn->prepare('SELECT public FROM storage.buckets WHERE id = :bucket LIMIT 1');
        $bucketStmt->execute([':bucket' => $bucket]);
        $bucketRow = $bucketStmt->fetch(PDO::FETCH_ASSOC);
        $bucketExists = $bucketRow !== false;
        $bucketPublic = $bucketExists ? filter_var($bucketRow['public'], FILTER_VALIDATE_BOOLEAN) : null;
        $storageHttpStatus = null;
        $storageApiAccepted = null;
        $storageStatusMessage = 'ตั้งค่าไม่ครบ';
        if (!$keyConfigured) {
            $storageStatusMessage = 'PHP ไม่พบ SUPABASE_SERVICE_ROLE_KEY ใน flexreal.env หรือ Environment Variables ของ Apache';
        } elseif ($storageUrl === '' || str_contains($storageUrl, 'your_project_ref')) {
            $storageStatusMessage = 'SUPABASE_URL ไม่มีค่าหรือยังเป็นค่า placeholder';
        } elseif (!$bucketExists) {
            $storageStatusMessage = 'ไม่พบ bucket ' . $bucket . ' ใน Supabase Storage';
        } else {
            $headers = ['apikey: ' . $storageKey];
            if (!str_starts_with($storageKey, 'sb_secret_')) $headers[] = 'Authorization: Bearer ' . $storageKey;
            $probe = curl_init($storageUrl . '/storage/v1/bucket/' . rawurlencode($bucket));
            curl_setopt_array($probe, [CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 15]);
            $probeBody = curl_exec($probe);
            $storageHttpStatus = (int) curl_getinfo($probe, CURLINFO_RESPONSE_CODE);
            $probeError = curl_error($probe);
            curl_close($probe);
            $storageApiAccepted = $probeBody !== false && $storageHttpStatus >= 200 && $storageHttpStatus < 300;
            $storageStatusMessage = $storageApiAccepted ? 'คีย์ได้รับการยอมรับและเข้าถึง bucket ได้' : ($storageHttpStatus ? 'Supabase ปฏิเสธการตรวจสอบ (HTTP ' . $storageHttpStatus . '); ตรวจสอบว่า URL และคีย์เป็นของโปรเจกต์เดียวกันและคีย์มีสิทธิ์ Storage' : 'เชื่อมต่อ Supabase Storage ไม่สำเร็จ: ' . $probeError);
        }
        echo json_encode([
            'success' => true,
            'status' => [
                'url_configured' => $storageUrl !== '' && !str_contains($storageUrl, 'your_project_ref'),
                'service_role_key_configured' => $keyConfigured,
                'bucket' => $bucket,
                'bucket_exists' => $bucketExists,
                'bucket_public' => $bucketPublic,
                'storage_api_accepted_key' => $storageApiAccepted,
                'storage_http_status' => $storageHttpStatus,
                'message' => $storageStatusMessage,
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    ensureCourseWorkTables($conn);
    if ($action === 'get_course_work') {
        $subjectId = trim((string) ($_POST['subject_id'] ?? ''));
        if ($subjectId === '' || !teacherOwnsSubject($conn, $teacherId, $subjectId)) throw new Exception('คุณไม่มีสิทธิ์จัดการรายวิชานี้');
        $assignments = $conn->prepare("SELECT a.assignment_id, a.title, a.description, a.due_at, a.max_score, 0 AS submission_count, 0 AS pending_count FROM public.course_assignments a WHERE a.course_id = :course_id AND a.teacher_id = :teacher_id ORDER BY a.due_at ASC");
        $assignments->execute([':course_id' => $subjectId, ':teacher_id' => $teacherId]);
        $announcements = $conn->prepare('SELECT announcement_id, title, body, created_at FROM public.course_announcements WHERE course_id = :course_id AND teacher_id = :teacher_id ORDER BY created_at DESC');
        $announcements->execute([':course_id' => $subjectId, ':teacher_id' => $teacherId]);
        echo json_encode(['success' => true, 'assignments' => $assignments->fetchAll(PDO::FETCH_ASSOC), 'announcements' => $announcements->fetchAll(PDO::FETCH_ASSOC)]); exit;
    }
    if ($action === 'save_assignment') {
        $subjectId = trim((string) ($_POST['subject_id'] ?? '')); $title = trim((string) ($_POST['title'] ?? '')); $dueAt = trim((string) ($_POST['due_at'] ?? ''));
        if ($subjectId === '' || $title === '' || $dueAt === '' || !teacherOwnsSubject($conn, $teacherId, $subjectId)) throw new Exception('กรุณากรอกชื่องานและกำหนดส่งให้ครบถ้วน');
        $stmt = $conn->prepare('INSERT INTO public.course_assignments (course_id, teacher_id, title, description, due_at, max_score) VALUES (:course_id, :teacher_id, :title, :description, :due_at, :max_score)');
        $stmt->execute([':course_id' => $subjectId, ':teacher_id' => $teacherId, ':title' => $title, ':description' => trim((string) ($_POST['description'] ?? '')), ':due_at' => $dueAt, ':max_score' => max(0, (float) ($_POST['max_score'] ?? 10))]);
        echo json_encode(['success' => true, 'message' => 'สร้างงานและแจ้งนักเรียนเรียบร้อยแล้ว']); exit;
    }
    if ($action === 'save_announcement') {
        $subjectId = trim((string) ($_POST['subject_id'] ?? '')); $title = trim((string) ($_POST['title'] ?? ''));
        if ($subjectId === '' || $title === '' || !teacherOwnsSubject($conn, $teacherId, $subjectId)) throw new Exception('กรุณากรอกหัวข้อประกาศ');
        $stmt = $conn->prepare('INSERT INTO public.course_announcements (course_id, teacher_id, title, body) VALUES (:course_id, :teacher_id, :title, :body)');
        $stmt->execute([':course_id' => $subjectId, ':teacher_id' => $teacherId, ':title' => $title, ':body' => trim((string) ($_POST['body'] ?? ''))]);
        echo json_encode(['success' => true, 'message' => 'เผยแพร่ประกาศเรียบร้อยแล้ว']); exit;
    }
    if ($action === 'add_lesson') {
        $lessonName = trim((string) ($_POST['lesson_name'] ?? ''));
        $lessonContent = trim((string) ($_POST['lesson_content'] ?? ''));
        $subjectId = trim((string) ($_POST['subject_id'] ?? ''));
        if ($lessonName === '' || $subjectId === '') {
            throw new Exception('ข้อมูลไม่ครบถ้วน');
        }
        if (!teacherOwnsSubject($conn, $teacherId, $subjectId)) {
            throw new Exception('คุณไม่มีสิทธิ์จัดการรายวิชานี้');
        }
        // Reject PHP upload errors before creating the lesson row.
        foreach (['lesson_document' => 'เอกสาร PDF', 'lesson_video' => 'วิดีโอ MP4'] as $uploadField => $label) {
            $uploadError = (int) ($_FILES[$uploadField]['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($uploadError !== UPLOAD_ERR_OK && $uploadError !== UPLOAD_ERR_NO_FILE) {
                if ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE) throw new Exception("{$label} มีขนาดเกินข้อจำกัด PHP (upload_max_filesize=" . ini_get('upload_max_filesize') . ', post_max_size=' . ini_get('post_max_size') . ')');
                throw new Exception("รับไฟล์{$label}ไม่สำเร็จ (PHP upload error {$uploadError})");
            }
        }

        $conn->beginTransaction();
        $stmt = $conn->prepare('INSERT INTO public.lessons (course_id, title, content, study_hours, position, created_by, updated_at, updated_by) VALUES (:course_id, :title, :content, 1, (SELECT COALESCE(MAX(position), 0) + 1 FROM public.lessons WHERE course_id = :course_id), :created_by, clock_timestamp(), :updated_by) RETURNING lesson_id');
        $stmt->execute([':course_id' => $subjectId, ':title' => $lessonName, ':content' => $lessonContent, ':created_by' => $teacherId, ':updated_by' => $teacherId]);
        $lessonId = (string) $stmt->fetchColumn();
        $documentUpload = uploadLessonFile('lesson_document', buildLessonMediaSegments($teacherId, $subjectId, $lessonId, 'documents'), 'lesson_doc', false, ['pdf']);
        if ($documentUpload['path'] !== '') {
            $resource = $conn->prepare("INSERT INTO public.lesson_resources (lesson_id, resource_type, title, url, position, updated_at, updated_by) VALUES (:lesson_id, 'document', :title, :url, 1, clock_timestamp(), :updated_by)");
            $resource->execute([':lesson_id' => $lessonId, ':title' => $documentUpload['name'], ':url' => $documentUpload['path'], ':updated_by' => $teacherId]);
        }
        $videoUpload = uploadLessonFile('lesson_video', buildLessonMediaSegments($teacherId, $subjectId, $lessonId, 'videos'), 'video', false, ['mp4']);
        if ($videoUpload['path'] !== '') {
            $resource = $conn->prepare("INSERT INTO public.lesson_resources (lesson_id, resource_type, title, url, position, updated_at, updated_by) VALUES (:lesson_id, 'video', :title, :url, 1, clock_timestamp(), :updated_by)");
            $resource->execute([':lesson_id' => $lessonId, ':title' => $videoUpload['name'], ':url' => $videoUpload['path'], ':updated_by' => $teacherId]);
        }
        $conn->commit();

        echo json_encode(['success' => true, 'message' => 'บันทึกบทเรียนและไฟล์ที่เลือกเรียบร้อยแล้ว', 'lesson_id' => $lessonId], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'edit_lesson') {
        $lessonId = trim((string) ($_POST['lesson_id'] ?? ''));
        $lessonName = trim((string) ($_POST['lesson_name'] ?? ''));
        if ($lessonId === '' || $lessonName === '') {
            throw new Exception('ข้อมูลไม่ครบถ้วน');
        }
        if (!teacherOwnsLesson($conn, $teacherId, $lessonId)) {
            throw new Exception('คุณไม่มีสิทธิ์แก้ไขบทเรียนนี้');
        }

        $currentLesson = $conn->prepare('SELECT title FROM public.lessons WHERE lesson_id = :id');
        $currentLesson->execute([':id' => $lessonId]);
        $currentTitle = $currentLesson->fetchColumn();
        if ($currentTitle === false) throw new Exception('ไม่พบบทเรียน');
        if ((string) $currentTitle !== $lessonName) {
            $stmt = $conn->prepare('UPDATE public.lessons SET title = :name, updated_at = clock_timestamp(), updated_by = :updated_by WHERE lesson_id = :id');
            $stmt->execute([':name' => $lessonName, ':updated_by' => $teacherId, ':id' => $lessonId]);
        }

        $hasDocumentUpload = !empty($_FILES['lesson_document']) && ($_FILES['lesson_document']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if (!$hasDocumentUpload) {
            $currentDocument = $conn->prepare("SELECT r.url FROM public.lesson_resources r WHERE r.lesson_id = :lesson_id AND r.resource_type = 'document' ORDER BY r.position, r.resource_id LIMIT 1");
            $currentDocument->execute([':lesson_id' => $lessonId]);
            $currentDocumentPath = (string) ($currentDocument->fetchColumn() ?: '');
            if ($currentDocumentPath !== '' && strtolower(pathinfo($currentDocumentPath, PATHINFO_EXTENSION)) !== 'pdf') {
                throw new Exception('เอกสารเดิมไม่ใช่ PDF กรุณาเลือกไฟล์ PDF ใหม่ก่อนบันทึก');
            }
            if ($currentDocumentPath !== '' && str_starts_with($currentDocumentPath, 'uploads/')) {
                $sharedDocumentUrl = shareLegacyLessonMedia($currentDocumentPath);
                $conn->prepare("UPDATE public.lesson_resources SET url = :url, updated_at = clock_timestamp(), updated_by = :updated_by WHERE lesson_id = :lesson_id AND resource_type = 'document'")
                    ->execute([':url' => $sharedDocumentUrl, ':updated_by' => $teacherId, ':lesson_id' => $lessonId]);
            }
        }
        if ($hasDocumentUpload) {
            $courseStmt = $conn->prepare('SELECT course_id FROM public.lessons WHERE lesson_id = :lesson_id');
            $courseStmt->execute([':lesson_id' => $lessonId]);
            $subjectId = (string) $courseStmt->fetchColumn();
            $documentUpload = uploadLessonFile('lesson_document', buildLessonMediaSegments($teacherId, $subjectId, $lessonId, 'documents'), 'lesson_doc', false, ['pdf']);
            if ($documentUpload['path'] !== '') {


                $currentDocument = $conn->prepare(
                    "SELECT resource_id
                     FROM public.lesson_resources
                     WHERE lesson_id = :lesson_id AND resource_type = 'document'
                     ORDER BY position ASC, resource_id ASC
                     LIMIT 1"
                );
                $currentDocument->execute([':lesson_id' => $lessonId]);
                $resourceId = $currentDocument->fetchColumn();

                if ($resourceId !== false) {
                    $resource = $conn->prepare(
                        "UPDATE public.lesson_resources
                         SET title = :title, url = :url, updated_at = clock_timestamp(), updated_by = :updated_by
                         WHERE resource_id = :resource_id"
                    );
                    $resource->execute([
                        ':resource_id' => $resourceId,
                        ':title' => $documentUpload['name'],
                        ':url' => $documentUpload['path'],
                        ':updated_by' => $teacherId,
                    ]);
                    $conn->prepare(
                        "DELETE FROM public.lesson_resources
                         WHERE lesson_id = :lesson_id AND resource_type = 'document' AND resource_id <> :resource_id"
                    )->execute([':lesson_id' => $lessonId, ':resource_id' => $resourceId]);
                } else {
                    $resource = $conn->prepare(
                        "INSERT INTO public.lesson_resources (lesson_id, resource_type, title, url, position, updated_at, updated_by)
                         VALUES (:lesson_id, 'document', :title, :url,
                           (SELECT COALESCE(MAX(position), 0) + 1
                            FROM public.lesson_resources WHERE lesson_id = :lesson_id), clock_timestamp(), :updated_by)"
                    );
                    $resource->execute([
                        ':lesson_id' => $lessonId,
                        ':title' => $documentUpload['name'],
                        ':url' => $documentUpload['path'],
                        ':updated_by' => $teacherId,
                    ]);
                }
            }
        }

        $conn->prepare('UPDATE public.lessons SET updated_at = clock_timestamp(), updated_by = :updated_by WHERE lesson_id = :lesson_id')
            ->execute([':lesson_id' => $lessonId, ':updated_by' => $teacherId]);
        echo json_encode(['success' => true, 'message' => 'แก้ไขบทเรียนสำเร็จ']);
        exit;
    }

    if ($action === 'get_lesson_content') {
        $lessonId = trim((string) ($_POST['lesson_id'] ?? ''));
        if ($lessonId === '' || !teacherOwnsLesson($conn, $teacherId, $lessonId)) {
            throw new Exception('คุณไม่มีสิทธิ์จัดการบทเรียนนี้');
        }
        $lessonStmt = $conn->prepare('SELECT lesson_id AS id, title FROM public.lessons WHERE lesson_id = :lesson_id');
        $lessonStmt->execute([':lesson_id' => $lessonId]);
        $lesson = $lessonStmt->fetch(PDO::FETCH_ASSOC);
        $documentStmt = $conn->prepare("SELECT resource_id AS id, title, url FROM public.lesson_resources WHERE lesson_id = :lesson_id AND resource_type = 'document' ORDER BY position, resource_id");
        $documentStmt->execute([':lesson_id' => $lessonId]);
        echo json_encode(['success' => true, 'lesson' => $lesson, 'documents' => $documentStmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'delete_lesson') {
        $lessonId = trim((string) ($_POST['lesson_id'] ?? ''));
        if ($lessonId === '') {
            throw new Exception('ไม่พบบทเรียน');
        }
        if (!teacherOwnsLesson($conn, $teacherId, $lessonId)) {
            throw new Exception('คุณไม่มีสิทธิ์ลบบทเรียนนี้');
        }

        $conn->beginTransaction();
        $lesson = $conn->prepare('SELECT lesson_id FROM public.lessons WHERE lesson_id = :id FOR UPDATE');
        $lesson->execute([':id' => $lessonId]);
        if (!$lesson->fetchColumn()) throw new Exception('ไม่พบบทเรียนที่ต้องการลบ');
        // These tables reference lessons without ON DELETE CASCADE.
        $conn->prepare('DELETE FROM public.transcript_request_lessons WHERE lesson_id = :id')->execute([':id' => $lessonId]);
        $conn->prepare('DELETE FROM public.quiz_attempts WHERE lesson_id = :id')->execute([':id' => $lessonId]);
        $conn->prepare('DELETE FROM public.lessons WHERE lesson_id = :id')->execute([':id' => $lessonId]);
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'ลบบทเรียนสำเร็จ']);
        exit;
    }

    if ($action === 'set_quiz_duration') {
        $lessonId = trim((string) ($_POST['lesson_id'] ?? ''));
        $hours = filter_var($_POST['hours'] ?? null, FILTER_VALIDATE_INT);
        $minutes = filter_var($_POST['minutes'] ?? null, FILTER_VALIDATE_INT);
        if ($lessonId === '' || $hours === false || $minutes === false || $hours < 0 || $hours > 24 || $minutes < 0 || $minutes > 59) {
            throw new Exception('กรุณาระบุชั่วโมงและนาทีให้ถูกต้อง');
        }
        $durationMinutes = ($hours * 60) + $minutes;
        if ($durationMinutes < 1 || $durationMinutes > 1440) {
            throw new Exception('เวลาสอบต้องอยู่ระหว่าง 1 นาทีถึง 24 ชั่วโมง');
        }
        if (!teacherOwnsLesson($conn, $teacherId, $lessonId)) {
            throw new Exception('คุณไม่มีสิทธิ์ตั้งเวลาข้อสอบบทเรียนนี้');
        }
        $conn->prepare('UPDATE public.lessons SET quiz_duration_minutes = :duration, updated_at = now(), updated_by = :teacher_id WHERE lesson_id = :lesson_id')
            ->execute([':duration' => $durationMinutes, ':teacher_id' => $teacherId, ':lesson_id' => $lessonId]);
        echo json_encode(['success' => true, 'duration_minutes' => $durationMinutes, 'message' => 'บันทึกเวลาทำข้อสอบแล้ว']);
        exit;
    }

    if ($action === 'add_quiz') {
        $lessonId = trim((string) ($_POST['lesson_id'] ?? ''));
        $type = trim((string) ($_POST['type'] ?? 'choice'));
        $question = trim((string) ($_POST['question'] ?? ''));
        $maxScore = filter_var($_POST['max_score'] ?? 1, FILTER_VALIDATE_INT);
        if ($lessonId === '' || $question === '') {
            throw new Exception('ข้อมูลคำถามไม่ครบ');
        }
        if ($maxScore === false || $maxScore < 1 || $maxScore > 10000) throw new Exception('คะแนนเต็มต้องเป็นจำนวนเต็มระหว่าง 1 ถึง 10,000');
        $maxScore = intval($maxScore);
        if (!teacherOwnsLesson($conn, $teacherId, $lessonId)) {
            throw new Exception('คุณไม่มีสิทธิ์เพิ่มข้อสอบในบทเรียนนี้');
        }

        $choiceA = (string) ($_POST['choice_a'] ?? '');
        $choiceB = (string) ($_POST['choice_b'] ?? '');
        $choiceC = (string) ($_POST['choice_c'] ?? '');
        $choiceD = (string) ($_POST['choice_d'] ?? '');
        $answer = (string) ($_POST['answer'] ?? '');

        if (!in_array($type, ['choice', 'truefalse', 'essay'], true)) {
            throw new Exception('รูปแบบข้อสอบไม่ถูกต้อง');
        }

        if ($type === 'truefalse') {
            $choiceA = 'ถูก';
            $choiceB = 'ผิด';
            $choiceC = '-';
            $choiceD = '-';
        } elseif ($type === 'essay') {
            $choiceA = '-';
            $choiceB = '-';
            $choiceC = '-';
            $choiceD = '-';
            $answer = '-';
        }

        if ($type !== 'essay') {
            if ($choiceA === '' || $choiceB === '' || ($type === 'choice' && ($choiceC === '' || $choiceD === ''))) {
                throw new Exception('กรุณากรอกตัวเลือกให้ครบ');
            }
            if (!in_array(strtoupper($answer), ['A', 'B', 'C', 'D'], true)) {
                throw new Exception('กรุณาเลือกคำตอบที่ถูกต้อง');
            }
        }

        $stmt = $conn->prepare(
            'INSERT INTO public.questions
                (lesson_id, question_type, question_text, options, correct_choice, max_score)
             VALUES (:lesson_id, :question_type, :question_text, CAST(:options AS jsonb), :correct_choice, :max_score)'
        );
        $stmt->execute([
            ':lesson_id' => $lessonId,
            ':question_type' => $type === 'essay' ? 'essay' : 'multiple_choice',
            ':question_text' => $question,
            ':options' => $type === 'essay' ? null : json_encode($type === 'truefalse' ? [$choiceA, $choiceB] : [$choiceA, $choiceB, $choiceC, $choiceD], JSON_UNESCAPED_UNICODE),
            ':correct_choice' => $type === 'essay' ? null : strtoupper($answer),
            ':max_score' => $maxScore,
        ]);

        echo json_encode(['success' => true, 'message' => 'เพิ่มคำถามสำเร็จ']);
        exit;
    }

    if ($action === 'edit_quiz') {
        $quizId = trim((string) ($_POST['quiz_id'] ?? ''));
        $type = trim((string) ($_POST['type'] ?? 'choice'));
        $question = trim((string) ($_POST['question'] ?? ''));
        $maxScore = filter_var($_POST['max_score'] ?? 1, FILTER_VALIDATE_INT);
        if ($quizId === '' || $question === '') {
            throw new Exception('ข้อมูลไม่ครบถ้วน');
        }
        if ($maxScore === false || $maxScore < 1 || $maxScore > 10000) throw new Exception('คะแนนเต็มต้องเป็นจำนวนเต็มระหว่าง 1 ถึง 10,000');
        $maxScore = intval($maxScore);
        if (!teacherOwnsQuiz($conn, $teacherId, $quizId)) {
            throw new Exception('คุณไม่มีสิทธิ์แก้ไขข้อสอบนี้');
        }

        $choiceA = (string) ($_POST['choice_a'] ?? '');
        $choiceB = (string) ($_POST['choice_b'] ?? '');
        $choiceC = (string) ($_POST['choice_c'] ?? '');
        $choiceD = (string) ($_POST['choice_d'] ?? '');
        $answer = (string) ($_POST['answer'] ?? '');

        if (!in_array($type, ['choice', 'truefalse', 'essay'], true)) {
            throw new Exception('รูปแบบข้อสอบไม่ถูกต้อง');
        }

        if ($type === 'truefalse') {
            $choiceA = 'ถูก';
            $choiceB = 'ผิด';
            $choiceC = '-';
            $choiceD = '-';
        } elseif ($type === 'essay') {
            $choiceA = '-';
            $choiceB = '-';
            $choiceC = '-';
            $choiceD = '-';
            $answer = '-';
        }

        if ($type !== 'essay') {
            if ($choiceA === '' || $choiceB === '' || ($type === 'choice' && ($choiceC === '' || $choiceD === ''))) {
                throw new Exception('กรุณากรอกตัวเลือกให้ครบ');
            }
            if (!in_array(strtoupper($answer), ['A', 'B', 'C', 'D'], true)) {
                throw new Exception('กรุณาเลือกคำตอบที่ถูกต้อง');
            }
        }

        $stmt = $conn->prepare(
            'UPDATE public.questions
             SET question_type = :question_type,
                 question_text = :question_text,
                 options = CAST(:options AS jsonb),
                 correct_choice = :correct_choice,
                 max_score = :max_score,
                 updated_at = NOW()
             WHERE question_id = :question_id'
        );
        $stmt->execute([
            ':question_type' => $type === 'essay' ? 'essay' : 'multiple_choice',
            ':question_text' => $question,
            ':options' => $type === 'essay' ? null : json_encode($type === 'truefalse' ? [$choiceA, $choiceB] : [$choiceA, $choiceB, $choiceC, $choiceD], JSON_UNESCAPED_UNICODE),
            ':correct_choice' => $type === 'essay' ? null : strtoupper($answer),
            ':max_score' => $maxScore,
            ':question_id' => $quizId,
        ]);

        echo json_encode(['success' => true, 'message' => 'แก้ไขคำถามสำเร็จ']);
        exit;
    }

    if ($action === 'delete_quiz') {
        $quizId = trim((string) ($_POST['quiz_id'] ?? ''));
        if ($quizId === '') {
            throw new Exception('ไม่พบคำถาม');
        }
        if (!teacherOwnsQuiz($conn, $teacherId, $quizId)) {
            throw new Exception('คุณไม่มีสิทธิ์ลบข้อสอบนี้');
        }

        $conn->prepare('DELETE FROM public.questions WHERE question_id = ?')->execute([$quizId]);
        echo json_encode(['success' => true, 'message' => 'ลบคำถามสำเร็จ']);
        exit;
    }

    if ($action === 'get_videos') {
        $subjectId = trim((string) ($_POST['subject_id'] ?? ''));
        if ($subjectId === '' || !teacherOwnsSubject($conn, $teacherId, $subjectId)) {
            throw new Exception('คุณไม่มีสิทธิ์จัดการรายวิชานี้');
        }
        $lessonStmt = $conn->prepare('SELECT lesson_id AS id, title FROM public.lessons WHERE course_id = :subject_id ORDER BY position, lesson_id');
        $lessonStmt->execute([':subject_id' => $subjectId]);
        $videoStmt = $conn->prepare("SELECT r.resource_id AS id, r.title, r.url, '' AS description, r.duration_seconds, r.position AS display_order, r.lesson_id AS lessons_id, l.title AS lesson_title, EXTRACT(EPOCH FROM COALESCE(r.updated_at, r.created_at)) AS last_modified_epoch, COALESCE(t.full_name, r.updated_by, 'ยังไม่มีข้อมูล') AS updated_by_name FROM public.lesson_resources r INNER JOIN public.lessons l ON l.lesson_id = r.lesson_id LEFT JOIN public.teachers t ON t.user_id = r.updated_by WHERE l.course_id = :subject_id AND r.resource_type = 'video' ORDER BY r.lesson_id, r.position DESC, r.resource_id DESC");
        $videoStmt->execute([':subject_id' => $subjectId]);
        echo json_encode(['success' => true, 'lessons' => $lessonStmt->fetchAll(PDO::FETCH_ASSOC), 'videos' => $videoStmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'save_video') {
        $subjectId = trim((string) ($_POST['subject_id'] ?? ''));
        $videoId = trim((string) ($_POST['video_id'] ?? ''));
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($subjectId === '' || !teacherOwnsSubject($conn, $teacherId, $subjectId)) {
            throw new Exception('ข้อมูลไม่ครบถ้วนหรือคุณไม่มีสิทธิ์จัดการรายวิชานี้');
        }
        $lessonId = trim((string) ($_POST['lesson_id'] ?? ''));
        if ($lessonId === '' || !teacherOwnsLesson($conn, $teacherId, $lessonId)) throw new Exception('บทเรียนที่เลือกไม่ถูกต้อง');
        $lessonCourseStmt = $conn->prepare('SELECT course_id FROM public.lessons WHERE lesson_id = :lesson_id');
        $lessonCourseStmt->execute([':lesson_id' => $lessonId]);
        if ((string) $lessonCourseStmt->fetchColumn() !== $subjectId) throw new Exception('บทเรียนที่เลือกไม่อยู่ในรายวิชานี้');

        $filePresent = isset($_FILES['video_file']) && is_array($_FILES['video_file'])
            && (int) ($_FILES['video_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $oldUrl = '';
        if ($videoId !== '') {
            $existingStmt = $conn->prepare(
                "SELECT r.resource_id, r.url, r.title
                 FROM public.lesson_resources r
                 INNER JOIN public.lessons l ON l.lesson_id = r.lesson_id
                 WHERE r.resource_id = :id AND l.course_id = :subject_id AND r.resource_type = 'video'
                 LIMIT 1"
            );
            $existingStmt->execute([':id' => $videoId, ':subject_id' => $subjectId]);
            $existingVideo = $existingStmt->fetch(PDO::FETCH_ASSOC);
            if (!$existingVideo) throw new Exception('ไม่พบวิดีโอเดิมในรายวิชานี้');
            $oldUrl = (string) $existingVideo['url'];
            if ($title === '') $title = (string) $existingVideo['title'];
        }

        if ($title === '' && $filePresent) $title = pathinfo((string) ($_FILES['video_file']['name'] ?? ''), PATHINFO_FILENAME);
        if ($title === '') throw new Exception('กรุณาระบุชื่อวิดีโอ');
        if (!$filePresent && $videoId === '') throw new Exception('กรุณาเลือกไฟล์วิดีโอ MP4');

        $newFilePath = '';
        $newFileDiskPath = '';
        if ($filePresent) {
            $upload = uploadLessonFile('video_file', buildVideoMediaSegments($teacherId, $subjectId), 'video', true, ['mp4']);
            $url = $upload['path'];
            $newFilePath = $url;
            $newFileDiskPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $newFilePath);
        } else {
            $url = $oldUrl;
            if (str_starts_with($oldUrl, 'uploads/')) $url = shareLegacyLessonMedia($oldUrl);
        }
        try {
            $conn->beginTransaction();
            $params = [':title' => $title, ':url' => $url, ':lesson_id' => $lessonId, ':updated_by' => $teacherId];
            if ($videoId === '') {
                $stmt = $conn->prepare(
                    "INSERT INTO public.lesson_resources (lesson_id, resource_type, title, url, position, updated_at, updated_by)
                     VALUES (:lesson_id, 'video', :title, :url,
                       (SELECT COALESCE(MAX(position), 0) + 1 FROM public.lesson_resources WHERE lesson_id = :position_lesson_id),
                       clock_timestamp(), :updated_by)
                     RETURNING resource_id AS id, updated_by, EXTRACT(EPOCH FROM COALESCE(updated_at, created_at)) AS last_modified_epoch"
                );
                $params[':position_lesson_id'] = $lessonId;
            } else {
                $params[':id'] = $videoId;
                $stmt = $conn->prepare(
                    "UPDATE public.lesson_resources
                     SET title = :title, url = :url, lesson_id = :lesson_id, updated_at = clock_timestamp(), updated_by = :updated_by
                     WHERE resource_id = :id AND resource_type = 'video'
                     RETURNING resource_id AS id, updated_by, EXTRACT(EPOCH FROM COALESCE(updated_at, created_at)) AS last_modified_epoch"
                );
            }
            $stmt->execute($params);
            $savedVideo = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$savedVideo) throw new RuntimeException('Video resource was not saved.');
            $teacherNameStmt = $conn->prepare("SELECT COALESCE(NULLIF(full_name, ''), user_id) FROM public.teachers WHERE user_id = :teacher_id");
            $teacherNameStmt->execute([':teacher_id' => $teacherId]);
            $savedVideo['updated_by_name'] = (string) ($teacherNameStmt->fetchColumn() ?: 'ไม่ทราบชื่อ');
            $conn->commit();
        } catch (Throwable $saveError) {
            if ($conn->inTransaction()) $conn->rollBack();
            if ($newFileDiskPath !== '' && is_file($newFileDiskPath)) @unlink($newFileDiskPath);
            throw $saveError;
        }

        if ($newFilePath !== '' && $oldUrl !== '' && $oldUrl !== $newFilePath && str_starts_with($oldUrl, 'uploads/')) {
            $uploadsRoot = realpath(__DIR__ . '/uploads');
            $oldDiskPath = realpath(__DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, strtok($oldUrl, '?')));
            if ($uploadsRoot !== false && $oldDiskPath !== false && str_starts_with($oldDiskPath, $uploadsRoot . DIRECTORY_SEPARATOR) && is_file($oldDiskPath)) {
                @unlink($oldDiskPath);
            }
        }

        echo json_encode(['success' => true, 'message' => 'อัปโหลดวิดีโอสำเร็จและบันทึกเรียบร้อยแล้ว', 'video' => $savedVideo], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'delete_video') {
        $subjectId = trim((string) ($_POST['subject_id'] ?? ''));
        $videoId = trim((string) ($_POST['video_id'] ?? ''));
        if ($subjectId === '' || $videoId === '' || !teacherOwnsSubject($conn, $teacherId, $subjectId)) throw new Exception('คุณไม่มีสิทธิ์ลบวิดีโอนี้');
        $conn->prepare("DELETE FROM public.lesson_resources r USING public.lessons l WHERE r.lesson_id = l.lesson_id AND r.resource_id = :id AND l.course_id = :subject_id AND r.resource_type = 'video'")->execute([':id' => $videoId, ':subject_id' => $subjectId]);
        echo json_encode(['success' => true, 'message' => 'ลบวิดีโอสำเร็จ']);
        exit;
    }

    if ($action === 'get_essay_submissions') {
        $subjectId = trim((string) ($_POST['subject_id'] ?? ''));
        if ($subjectId === '' || !teacherOwnsSubject($conn, $teacherId, $subjectId)) throw new Exception('คุณไม่มีสิทธิ์จัดการรายวิชานี้');
        $stmt = $conn->prepare(
            "SELECT aa.attempt_answer_id AS submission_id, a.attempt_id AS test_id, a.student_id,
                    l.course_id, a.lesson_id, aa.question_id, aa.reviewer_id, aa.answer_text,
                    aa.review_status, aa.result_status,
                    aa.score, q.max_score,
                    aa.reviewer_comment AS teacher_comment, a.submitted_at, aa.reviewed_at,
                    s.full_name AS student_name, l.title AS lessons_name, c.name AS course_name, q.question_text AS questions_text,
                    t.full_name AS reviewer_name
             FROM public.quiz_attempt_answers aa
             INNER JOIN public.quiz_attempts a ON a.attempt_id = aa.attempt_id
             INNER JOIN public.questions q ON q.question_id = aa.question_id
             INNER JOIN public.lessons l ON l.lesson_id = a.lesson_id
             INNER JOIN public.courses c ON c.course_id = l.course_id
             INNER JOIN public.students s ON s.user_id = a.student_id
             LEFT JOIN public.teachers t ON t.user_id = aa.reviewer_id
             WHERE l.course_id = :subject_id AND q.question_type = 'essay' AND aa.review_status IN ('pending', 'graded')
             ORDER BY CASE WHEN aa.review_status = 'pending' THEN 0 ELSE 1 END, a.submitted_at DESC"
        );
        $stmt->execute([':subject_id' => $subjectId]);
        echo json_encode(['success' => true, 'submissions' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'review_essay_submission') {
        $submissionId = (int) ($_POST['submission_id'] ?? 0);
        $decision = trim((string) ($_POST['decision'] ?? ''));
        $submittedScore = $_POST['score'] ?? null;
        $submittedResultStatus = trim((string) ($_POST['result_status'] ?? ''));
        if ($submissionId <= 0 || ($submittedScore === null && !in_array($decision, ['pass', 'fail'], true))) throw new Exception('ข้อมูลการตรวจไม่ถูกต้อง');
        $conn->beginTransaction();
        $lookup = $conn->prepare(
            'SELECT aa.attempt_id, aa.review_status, q.max_score
             FROM public.quiz_attempt_answers aa
             INNER JOIN public.questions q ON q.question_id = aa.question_id
             INNER JOIN public.quiz_attempts a ON a.attempt_id = aa.attempt_id
             INNER JOIN public.lessons l ON l.lesson_id = a.lesson_id
             INNER JOIN public.course_teachers ct ON ct.course_id = l.course_id
             WHERE aa.attempt_answer_id = :id AND ct.teacher_id = :teacher_id AND q.question_type = \'essay\' AND aa.review_status IN (\'pending\', \'graded\')
             FOR UPDATE'
        );
        $lookup->execute([':id' => $submissionId, ':teacher_id' => $teacherId]);
        $row = $lookup->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new Exception('ไม่พบคำตอบข้อเขียนหรือคุณไม่มีสิทธิ์ตรวจ');
        $maxScore = (float) $row['max_score'];
        $score = $submittedScore === null ? ($decision === 'pass' ? $maxScore : 0.0) : filter_var($submittedScore, FILTER_VALIDATE_FLOAT);
        if ($score === false || $score < 0 || $score > $maxScore || floor((float) $score) !== (float) $score) throw new Exception('คะแนนต้องเป็นจำนวนเต็มระหว่าง 0 ถึงคะแนนเต็ม');
        if (!in_array($row['review_status'], ['pending', 'graded'], true)) throw new Exception('สถานะคำตอบไม่ถูกต้อง');
        if ($submittedResultStatus !== '' && !in_array($submittedResultStatus, ['passed', 'failed'], true)) throw new Exception('สถานะผลสอบไม่ถูกต้อง');
        $resultStatus = $submittedResultStatus !== '' ? $submittedResultStatus : ((float) $score >= $maxScore ? 'passed' : 'failed');
        $conn->prepare("UPDATE public.quiz_attempt_answers SET score = :score, review_status = 'graded', result_status = :result_status, reviewer_id = :teacher_id, reviewer_comment = :comment, reviewed_at = CURRENT_TIMESTAMP WHERE attempt_answer_id = :id")
            ->execute([':score' => $score, ':result_status' => $resultStatus, ':teacher_id' => $teacherId, ':comment' => trim((string) ($_POST['comment'] ?? '')), ':id' => $submissionId]);
        $attemptId = (int) $row['attempt_id'];
        $summary = $conn->prepare("SELECT COALESCE(SUM(score), 0) AS score, COUNT(*) FILTER (WHERE review_status = 'pending') AS pending FROM public.quiz_attempt_answers WHERE attempt_id = :attempt_id");
        $summary->execute([':attempt_id' => $attemptId]);
        $result = $summary->fetch(PDO::FETCH_ASSOC);
        $attempt = $conn->prepare('SELECT total_score FROM public.quiz_attempts WHERE attempt_id = :attempt_id FOR UPDATE');
        $attempt->execute([':attempt_id' => $attemptId]);
        $totalScore = (float) $attempt->fetchColumn();
        $testStatus = (int) $result['pending'] > 0 ? 'pending_review' : ((float) $result['score'] >= $totalScore ? 'passed' : 'failed');
        $conn->prepare('UPDATE public.quiz_attempts SET score = :score, status = :status WHERE attempt_id = :attempt_id')->execute([':score' => $result['score'], ':status' => $testStatus, ':attempt_id' => $attemptId]);
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'บันทึกคะแนนและผลตรวจลงฐานข้อมูลแล้ว', 'score' => (float) $score, 'result_status' => $resultStatus, 'quiz_status' => $testStatus, 'reviewed_at' => date(DATE_ATOM)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    throw new Exception('ไม่พบคำสั่งที่ต้องการ');
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    error_log('Teacher API request failed (' . $action . '): ' . $e->getMessage());
    $userMessage = $e instanceof PDOException || $e instanceof ErrorException || $e instanceof TypeError
        ? 'ระบบบันทึกข้อมูลขัดข้อง กรุณาลองใหม่อีกครั้ง'
        : $e->getMessage();
    echo json_encode(['success' => false, 'message' => $userMessage], JSON_UNESCAPED_UNICODE);
}
?>
