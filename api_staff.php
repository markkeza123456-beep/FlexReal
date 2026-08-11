<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db_connect.php';

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

function ensureLessonMediaColumns(PDO $conn): void
{
    $conn->exec("ALTER TABLE public.lessons ADD COLUMN IF NOT EXISTS document_path VARCHAR(255)");
    $conn->exec("ALTER TABLE public.lessons ADD COLUMN IF NOT EXISTS document_name VARCHAR(255)");
    $conn->exec("ALTER TABLE public.lessons ADD COLUMN IF NOT EXISTS video_path VARCHAR(255)");
    $conn->exec("ALTER TABLE public.lessons ADD COLUMN IF NOT EXISTS video_name VARCHAR(255)");
}

function uploadLessonFile(string $fieldName, string $subDir, string $prefix): array
{
    if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) {
        return ['path' => '', 'name' => ''];
    }

    $file = $_FILES[$fieldName];
    if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['path' => '', 'name' => ''];
    }

    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $subDir;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $originalName = trim((string) ($file['name'] ?? ''));
    $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($originalName));
    $safeName = $safeName !== '' ? $safeName : ($prefix . '_' . time());
    $targetName = $prefix . '_' . time() . '_' . $safeName;
    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $targetName;

    if (!move_uploaded_file((string) $file['tmp_name'], $targetPath)) {
        return ['path' => '', 'name' => ''];
    }

    return [
        'path' => 'uploads/' . $subDir . '/' . $targetName,
        'name' => $originalName !== '' ? $originalName : $targetName,
    ];
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
            // 1. ดึงข้อมูลสมาชิกทั้งหมด
            $membersStmt = $conn->query('
                SELECT 
                    u.user_id as id,
                    u.status as role,
                    \'active\' as status_account,
                    CASE 
                        WHEN u.status = \'Student\' THEN COALESCE(s.student_name, \'-\')
                        WHEN u.status = \'Teacher\' THEN COALESCE(t.teachers_name, \'-\')
                        WHEN u.status = \'Parent\'  THEN COALESCE(p.parents_name, \'-\')
                        WHEN u.status = \'Staff\'   THEN COALESCE(stf.firstname || \' \' || stf.lastname, \'-\')
                        ELSE \'-\'
                    END as name,
                    CASE 
                        WHEN u.status = \'Student\' THEN COALESCE(s.email, \'-\')
                        WHEN u.status = \'Teacher\' THEN COALESCE(t.email, \'-\')
                        WHEN u.status = \'Parent\'  THEN COALESCE(p.email, \'-\')
                        ELSE \'-\'
                    END as email
                FROM public."User" u
                LEFT JOIN public.student s  ON u.user_id = s.student_id
                LEFT JOIN public.teachers t ON u.user_id = t.teachers_id
                LEFT JOIN public.parents p  ON u.user_id = p.parents_id
                LEFT JOIN public.staff stf   ON u.user_id = stf.user_id
                ORDER BY u.user_id DESC
            ');

            // 2. ดึงข้อมูลหลักสูตร
            $curriculaStmt = $conn->query("
                SELECT 
                    curriculums_id AS id, 
                    curriculums_id AS code, 
                    curriculums_name AS name, 
                    COALESCE(level, 'ม.ปลาย') AS level, 
                    COALESCE(status, 'active') AS status 
                FROM public.curriculums 
                ORDER BY curriculums_id DESC
            ");
            
            // 3. ดึงข้อมูลรายวิชา (เชื่อม JOIN ดึงชื่ออาจารย์ผู้รับผิดชอบมาแสดงด้วย)
            $subjectsStmt = $conn->query("
                SELECT 
                    s.subjects_id AS id, 
                    COALESCE(s.code, s.subjects_id) AS code, 
                    s.subjects_name AS name, 
                    COALESCE(s.credit, 0) AS credit,
                    'required' AS type,
                    s.teachers_id,
                    COALESCE(t.teachers_name, 'ยังไม่มีผู้ดูแล') AS teacher_name
                FROM public.subjects s
                LEFT JOIN public.teachers t ON s.teachers_id = t.teachers_id
                ORDER BY s.subjects_id DESC
            ");

            // 4. ดึงรายชื่ออาจารย์ทั้งหมดเพื่อนำไปใช้เลือกใน Dropdown หน้าบ้าน (สำคัญ)
            $teachersStmt = $conn->query("
                SELECT teachers_id AS id, teachers_name AS name 
                FROM public.teachers 
                ORDER BY teachers_name ASC
            ");

            jsonResponse([
                'status' => 'success',
                'members' => fetchAllRows($membersStmt),
                'curricula' => fetchAllRows($curriculaStmt),
                'subjects' => fetchAllRows($subjectsStmt),
                'teachers' => fetchAllRows($teachersStmt)
            ]);
            break;

        case 'saveSubject':
            $subjectId = $_POST['id'] ?? '';
            // รับค่าอาจารย์ผู้ดูแลรายวิชามาจากฟอร์มหน้าบ้าน
            $teacherId = postValue('teacher_id', null);
            if ($teacherId === '') { $teacherId = null; }

            $params = [
                ':code' => postValue('code'),
                ':name' => postValue('name'),
                ':credit' => (int) postValue('credit', '0'),
                ':teacher_id' => $teacherId
            ];

            if (!empty($subjectId)) {
                $params[':id'] = $subjectId;
                $statement = $conn->prepare("
                    UPDATE public.subjects 
                    SET code = :code, 
                        subjects_name = :name, 
                        credit = :credit,
                        teachers_id = :teacher_id
                    WHERE subjects_id = :id
                ");
            } else {
                // รันรหัสวิชาอัตโนมัติ (SUB001, SUB002...)
                $stmtId = $conn->query("SELECT subjects_id FROM public.subjects WHERE subjects_id LIKE 'SUB%' ORDER BY LENGTH(subjects_id) DESC, subjects_id DESC LIMIT 1");
                $lastId = $stmtId->fetchColumn();
                $nextNum = $lastId ? intval(substr($lastId, 3)) + 1 : 1;
                $newId = 'SUB' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
                
                $params[':id'] = $newId;
                $statement = $conn->prepare("
                    INSERT INTO public.subjects (subjects_id, code, subjects_name, credit, teachers_id) 
                    VALUES (:id, :code, :name, :credit, :teacher_id)
                ");
            }

            $statement->execute($params);
            jsonResponse(['status' => 'success']);
            break;

        case 'deleteSubject':
            $subjectId = $_POST['id'] ?? '';
            $statement = $conn->prepare("DELETE FROM public.subjects WHERE subjects_id = :id");
            $statement->execute([':id' => $subjectId]);
            jsonResponse(['status' => 'success']);
            break;

        case 'getCurriculumSubjects':
            $curriculumId = $_GET['curriculum_id'] ?? '';
            if (empty($curriculumId)) { jsonResponse(['status' => 'error', 'message' => 'Missing ID'], 400); }
            $allSubjectsStmt = $conn->query("SELECT subjects_id AS id, COALESCE(code, subjects_id) AS code, subjects_name AS name FROM public.subjects ORDER BY subjects_id ASC");
            $selectedStmt = $conn->prepare("SELECT subject_id FROM public.curriculums_subject WHERE curriculums_id = :id");
            $selectedStmt->execute([':id' => $curriculumId]);
            jsonResponse([
                'status' => 'success',
                'subjects' => fetchAllRows($allSubjectsStmt),
                'selected' => $selectedStmt->fetchAll(PDO::FETCH_COLUMN) ?: []
            ]);
            break;

        case 'saveCurriculumSubjects':
            $curriculumId = $_POST['curriculum_id'] ?? '';
            $subjectIds = isset($_POST['subjects']) ? json_decode($_POST['subjects'], true) : [];
            if (empty($curriculumId)) { jsonResponse(['status' => 'error', 'message' => 'Missing ID'], 400); }
            $conn->beginTransaction();
            try {
                $delStmt = $conn->prepare("DELETE FROM public.curriculums_subject WHERE curriculums_id = :id");
                $delStmt->execute([':id' => $curriculumId]);
                if (!empty($subjectIds) && is_array($subjectIds)) {
                    $insertStmt = $conn->prepare("INSERT INTO public.curriculums_subject (curriculums_id, subject_id) VALUES (:cid, :sid)");
                    foreach ($subjectIds as $sid) { $insertStmt->execute([':cid' => $curriculumId, ':sid' => $sid]); }
                }
                $conn->commit();
                jsonResponse(['status' => 'success']);
            } catch (Exception $e) { $conn->rollBack(); throw $e; }
            break;

        case 'saveCurriculum':
            $curriculumId = $_POST['id'] ?? '';
            $params = [':name' => postValue('name'), ':level' => postValue('level', 'ม.ปลาย'), ':status' => postValue('status', 'active')];
            if (!empty($curriculumId)) {
                $params[':id'] = $curriculumId;
                $statement = $conn->prepare("UPDATE public.curriculums SET curriculums_name = :name, level = :level, status = :status WHERE curriculums_id = :id");
            } else {
                $stmtId = $conn->query("SELECT curriculums_id FROM public.curriculums WHERE curriculums_id LIKE 'C%' ORDER BY LENGTH(curriculums_id) DESC, curriculums_id DESC LIMIT 1");
                $lastId = $stmtId->fetchColumn();
                $nextNum = $lastId ? intval(substr($lastId, 1)) + 1 : 1;
                $newId = 'C' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
                $params[':id'] = $newId;
                $statement = $conn->prepare("INSERT INTO public.curriculums (curriculums_id, curriculums_name, level, status) VALUES (:id, :name, :level, :status)");
            }
            $statement->execute($params);
            jsonResponse(['status' => 'success']);
            break;

        case 'deleteCurriculum':
            $curriculumId = $_POST['id'] ?? '';
            $statement = $conn->prepare("DELETE FROM public.curriculums WHERE curriculums_id = :id");
            $statement->execute([':id' => $curriculumId]);
            jsonResponse(['status' => 'success']);
            break;

        case 'getLessons':
            $subjectId = $_GET['subject_id'] ?? '';
            if (empty($subjectId)) { jsonResponse(['status' => 'error', 'message' => 'Missing ID'], 400); }
            ensureLessonMediaColumns($conn);
            $lessonStmt = $conn->prepare("
                SELECT
                    lessons_id AS id,
                    lessons_name AS title,
                    image_path,
                    study_hours AS content,
                    COALESCE(document_path, '') AS document_path,
                    COALESCE(document_name, '') AS document_name,
                    COALESCE(video_path, '') AS video_path,
                    COALESCE(video_name, '') AS video_name,
                    COALESCE(video_url, '') AS video_url
                FROM public.lessons
                WHERE subjects_id = :subject_id
                ORDER BY lessons_id ASC
            ");
            $lessonStmt->execute([':subject_id' => $subjectId]);
            jsonResponse(['status' => 'success', 'lessons' => fetchAllRows($lessonStmt)]);
            break;

        case 'getSubjectEditorData':
            $subjectId = $_GET['subject_id'] ?? '';
            if (empty($subjectId)) { jsonResponse(['status' => 'error', 'message' => 'Missing ID'], 400); }
            ensureLessonMediaColumns($conn);

            $subjectStmt = $conn->prepare("
                SELECT
                    subjects_id AS id,
                    COALESCE(code, subjects_id) AS code,
                    subjects_name AS name,
                    COALESCE(credit, 0) AS credit,
                    COALESCE(NULLIF(TRIM(subject_type), ''), 'required') AS type
                FROM public.subjects
                WHERE subjects_id = :id
                LIMIT 1
            ");
            $subjectStmt->execute([':id' => $subjectId]);
            $subject = $subjectStmt->fetch(PDO::FETCH_ASSOC);
            if (!$subject) {
                jsonResponse(['status' => 'error', 'message' => 'Subject not found'], 404);
            }

            $lessonStmt = $conn->prepare("
                SELECT
                    lessons_id AS id,
                    lessons_name AS title,
                    study_hours AS content,
                    image_path,
                    COALESCE(document_path, '') AS document_path,
                    COALESCE(document_name, '') AS document_name,
                    COALESCE(video_path, '') AS video_path,
                    COALESCE(video_name, '') AS video_name,
                    COALESCE(video_url, '') AS video_url
                FROM public.lessons
                WHERE subjects_id = :subject_id
                ORDER BY lessons_id ASC
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
            ensureLessonMediaColumns($conn);

            $currentLesson = [
                'document_path' => '',
                'document_name' => '',
                'video_path' => '',
                'video_name' => '',
                'video_url' => '',
            ];
            if (!empty($lessonId)) {
                $currentStmt = $conn->prepare("
                    SELECT
                        COALESCE(document_path, '') AS document_path,
                        COALESCE(document_name, '') AS document_name,
                        COALESCE(video_path, '') AS video_path,
                        COALESCE(video_name, '') AS video_name,
                        COALESCE(video_url, '') AS video_url
                    FROM public.lessons
                    WHERE lessons_id = :id
                    LIMIT 1
                ");
                $currentStmt->execute([':id' => $lessonId]);
                $currentLesson = $currentStmt->fetch(PDO::FETCH_ASSOC) ?: $currentLesson;
            }

            $imagePath = '';
            if (isset($_FILES['image']) && (int) $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/uploads';
                if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }
                $fileName = time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', basename($_FILES['image']['name']));
                if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . '/' . $fileName)) { $imagePath = 'uploads/' . $fileName; }
            }

            $documentUpload = uploadLessonFile('document', 'lesson-documents', 'lesson_doc');
            $videoUpload = uploadLessonFile('video_file', 'lesson-videos', 'lesson_video');
            $videoUrl = postValue('video_url');
            if ($videoUrl === '') {
                $videoUrl = (string) ($currentLesson['video_url'] ?? '');
            }

            $documentPath = $documentUpload['path'] !== '' ? $documentUpload['path'] : (string) ($currentLesson['document_path'] ?? '');
            $documentName = $documentUpload['name'] !== '' ? $documentUpload['name'] : (string) ($currentLesson['document_name'] ?? '');
            $videoPath = $videoUpload['path'] !== '' ? $videoUpload['path'] : (string) ($currentLesson['video_path'] ?? '');
            $videoName = $videoUpload['name'] !== '' ? $videoUpload['name'] : (string) ($currentLesson['video_name'] ?? '');

            $baseParams = [
                ':subject_id' => $subjectId,
                ':title' => postValue('title'),
                ':content' => postValue('content'),
                ':video_url' => $videoUrl,
                ':document_path' => $documentPath,
                ':document_name' => $documentName,
                ':video_path' => $videoPath,
                ':video_name' => $videoName,
            ];
            if (!empty($lessonId)) {
                $baseParams[':id'] = $lessonId;
                if ($imagePath !== '') {
                    $statement = $conn->prepare("
                        UPDATE public.lessons
                        SET lessons_name = :title,
                            study_hours = :content,
                            image_path = :image_path,
                            video_url = :video_url,
                            document_path = :document_path,
                            document_name = :document_name,
                            video_path = :video_path,
                            video_name = :video_name
                        WHERE lessons_id = :id
                    ");
                    $baseParams[':image_path'] = $imagePath;
                } else {
                    $statement = $conn->prepare("
                        UPDATE public.lessons
                        SET lessons_name = :title,
                            study_hours = :content,
                            video_url = :video_url,
                            document_path = :document_path,
                            document_name = :document_name,
                            video_path = :video_path,
                            video_name = :video_name
                        WHERE lessons_id = :id
                    ");
                }
            } else {
                $stmtId = $conn->query("SELECT lessons_id FROM public.lessons WHERE lessons_id LIKE 'L%' ORDER BY LENGTH(lessons_id) DESC, lessons_id DESC LIMIT 1");
                $lastId = $stmtId->fetchColumn();
                $nextNum = $lastId ? intval(substr($lastId, 1)) + 1 : 1;
                $baseParams[':id'] = 'L' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
                $baseParams[':image_path'] = $imagePath;
                $statement = $conn->prepare("
                    INSERT INTO public.lessons (
                        lessons_id,
                        subjects_id,
                        lessons_name,
                        study_hours,
                        image_path,
                        video_url,
                        document_path,
                        document_name,
                        video_path,
                        video_name
                    ) VALUES (
                        :id,
                        :subject_id,
                        :title,
                        :content,
                        :image_path,
                        :video_url,
                        :document_path,
                        :document_name,
                        :video_path,
                        :video_name
                    )
                ");
            }
            $statement->execute($baseParams);
            jsonResponse(['status' => 'success']);
            break;

        case 'deleteLesson':
            $lessonId = $_POST['id'] ?? '';
            $statement = $conn->prepare("DELETE FROM public.lessons WHERE lessons_id = :id");
            $statement->execute([':id' => $lessonId]);
            jsonResponse(['status' => 'success']);
            break;

        case 'deleteMember':
            $memberId = $_POST['id'] ?? '';
            if (empty($memberId)) { jsonResponse(['status' => 'error', 'message' => 'Missing ID'], 400); }
            $conn->prepare("DELETE FROM public.staff WHERE user_id = :id")->execute([':id' => $memberId]);
            $conn->prepare("DELETE FROM public.student WHERE student_id = :id")->execute([':id' => $memberId]);
            $conn->prepare("DELETE FROM public.teachers WHERE teachers_id = :id")->execute([':id' => $memberId]);
            $conn->prepare("DELETE FROM public.parents WHERE parents_id = :id")->execute([':id' => $memberId]);
            $statement = $conn->prepare('DELETE FROM public."User" WHERE user_id = :id');
            $statement->execute([':id' => $memberId]);
            jsonResponse(['status' => 'success']);
            break;

        default:
            jsonResponse(['status' => 'error', 'message' => 'Invalid action'], 400);
    }
} catch (Throwable $exception) {
    if ($conn->inTransaction()) { $conn->rollBack(); }
    jsonResponse(['status' => 'error', 'message' => 'DB Error: ' . $exception->getMessage()], 500);
}
