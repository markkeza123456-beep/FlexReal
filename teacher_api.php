<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'กรุณาล็อกอินใหม่']);
    exit;
}

$action = $_POST['action'] ?? '';
$teacherId = (string) $_SESSION['user_id'];

function teacherOwnsSubject(PDO $conn, string $teacherId, string $subjectId): bool
{
    $stmt = $conn->prepare(
        'SELECT 1
         FROM public.subjects
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
         INNER JOIN public.subjects s ON s.subjects_id = l.subjects_id
         WHERE l.lessons_id = :lesson_id
           AND s.teachers_id = :teacher_id
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
         FROM public.test_questions tq
         INNER JOIN public.lessons l ON l.lessons_id = tq.lessons_id
         INNER JOIN public.subjects s ON s.subjects_id = l.subjects_id
         WHERE tq.questions_id = :quiz_id
           AND s.teachers_id = :teacher_id
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

        $stmtId = $conn->query("SELECT lessons_id FROM public.lessons WHERE lessons_id LIKE 'L%' ORDER BY LENGTH(lessons_id) DESC, lessons_id DESC LIMIT 1");
        $lastId = (string) ($stmtId->fetchColumn() ?: '');
        $nextNum = $lastId !== '' ? intval(substr($lastId, 1)) + 1 : 1;
        $lessonId = 'L' . str_pad((string) $nextNum, 3, '0', STR_PAD_LEFT);

        $stmt = $conn->prepare(
            'INSERT INTO public.lessons (lessons_id, lessons_name, study_hours, subjects_id)
             VALUES (:id, :name, :hours, :subject_id)'
        );
        $stmt->execute([
            ':id' => $lessonId,
            ':name' => $lessonName,
            ':hours' => 1,
            ':subject_id' => $subjectId,
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

    throw new Exception('ไม่พบคำสั่งที่ต้องการ');
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
}
?>