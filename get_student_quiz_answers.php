<?php
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

if (($_SESSION['role'] ?? '') !== 'teacher' || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'กรุณาล็อกอินในฐานะอาจารย์']);
    exit;
}

require_once __DIR__ . '/config/db.php';
$teacherId = (string) $_SESSION['user_id'];
$studentId = trim((string) ($_GET['student_id'] ?? ''));
$lessonId = trim((string) ($_GET['lesson_id'] ?? ''));
if ($studentId === '' || $lessonId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'ข้อมูลนักเรียนหรือบทเรียนไม่ครบ']);
    exit;
}

try {
    $accessStmt = $conn->prepare(
        "SELECT l.course_id, l.title AS lesson_name, c.name AS course_name
         FROM public.lessons l
         INNER JOIN public.courses c ON c.course_id = l.course_id
         INNER JOIN public.course_teachers ct ON ct.course_id = c.course_id
         INNER JOIN public.student_courses sc ON sc.course_id = c.course_id
         WHERE l.lesson_id = :lesson_id AND ct.teacher_id = :teacher_id
           AND sc.student_id = :student_id AND sc.status = 'active'
         LIMIT 1"
    );
    $accessStmt->execute([':lesson_id' => $lessonId, ':teacher_id' => $teacherId, ':student_id' => $studentId]);
    $lesson = $accessStmt->fetch(PDO::FETCH_ASSOC);
    if (!$lesson) {
        http_response_code(403);
        echo json_encode(['error' => 'ไม่มีสิทธิ์ดูผลสอบบทเรียนนี้']);
        exit;
    }

    $answerStmt = $conn->prepare(
        "SELECT q.question_id, q.question_text, q.options, q.correct_choice, q.max_score, q.question_type,
                aa.selected_choice, aa.answer_text, aa.score, aa.review_status, aa.result_status,
                CASE WHEN aa.selected_choice IS NOT NULL AND aa.selected_choice = q.correct_choice THEN TRUE ELSE FALSE END AS is_correct,
                COALESCE(class_stats.incorrect_count, 0) AS class_incorrect_count,
                COALESCE(class_stats.answered_count, 0) AS class_answered_count
         FROM public.questions q
         LEFT JOIN LATERAL (
             SELECT a.attempt_id
             FROM public.quiz_attempts a
             WHERE a.student_id = :student_id AND a.lesson_id = q.lesson_id
             ORDER BY a.attempt_number DESC, a.submitted_at DESC
             LIMIT 1
         ) latest_attempt ON TRUE
         LEFT JOIN public.quiz_attempt_answers aa
           ON aa.attempt_id = latest_attempt.attempt_id AND aa.question_id = q.question_id
         LEFT JOIN LATERAL (
             SELECT COUNT(*) FILTER (WHERE class_answer.selected_choice IS NOT NULL AND class_answer.selected_choice <> q.correct_choice) AS incorrect_count,
                    COUNT(*) FILTER (WHERE class_answer.selected_choice IS NOT NULL) AS answered_count
             FROM public.student_courses class_sc
             INNER JOIN public.students class_student ON class_student.user_id = class_sc.student_id
             LEFT JOIN LATERAL (
                 SELECT a.attempt_id
                 FROM public.quiz_attempts a
                 WHERE a.student_id = class_sc.student_id AND a.lesson_id = q.lesson_id
                 ORDER BY a.attempt_number DESC, a.submitted_at DESC
                 LIMIT 1
             ) class_attempt ON TRUE
             LEFT JOIN public.quiz_attempt_answers class_answer
               ON class_answer.attempt_id = class_attempt.attempt_id AND class_answer.question_id = q.question_id
             WHERE class_sc.course_id = :course_id AND class_sc.status = 'active'
         ) class_stats ON TRUE
         WHERE q.lesson_id = :lesson_id
         ORDER BY q.question_id"
    );
    $answerStmt->execute([
        ':student_id' => $studentId,
        ':course_id' => $lesson['course_id'],
        ':lesson_id' => $lessonId,
    ]);
    $rows = $answerStmt->fetchAll(PDO::FETCH_ASSOC);
    $questions = [];
    foreach ($rows as $row) {
        $options = is_array($row['options']) ? $row['options'] : json_decode((string) $row['options'], true);
        if (!is_array($options)) $options = [];
        $questions[] = [
            'question_id' => (int) $row['question_id'],
            'question_text' => (string) $row['question_text'],
            'question_type' => (string) $row['question_type'],
            'options' => array_values($options),
            'correct_choice' => strtoupper(trim((string) $row['correct_choice'])),
            'selected_choice' => strtoupper(trim((string) ($row['selected_choice'] ?? ''))),
            'answer_text' => (string) ($row['answer_text'] ?? ''),
            'score' => $row['score'] !== null ? (float) $row['score'] : null,
            'max_score' => (float) $row['max_score'],
            'review_status' => (string) ($row['review_status'] ?? ''),
            'result_status' => (string) ($row['result_status'] ?? ''),
            'is_correct' => filter_var($row['is_correct'], FILTER_VALIDATE_BOOLEAN),
            'class_incorrect_count' => (int) $row['class_incorrect_count'],
            'class_answered_count' => (int) $row['class_answered_count'],
        ];
    }
    echo json_encode(['lesson' => $lesson, 'questions' => $questions], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    error_log('Student quiz answer detail failed: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'โหลดรายละเอียดคำตอบไม่สำเร็จ'], JSON_UNESCAPED_UNICODE);
}
