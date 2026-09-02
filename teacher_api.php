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
    $conn->exec("ALTER TABLE public.lessons ADD COLUMN IF NOT EXISTS lesson_content TEXT");
    $conn->exec("ALTER TABLE public.lessons ADD COLUMN IF NOT EXISTS document_path VARCHAR(255)");
    $conn->exec("ALTER TABLE public.lessons ADD COLUMN IF NOT EXISTS document_name VARCHAR(255)");
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
    $stmtId = $conn->query("SELECT lessons_id FROM public.lessons WHERE lessons_id LIKE 'L%' ORDER BY LENGTH(lessons_id) DESC, lessons_id DESC LIMIT 1");
    $lastId = $stmtId->fetchColumn();
    $nextNum = $lastId ? intval(substr((string) $lastId, 1)) + 1 : 1;
    return 'L' . str_pad((string) $nextNum, 3, '0', STR_PAD_LEFT);
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
         FROM public.subject_teachers
         WHERE subjects_id = :subject_id
           AND teachers_id = :teacher_id
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
         INNER JOIN public.subject_teachers st ON st.subjects_id = l.subjects_id
         WHERE l.lessons_id = :lesson_id
           AND st.teachers_id = :teacher_id
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
    $stmt = $conn->prepare('SELECT COUNT(*) FROM public.lessons WHERE subjects_id = :subject_id');
    $stmt->execute([':subject_id' => $subjectId]);
    return (int) $stmt->fetchColumn();
}

