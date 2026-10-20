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

const MAX_LESSONS_PER_SUBJECT = 3;

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
    $conn->exec("CREATE TABLE IF NOT EXISTS public.assignment_submissions (
        submission_id BIGSERIAL PRIMARY KEY, assignment_id bigint NOT NULL REFERENCES public.course_assignments(assignment_id) ON DELETE CASCADE,
        student_id varchar(50) NOT NULL REFERENCES public.students(user_id), note text NOT NULL DEFAULT '', file_url text, status varchar(20) NOT NULL DEFAULT 'submitted',
        submitted_at timestamptz NOT NULL DEFAULT now(), reviewed_at timestamptz, UNIQUE(assignment_id, student_id)
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
        throw new Exception('ไฟล์วิดีโอว่างเปล่าหรืออ่านไม่ได้');
    }
    if ($prefix === 'video' && (int) ($file['size'] ?? 0) > phpSizeToBytes((string) ini_get('upload_max_filesize'))) {
        throw new Exception('ไฟล์วิดีโอมีขนาดเกินขีดจำกัดของเซิร์ฟเวอร์');
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

    return [
        'path' => $relativeDir . '/' . $targetName,
        'name' => $originalName !== '' ? $originalName : $targetName,
    ];
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

function countSubjectLessons(PDO $conn, string $subjectId): int
{
    $stmt = $conn->prepare('SELECT COUNT(*) FROM public.lessons WHERE course_id = :subject_id');
    $stmt->execute([':subject_id' => $subjectId]);
    return (int) $stmt->fetchColumn();
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
    ensureCourseWorkTables($conn);
    if ($action === 'get_course_work') {
        $subjectId = trim((string) ($_POST['subject_id'] ?? ''));
        if ($subjectId === '' || !teacherOwnsSubject($conn, $teacherId, $subjectId)) throw new Exception('คุณไม่มีสิทธิ์จัดการรายวิชานี้');
        $assignments = $conn->prepare("SELECT a.assignment_id, a.title, a.description, a.due_at, a.max_score, COUNT(s.submission_id) AS submission_count, COUNT(s.submission_id) FILTER (WHERE s.status = 'submitted') AS pending_count FROM public.course_assignments a LEFT JOIN public.assignment_submissions s ON s.assignment_id = a.assignment_id WHERE a.course_id = :course_id AND a.teacher_id = :teacher_id GROUP BY a.assignment_id ORDER BY a.due_at ASC");
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
        if (countSubjectLessons($conn, $subjectId) >= MAX_LESSONS_PER_SUBJECT) {
            throw new Exception('รายวิชานี้มีบทเรียนครบ 3 บทแล้ว');
        }

        $conn->beginTransaction();
        $stmt = $conn->prepare('INSERT INTO public.lessons (course_id, title, content, study_hours, position, updated_at, updated_by) VALUES (:course_id, :title, :content, 1, (SELECT COALESCE(MAX(position), 0) + 1 FROM public.lessons WHERE course_id = :course_id), clock_timestamp(), :updated_by) RETURNING lesson_id');
        $stmt->execute([':course_id' => $subjectId, ':title' => $lessonName, ':content' => $lessonContent, ':updated_by' => $teacherId]);
        $lessonId = (string) $stmt->fetchColumn();
        $documentUpload = uploadLessonFile('lesson_document', buildLessonMediaSegments($teacherId, $subjectId, $lessonId, 'documents'), 'lesson_doc', false, ['pdf']);
        if ($documentUpload['path'] !== '') {
            $resource = $conn->prepare("INSERT INTO public.lesson_resources (lesson_id, resource_type, title, url, position, updated_at, updated_by) VALUES (:lesson_id, 'document', :title, :url, 1, clock_timestamp(), :updated_by)");
            $resource->execute([':lesson_id' => $lessonId, ':title' => $documentUpload['name'], ':url' => $documentUpload['path'], ':updated_by' => $teacherId]);
        }
        $conn->commit();

        echo json_encode(['success' => true, 'message' => 'เพิ่มบทเรียนสำเร็จ', 'lesson_id' => $lessonId]);
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
            $stmt = $conn->prepare('UPDATE public.lessons SET title = :name WHERE lesson_id = :id');
            $stmt->execute([':name' => $lessonName, ':id' => $lessonId]);
        }

        $hasDocumentUpload = !empty($_FILES['lesson_document']) && ($_FILES['lesson_document']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if (!$hasDocumentUpload) {
            $currentDocument = $conn->prepare("SELECT r.url FROM public.lesson_resources r WHERE r.lesson_id = :lesson_id AND r.resource_type = 'document' ORDER BY r.position, r.resource_id LIMIT 1");
            $currentDocument->execute([':lesson_id' => $lessonId]);
            $currentDocumentPath = (string) ($currentDocument->fetchColumn() ?: '');
            if ($currentDocumentPath !== '' && strtolower(pathinfo($currentDocumentPath, PATHINFO_EXTENSION)) !== 'pdf') {
                throw new Exception('เอกสารเดิมไม่ใช่ PDF กรุณาเลือกไฟล์ PDF ใหม่ก่อนบันทึก');
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

        $conn->prepare('DELETE FROM public.lessons WHERE lesson_id = ?')->execute([$lessonId]);
        echo json_encode(['success' => true, 'message' => 'ลบบทเรียนสำเร็จ']);
        exit;
    }

    if ($action === 'add_quiz') {
        $lessonId = trim((string) ($_POST['lesson_id'] ?? ''));
        $type = trim((string) ($_POST['type'] ?? 'choice'));
        $question = trim((string) ($_POST['question'] ?? ''));
        if ($lessonId === '' || $question === '') {
            throw new Exception('ข้อมูลคำถามไม่ครบ');
        }
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
                (lesson_id, question_type, question_text, options, correct_choice)
             VALUES (:lesson_id, :question_type, :question_text, CAST(:options AS jsonb), :correct_choice)'
        );
        $stmt->execute([
            ':lesson_id' => $lessonId,
            ':question_type' => $type === 'essay' ? 'essay' : 'multiple_choice',
            ':question_text' => $question,
            ':options' => $type === 'essay' ? null : json_encode($type === 'truefalse' ? [$choiceA, $choiceB] : [$choiceA, $choiceB, $choiceC, $choiceD], JSON_UNESCAPED_UNICODE),
            ':correct_choice' => $type === 'essay' ? null : strtoupper($answer),
        ]);

        echo json_encode(['success' => true, 'message' => 'เพิ่มคำถามสำเร็จ']);
        exit;
    }

    if ($action === 'edit_quiz') {
        $quizId = trim((string) ($_POST['quiz_id'] ?? ''));
        $type = trim((string) ($_POST['type'] ?? 'choice'));
        $question = trim((string) ($_POST['question'] ?? ''));
        if ($quizId === '' || $question === '') {
            throw new Exception('ข้อมูลไม่ครบถ้วน');
        }
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
                 updated_at = NOW()
             WHERE question_id = :question_id'
        );
        $stmt->execute([
            ':question_type' => $type === 'essay' ? 'essay' : 'multiple_choice',
            ':question_text' => $question,
            ':options' => $type === 'essay' ? null : json_encode($type === 'truefalse' ? [$choiceA, $choiceB] : [$choiceA, $choiceB, $choiceC, $choiceD], JSON_UNESCAPED_UNICODE),
            ':correct_choice' => $type === 'essay' ? null : strtoupper($answer),
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
        $lessonStmt = $conn->prepare('SELECT lesson_id AS id, title FROM public.lessons WHERE course_id = :subject_id ORDER BY position LIMIT 3');
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
        if ($score === false || $score < 0 || $score > $maxScore) throw new Exception('คะแนนต้องอยู่ระหว่าง 0 ถึงคะแนนเต็ม');
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
