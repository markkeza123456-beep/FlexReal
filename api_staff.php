<?php
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

function intValue($value): int {
    return (int) filter_var($value, FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'getAllData':
            $membersStmt = $conn->query("SELECT id, firstname, lastname, email, role, status FROM users ORDER BY id DESC");
            $curriculaStmt = $conn->query("SELECT * FROM curricula ORDER BY id DESC");
            $subjectsStmt = $conn->query("SELECT * FROM subjects ORDER BY id DESC");

            jsonResponse([
                'status' => 'success',
                'members' => fetchAllRows($membersStmt),
                'curricula' => fetchAllRows($curriculaStmt),
                'subjects' => fetchAllRows($subjectsStmt),
            ]);
            break;

        case 'getSubjectEditorData':
            $subjectId = intValue($_GET['subject_id'] ?? 0);
            if ($subjectId <= 0) {
                jsonResponse(['status' => 'error', 'message' => 'ไม่พบรหัสรายวิชา'], 400);
            }

            $subjectStmt = $conn->prepare("SELECT * FROM subjects WHERE id = :id LIMIT 1");
            $subjectStmt->execute([':id' => $subjectId]);
            $subject = $subjectStmt->fetch(PDO::FETCH_ASSOC);

            if (!$subject) {
                jsonResponse(['status' => 'error', 'message' => 'ไม่พบข้อมูลรายวิชา'], 404);
            }

            $lessonStmt = $conn->prepare("SELECT * FROM lessons WHERE subject_id = :subject_id ORDER BY id ASC");
            $lessonStmt->execute([':subject_id' => $subjectId]);

            jsonResponse([
                'status' => 'success',
                'subject' => $subject,
                'lessons' => fetchAllRows($lessonStmt),
            ]);
            break;

        case 'getLessons':
            $subjectId = intValue($_GET['subject_id'] ?? 0);
            if ($subjectId <= 0) {
                jsonResponse(['status' => 'error', 'message' => 'Missing subject_id'], 400);
            }

            $lessonStmt = $conn->prepare("SELECT * FROM lessons WHERE subject_id = :subject_id ORDER BY id ASC");
            $lessonStmt->execute([':subject_id' => $subjectId]);

            jsonResponse([
                'status' => 'success',
                'lessons' => fetchAllRows($lessonStmt),
            ]);
            break;

        case 'saveMember':
            $memberId = intValue($_POST['id'] ?? 0);
            if ($memberId <= 0) {
                jsonResponse(['status' => 'error', 'message' => 'ไม่พบรหัสสมาชิก'], 400);
            }

            $statement = $conn->prepare(
                "UPDATE users
                 SET firstname = :firstname,
                     lastname = :lastname,
                     email = :email,
                     role = :role,
                     status = :status
                 WHERE id = :id"
            );
            $statement->execute([
                ':firstname' => postValue('firstname'),
                ':lastname' => postValue('lastname'),
                ':email' => postValue('email'),
                ':role' => postValue('role', 'student'),
                ':status' => postValue('status', 'active'),
                ':id' => $memberId,
            ]);

            jsonResponse(['status' => 'success']);
            break;

        case 'deleteMember':
            $statement = $conn->prepare("DELETE FROM users WHERE id = :id");
            $statement->execute([':id' => intValue($_POST['id'] ?? 0)]);
            jsonResponse(['status' => 'success']);
            break;

        case 'saveCurriculum':
            $curriculumId = intValue($_POST['id'] ?? 0);
            $params = [
                ':code' => postValue('code'),
                ':name' => postValue('name'),
                ':level' => postValue('level'),
                ':year' => postValue('year'),
                ':description' => postValue('description'),
                ':status' => postValue('status', 'active'),
            ];

            if ($curriculumId > 0) {
                $params[':id'] = $curriculumId;
                $statement = $conn->prepare(
                    "UPDATE curricula
                     SET code = :code,
                         name = :name,
                         level = :level,
                         year = :year,
                         description = :description,
                         status = :status
                     WHERE id = :id"
                );
            } else {
                $statement = $conn->prepare(
                    "INSERT INTO curricula (code, name, level, year, description, status)
                     VALUES (:code, :name, :level, :year, :description, :status)"
                );
            }

            $statement->execute($params);
            jsonResponse(['status' => 'success']);
            break;

        case 'deleteCurriculum':
            $statement = $conn->prepare("DELETE FROM curricula WHERE id = :id");
            $statement->execute([':id' => intValue($_POST['id'] ?? 0)]);
            jsonResponse(['status' => 'success']);
            break;

        case 'saveSubject':
            $subjectId = intValue($_POST['id'] ?? 0);
            $params = [
                ':code' => postValue('code'),
                ':name' => postValue('name'),
                ':credit' => intValue($_POST['credit'] ?? 0),
                ':type' => postValue('type', 'required'),
            ];

            if ($subjectId > 0) {
                $params[':id'] = $subjectId;
                $statement = $conn->prepare(
                    "UPDATE subjects
                     SET code = :code,
                         name = :name,
                         credit = :credit,
                         type = :type
                     WHERE id = :id"
                );
            } else {
                $statement = $conn->prepare(
                    "INSERT INTO subjects (code, name, credit, type)
                     VALUES (:code, :name, :credit, :type)"
                );
            }

            $statement->execute($params);
            jsonResponse(['status' => 'success']);
            break;

        case 'deleteSubject':
            $statement = $conn->prepare("DELETE FROM subjects WHERE id = :id");
            $statement->execute([':id' => intValue($_POST['id'] ?? 0)]);
            jsonResponse(['status' => 'success']);
            break;

        case 'saveLesson':
            $lessonId = intValue($_POST['id'] ?? 0);
            $subjectId = intValue($_POST['subject_id'] ?? 0);

            if ($subjectId <= 0) {
                jsonResponse(['status' => 'error', 'message' => 'ไม่พบรหัสรายวิชา'], 400);
            }

            $imagePath = '';
            if (isset($_FILES['image']) && (int) $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/uploads';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($_FILES['image']['name']));
                $fileName = time() . '_' . $safeName;
                $destination = $uploadDir . '/' . $fileName;

                if (!move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                    jsonResponse(['status' => 'error', 'message' => 'อัปโหลดรูปภาพไม่สำเร็จ'], 500);
                }

                $imagePath = 'uploads/' . $fileName;
            }

            $baseParams = [
                ':subject_id' => $subjectId,
                ':title' => postValue('title'),
                ':content' => postValue('content'),
                ':video_url' => postValue('video_url'),
            ];

            if ($lessonId > 0) {
                if ($imagePath !== '') {
                    $statement = $conn->prepare(
                        "UPDATE lessons
                         SET title = :title,
                             content = :content,
                             image_path = :image_path,
                             video_url = :video_url
                         WHERE id = :id"
                    );
                    $baseParams[':image_path'] = $imagePath;
                } else {
                    $statement = $conn->prepare(
                        "UPDATE lessons
                         SET title = :title,
                             content = :content,
                             video_url = :video_url
                         WHERE id = :id"
                    );
                }

                $baseParams[':id'] = $lessonId;
            } else {
                $statement = $conn->prepare(
                    "INSERT INTO lessons (subject_id, title, content, image_path, video_url)
                     VALUES (:subject_id, :title, :content, :image_path, :video_url)"
                );
                $baseParams[':image_path'] = $imagePath;
            }

            $statement->execute($baseParams);
            jsonResponse(['status' => 'success']);
            break;

        case 'deleteLesson':
            $statement = $conn->prepare("DELETE FROM lessons WHERE id = :id");
            $statement->execute([':id' => intValue($_POST['id'] ?? 0)]);
            jsonResponse(['status' => 'success']);
            break;

        default:
            jsonResponse(['status' => 'error', 'message' => 'Invalid action'], 400);
    }
} catch (Throwable $exception) {
    jsonResponse([
        'status' => 'error',
        'message' => $exception->getMessage(),
    ], 500);
}