function teacherOwnsQuiz(PDO $conn, string $teacherId, string $quizId): bool
{
    $stmt = $conn->prepare(
        'SELECT 1
         FROM public.test_questions tq
         INNER JOIN public.lessons l ON l.lessons_id = tq.lessons_id
         INNER JOIN public.subject_teachers st ON st.subjects_id = l.subjects_id
         WHERE tq.questions_id = :quiz_id
           AND st.teachers_id = :teacher_id
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

        ensureLessonMediaColumns($conn);
        $lessonId = generateLessonId($conn);
        $documentUpload = uploadLessonFile('lesson_document', buildLessonMediaSegments($teacherId, $subjectId, $lessonId, 'documents'), 'lesson_doc');

        $stmt = $conn->prepare(
            'INSERT INTO public.lessons (
                lessons_id,
                lessons_name,
                study_hours,
                subjects_id,
                document_path,
                document_name
            )
             VALUES (
                :id,
                :name,
                :hours,
                :subject_id,
                :document_path,
                :document_name
            )'
        );
        $stmt->execute([
            ':id' => $lessonId,
            ':name' => $lessonName,
            ':hours' => 1,
            ':subject_id' => $subjectId,
            ':document_path' => $documentUpload['path'],
            ':document_name' => $documentUpload['name'],
        ]);

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

        $stmt = $conn->prepare('UPDATE public.lessons SET lessons_name = :name WHERE lessons_id = :id');
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

        $conn->prepare('DELETE FROM public.test_questions WHERE lessons_id = ?')->execute([$lessonId]);
        $conn->prepare('DELETE FROM public.lessons WHERE lessons_id = ?')->execute([$lessonId]);
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

        $stmtId = $conn->query("SELECT COALESCE(MAX(questions_id), 0) + 1 FROM public.test_questions");
        $nextId = (int) $stmtId->fetchColumn();

        $stmt = $conn->prepare(
            'INSERT INTO public.test_questions
                (questions_id, questions_text, choice_a, choice_b, choice_c, choice_d, correct_answer, lessons_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$nextId, $question, $choiceA, $choiceB, $choiceC, $choiceD, $answer, $lessonId]);

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

        $stmt = $conn->prepare(
            'UPDATE public.test_questions
             SET questions_text = ?, choice_a = ?, choice_b = ?, choice_c = ?, choice_d = ?, correct_answer = ?
             WHERE questions_id = ?'
        );
        $stmt->execute([$question, $choiceA, $choiceB, $choiceC, $choiceD, $answer, $quizId]);

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

        $conn->prepare('DELETE FROM public.test_questions WHERE questions_id = ?')->execute([$quizId]);
        echo json_encode(['success' => true, 'message' => 'ลบคำถามสำเร็จ']);
        exit;
    }

    if ($action === 'get_videos') {
        $subjectId = trim((string) ($_POST['subject_id'] ?? ''));
        if ($subjectId === '' || !teacherOwnsSubject($conn, $teacherId, $subjectId)) {
            throw new Exception('คุณไม่มีสิทธิ์จัดการรายวิชานี้');
        }
        $lessonStmt = $conn->prepare('SELECT lessons_id AS id, lessons_name AS title FROM public.lessons WHERE subjects_id = :subject_id ORDER BY lessons_id LIMIT 3');
        $lessonStmt->execute([':subject_id' => $subjectId]);
        $videoStmt = $conn->prepare('SELECT v.videos_id AS id, v.videos_title AS title, v.videos_url AS url, COALESCE(v.videos_description, \'\') AS description, v.duration_seconds, v.display_order, v.lessons_id, COALESCE(l.lessons_name, \'\') AS lesson_title FROM public.videos v LEFT JOIN public.lessons l ON l.lessons_id = v.lessons_id WHERE v.subjects_id = :subject_id ORDER BY v.display_order, v.videos_id');
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
        if ($lessonId !== '' && !teacherOwnsLesson($conn, $teacherId, $lessonId)) throw new Exception('บทเรียนที่เลือกไม่ถูกต้อง');
        $params = [':title' => $title, ':url' => $url, ':description' => trim((string) ($_POST['description'] ?? '')), ':subject_id' => $subjectId, ':lesson_id' => $lessonId !== '' ? $lessonId : null];
        if ($videoId === '') {
            $stmt = $conn->prepare('INSERT INTO public.videos (videos_title, videos_url, videos_description, subjects_id, lessons_id) VALUES (:title, :url, :description, :subject_id, :lesson_id)');
        } else {
            $params[':id'] = $videoId;
            $stmt = $conn->prepare('UPDATE public.videos SET videos_title = :title, videos_url = :url, videos_description = :description, lessons_id = :lesson_id WHERE videos_id = :id AND subjects_id = :subject_id');
        }
        $stmt->execute($params);
        echo json_encode(['success' => true, 'message' => 'บันทึกวิดีโอสำเร็จ']);
        exit;
    }

    if ($action === 'delete_video') {
        $subjectId = trim((string) ($_POST['subject_id'] ?? ''));
        $videoId = trim((string) ($_POST['video_id'] ?? ''));
        if ($subjectId === '' || $videoId === '' || !teacherOwnsSubject($conn, $teacherId, $subjectId)) throw new Exception('คุณไม่มีสิทธิ์ลบวิดีโอนี้');
        $conn->prepare('DELETE FROM public.videos WHERE videos_id = :id AND subjects_id = :subject_id')->execute([':id' => $videoId, ':subject_id' => $subjectId]);
        echo json_encode(['success' => true, 'message' => 'ลบวิดีโอสำเร็จ']);
        exit;
    }

    if ($action === 'get_essay_submissions') {
        $subjectId = trim((string) ($_POST['subject_id'] ?? ''));
        if ($subjectId === '' || !teacherOwnsSubject($conn, $teacherId, $subjectId)) throw new Exception('คุณไม่มีสิทธิ์จัดการรายวิชานี้');
        ensureEssaySubmissionTable($conn);
        $stmt = $conn->prepare(
            'SELECT es.submission_id, es.test_id, es.answer_text, es.review_status, es.teacher_comment, es.submitted_at,
                    st.student_name, l.lessons_name, tq.questions_text
             FROM public.essay_submissions es
             INNER JOIN public.student st ON st.student_id = es.student_id
             INNER JOIN public.lessons l ON l.lessons_id = es.lessons_id
             INNER JOIN public.test_questions tq ON tq.questions_id = es.questions_id
             WHERE es.subjects_id = :subject_id
             ORDER BY CASE WHEN es.review_status = \'pending\' THEN 0 ELSE 1 END, es.submitted_at DESC'
        );
        $stmt->execute([':subject_id' => $subjectId]);
        echo json_encode(['success' => true, 'submissions' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'review_essay_submission') {
        $submissionId = (int) ($_POST['submission_id'] ?? 0);
        $decision = trim((string) ($_POST['decision'] ?? ''));
        if ($submissionId <= 0 || !in_array($decision, ['pass', 'fail'], true)) throw new Exception('ข้อมูลการตรวจไม่ถูกต้อง');
        ensureEssaySubmissionTable($conn);
        $conn->beginTransaction();
        $lookup = $conn->prepare('SELECT es.test_id, es.subjects_id, es.review_status FROM public.essay_submissions es INNER JOIN public.subject_teachers st ON st.subjects_id = es.subjects_id WHERE es.submission_id = :id AND st.teachers_id = :teacher_id FOR UPDATE');
        $lookup->execute([':id' => $submissionId, ':teacher_id' => $teacherId]);
        $row = $lookup->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new Exception('ไม่พบคำตอบข้อเขียนหรือคุณไม่มีสิทธิ์ตรวจ');
        if ($row['review_status'] !== 'pending') throw new Exception('คำตอบข้อนี้ถูกตรวจแล้ว');
        $conn->prepare('UPDATE public.essay_submissions SET review_status = :decision, teacher_id = :teacher_id, teacher_comment = :comment, reviewed_at = CURRENT_TIMESTAMP WHERE submission_id = :id')->execute([':decision' => $decision, ':teacher_id' => $teacherId, ':comment' => trim((string) ($_POST['comment'] ?? '')), ':id' => $submissionId]);
        $testId = (int) $row['test_id'];
        if ($decision === 'pass') {
            $conn->prepare('UPDATE public.test SET score = LEAST(total_score, score + 1) WHERE test_id = :test_id')->execute([':test_id' => $testId]);
        }
        $testStmt = $conn->prepare('SELECT student_id, subjects_id, lesson_no, score, total_score FROM public.test WHERE test_id = :test_id FOR UPDATE');
        $testStmt->execute([':test_id' => $testId]);
        $test = $testStmt->fetch(PDO::FETCH_ASSOC);
        if (!$test) throw new Exception('ไม่พบผลการทดสอบ');
        $statusStmt = $conn->prepare("SELECT CASE WHEN EXISTS (SELECT 1 FROM public.essay_submissions WHERE test_id = :test_id AND review_status = 'pending') THEN 'pending_review' WHEN :score >= CEIL(:total_score) THEN 'pass' ELSE 'fail' END");
        $statusStmt->execute([':test_id' => $testId, ':score' => (int) $test['score'], ':total_score' => max(1, (int) $test['total_score'])]);
        $testStatus = (string) $statusStmt->fetchColumn();
        $conn->prepare('UPDATE public.test SET status = :status WHERE test_id = :test_id')->execute([':status' => $testStatus, ':test_id' => $testId]);
        syncReviewedQuizScore($conn, (string) $test['student_id'], (string) $test['subjects_id'], (int) $test['lesson_no'], (int) $test['score'], (int) $test['total_score']);
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
