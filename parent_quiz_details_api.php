<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once __DIR__ . '/config/db.php';

function parentQuizJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (($_SESSION['role'] ?? '') !== 'parent' || empty($_SESSION['user_id'])) {
    parentQuizJson(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบผู้ปกครองก่อน'], 401);
}

$parentId = (string) $_SESSION['user_id'];
$studentId = trim((string) ($_GET['student_id'] ?? ''));
$courseId = trim((string) ($_GET['course_id'] ?? ''));
if ($studentId === '' || $courseId === '') {
    parentQuizJson(['status' => 'error', 'message' => 'ข้อมูลนักเรียนหรือรายวิชาไม่ครบ'], 400);
}

try {
    $accessStmt = $conn->prepare(
        "SELECT c.course_id, c.name AS course_name
         FROM public.courses c
         WHERE c.course_id = :course_id
           AND EXISTS (
               SELECT 1
               FROM public.students s
               INNER JOIN public.student_courses sc ON sc.student_id = s.user_id
               WHERE s.user_id = :student_id AND s.parent_user_id = :parent_id
                 AND sc.course_id = c.course_id
           )
         LIMIT 1"
    );
    $accessStmt->execute([
        ':course_id' => $courseId,
        ':student_id' => $studentId,
        ':parent_id' => $parentId,
    ]);
    $course = $accessStmt->fetch(PDO::FETCH_ASSOC);
    if (!$course) parentQuizJson(['status' => 'error', 'message' => 'ไม่มีสิทธิ์ดูผลสอบของนักเรียนในรายวิชานี้'], 403);

    $lessonStmt = $conn->prepare(
        "SELECT l.lesson_id, l.position, l.title,
                latest.attempt_id, latest.submitted_at, latest.status AS attempt_status
         FROM public.lessons l
         LEFT JOIN LATERAL (
             SELECT a.attempt_id, a.submitted_at, a.status
             FROM public.quiz_attempts a
             WHERE a.student_id = :student_id AND a.lesson_id = l.lesson_id
             ORDER BY a.submitted_at DESC, a.attempt_number DESC
             LIMIT 1
         ) latest ON TRUE
         WHERE l.course_id = :course_id
         ORDER BY l.position ASC"
    );
    $lessonStmt->execute([':student_id' => $studentId, ':course_id' => $courseId]);
    $answerStmt = $conn->prepare(
        "SELECT q.question_id, q.question_text, q.options, q.correct_choice,
                q.question_type, q.max_score,
                aa.selected_choice, aa.answer_text, aa.score,
                aa.review_status, aa.result_status
         FROM public.quiz_attempt_answers aa
         INNER JOIN public.questions q ON q.question_id = aa.question_id
         WHERE aa.attempt_id = :attempt_id
         ORDER BY q.question_id"
    );

    $lessons = [];
    foreach ($lessonStmt->fetchAll(PDO::FETCH_ASSOC) as $lesson) {
        $hasAttempt = $lesson['attempt_id'] !== null;
        $questions = [];
        if ($hasAttempt) {
            $answerStmt->execute([':attempt_id' => $lesson['attempt_id']]);
            foreach ($answerStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $options = is_array($row['options']) ? $row['options'] : json_decode((string) $row['options'], true);
                if (!is_array($options)) $options = [];
                $options = array_values($options);
                $selectedChoice = strtoupper(trim((string) ($row['selected_choice'] ?? '')));
                $correctChoice = strtoupper(trim((string) ($row['correct_choice'] ?? '')));
                $isEssay = strtolower((string) $row['question_type']) === 'essay';
                $selectedIndex = strlen($selectedChoice) === 1 ? ord($selectedChoice) - 65 : -1;
                $questions[] = [
                    'question_text' => (string) $row['question_text'],
                    'question_type' => (string) $row['question_type'],
                    'options' => $options,
                    'selected_choice' => $selectedChoice,
                    'selected_option' => $selectedIndex >= 0 && isset($options[$selectedIndex]) ? (string) $options[$selectedIndex] : '',
                    'correct_choice' => $correctChoice,
                    'answer_text' => (string) ($row['answer_text'] ?? ''),
                    'score' => $row['score'] !== null ? (float) $row['score'] : null,
                    'max_score' => (float) $row['max_score'],
                    'review_status' => (string) ($row['review_status'] ?? ''),
                    'result_status' => (string) ($row['result_status'] ?? ''),
                    'is_correct' => !$isEssay && $selectedChoice !== '' && $selectedChoice === $correctChoice,
                ];
            }
        }
        $lessons[] = [
            'position' => (int) $lesson['position'],
            'title' => (string) $lesson['title'],
            'has_attempt' => $hasAttempt,
            'submitted_at' => $lesson['submitted_at'],
            'questions' => $questions,
        ];
    }

    parentQuizJson(['status' => 'success', 'course_name' => (string) $course['course_name'], 'lessons' => $lessons]);
} catch (Throwable $error) {
    error_log('Parent quiz details failed: ' . $error->getMessage());
    parentQuizJson(['status' => 'error', 'message' => 'โหลดรายละเอียดคำตอบไม่สำเร็จ'], 500);
}
