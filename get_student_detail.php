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
    // ดึงคะแนนแต่ละบทเรียนของนักเรียน เฉพาะวิชาที่อาจารย์คนนี้สอน
    $stmt = $conn->prepare("
        SELECT
            l.Lessons_Name        AS lesson_name,
            s.Subjects_Name       AS subject_name,
            lp.best_quiz_score,
            lp.quiz_total_score,
            lp.lesson_index
        FROM public.student_learning_progress lp
        INNER JOIN public.subjects s  ON s.Subjects_ID  = lp.subjects_id
        INNER JOIN public.lessons  l  ON l.Lessons_ID   = lp.lesson_id
        WHERE lp.student_id = :sid
          AND s.teachers_id = :tid
        ORDER BY s.Subjects_Name ASC, lp.lesson_index ASC
    ");
    $stmt->execute([':sid' => $studentId, ':tid' => $teacherId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ถ้าไม่มีข้อมูลใน student_learning_progress ให้ดึงรายการบทเรียนที่นักเรียนลงทะเบียนแล้ว
    if (empty($rows)) {
        $stmt2 = $conn->prepare("
            SELECT
                l.Lessons_Name  AS lesson_name,
                s.Subjects_Name AS subject_name,
                0               AS best_quiz_score,
                0               AS quiz_total_score,
                l.Lessons_ID    AS lesson_index
            FROM public.student_subject ss
            INNER JOIN public.subjects s ON s.Subjects_ID = ss.Subjects_ID
            INNER JOIN public.lessons  l ON l.Subjects_ID = s.Subjects_ID
            WHERE ss.Student_ID = :sid
              AND s.Teachers_ID = :tid
            ORDER BY s.Subjects_Name ASC, l.Lessons_ID ASC
        ");
        $stmt2->execute([':sid' => $studentId, ':tid' => $teacherId]);
        $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    }

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