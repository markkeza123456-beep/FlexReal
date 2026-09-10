<?php
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ตรวจสอบสิทธิ์
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/db_connect.php';

$teacherId = (string) $_SESSION['user_id'];
$studentId = trim((string) ($_GET['student_id'] ?? ''));

if ($studentId === '') {
    echo json_encode(['error' => 'student_id required']);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT
            l.title AS lesson_name,
            c.name AS subject_name,
            COALESCE(a.score, 0) AS best_quiz_score,
            COALESCE(a.total_score, 0) AS quiz_total_score,
            l.position AS lesson_index
        FROM public.student_courses sc
        INNER JOIN public.courses c ON c.course_id = sc.course_id
        INNER JOIN public.course_teachers ct ON ct.course_id = c.course_id AND ct.teacher_id = :tid
        INNER JOIN public.lessons l ON l.course_id = c.course_id
        LEFT JOIN LATERAL (
            SELECT score, total_score FROM public.quiz_attempts
            WHERE student_id = :sid AND lesson_id = l.lesson_id
            ORDER BY submitted_at DESC LIMIT 1
        ) a ON true
        WHERE sc.student_id = :sid AND sc.status = 'active'
        ORDER BY c.name ASC, l.position ASC
    ");
    $stmt->execute([':sid' => $studentId, ':tid' => $teacherId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $lessons = array_map(function($row) {
        return [
            'lesson_name'      => $row['lesson_name'] ?: ('บทที่ ' . $row['lesson_index']),
            'subject_name'     => $row['subject_name'] ?: '',
            'best_quiz_score'  => (int) $row['best_quiz_score'],
            'quiz_total_score' => (int) $row['quiz_total_score'],
        ];
    }, $rows);

    echo json_encode(['lessons' => $lessons], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
