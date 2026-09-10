<?php
session_start();
require_once 'db_connect.php';
require_once __DIR__ . '/learning_progress_lib.php';
header('Content-Type: application/json; charset=utf-8');

const MAX_LESSONS_PER_SUBJECT = 3;

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'กรุณาล็อกอินใหม่']);
    exit;
}

$action = $_POST['action'] ?? '';
$teacherId = (string) $_SESSION['user_id'];

function ensureLessonMediaColumns(PDO $conn): void
{
    // The supplied schema stores files in public.lesson_resources.
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

function uploadLessonFile(string $fieldName, array $segments, string $prefix, bool $required = false): array
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
        ];
        throw new Exception($errors[$errorCode] ?? 'เกิดข้อผิดพลาดระหว่างอัปโหลดไฟล์วิดีโอ');
    }

    $relativeDir = 'uploads/' . implode('/', $segments);
    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $originalName = trim((string) ($file['name'] ?? ''));
    $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($originalName));
    $safeName = $safeName !== '' ? $safeName : ($prefix . '_' . time());
    $targetName = $prefix . '_' . time() . '_' . $safeName;
    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $targetName;

    if (!move_uploaded_file((string) $file['tmp_name'], $targetPath)) {
        throw new Exception('เซิร์ฟเวอร์ไม่สามารถย้ายไฟล์วิดีโอไปยังโฟลเดอร์จัดเก็บได้');
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
    if ($action === 'add_lesson') {
        $lessonName = trim((string) ($_POST['lesson_name'] ?? ''));
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
        $stmt = $conn->prepare('INSERT INTO public.lessons (course_id, title, study_hours, position) VALUES (:course_id, :title, 1, (SELECT COALESCE(MAX(position), 0) + 1 FROM public.lessons WHERE course_id = :course_id)) RETURNING lesson_id');
        $stmt->execute([':course_id' => $subjectId, ':title' => $lessonName]);
        $lessonId = (string) $stmt->fetchColumn();
        $documentUpload = uploadLessonFile('lesson_document', buildLessonMediaSegments($teacherId, $subjectId, $lessonId, 'documents'), 'lesson_doc');
        if ($documentUpload['path'] !== '') {
            $resource = $conn->prepare("INSERT INTO public.lesson_resources (lesson_id, resource_type, title, url, position) VALUES (:lesson_id, 'document', :title, :url, 1)");
            $resource->execute([':lesson_id' => $lessonId, ':title' => $documentUpload['name'], ':url' => $documentUpload['path']]);
        }
        $conn->commit();

        echo json_encode(['success' => true, 'message' => 'เพิ่มบทเรียนสำเร็จ']);
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

        $stmt = $conn->prepare('UPDATE public.lessons SET title = :name, updated_at = NOW() WHERE lesson_id = :id');
        $stmt->execute([
            ':name' => $lessonName,
            ':id' => $lessonId,
        ]);

        echo json_encode(['success' => true, 'message' => 'แก้ไขบทเรียนสำเร็จ']);
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
                 correct_choice = :correct_choice
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
        $videoStmt = $conn->prepare("SELECT r.resource_id AS id, r.title, r.url, '' AS description, r.duration_seconds, r.position AS display_order, r.lesson_id AS lessons_id, l.title AS lesson_title FROM public.lesson_resources r INNER JOIN public.lessons l ON l.lesson_id = r.lesson_id WHERE l.course_id = :subject_id AND r.resource_type = 'video' ORDER BY r.position, r.resource_id");
        $videoStmt->execute([':subject_id' => $subjectId]);
        echo json_encode(['success' => true, 'lessons' => $lessonStmt->fetchAll(PDO::FETCH_ASSOC), 'videos' => $videoStmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'save_video') {
        $subjectId = trim((string) ($_POST['subject_id'] ?? ''));
        $videoId = trim((string) ($_POST['video_id'] ?? ''));
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($subjectId === '' || $title === '' || !teacherOwnsSubject($conn, $teacherId, $subjectId)) {
            throw new Exception('ข้อมูลไม่ครบถ้วนหรือคุณไม่มีสิทธิ์จัดการรายวิชานี้');
        }
        $upload = uploadLessonFile('video_file', buildVideoMediaSegments($teacherId, $subjectId), 'video', true);
        $url = $upload['path'];
        $lessonId = trim((string) ($_POST['lesson_id'] ?? ''));
        if ($lessonId === '' || !teacherOwnsLesson($conn, $teacherId, $lessonId)) throw new Exception('บทเรียนที่เลือกไม่ถูกต้อง');
        $params = [':title' => $title, ':url' => $url, ':lesson_id' => $lessonId];
        if ($videoId === '') {
            $stmt = $conn->prepare("INSERT INTO public.lesson_resources (lesson_id, resource_type, title, url, position) VALUES (:lesson_id, 'video', :title, :url, (SELECT COALESCE(MAX(position), 0) + 1 FROM public.lesson_resources WHERE lesson_id = :lesson_id))");
        } else {
            $params[':id'] = $videoId;
            $stmt = $conn->prepare("UPDATE public.lesson_resources SET title = :title, url = :url, lesson_id = :lesson_id WHERE resource_id = :id AND resource_type = 'video'");
        }
        $stmt->execute($params);
        echo json_encode(['success' => true, 'message' => 'บันทึกวิดีโอสำเร็จ']);
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
            "SELECT aa.attempt_answer_id AS submission_id, a.attempt_id AS test_id, aa.answer_text,
                    CASE WHEN aa.review_status = 'pending' THEN 'pending' WHEN COALESCE(aa.score, 0) > 0 THEN 'pass' ELSE 'fail' END AS review_status,
                    aa.reviewer_comment AS teacher_comment, a.submitted_at,
                    s.full_name AS student_name, l.title AS lessons_name, q.question_text AS questions_text
             FROM public.quiz_attempt_answers aa
             INNER JOIN public.quiz_attempts a ON a.attempt_id = aa.attempt_id
             INNER JOIN public.questions q ON q.question_id = aa.question_id
             INNER JOIN public.lessons l ON l.lesson_id = a.lesson_id
             INNER JOIN public.students s ON s.user_id = a.student_id
             WHERE l.course_id = :subject_id AND q.question_type = 'essay'
             ORDER BY CASE WHEN aa.review_status = 'pending' THEN 0 ELSE 1 END, a.submitted_at DESC"
        );
        $stmt->execute([':subject_id' => $subjectId]);
        echo json_encode(['success' => true, 'submissions' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'review_essay_submission') {
        $submissionId = (int) ($_POST['submission_id'] ?? 0);
        $decision = trim((string) ($_POST['decision'] ?? ''));
        if ($submissionId <= 0 || !in_array($decision, ['pass', 'fail'], true)) throw new Exception('ข้อมูลการตรวจไม่ถูกต้อง');
        $conn->beginTransaction();
        $lookup = $conn->prepare(
            'SELECT aa.attempt_id, aa.review_status, q.max_score
             FROM public.quiz_attempt_answers aa
             INNER JOIN public.questions q ON q.question_id = aa.question_id
             INNER JOIN public.quiz_attempts a ON a.attempt_id = aa.attempt_id
             INNER JOIN public.lessons l ON l.lesson_id = a.lesson_id
             INNER JOIN public.course_teachers ct ON ct.course_id = l.course_id
             WHERE aa.attempt_answer_id = :id AND ct.teacher_id = :teacher_id AND q.question_type = \'essay\'
             FOR UPDATE'
        );
        $lookup->execute([':id' => $submissionId, ':teacher_id' => $teacherId]);
        $row = $lookup->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new Exception('ไม่พบคำตอบข้อเขียนหรือคุณไม่มีสิทธิ์ตรวจ');
        if ($row['review_status'] !== 'pending') throw new Exception('คำตอบข้อนี้ถูกตรวจแล้ว');
        $conn->prepare("UPDATE public.quiz_attempt_answers SET score = :score, review_status = 'reviewed', reviewer_id = :teacher_id, reviewer_comment = :comment, reviewed_at = CURRENT_TIMESTAMP WHERE attempt_answer_id = :id")
            ->execute([':score' => $decision === 'pass' ? $row['max_score'] : 0, ':teacher_id' => $teacherId, ':comment' => trim((string) ($_POST['comment'] ?? '')), ':id' => $submissionId]);
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
        echo json_encode(['success' => true, 'message' => $testStatus === 'pending_review' ? 'บันทึกผลแล้ว ยังมีข้อเขียนรอตรวจ' : 'บันทึกผลการตรวจแล้ว']);
        exit;
    }

    throw new Exception('ไม่พบคำสั่งที่ต้องการ');
} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
}
?>
