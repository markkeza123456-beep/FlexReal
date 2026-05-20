<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/account_status_lib.php';
require_once __DIR__ . '/curriculum_subjects_lib.php';

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

function normalizeRoleValue(string $role): string {
    $map = [
        'student' => 'Student',
        'teacher' => 'Teacher',
        'parent' => 'Parent',
        'staff' => 'Staff',
    ];

    $key = strtolower(trim($role));
    return $map[$key] ?? 'Student';
}

function ensureValue(string $value, string $message): string {
    $trimmed = trim($value);
    if ($trimmed === '') {
        jsonResponse([
            'status' => 'error',
            'message' => $message,
        ], 400);
    }

    return $trimmed;
}

if (!isset($_SESSION['user_id']) || strtolower((string) ($_SESSION['role'] ?? '')) !== 'staff') {
    jsonResponse([
        'status' => 'error',
        'message' => 'Unauthorized',
    ], 403);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    ensureUserAccountStatusColumn($conn);
    ensureSubjectTypeColumn($conn);
    ensureCurriculumSubjectTypeColumn($conn);

    switch ($action) {
        case 'getAllData':
            // 💥 แก้ไขแล้ว: ดึงข้อมูลชื่อและอีเมลจากทุกกลุ่มผู้ใช้งาน (Student, Teacher, Parent, Staff)
            $membersStmt = $conn->query('
                SELECT 
                    u.user_id as id,
                    u.status as role,
                    COALESCE(NULLIF(TRIM(u.account_status), \'\'), \'active\') as status_account,
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

            // ดึงข้อมูลหลักสูตร
            $curriculaStmt = $conn->query("
                SELECT curriculums_id AS id, curriculums_id AS code, curriculums_name AS name, level, status 
                FROM public.curriculums ORDER BY curriculums_id DESC
            ");
            
            // ดึงรายวิชา
            $subjectsStmt = $conn->query("
                SELECT *,
                       subjects_id AS id,
                       COALESCE(code, subjects_id) AS code,
                       subjects_name AS name,
                       COALESCE(credit, 0) AS credit,
                       COALESCE(NULLIF(TRIM(subject_type), ''), 'elective') AS type
                FROM public.subjects
                WHERE deleted_at IS NULL
                ORDER BY subjects_id DESC
            ");

            jsonResponse([
                'status' => 'success',
                'members' => fetchAllRows($membersStmt),
                'curricula' => fetchAllRows($curriculaStmt),
                'subjects' => fetchAllRows($subjectsStmt),
            ]);
            break;

        case 'getCurriculumSubjects':
            $curriculumId = $_GET['curriculum_id'] ?? '';
            $allSubjectsStmt = $conn->query("SELECT subjects_id AS id, COALESCE(code, subjects_id) AS code, subjects_name AS name FROM public.subjects WHERE deleted_at IS NULL ORDER BY subjects_id ASC");
            $allSubjects = fetchAllRows($allSubjectsStmt);
            $selectedStmt = $conn->prepare("SELECT subject_id, COALESCE(NULLIF(TRIM(subject_type), ''), 'required') AS subject_type FROM public.curriculums_subject WHERE curriculums_id = :id");
            $selectedStmt->execute([':id' => $curriculumId]);
            $selectedRows = fetchAllRows($selectedStmt);
            $selectedIds = [];
            foreach ($selectedRows as $row) {
                $selectedIds[] = (string) ($row['subject_id'] ?? '');
            }
            jsonResponse(['status' => 'success', 'subjects' => $allSubjects, 'selected' => $selectedIds]);
            break;

        case 'saveCurriculumSubjects':
            $curriculumId = $_POST['curriculum_id'] ?? '';
            $subjectRows = isset($_POST['subjects']) ? json_decode($_POST['subjects'], true) : [];
            $conn->beginTransaction();
            try {
                $conn->prepare("DELETE FROM public.curriculums_subject WHERE curriculums_id = :id")->execute([':id' => $curriculumId]);
                if (!empty($subjectRows) && is_array($subjectRows)) {
                    $insertStmt = $conn->prepare("INSERT INTO public.curriculums_subject (curriculums_id, subject_id, subject_type) VALUES (:cid, :sid, :type)");
                    foreach ($subjectRows as $row) {
                        $subjectId = '';
                        if (is_array($row)) {
                            $subjectId = trim((string) ($row['id'] ?? ''));
                        } else {
                            $subjectId = trim((string) $row);
                        }
                        if ($subjectId === '') {
                            continue;
                        }
                        $insertStmt->execute([
                            ':cid' => $curriculumId,
                            ':sid' => $subjectId,
                            ':type' => 'required',
                        ]);
                    }
                }
                $conn->commit();
                jsonResponse(['status' => 'success']);
            } catch (Exception $e) { $conn->rollBack(); throw $e; }
            break;

        case 'saveCurriculum':
            $curriculumId = trim((string) ($_POST['id'] ?? ''));
            $curriculumCode = ensureValue(postValue('code'), 'กรุณากรอกรหัสหลักสูตร');
            $curriculumName = ensureValue(postValue('name'), 'กรุณากรอกชื่อหลักสูตร');
            $curriculumLevel = ensureValue(postValue('level'), 'กรุณาเลือกระดับชั้น');
            $curriculumStatus = strtolower(postValue('status', 'active'));
            if (!in_array($curriculumStatus, ['active', 'draft', 'inactive'], true)) {
                $curriculumStatus = 'active';
            }

            $conn->beginTransaction();
            try {
                if ($curriculumId !== '') {
                    $duplicateStmt = $conn->prepare(
                        "SELECT 1
                         FROM public.curriculums
                         WHERE curriculums_id = :new_id
                           AND curriculums_id <> :current_id
                         LIMIT 1"
                    );
                    $duplicateStmt->execute([
                        ':new_id' => $curriculumCode,
                        ':current_id' => $curriculumId,
                    ]);
                    if ($duplicateStmt->fetchColumn()) {
                        jsonResponse(['status' => 'error', 'message' => 'รหัสหลักสูตรนี้ถูกใช้งานแล้ว'], 400);
                    }

                    if ($curriculumCode !== $curriculumId) {
                        $conn->prepare(
                            "UPDATE public.curriculums_subject
                             SET curriculums_id = :new_id
                             WHERE curriculums_id = :current_id"
                        )->execute([
                            ':new_id' => $curriculumCode,
                            ':current_id' => $curriculumId,
                        ]);

                        $conn->prepare(
                            "UPDATE public.student
                             SET studcurriculums_id = :new_id
                             WHERE studcurriculums_id = :current_id"
                        )->execute([
                            ':new_id' => $curriculumCode,
                            ':current_id' => $curriculumId,
                        ]);
                    }

                    $conn->prepare(
                        "UPDATE public.curriculums
                         SET curriculums_id = :new_id,
                             curriculums_name = :name,
                             level = :level,
                             status = :status
                         WHERE curriculums_id = :current_id"
                    )->execute([
                        ':new_id' => $curriculumCode,
                        ':name' => $curriculumName,
                        ':level' => $curriculumLevel,
                        ':status' => $curriculumStatus,
                        ':current_id' => $curriculumId,
                    ]);
                } else {
                    $duplicateStmt = $conn->prepare(
                        "SELECT 1
                         FROM public.curriculums
                         WHERE curriculums_id = :id
                         LIMIT 1"
                    );
                    $duplicateStmt->execute([':id' => $curriculumCode]);
                    if ($duplicateStmt->fetchColumn()) {
                        jsonResponse(['status' => 'error', 'message' => 'รหัสหลักสูตรนี้ถูกใช้งานแล้ว'], 400);
                    }

                    $conn->prepare(
                        "INSERT INTO public.curriculums
                            (curriculums_id, curriculums_name, level, status)
                         VALUES
                            (:id, :name, :level, :status)"
                    )->execute([
                        ':id' => $curriculumCode,
                        ':name' => $curriculumName,
                        ':level' => $curriculumLevel,
                        ':status' => $curriculumStatus,
                    ]);
                }

                $conn->commit();
                jsonResponse(['status' => 'success']);
            } catch (Throwable $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                throw $e;
            }
            break;

        case 'deleteCurriculum':
            $curriculumId = ensureValue((string) ($_POST['id'] ?? ''), 'ไม่พบรหัสหลักสูตร');
            $conn->beginTransaction();
            try {
                $conn->prepare(
                    "DELETE FROM public.curriculums_subject
                     WHERE curriculums_id = :id"
                )->execute([':id' => $curriculumId]);

                $conn->prepare(
                    "UPDATE public.student
                     SET studcurriculums_id = NULL
                     WHERE studcurriculums_id = :id"
                )->execute([':id' => $curriculumId]);

                $conn->prepare(
                    "DELETE FROM public.curriculums
                     WHERE curriculums_id = :id"
                )->execute([':id' => $curriculumId]);

                $conn->commit();
                jsonResponse(['status' => 'success']);
            } catch (Throwable $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                throw $e;
            }
            break;

        case 'saveSubject':
            $subjectId = $_POST['id'] ?? '';
            $params = [
                ':code' => postValue('code'),
                ':name' => postValue('name'),
                ':credit' => (int) postValue('credit', '0'),
                ':type' => normalizeSubjectType(postValue('type', 'elective')),
            ];
            if (!empty($subjectId)) {
                $params[':id'] = $subjectId;
                $conn->prepare("UPDATE public.subjects SET code = :code, subjects_name = :name, credit = :credit, subject_type = :type, updated_at = NOW() WHERE subjects_id = :id")->execute($params);
            } else {
                $stmtId = $conn->query("SELECT subjects_id FROM public.subjects WHERE subjects_id LIKE 'SUB%' ORDER BY LENGTH(subjects_id) DESC, subjects_id DESC LIMIT 1");
                $lastId = $stmtId->fetchColumn();
                $newId = 'SUB' . str_pad($lastId ? intval(substr($lastId, 3)) + 1 : 1, 3, '0', STR_PAD_LEFT);
                $params[':id'] = $newId;
                $conn->prepare("INSERT INTO public.subjects (subjects_id, code, subjects_name, credit, subject_type) VALUES (:id, :code, :name, :credit, :type)")->execute($params);
            }
            jsonResponse(['status' => 'success']);
            break;

        case 'deleteSubject':
            $subjectId = $_POST['id'] ?? '';
            if ($subjectId === '') {
                jsonResponse(['status' => 'error', 'message' => 'ไม่พบรหัสรายวิชา'], 400);
            }

            $conn->prepare("UPDATE public.subjects SET deleted_at = NOW() WHERE subjects_id = :id")->execute([
                ':id' => $subjectId,
            ]);
            jsonResponse(['status' => 'success']);
            break;

        case 'saveMember':
            $memberId = $_POST['id'] ?? '';
            $role = normalizeRoleValue(postValue('role', 'student'));
            $accountStatus = normalizeAccountStatus(postValue('status', 'active'));
            $firstname = postValue('firstname');
            $lastname = postValue('lastname');
            $email = postValue('email');
            $fullName = trim($firstname . ' ' . $lastname);

            $conn->beginTransaction();

            $conn->prepare('UPDATE public."User" SET status = :role, account_status = :account_status WHERE user_id = :id')->execute([
                ':role' => $role,
                ':account_status' => $accountStatus,
                ':id' => $memberId,
            ]);

            if ($fullName !== '') {
                $conn->prepare('UPDATE public.student SET student_name = :name WHERE student_id = :id')
                    ->execute([':name' => $fullName, ':id' => $memberId]);
                $conn->prepare('UPDATE public.teachers SET teachers_name = :name WHERE teachers_id = :id')
                    ->execute([':name' => $fullName, ':id' => $memberId]);
                $conn->prepare('UPDATE public.parents SET parents_name = :name WHERE parents_id = :id')
                    ->execute([':name' => $fullName, ':id' => $memberId]);
            }

            $conn->prepare('UPDATE public.staff SET firstname = :firstname, lastname = :lastname WHERE user_id = :id')
                ->execute([
                    ':firstname' => $firstname,
                    ':lastname' => $lastname,
                    ':id' => $memberId,
                ]);

            $conn->prepare('UPDATE public.student SET email = :email WHERE student_id = :id')
                ->execute([':email' => $email, ':id' => $memberId]);
            $conn->prepare('UPDATE public.teachers SET email = :email WHERE teachers_id = :id')
                ->execute([':email' => $email, ':id' => $memberId]);
            $conn->prepare('UPDATE public.parents SET email = :email WHERE parents_id = :id')
                ->execute([':email' => $email, ':id' => $memberId]);

            $conn->commit();
            jsonResponse(['status' => 'success']);
            break;

        case 'deleteMember':
            $memberId = $_POST['id'] ?? '';
            $conn->prepare('DELETE FROM public."User" WHERE user_id = :id')->execute([':id' => $memberId]);
            jsonResponse(['status' => 'success']);
            break;

        case 'createStaff':
            $userId = preg_replace('/\D+/', '', postValue('user_id'));
            $password = postValue('password');
            $firstname = ensureValue(postValue('firstname'), 'กรุณากรอกชื่อเจ้าหน้าที่');
            $lastname = ensureValue(postValue('lastname'), 'กรุณากรอกนามสกุลเจ้าหน้าที่');

            if (strlen($userId) !== 13) {
                jsonResponse(['status' => 'error', 'message' => 'กรุณากรอกเลขบัตรประชาชน 13 หลัก'], 400);
            }
            if (strlen($password) < 6) {
                jsonResponse(['status' => 'error', 'message' => 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร'], 400);
            }

            $existsStmt = $conn->prepare('SELECT 1 FROM public."User" WHERE user_id = :id LIMIT 1');
            $existsStmt->execute([':id' => $userId]);
            if ($existsStmt->fetchColumn()) {
                jsonResponse(['status' => 'error', 'message' => 'เลขบัตรประชาชนนี้ถูกใช้งานแล้ว'], 400);
            }

            $conn->beginTransaction();
            try {
                $conn->prepare(
                    'INSERT INTO public."User" (user_id, password, status, account_status)
                     VALUES (:id, :password, :status, :account_status)'
                )->execute([
                    ':id' => $userId,
                    ':password' => $password,
                    ':status' => 'Staff',
                    ':account_status' => 'active',
                ]);

                $conn->prepare(
                    'INSERT INTO public.staff (user_id, firstname, lastname, created_at, updated_at)
                     VALUES (:id, :firstname, :lastname, NOW(), NOW())'
                )->execute([
                    ':id' => $userId,
                    ':firstname' => $firstname,
                    ':lastname' => $lastname,
                ]);

                $conn->commit();
                jsonResponse(['status' => 'success']);
            } catch (Throwable $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                throw $e;
            }
            break;

        default:
            jsonResponse(['status' => 'error', 'message' => 'Invalid action'], 400);
    }
} catch (Throwable $exception) {
    if ($conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    jsonResponse(['status' => 'error', 'message' => 'DB Error: ' . $exception->getMessage()], 500);
}
