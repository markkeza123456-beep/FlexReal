<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config/db.php';


function fetchAllRows(PDOStatement $statement): array {
    return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function jsonResponse(array $payload, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function postValue(string $key, $default = '') {
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
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

function uploadLessonFile(string $fieldName, array $segments, string $prefix): array
{
    if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) {
        return ['path' => '', 'name' => ''];
    }

    $file = $_FILES[$fieldName];
    $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError === UPLOAD_ERR_NO_FILE) return ['path' => '', 'name' => ''];
    if ($uploadError !== UPLOAD_ERR_OK) {
        $message = match ($uploadError) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'ไฟล์มีขนาดใหญ่เกินที่ระบบรองรับ กรุณาเลือกไฟล์ที่เล็กลง',
            UPLOAD_ERR_PARTIAL => 'อัปโหลดไฟล์ไม่ครบ กรุณาลองใหม่อีกครั้ง',
            UPLOAD_ERR_NO_TMP_DIR => 'ระบบไม่พบพื้นที่พักไฟล์ กรุณาติดต่อผู้ดูแลระบบ',
            UPLOAD_ERR_CANT_WRITE => 'ระบบบันทึกไฟล์ไม่สำเร็จ กรุณาลองใหม่อีกครั้ง',
            UPLOAD_ERR_EXTENSION => 'ระบบไม่อนุญาตให้อัปโหลดไฟล์นี้',
            default => 'อัปโหลดไฟล์ไม่สำเร็จ กรุณาลองใหม่อีกครั้ง',
        };
        jsonResponse(['status' => 'error', 'message' => $message], 400);
    }

    $relativeDir = 'uploads/' . implode('/', $segments);
    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
    if ((!is_dir($uploadDir) && !@mkdir($uploadDir, 0777, true)) || !is_writable($uploadDir)) {
        // Some local XAMPP installs make uploads/courses read-only to Apache.
        // The public uploads root is already the configured writable media area.
        $relativeDir = 'uploads';
        $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
    }
    if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
        error_log('Lesson media upload directory is not writable: ' . $uploadDir);
        jsonResponse(['status' => 'error', 'message' => 'โฟลเดอร์อัปโหลดไม่มีสิทธิ์บันทึกไฟล์ กรุณาติดต่อผู้ดูแลระบบ'], 500);
    }

    $originalName = trim((string) ($file['name'] ?? ''));
    $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($originalName));
    $safeName = $safeName !== '' ? $safeName : ($prefix . '_' . time());
    $scope = implode('_', array_map('sanitizePathSegment', $segments));
    $targetName = $scope . '_' . $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '_' . $safeName;
    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $targetName;

    if (!move_uploaded_file((string) $file['tmp_name'], $targetPath)) {
        error_log('Failed to move uploaded lesson media into writable directory: ' . $uploadDir);
        jsonResponse(['status' => 'error', 'message' => 'ระบบบันทึกไฟล์ไม่สำเร็จ กรุณาลองใหม่อีกครั้ง'], 500);
    }

    return [
        'path' => $relativeDir . '/' . $targetName,
        'name' => $originalName !== '' ? $originalName : $targetName,
    ];
}

function uploadCourseCover(): ?string
{
    if (!isset($_FILES['course_image']) || (int) ($_FILES['course_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES['course_image'];
    if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('อัปโหลดรูปภาพไม่สำเร็จ');
    }
    if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('รูปภาพต้องมีขนาดไม่เกิน 5 MB');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file((string) $file['tmp_name']);
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($mime, $allowedMimeTypes, true)) {
        throw new RuntimeException('รองรับเฉพาะรูป JPG, PNG, WEBP หรือ GIF');
    }
    $imageBytes = file_get_contents((string) $file['tmp_name']);
    if ($imageBytes === false || $imageBytes === '') {
        throw new RuntimeException('ไม่สามารถอ่านไฟล์รูปภาพได้');
    }
    if (@getimagesizefromstring($imageBytes) === false) {
        throw new RuntimeException('ไฟล์ที่อัปโหลดไม่ใช่รูปภาพที่ถูกต้อง');
    }
    return 'data:' . $mime . ';base64,' . base64_encode($imageBytes);
}

function localUploadPath(string $storedPath): ?string
{
    $storedPath = strtok(trim(str_replace('\\', '/', $storedPath)), '?');
    if ($storedPath === '' || !str_starts_with($storedPath, 'uploads/')) return null;
    $uploadsRoot = realpath(__DIR__ . '/uploads');
    $candidate = realpath(__DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $storedPath));
    if ($uploadsRoot === false || $candidate === false || !str_starts_with($candidate, $uploadsRoot . DIRECTORY_SEPARATOR) || !is_file($candidate)) return null;
    return $candidate;
}

function deleteUnreferencedLessonFiles(PDO $conn, array $urls): void
{
    $uniqueUrls = array_values(array_unique(array_filter(array_map('trim', $urls))));
    $check = $conn->prepare('SELECT EXISTS (SELECT 1 FROM public.lesson_resources WHERE url = :url)');
    foreach ($uniqueUrls as $url) {
        $check->execute([':url' => $url]);
        if ($check->fetchColumn()) continue;
        $path = localUploadPath($url);
        if ($path !== null && !unlink($path)) throw new RuntimeException('ไม่สามารถลบไฟล์สื่อที่เกี่ยวข้องได้');
    }
}

function tableExists(PDO $conn, string $table): bool
{
    $statement = $conn->prepare("SELECT to_regclass(:table_name) IS NOT NULL");
    $statement->execute([':table_name' => 'public.' . $table]);
    return (bool) $statement->fetchColumn();
}

function deleteRowsIfTableExists(PDO $conn, string $table, string $whereSql, array $params): void
{
    if (!tableExists($conn, $table)) return;
    $conn->prepare('DELETE FROM public.' . $table . ' WHERE ' . $whereSql)->execute($params);
}

function deleteMemberRecord(PDO $conn, string $memberId, string $actorId): void
{
    if ($memberId === $actorId) throw new RuntimeException('ไม่สามารถลบบัญชีที่กำลังใช้งานอยู่ได้');
    $conn->beginTransaction();
    try {
        $targetStmt = $conn->prepare('SELECT role FROM public.users WHERE user_id = :id FOR UPDATE');
        $targetStmt->execute([':id' => $memberId]);
        $role = $targetStmt->fetchColumn();
        if ($role === false) throw new RuntimeException('ไม่พบบัญชีผู้ใช้งาน');

        if (tableExists($conn, 'students')) {
            $conn->prepare('UPDATE public.students SET advisor_user_id = NULL WHERE advisor_user_id = :id')->execute([':id' => $memberId]);
            $conn->prepare('UPDATE public.students SET parent_user_id = NULL WHERE parent_user_id = :id')->execute([':id' => $memberId]);
        }
        foreach (['courses', 'curricula', 'lessons'] as $auditTable) {
            if (tableExists($conn, $auditTable)) {
                foreach (['created_by', 'updated_by'] as $auditColumn) {
                    $conn->prepare('UPDATE public.' . $auditTable . ' SET ' . $auditColumn . ' = NULL WHERE ' . $auditColumn . ' = :id')->execute([':id' => $memberId]);
                }
            }
        }
        if (tableExists($conn, 'graduation_requests')) $conn->prepare('UPDATE public.graduation_requests SET approved_by = NULL WHERE approved_by = :id')->execute([':id' => $memberId]);
        deleteRowsIfTableExists($conn, 'transfer_permissions', 'instructor_id = :id OR granted_by = :id', [':id' => $memberId]);
        if (tableExists($conn, 'transcript_requests')) $conn->prepare('UPDATE public.transcript_requests SET reviewed_by = NULL, approved_by = NULL WHERE reviewed_by = :id OR approved_by = :id')->execute([':id' => $memberId]);
        deleteRowsIfTableExists($conn, 'transcript_request_lessons', 'reviewed_by = :id', [':id' => $memberId]);
        if (tableExists($conn, 'transcript_request_lessons') && tableExists($conn, 'transcript_requests')) {
            $conn->prepare('DELETE FROM public.transcript_request_lessons WHERE request_id IN (SELECT request_id FROM public.transcript_requests WHERE student_id = :id)')->execute([':id' => $memberId]);
        }
        if (tableExists($conn, 'lesson_progress')) $conn->prepare('UPDATE public.lesson_progress SET transferred_by = NULL WHERE transferred_by = :id')->execute([':id' => $memberId]);
        deleteRowsIfTableExists($conn, 'graduation_requests', 'student_id = :id', [':id' => $memberId]);
        deleteRowsIfTableExists($conn, 'exam_logs', 'student_id = :id', [':id' => $memberId]);
        deleteRowsIfTableExists($conn, 'lesson_progress', 'student_id = :id', [':id' => $memberId]);
        deleteRowsIfTableExists($conn, 'transcript_requests', 'student_id = :id', [':id' => $memberId]);
        deleteRowsIfTableExists($conn, 'student_curricula', 'student_id = :id', [':id' => $memberId]);
        deleteRowsIfTableExists($conn, 'student_courses', 'student_id = :id', [':id' => $memberId]);
        deleteRowsIfTableExists($conn, 'course_certificates', 'student_id = :id', [':id' => $memberId]);
        if (tableExists($conn, 'quiz_attempt_answers') && tableExists($conn, 'quiz_attempts')) {
            $conn->prepare('DELETE FROM public.quiz_attempt_answers WHERE attempt_id IN (SELECT attempt_id FROM public.quiz_attempts WHERE student_id = :id)')->execute([':id' => $memberId]);
        }
        deleteRowsIfTableExists($conn, 'quiz_attempt_sessions', 'student_id = :id', [':id' => $memberId]);
        deleteRowsIfTableExists($conn, 'quiz_attempts', 'student_id = :id', [':id' => $memberId]);
        deleteRowsIfTableExists($conn, 'course_teachers', 'teacher_id = :id', [':id' => $memberId]);
        if (tableExists($conn, 'quiz_attempt_answers')) $conn->prepare('UPDATE public.quiz_attempt_answers SET reviewer_id = NULL WHERE reviewer_id = :id')->execute([':id' => $memberId]);
        deleteRowsIfTableExists($conn, 'staff', 'user_id = :id', [':id' => $memberId]);
        $profileTable = ['student' => 'students', 'teacher' => 'teachers', 'parent' => 'parents'][$role] ?? null;
        if ($profileTable !== null) deleteRowsIfTableExists($conn, $profileTable, 'user_id = :id', [':id' => $memberId]);
        deleteRowsIfTableExists($conn, 'user_addresses', 'user_id = :id', [':id' => $memberId]);
        deleteRowsIfTableExists($conn, 'site_visits', 'user_id = :id', [':id' => $memberId]);
        deleteRowsIfTableExists($conn, 'password_reset_tokens', 'user_id = :id', [':id' => $memberId]);
        $conn->prepare('DELETE FROM public.users WHERE user_id = :id')->execute([':id' => $memberId]);
        $conn->commit();
    } catch (Throwable $error) {
        if ($conn->inTransaction()) $conn->rollBack();
        throw $error;
    }
}

if (!isset($_SESSION['user_id']) || strtolower((string) ($_SESSION['role'] ?? '')) !== 'staff') {
    jsonResponse([
        'status' => 'error',
        'message' => 'Unauthorized',
    ], 403);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
try {
    switch ($action) {
        case 'getAllData':

            $membersStmt = $conn->query('
                SELECT
                    u.user_id as id,
                    u.role,
                    u.account_status as status_account,
                    u.created_at as registered_at,
                    u.updated_at,
                    COALESCE(s.full_name, t.full_name, p.full_name, NULLIF(TRIM(stf.first_name || \' \' || stf.last_name), \'\'), \'-\') as name,
                    COALESCE(s.email, t.email, p.email, stf.email, \'-\') as email,
                    COALESCE(s.phone, t.phone, p.phone, stf.phone, \'-\') as phone,
                    COALESCE(parent_students.linked_students, \'[]\'::json) AS linked_students,
                    COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', creator_staff.first_name, creator_staff.last_name)), \'\'), u.created_by, \'-\') AS created_by_name,
                    COALESCE(NULLIF(TRIM(CONCAT_WS(\' \', editor_staff.first_name, editor_staff.last_name)), \'\'), u.updated_by, \'-\') AS updated_by_name
                FROM public.users u
                LEFT JOIN public.students s ON s.user_id = u.user_id
                LEFT JOIN public.teachers t ON t.user_id = u.user_id
                LEFT JOIN public.parents p ON p.user_id = u.user_id
                LEFT JOIN public.staff stf ON u.user_id = stf.user_id
                LEFT JOIN LATERAL (
                    SELECT JSON_AGG(JSON_BUILD_OBJECT(\'id\', s.user_id::text, \'name\', s.full_name) ORDER BY s.full_name) AS linked_students
                    FROM public.students s
                    WHERE s.parent_user_id = u.user_id
                ) parent_students ON TRUE
                LEFT JOIN public.staff creator_staff ON creator_staff.user_id = u.created_by
                LEFT JOIN public.staff editor_staff ON editor_staff.user_id = u.updated_by
                ORDER BY u.user_id DESC
            ');
            $studentsStmt = $conn->query("SELECT s.user_id::text AS id, s.full_name AS name, s.parent_user_id::text AS parent_id, COALESCE(p.full_name, '') AS parent_name FROM public.students s LEFT JOIN public.parents p ON p.user_id = s.parent_user_id ORDER BY s.full_name, s.user_id");
            $curriculaStmt = $conn->query("SELECT c.curriculum_id AS id, c.code, c.name, c.level, c.status, c.created_at, c.updated_at, COALESCE(NULLIF(TRIM(CONCAT_WS(' ', creator_staff.first_name, creator_staff.last_name)), ''), creator_user.user_id, '-') AS created_by_name, COALESCE(NULLIF(TRIM(CONCAT_WS(' ', editor_staff.first_name, editor_staff.last_name)), ''), editor_user.user_id, '-') AS updated_by_name FROM public.curricula c LEFT JOIN public.users creator_user ON creator_user.user_id = c.created_by LEFT JOIN public.staff creator_staff ON creator_staff.user_id = c.created_by LEFT JOIN public.users editor_user ON editor_user.user_id = c.updated_by LEFT JOIN public.staff editor_staff ON editor_staff.user_id = c.updated_by ORDER BY c.curriculum_id ASC");
            $subjectsStmt = $conn->query("
                SELECT c.course_id AS id, c.code, c.name, c.credits AS credit, c.description, c.image_path,
                       COALESCE(STRING_AGG(DISTINCT ct.teacher_id::text, ','), '') AS teacher_ids,
                       COALESCE(STRING_AGG(DISTINCT t.full_name, ', '), 'ยังไม่มีผู้ดูแล') AS teacher_name,
                       c.subject_type AS type, c.subject_type AS category, c.created_at, c.updated_at,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(' ', creator_staff.first_name, creator_staff.last_name)), ''), creator_user.user_id, '-') AS created_by_name,
                       COALESCE(NULLIF(TRIM(CONCAT_WS(' ', editor_staff.first_name, editor_staff.last_name)), ''), editor_user.user_id, '-') AS updated_by_name
                FROM public.courses c
                LEFT JOIN public.course_teachers ct ON ct.course_id = c.course_id
                LEFT JOIN public.teachers t ON t.user_id = ct.teacher_id
                LEFT JOIN public.users creator_user ON creator_user.user_id = c.created_by
                LEFT JOIN public.staff creator_staff ON creator_staff.user_id = c.created_by
                LEFT JOIN public.users editor_user ON editor_user.user_id = c.updated_by
                LEFT JOIN public.staff editor_staff ON editor_staff.user_id = c.updated_by
                GROUP BY c.course_id, c.code, c.name, c.credits, c.description, c.image_path, c.subject_type, c.created_at, c.updated_at,
                         creator_user.user_id, creator_staff.first_name, creator_staff.last_name,
                         editor_user.user_id, editor_staff.first_name, editor_staff.last_name
                ORDER BY c.course_id ASC
            ");
            $curriculumSubjectsStmt = $conn->query('SELECT curriculum_id, course_id, requirement_type, curriculum_order FROM public.curriculum_courses ORDER BY curriculum_id, curriculum_order NULLS LAST, course_id');
            $curriculumIssuesStmt = $conn->query("SELECT ac.curriculum_id, cu.code AS curriculum_code, cu.name AS curriculum_name,
                       c.course_id, c.code AS subject_code, c.name AS subject_name,
                       COUNT(DISTINCT sc.student_id)::integer AS affected_students
                FROM public.student_courses sc
                JOIN LATERAL (
                    SELECT curriculum_id FROM public.student_curricula
                    WHERE student_id = sc.student_id AND status = 'active'
                    ORDER BY enrolled_at DESC LIMIT 1
                ) ac ON TRUE
                JOIN public.curricula cu ON cu.curriculum_id = ac.curriculum_id
                JOIN public.courses c ON c.course_id = sc.course_id
                LEFT JOIN public.curriculum_courses cc ON cc.curriculum_id = ac.curriculum_id AND cc.course_id = c.course_id
                WHERE sc.status = 'active' AND cc.course_id IS NULL
                GROUP BY ac.curriculum_id, cu.code, cu.name, c.course_id, c.code, c.name
                ORDER BY cu.code, c.code, c.course_id");
            $teachersStmt = $conn->query("SELECT user_id AS id, full_name AS name FROM public.teachers ORDER BY full_name ASC");

            jsonResponse([
                'status' => 'success',
                'members' => fetchAllRows($membersStmt),
                'students' => fetchAllRows($studentsStmt),
                'curricula' => fetchAllRows($curriculaStmt),
                'subjects' => fetchAllRows($subjectsStmt),
                'curriculum_subjects' => fetchAllRows($curriculumSubjectsStmt),
                'curriculum_issues' => fetchAllRows($curriculumIssuesStmt),
                'teachers' => fetchAllRows($teachersStmt)
            ]);
            break;

        case 'createStaff':
            $memberId = preg_replace('/\D+/', '', postValue('user_id'));
            $password = (string) ($_POST['password'] ?? '');
            $firstName = postValue('firstname');
            $lastName = postValue('lastname');
            $email = postValue('email');
            $phone = postValue('phone');
            $requestedRole = strtolower(postValue('role', 'staff'));

            if (strlen($memberId) !== 13 || strlen($password) < 6 || $firstName === '' || $lastName === '') {
                jsonResponse(['status' => 'error', 'message' => 'ข้อมูลเจ้าหน้าที่ไม่ครบถ้วน'], 400);
            }
            if (!in_array($requestedRole, ['staff', 'director'], true)) {
                jsonResponse(['status' => 'error', 'message' => 'ระดับสิทธิ์ไม่ถูกต้อง'], 400);
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                jsonResponse(['status' => 'error', 'message' => 'รูปแบบอีเมลไม่ถูกต้อง'], 400);
            }

            $conn->beginTransaction();
            $actorId = (string) $_SESSION['user_id'];
            $conn->prepare('INSERT INTO public.users (user_id, password_hash, role, created_by, updated_by) VALUES (:id, :password_hash, :role, :created_by, :updated_by)')
                ->execute([':id' => $memberId, ':password_hash' => password_hash($password, PASSWORD_DEFAULT), ':role' => $requestedRole, ':created_by' => $actorId, ':updated_by' => $actorId]);
            $conn->prepare('INSERT INTO public.staff (user_id, first_name, last_name, email, phone, created_by, updated_by) VALUES (:id, :first_name, :last_name, :email, :phone, :created_by, :updated_by)')
                ->execute([':id' => $memberId, ':first_name' => $firstName, ':last_name' => $lastName, ':email' => $email !== '' ? $email : null, ':phone' => $phone !== '' ? $phone : null, ':created_by' => $actorId, ':updated_by' => $actorId]);
            $conn->commit();
            jsonResponse(['status' => 'success']);
            break;

        case 'saveMember':
            $memberId = postValue('id');
            $firstName = postValue('firstname');
            $lastName = postValue('lastname');
            $email = postValue('email');
            $phone = postValue('phone');
            $requestedRole = strtolower(postValue('role'));
            $accountStatus = strtolower(postValue('status', 'active'));
            $linkedStudentIds = [];
            $hasLinkedStudentIds = array_key_exists('linked_student_ids', $_POST);

            $roles = ['student' => true, 'teacher' => true, 'parent' => true, 'staff' => true, 'director' => true];

            if ($memberId === '' || $firstName === '') {
                jsonResponse(['status' => 'error', 'message' => 'กรุณาระบุรหัสสมาชิกและชื่อ'], 400);
            }
            if (!isset($roles[$requestedRole])) {
                jsonResponse(['status' => 'error', 'message' => 'บทบาทสมาชิกไม่ถูกต้อง'], 400);
            }
            if (!in_array($accountStatus, ['active', 'inactive'], true)) {
                jsonResponse(['status' => 'error', 'message' => 'สถานะบัญชีไม่ถูกต้อง'], 400);
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                jsonResponse(['status' => 'error', 'message' => 'รูปแบบอีเมลไม่ถูกต้อง'], 400);
            }
            if ($requestedRole === 'parent' && $hasLinkedStudentIds) {
                $submittedStudentIds = json_decode((string) ($_POST['linked_student_ids'] ?? '[]'), true);
                if (!is_array($submittedStudentIds)) {
                    jsonResponse(['status' => 'error', 'message' => 'รายชื่อนักเรียนไม่ถูกต้อง'], 400);
                }
                foreach ($submittedStudentIds as $studentId) {
                    if (!is_scalar($studentId)) {
                        jsonResponse(['status' => 'error', 'message' => 'รายชื่อนักเรียนไม่ถูกต้อง'], 400);
                    }
                }
                $linkedStudentIds = array_values(array_unique(array_filter(array_map(
                    static fn($id): string => trim((string) $id),
                    $submittedStudentIds
                ), static fn(string $id): bool => $id !== '')));
            }

            $userStmt = $conn->prepare('SELECT user_id FROM public.users WHERE user_id = :id FOR UPDATE');
            $conn->beginTransaction();
            $userStmt->execute([':id' => $memberId]);
            if (!$userStmt->fetchColumn()) {
                $conn->rollBack();
                jsonResponse(['status' => 'error', 'message' => 'ไม่พบสมาชิกที่ต้องการแก้ไข'], 404);
            }

            $fullName = trim($firstName . ' ' . $lastName);
            $actorId = (string) $_SESSION['user_id'];
            switch ($requestedRole) {
                case 'student':
                    $profile = $conn->prepare('SELECT 1 FROM public.students WHERE user_id = :id');
                    $profile->execute([':id' => $memberId]);
                    if ($profile->fetchColumn()) {
                        $statement = $conn->prepare('UPDATE public.students SET full_name = :name, email = :email, phone = :phone, updated_at = NOW() WHERE user_id = :id');
                    } else {
                        $statement = $conn->prepare('INSERT INTO public.students (user_id, full_name, email, phone) VALUES (:id, :name, :email, :phone)');
                    }
                    $statement->execute([':id' => $memberId, ':name' => $fullName, ':email' => $email !== '' ? $email : null, ':phone' => $phone !== '' ? $phone : null]);
                    break;

                case 'teacher':
                    $profile = $conn->prepare('SELECT 1 FROM public.teachers WHERE user_id = :id');
                    $profile->execute([':id' => $memberId]);
                    if ($profile->fetchColumn()) {
                        $statement = $conn->prepare('UPDATE public.teachers SET full_name = :name, email = :email, phone = :phone, updated_at = NOW() WHERE user_id = :id');
                    } else {
                        $statement = $conn->prepare('INSERT INTO public.teachers (user_id, full_name, email, phone) VALUES (:id, :name, :email, :phone)');
                    }
                    $statement->execute([':id' => $memberId, ':name' => $fullName, ':email' => $email !== '' ? $email : null, ':phone' => $phone !== '' ? $phone : null]);
                    break;

                case 'parent':
                    $profile = $conn->prepare('SELECT 1 FROM public.parents WHERE user_id = :id');
                    $profile->execute([':id' => $memberId]);
                    if ($profile->fetchColumn()) {
                        $statement = $conn->prepare('UPDATE public.parents SET full_name = :name, email = :email, phone = :phone, updated_at = NOW() WHERE user_id = :id');
                    } else {
                        $statement = $conn->prepare('INSERT INTO public.parents (user_id, full_name, email, phone) VALUES (:id, :name, :email, :phone)');
                    }
                    $statement->execute([':id' => $memberId, ':name' => $fullName, ':email' => $email !== '' ? $email : null, ':phone' => $phone !== '' ? $phone : null]);
                    break;

                case 'staff':
                case 'director':
                    $profile = $conn->prepare('SELECT 1 FROM public.staff WHERE user_id = :id');
                    $profile->execute([':id' => $memberId]);
                    $staffProfileExists = (bool) $profile->fetchColumn();
                    if ($staffProfileExists) {
                        $statement = $conn->prepare('UPDATE public.staff SET first_name = :firstname, last_name = :lastname, email = :email, phone = :phone, updated_by = :updated_by, updated_at = NOW() WHERE user_id = :id');
                    } else {
                        $statement = $conn->prepare('INSERT INTO public.staff (user_id, first_name, last_name, email, phone, created_by, updated_by) VALUES (:id, :firstname, :lastname, :email, :phone, :created_by, :updated_by)');
                    }
                    $staffParams = [':id' => $memberId, ':firstname' => $firstName, ':lastname' => $lastName, ':email' => $email !== '' ? $email : null, ':phone' => $phone !== '' ? $phone : null, ':updated_by' => $actorId];
                    if (!$staffProfileExists) { $staffParams[':created_by'] = $actorId; }
                    $statement->execute($staffParams);
                    break;
            }

            $statement = $conn->prepare('UPDATE public.users SET role = :role, account_status = :account_status, updated_by = :updated_by, updated_at = NOW() WHERE user_id = :id');
            $statement->execute([':id' => $memberId, ':role' => $requestedRole, ':account_status' => $accountStatus, ':updated_by' => $actorId]);

            if ($requestedRole === 'parent' && $hasLinkedStudentIds) {
                $studentParams = [':parent_id' => $memberId];
                $studentPlaceholders = [];
                foreach ($linkedStudentIds as $index => $studentId) {
                    $placeholder = ':student_' . $index;
                    $studentPlaceholders[] = $placeholder;
                    $studentParams[$placeholder] = $studentId;
                }

                if ($studentPlaceholders !== []) {
                    $availableStmt = $conn->prepare(
                        'SELECT user_id FROM public.students WHERE user_id IN (' . implode(', ', $studentPlaceholders) . ')
                         AND (parent_user_id IS NULL OR parent_user_id = :parent_id) FOR UPDATE'
                    );
                    $availableStmt->execute($studentParams);
                    if (count($availableStmt->fetchAll(PDO::FETCH_COLUMN)) !== count($linkedStudentIds)) {
                        $conn->rollBack();
                        jsonResponse(['status' => 'error', 'message' => 'นักเรียนบางคนถูกเชื่อมกับผู้ปกครองบัญชีอื่นแล้ว กรุณาโหลดข้อมูลใหม่'], 409);
                    }
                    $detachStmt = $conn->prepare(
                        'UPDATE public.students SET parent_user_id = NULL, updated_at = NOW()
                         WHERE parent_user_id = :parent_id AND user_id NOT IN (' . implode(', ', $studentPlaceholders) . ')'
                    );
                    $detachStmt->execute($studentParams);
                    $linkStmt = $conn->prepare(
                        'UPDATE public.students SET parent_user_id = :parent_id, updated_at = NOW()
                         WHERE user_id IN (' . implode(', ', $studentPlaceholders) . ')'
                    );
                    $linkStmt->execute($studentParams);
                } else {
                    $conn->prepare('UPDATE public.students SET parent_user_id = NULL, updated_at = NOW() WHERE parent_user_id = :parent_id')
                        ->execute([':parent_id' => $memberId]);
                }
            } elseif ($requestedRole !== 'parent') {
                $conn->prepare('UPDATE public.students SET parent_user_id = NULL, updated_at = NOW() WHERE parent_user_id = :parent_id')
                    ->execute([':parent_id' => $memberId]);
            }
            $conn->commit();
            jsonResponse(['status' => 'success']);
            break;

        case 'saveSubject':
            $subjectId = $_POST['id'] ?? '';
            $subjectCode = postValue('code');
            if ($subjectCode === '') {
                jsonResponse(['status' => 'error', 'message' => 'กรุณากรอกรหัสวิชา'], 400);
            }

            if (!empty($subjectId)) {
                $codeCheck = $conn->prepare('SELECT 1 FROM public.courses WHERE code = :code AND course_id <> :id');
                $codeCheck->execute([':code' => $subjectCode, ':id' => $subjectId]);
            } else {
                $codeCheck = $conn->prepare('SELECT 1 FROM public.courses WHERE code = :code');
                $codeCheck->execute([':code' => $subjectCode]);
            }
            if ($codeCheck->fetchColumn()) {
                jsonResponse(['status' => 'error', 'message' => 'รหัสวิชานี้มีอยู่แล้ว กรุณาใช้รหัสอื่น'], 409);
            }

            $teacherIds = json_decode((string) ($_POST['teacher_ids'] ?? '[]'), true);
            if (!is_array($teacherIds)) { $teacherIds = []; }

            if (!$teacherIds && postValue('teacher_id') !== '') { $teacherIds = [postValue('teacher_id')]; }
            $teacherIds = array_values(array_unique(array_filter(array_map(static fn($id) => trim((string) $id), $teacherIds))));
            if ($teacherIds) {
                $teacherCheck = $conn->prepare('SELECT 1 FROM public.teachers WHERE user_id = :id');
                foreach ($teacherIds as $teacherId) {
                    $teacherCheck->execute([':id' => $teacherId]);
                    if (!$teacherCheck->fetchColumn()) {
                        jsonResponse(['status' => 'error', 'message' => 'พบอาจารย์ที่เลือกไม่อยู่ในระบบ'], 400);
                    }
                }
            }
            $primaryTeacherId = $teacherIds[0] ?? null;

            $requestedType = strtolower(postValue('type', 'compulsory'));
            $subjectType = in_array($requestedType, ['required', 'compulsory'], true) ? 'compulsory' : 'elective';
            $imagePath = uploadCourseCover();
            $actorId = (string) $_SESSION['user_id'];
            $params = [':code' => $subjectCode, ':name' => postValue('name'), ':credit' => (int) postValue('credit', '0'), ':subject_type' => $subjectType, ':description' => postValue('description') ?: null, ':image_path' => $imagePath, ':updated_by' => $actorId];

            if (!empty($subjectId)) {
                $params[':id'] = $subjectId;
                $statement = $conn->prepare("
                    UPDATE public.courses SET code = :code, name = :name, credits = :credit, description = :description, image_path = COALESCE(:image_path, image_path), subject_type = :subject_type, updated_by = :updated_by, updated_at = NOW() WHERE course_id = :id
                ");
            } else {
                $params[':created_by'] = $actorId;
                $statement = $conn->prepare("
                    INSERT INTO public.courses (code, name, credits, description, image_path, subject_type, created_by, updated_by) VALUES (:code, :name, :credit, :description, :image_path, :subject_type, :created_by, :updated_by) RETURNING course_id
                ");
            }
            $conn->beginTransaction();
            $statement->execute($params);
            $savedSubjectId = !empty($subjectId) ? $subjectId : (string) $statement->fetchColumn();
            $conn->prepare('DELETE FROM public.course_teachers WHERE course_id = :course_id')->execute([':course_id' => $savedSubjectId]);
            if ($teacherIds) {
                $assignment = $conn->prepare('INSERT INTO public.course_teachers (course_id, teacher_id) VALUES (:course_id, :teacher_id)');
                foreach ($teacherIds as $teacherId) {
                    $assignment->execute([':course_id' => $savedSubjectId, ':teacher_id' => $teacherId]);
                }
            }
            $conn->commit();
            jsonResponse(['status' => 'success']);
            break;

        case 'deleteSubject':
            $subjectId = trim((string) ($_POST['id'] ?? ''));
            if ($subjectId === '') { jsonResponse(['status' => 'error', 'message' => 'Missing ID'], 400); }

            $conn->beginTransaction();
            $exists = $conn->prepare('SELECT image_path FROM public.courses WHERE course_id = :id FOR UPDATE');
            $exists->execute([':id' => $subjectId]);
            $courseImage = $exists->fetchColumn();
            if ($courseImage === false) {
                $conn->rollBack();
                jsonResponse(['status' => 'error', 'message' => 'ไม่พบรายวิชาที่ต้องการลบ'], 404);
            }
            $resources = $conn->prepare('SELECT r.url FROM public.lesson_resources r JOIN public.lessons l ON l.lesson_id = r.lesson_id WHERE l.course_id = :id');
            $resources->execute([':id' => $subjectId]);
            $resourceUrls = $resources->fetchAll(PDO::FETCH_COLUMN);
            $lessons = $conn->prepare('SELECT lesson_id FROM public.lessons WHERE course_id = :id');
            $lessons->execute([':id' => $subjectId]);
            $lessonIds = $lessons->fetchAll(PDO::FETCH_COLUMN);
            if ($lessonIds) {
                $ids = implode(',', array_map('intval', $lessonIds));
                $conn->exec("DELETE FROM public.quiz_attempt_answers WHERE attempt_id IN (SELECT attempt_id FROM public.quiz_attempts WHERE lesson_id IN ({$ids}))");
                $conn->exec("DELETE FROM public.quiz_attempts WHERE lesson_id IN ({$ids})");
                $conn->exec("DELETE FROM public.quiz_attempt_sessions WHERE lesson_id IN ({$ids})");
                $conn->exec("DELETE FROM public.lesson_progress WHERE lesson_id IN ({$ids})");
                $conn->exec("DELETE FROM public.transcript_request_lessons WHERE lesson_id IN ({$ids})");
                $conn->exec("DELETE FROM public.questions WHERE lesson_id IN ({$ids})");
                $conn->exec("DELETE FROM public.lesson_resources WHERE lesson_id IN ({$ids})");
            }
            deleteRowsIfTableExists($conn, 'course_certificates', 'course_id = :id', [':id' => $subjectId]);
            $conn->prepare('DELETE FROM public.course_teachers WHERE course_id = :id')->execute([':id' => $subjectId]);
            $conn->prepare('DELETE FROM public.curriculum_courses WHERE course_id = :id')->execute([':id' => $subjectId]);
            $conn->prepare('DELETE FROM public.student_courses WHERE course_id = :id')->execute([':id' => $subjectId]);
            $conn->prepare('DELETE FROM public.courses WHERE course_id = :id')->execute([':id' => $subjectId]);
            $conn->commit();
            deleteUnreferencedLessonFiles($conn, array_merge($resourceUrls, [(string) $courseImage]));
            jsonResponse(['status' => 'success']);
            break;

        case 'getCurriculumSubjects':
            $curriculumId = $_GET['curriculum_id'] ?? '';
            if (empty($curriculumId)) { jsonResponse(['status' => 'error', 'message' => 'Missing ID'], 400); }
            $allSubjectsStmt = $conn->query("SELECT course_id AS id, code, name FROM public.courses ORDER BY code ASC NULLS LAST, course_id ASC");
            $selectedStmt = $conn->prepare("SELECT course_id, requirement_type, curriculum_order FROM public.curriculum_courses WHERE curriculum_id = :id ORDER BY curriculum_order NULLS LAST, course_id");
            $selectedStmt->execute([':id' => $curriculumId]);
            $selectedRows = $selectedStmt->fetchAll(PDO::FETCH_ASSOC);
            $selectedTypes = [];
            $selectedOrders = [];
            foreach ($selectedRows as $selectedRow) {
                $selectedTypes[(string) $selectedRow['course_id']] = $selectedRow['requirement_type'] === 'required' ? 'compulsory' : 'elective';
                $selectedOrders[(string) $selectedRow['course_id']] = $selectedRow['curriculum_order'] !== null ? (int) $selectedRow['curriculum_order'] : null;
            }
            jsonResponse([
                'status' => 'success',
                'curriculum_id' => (string) $curriculumId,
                'subjects' => fetchAllRows($allSubjectsStmt),
                'selected' => array_column($selectedRows, 'course_id'),
                'selected_types' => $selectedTypes,
                'selected_orders' => $selectedOrders
            ]);
            break;

        case 'saveCurriculumSubjects':
            $curriculumId = trim((string) ($_POST['curriculum_id'] ?? ''));
            $subjectIds = isset($_POST['subjects']) ? json_decode($_POST['subjects'], true) : [];
            if ($curriculumId === '' || !ctype_digit($curriculumId) || !is_array($subjectIds)) { jsonResponse(['status' => 'error', 'message' => 'ข้อมูลหลักสูตรหรือรายวิชาไม่ถูกต้อง'], 400); }
            $conn->beginTransaction();
            try {
                $actorId = (string) $_SESSION['user_id'];
                $existingStmt = $conn->prepare('SELECT course_id FROM public.curriculum_courses WHERE curriculum_id = :id');
                $existingStmt->execute([':id' => $curriculumId]);
                $existingIds = array_map('strval', $existingStmt->fetchAll(PDO::FETCH_COLUMN));
                $selectedIds = [];
                $insertStmt = $conn->prepare("INSERT INTO public.curriculum_courses (curriculum_id, course_id, requirement_type, curriculum_order) SELECT :cid, course_id, :requirement_type, :curriculum_order FROM public.courses WHERE course_id = :sid ON CONFLICT (curriculum_id, course_id) DO UPDATE SET requirement_type = EXCLUDED.requirement_type, curriculum_order = EXCLUDED.curriculum_order, updated_at = NOW()");
                if (!empty($subjectIds) && is_array($subjectIds)) {
                    $usedOrders = [];
                    $usedSubjects = [];
                    foreach ($subjectIds as $selection) {
                        if (is_array($selection)) {
                            $sid = trim((string) ($selection['id'] ?? ''));
                            $type = strtolower(trim((string) ($selection['type'] ?? 'elective')));
                            $order = filter_var($selection['order'] ?? null, FILTER_VALIDATE_INT);
                        } else {
                            $sid = trim((string) $selection);
                            $type = 'elective';
                            $order = false;
                        }
                        if ($sid === '' || !ctype_digit($sid) || !in_array($type, ['compulsory', 'elective'], true)) throw new RuntimeException('ประเภทวิชาในหลักสูตรไม่ถูกต้อง');
                        if ($order === false || $order < 1 || isset($usedOrders[$order])) throw new RuntimeException('กรุณากำหนดลำดับหลักสูตรเป็นจำนวนเต็มบวกที่ไม่ซ้ำกัน');
                        if (isset($usedSubjects[$sid])) throw new RuntimeException('พบรายวิชาซ้ำในรายการหลักสูตร');
                        $usedOrders[$order] = true;
                        $usedSubjects[$sid] = true;
                        $selectedIds[] = $sid;
                        $insertStmt->execute([':cid' => $curriculumId, ':sid' => $sid, ':requirement_type' => $type === 'compulsory' ? 'required' : 'elective', ':curriculum_order' => $order]);
                        if ($insertStmt->rowCount() < 1) throw new RuntimeException('ไม่พบรายวิชาที่ต้องการบันทึก');
                    }
                }
                $removeStmt = $conn->prepare('DELETE FROM public.curriculum_courses WHERE curriculum_id = :cid AND course_id = :sid');
                foreach (array_diff($existingIds, $selectedIds) as $removedId) $removeStmt->execute([':cid' => $curriculumId, ':sid' => $removedId]);
                $conn->prepare('UPDATE public.curricula SET updated_by = :updated_by, updated_at = NOW() WHERE curriculum_id = :id')
                    ->execute([':updated_by' => $actorId, ':id' => $curriculumId]);
                $conn->commit();
                jsonResponse(['status' => 'success']);
            } catch (Exception $e) { $conn->rollBack(); throw $e; }
            break;

        case 'saveCurriculum':
            $curriculumId = $_POST['id'] ?? '';
            $code = strtoupper(postValue('code'));
            $name = postValue('name');
            $status = postValue('status', 'active');
            if ($status === 'inactive') { $status = 'archived'; }
            if ($code === '' || $name === '') { jsonResponse(['status' => 'error', 'message' => 'กรุณากรอกรหัสและชื่อหลักสูตร'], 400); }
            if (!in_array($status, ['draft', 'active', 'archived'], true)) { jsonResponse(['status' => 'error', 'message' => 'สถานะหลักสูตรไม่ถูกต้อง'], 400); }
            $duplicate = $conn->prepare('SELECT 1 FROM public.curricula WHERE code = :code AND curriculum_id <> COALESCE(NULLIF(:id, \'\')::bigint, 0)');
            $duplicate->execute([':code' => $code, ':id' => (string) $curriculumId]);
            if ($duplicate->fetchColumn()) { jsonResponse(['status' => 'error', 'message' => 'รหัสหลักสูตรนี้มีอยู่แล้ว กรุณาใช้รหัสอื่น หรือเลือกแก้ไขหลักสูตรเดิม'], 409); }
            $actorId = (string) $_SESSION['user_id'];
            $params = [':code' => $code, ':name' => $name, ':level' => postValue('level', 'ม.ปลาย'), ':status' => $status, ':updated_by' => $actorId];
            if (!empty($curriculumId)) {
                $params[':id'] = $curriculumId;
                $statement = $conn->prepare("UPDATE public.curricula SET code = :code, name = :name, level = :level, status = :status, updated_by = :updated_by, updated_at = NOW() WHERE curriculum_id = :id");
            } else {
                $params[':created_by'] = $actorId;
                $statement = $conn->prepare("INSERT INTO public.curricula (code, name, level, status, created_by, updated_by) VALUES (:code, :name, :level, :status, :created_by, :updated_by)");
            }
            $statement->execute($params);
            jsonResponse(['status' => 'success']);
            break;

        case 'deleteCurriculum':
            $curriculumId = trim((string) ($_POST['id'] ?? ''));
            if ($curriculumId === '') { jsonResponse(['status' => 'error', 'message' => 'Missing ID'], 400); }

            $conn->beginTransaction();
            $exists = $conn->prepare('SELECT 1 FROM public.curricula WHERE curriculum_id = :id FOR UPDATE');
            $exists->execute([':id' => $curriculumId]);
            if (!$exists->fetchColumn()) {
                $conn->rollBack();
                jsonResponse(['status' => 'error', 'message' => 'ไม่พบหลักสูตรที่ต้องการลบ'], 404);
            }

            $conn->prepare('DELETE FROM public.transcript_request_lessons WHERE request_id IN (SELECT tr.request_id FROM public.transcript_requests tr JOIN public.students s ON s.user_id = tr.student_id JOIN public.student_curricula sc ON sc.student_id = s.user_id WHERE sc.curriculum_id = :id)')->execute([':id' => $curriculumId]);
            $conn->prepare('DELETE FROM public.transcript_requests WHERE student_id IN (SELECT student_id FROM public.student_curricula WHERE curriculum_id = :id)')->execute([':id' => $curriculumId]);
            $conn->prepare('DELETE FROM public.lesson_progress WHERE student_id IN (SELECT student_id FROM public.student_curricula WHERE curriculum_id = :id)')->execute([':id' => $curriculumId]);
            $conn->prepare('DELETE FROM public.quiz_attempt_answers WHERE attempt_id IN (SELECT attempt_id FROM public.quiz_attempts WHERE student_id IN (SELECT student_id FROM public.student_curricula WHERE curriculum_id = :id))')->execute([':id' => $curriculumId]);
            $conn->prepare('DELETE FROM public.quiz_attempts WHERE student_id IN (SELECT student_id FROM public.student_curricula WHERE curriculum_id = :id)')->execute([':id' => $curriculumId]);
            $conn->prepare('DELETE FROM public.quiz_attempt_sessions WHERE student_id IN (SELECT student_id FROM public.student_curricula WHERE curriculum_id = :id)')->execute([':id' => $curriculumId]);
            $conn->prepare('DELETE FROM public.exam_logs WHERE student_id IN (SELECT student_id FROM public.student_curricula WHERE curriculum_id = :id)')->execute([':id' => $curriculumId]);
            $conn->prepare('DELETE FROM public.student_curricula WHERE curriculum_id = :id')->execute([':id' => $curriculumId]);
            $conn->prepare('DELETE FROM public.curriculum_courses WHERE curriculum_id = :id')->execute([':id' => $curriculumId]);
            $conn->prepare('DELETE FROM public.curricula WHERE curriculum_id = :id')->execute([':id' => $curriculumId]);
            $conn->commit();
            jsonResponse(['status' => 'success']);
            break;

        case 'getLessons':
            $subjectId = $_GET['subject_id'] ?? '';
            if (empty($subjectId)) { jsonResponse(['status' => 'error', 'message' => 'Missing ID'], 400); }
            $lessonStmt = $conn->prepare("
                SELECT
                    l.lesson_id AS id, l.title, l.content, l.created_at, l.updated_at,
                    l.created_by, l.updated_by,
                    COALESCE(d.url, '') AS document_path, COALESCE(d.title, '') AS document_name
                FROM public.lessons
                l LEFT JOIN LATERAL (
                    SELECT title, url FROM public.lesson_resources
                    WHERE lesson_id = l.lesson_id AND resource_type = 'document' ORDER BY position LIMIT 1
                ) d ON true
                WHERE l.course_id = :subject_id
                ORDER BY l.position ASC, l.lesson_id ASC
            ");
            $lessonStmt->execute([':subject_id' => $subjectId]);
            jsonResponse(['status' => 'success', 'lessons' => fetchAllRows($lessonStmt)]);
            break;

        case 'getSubjectEditorData':
            $subjectId = $_GET['subject_id'] ?? '';
            if (empty($subjectId)) { jsonResponse(['status' => 'error', 'message' => 'Missing ID'], 400); }
            $subjectStmt = $conn->prepare("
                SELECT
                    course_id AS id, code, name, credits AS credit, subject_type AS type, subject_type AS category
                FROM public.courses
                WHERE course_id = :id
                LIMIT 1
            ");
            $subjectStmt->execute([':id' => $subjectId]);
            $subject = $subjectStmt->fetch(PDO::FETCH_ASSOC);
            if (!$subject) {
                jsonResponse(['status' => 'error', 'message' => 'Subject not found'], 404);
            }

            $lessonStmt = $conn->prepare("
                SELECT
                    l.lesson_id AS id, l.title, l.content, l.created_at, l.updated_at,
                    COALESCE(l.updated_at, l.created_at) AS last_modified_at,
                    COALESCE(NULLIF(TRIM(lesson_editor.full_name), ''), NULLIF(TRIM(CONCAT_WS(' ', staff_editor.first_name, staff_editor.last_name)), ''), editor_user.user_id, l.updated_by, l.created_by, 'ยังไม่มีข้อมูล') AS editor_name,
                    COALESCE(d.url, '') AS document_path, COALESCE(d.title, '') AS document_name
                FROM public.lessons l
                LEFT JOIN public.teachers lesson_editor ON lesson_editor.user_id = COALESCE(l.updated_by, l.created_by)
                LEFT JOIN public.staff staff_editor ON staff_editor.user_id = COALESCE(l.updated_by, l.created_by)
                LEFT JOIN public.users editor_user ON editor_user.user_id = COALESCE(l.updated_by, l.created_by)
                LEFT JOIN LATERAL (
                    SELECT title, url FROM public.lesson_resources WHERE lesson_id = l.lesson_id AND resource_type = 'document' ORDER BY position LIMIT 1
                ) d ON true
                WHERE l.course_id = :subject_id
                ORDER BY l.position ASC, l.lesson_id ASC
            ");
            $lessonStmt->execute([':subject_id' => $subjectId]);

            jsonResponse([
                'status' => 'success',
                'subject' => $subject,
                'lessons' => fetchAllRows($lessonStmt),
            ]);
            break;

        case 'saveLesson':
            $lessonId = $_POST['id'] ?? '';
            $subjectId = $_POST['subject_id'] ?? '';
            if (empty($subjectId)) { jsonResponse(['status' => 'error', 'message' => 'Missing ID'], 400); }
            $isNewLesson = trim((string) $lessonId) === '';
            $documentUpload = uploadLessonFile('document', ['courses', sanitizePathSegment((string) $subjectId), 'documents'], 'lesson_doc');
            if ($documentUpload['path'] !== '' && strtolower(pathinfo($documentUpload['name'], PATHINFO_EXTENSION)) !== 'pdf') {
                @unlink(__DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $documentUpload['path']));
                jsonResponse(['status' => 'error', 'message' => 'เอกสารประกอบต้องเป็นไฟล์ PDF'], 400);
            }
            $actorId = (string) $_SESSION['user_id'];
            $params = [':course_id' => $subjectId, ':title' => postValue('title'), ':content' => postValue('content'), ':updated_by' => $actorId];
            if ($isNewLesson) {
                $params[':created_by'] = $actorId;
                $statement = $conn->prepare('INSERT INTO public.lessons (course_id, title, content, position, created_by, updated_by) VALUES (:course_id, :title, :content, (SELECT COALESCE(MAX(position), 0) + 1 FROM public.lessons WHERE course_id = :course_id), :created_by, :updated_by) RETURNING lesson_id');
            } else {
                $params[':id'] = $lessonId;
                $statement = $conn->prepare('UPDATE public.lessons SET title = :title, content = :content, updated_by = :updated_by, updated_at = NOW() WHERE lesson_id = :id AND course_id = :course_id');
            }
            $statement->execute($params);
            if ($isNewLesson) $lessonId = (string) $statement->fetchColumn();
            if ($documentUpload['path'] !== '') {
                $documentStmt = $conn->prepare("INSERT INTO public.lesson_resources (lesson_id, resource_type, title, url, position) VALUES (:lesson_id, 'document', :title, :url, 1)");
                $documentStmt->execute([':lesson_id' => $lessonId, ':title' => $documentUpload['name'], ':url' => $documentUpload['path']]);
            }
            jsonResponse(['status' => 'success', 'lesson_id' => (string) $lessonId]);
            break;

        case 'deleteLesson':
            $lessonId = trim((string) ($_POST['id'] ?? ''));
            $subjectId = trim((string) ($_POST['subject_id'] ?? ''));
            if ($lessonId === '' || $subjectId === '') { jsonResponse(['status' => 'error', 'message' => 'Missing lesson ID'], 400); }

            $conn->beginTransaction();
            $exists = $conn->prepare('SELECT 1 FROM public.lessons WHERE lesson_id = :id AND course_id = :course_id FOR UPDATE');
            $exists->execute([':id' => $lessonId, ':course_id' => $subjectId]);
            if (!$exists->fetchColumn()) {
                $conn->rollBack();
                jsonResponse(['status' => 'error', 'message' => 'ไม่พบบทเรียนที่ต้องการลบ'], 404);
            }

            $resources = $conn->prepare('SELECT url FROM public.lesson_resources WHERE lesson_id = :id');
            $resources->execute([':id' => $lessonId]);
            $resourceUrls = $resources->fetchAll(PDO::FETCH_COLUMN);
            $conn->prepare('DELETE FROM public.quiz_attempt_answers WHERE attempt_id IN (SELECT attempt_id FROM public.quiz_attempts WHERE lesson_id = :id)')->execute([':id' => $lessonId]);
            $conn->prepare('DELETE FROM public.quiz_attempts WHERE lesson_id = :id')->execute([':id' => $lessonId]);
            $conn->prepare('DELETE FROM public.quiz_attempt_sessions WHERE lesson_id = :id')->execute([':id' => $lessonId]);
            $conn->prepare('DELETE FROM public.lesson_progress WHERE lesson_id = :id')->execute([':id' => $lessonId]);
            $conn->prepare('DELETE FROM public.transcript_request_lessons WHERE lesson_id = :id')->execute([':id' => $lessonId]);
            $conn->prepare('DELETE FROM public.questions WHERE lesson_id = :id')->execute([':id' => $lessonId]);
            $conn->prepare('DELETE FROM public.lesson_resources WHERE lesson_id = :id')->execute([':id' => $lessonId]);
            $conn->prepare('DELETE FROM public.lessons WHERE lesson_id = :id AND course_id = :course_id')->execute([':id' => $lessonId, ':course_id' => $subjectId]);
            $conn->commit();
            deleteUnreferencedLessonFiles($conn, $resourceUrls);
            jsonResponse(['status' => 'success']);
            break;

        case 'getVideoEditorData':
            $subjectId = trim((string) ($_GET['subject_id'] ?? ''));
            if ($subjectId === '') { jsonResponse(['status' => 'error', 'message' => 'Missing subject ID'], 400); }

            $subjectStmt = $conn->prepare("SELECT course_id AS id, name FROM public.courses WHERE course_id = :id LIMIT 1");
            $subjectStmt->execute([':id' => $subjectId]);
            $subject = $subjectStmt->fetch(PDO::FETCH_ASSOC);
            if (!$subject) { jsonResponse(['status' => 'error', 'message' => 'ไม่พบรายวิชานี้'], 404); }

            $lessonStmt = $conn->prepare("SELECT lesson_id AS id, title FROM public.lessons WHERE course_id = :subject_id ORDER BY position ASC, lesson_id ASC");
            $lessonStmt->execute([':subject_id' => $subjectId]);

            $videoStmt = $conn->prepare("
                SELECT
                    r.resource_id AS id, r.title, r.url, '' AS description, r.duration_seconds,
                    r.position AS display_order, r.lesson_id AS lessons_id, l.title AS lesson_title,
                    EXTRACT(EPOCH FROM COALESCE(r.updated_at, r.created_at)) AS last_modified_epoch,
                    COALESCE(NULLIF(TRIM(video_editor.full_name), ''), NULLIF(TRIM(CONCAT_WS(' ', staff_editor.first_name, staff_editor.last_name)), ''), editor_user.user_id, r.updated_by, 'ยังไม่มีข้อมูล') AS editor_name
                FROM public.lesson_resources r
                INNER JOIN public.lessons l ON l.lesson_id = r.lesson_id
                LEFT JOIN public.teachers video_editor ON video_editor.user_id = r.updated_by
                LEFT JOIN public.staff staff_editor ON staff_editor.user_id = r.updated_by
                LEFT JOIN public.users editor_user ON editor_user.user_id = r.updated_by
                WHERE l.course_id = :subject_id AND r.resource_type = 'video'
                ORDER BY r.position ASC, r.resource_id ASC
            ");
            $videoStmt->execute([':subject_id' => $subjectId]);

            jsonResponse([
                'status' => 'success',
                'subject' => $subject,
                'lessons' => fetchAllRows($lessonStmt),
                'videos' => fetchAllRows($videoStmt),
            ]);
            break;

        case 'saveVideo':
            $subjectId = postValue('subject_id');
            $videoId = trim((string) ($_POST['id'] ?? ''));
            $title = postValue('title');
            if ($subjectId === '' || $title === '') { jsonResponse(['status' => 'error', 'message' => 'กรุณาระบุวิชาและชื่อวิดีโอ'], 400); }

            $currentUrl = '';
            if ($videoId !== '') {
                $currentStmt = $conn->prepare("SELECT r.url FROM public.lesson_resources r INNER JOIN public.lessons l ON l.lesson_id = r.lesson_id WHERE r.resource_id = :id AND l.course_id = :subject_id AND r.resource_type = 'video' LIMIT 1");
                $currentStmt->execute([':id' => $videoId, ':subject_id' => $subjectId]);
                $currentUrl = (string) ($currentStmt->fetchColumn() ?: '');
                if ($currentUrl === '') { jsonResponse(['status' => 'error', 'message' => 'ไม่พบวิดีโอที่ต้องการแก้ไข'], 404); }
            }

            $upload = uploadLessonFile('video_file', ['courses', sanitizePathSegment($subjectId), 'videos'], 'video');
            $videoUrl = postValue('url');
            if ($upload['path'] !== '') {
                $videoUrl = $upload['path'];
            } elseif ($videoUrl === '') {
                $videoUrl = $currentUrl;
            }
            if ($videoUrl === '') { jsonResponse(['status' => 'error', 'message' => 'กรุณาอัปโหลดไฟล์วิดีโอหรือระบุลิงก์วิดีโอ'], 400); }

            $lessonId = postValue('lesson_id');
            if ($lessonId !== '') {
                $lessonStmt = $conn->prepare("SELECT 1 FROM public.lessons WHERE lesson_id = :lesson_id AND course_id = :subject_id");
                $lessonStmt->execute([':lesson_id' => $lessonId, ':subject_id' => $subjectId]);
                if (!$lessonStmt->fetchColumn()) { jsonResponse(['status' => 'error', 'message' => 'บทเรียนที่เลือกไม่ได้อยู่ในรายวิชานี้'], 400); }
            }

            $params = [
                ':title' => $title,
                ':url' => $videoUrl,
                ':duration' => postValue('duration') !== '' ? (int) postValue('duration') : null,
                ':display_order' => max(1, (int) (postValue('display_order', '1') ?: 1)),
                ':lesson_id' => $lessonId !== '' ? $lessonId : null,
                ':updated_by' => (string) ($_SESSION['user_id'] ?? ''),
            ];
            if ($videoId === '') {
                if ($lessonId === '') { jsonResponse(['status' => 'error', 'message' => 'กรุณาเลือกบทเรียนสำหรับวิดีโอ'], 400); }
                $statement = $conn->prepare("INSERT INTO public.lesson_resources (lesson_id, resource_type, title, url, duration_seconds, position, updated_at, updated_by) VALUES (:lesson_id, 'video', :title, :url, :duration, :display_order, clock_timestamp(), :updated_by)");
            } else {
                $params[':id'] = $videoId;
                $statement = $conn->prepare("UPDATE public.lesson_resources SET title = :title, url = :url, duration_seconds = :duration, position = :display_order, lesson_id = :lesson_id, updated_at = clock_timestamp(), updated_by = :updated_by WHERE resource_id = :id AND resource_type = 'video'");
            }
            $statement->execute($params);
            jsonResponse(['status' => 'success']);
            break;

        case 'deleteVideo':
            $videoId = trim((string) ($_POST['id'] ?? ''));
            $subjectId = postValue('subject_id');
            if ($videoId === '' || $subjectId === '') { jsonResponse(['status' => 'error', 'message' => 'Missing video ID'], 400); }
            $conn->beginTransaction();
            $urlStmt = $conn->prepare("SELECT r.url FROM public.lesson_resources r JOIN public.lessons l ON l.lesson_id = r.lesson_id WHERE r.resource_id = :id AND l.course_id = :subject_id AND r.resource_type = 'video' FOR UPDATE OF r");
            $urlStmt->execute([':id' => $videoId, ':subject_id' => $subjectId]);
            $videoUrl = $urlStmt->fetchColumn();
            if ($videoUrl === false) { $conn->rollBack(); jsonResponse(['status' => 'error', 'message' => 'ไม่พบวิดีโอที่ต้องการลบ'], 404); }
            $conn->prepare("DELETE FROM public.lesson_resources WHERE resource_id = :id AND resource_type = 'video'")->execute([':id' => $videoId]);
            $conn->commit();
            deleteUnreferencedLessonFiles($conn, [(string) $videoUrl]);
            jsonResponse(['status' => 'success']);
            break;

        case 'deleteMember':
            $memberId = trim((string) ($_POST['id'] ?? ''));
            if ($memberId === '') { jsonResponse(['status' => 'error', 'message' => 'Missing ID'], 400); }
            if ($memberId === (string) $_SESSION['user_id']) { jsonResponse(['status' => 'error', 'message' => 'ไม่สามารถระงับบัญชีที่กำลังใช้งานอยู่ได้'], 400); }
            $statement = $conn->prepare("UPDATE public.users SET account_status = 'inactive', updated_by = :updated_by, updated_at = NOW() WHERE user_id = :id");
            $statement->execute([':id' => $memberId, ':updated_by' => (string) $_SESSION['user_id']]);
            if ($statement->rowCount() === 0) {
                $exists = $conn->prepare('SELECT 1 FROM public.users WHERE user_id = :id');
                $exists->execute([':id' => $memberId]);
                if (!$exists->fetchColumn()) { jsonResponse(['status' => 'error', 'message' => 'ไม่พบสมาชิกที่ต้องการระงับ'], 404); }
            }
            jsonResponse(['status' => 'success']);
            break;

        case 'permanentlyDeleteMember':
            $memberId = trim((string) ($_POST['id'] ?? ''));
            $actorId = (string) $_SESSION['user_id'];
            if ($memberId === '') { jsonResponse(['status' => 'error', 'message' => 'Missing ID'], 400); }
            if ($memberId === $actorId) { jsonResponse(['status' => 'error', 'message' => 'ไม่สามารถลบบัญชีที่กำลังใช้งานอยู่ได้'], 400); }

            $statusStmt = $conn->prepare('SELECT account_status FROM public.users WHERE user_id = :id');
            $statusStmt->execute([':id' => $memberId]);
            $accountStatus = $statusStmt->fetchColumn();
            if ($accountStatus === false) { jsonResponse(['status' => 'error', 'message' => 'ไม่พบบัญชีผู้ใช้งาน'], 404); }
            if ($accountStatus !== 'inactive') { jsonResponse(['status' => 'error', 'message' => 'ลบถาวรได้เฉพาะบัญชีที่ระงับแล้ว'], 409); }

            deleteMemberRecord($conn, $memberId, $actorId);
            jsonResponse(['status' => 'success']);
            break;

        default:
            jsonResponse(['status' => 'error', 'message' => 'Invalid action'], 400);
    }
} catch (Throwable $exception) {
    if ($conn->inTransaction()) { $conn->rollBack(); }
    if ($exception instanceof PDOException) {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        if ($sqlState === '23505' && str_contains($exception->getMessage(), 'courses_code_key')) {
            jsonResponse(['status' => 'error', 'message' => 'รหัสวิชานี้มีอยู่แล้ว กรุณาใช้รหัสอื่น'], 409);
        }
    }
    jsonResponse(['status' => 'error', 'message' => 'DB Error: ' . $exception->getMessage()], 500);
}
