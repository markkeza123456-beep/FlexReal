<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db_connect.php';

function gradeFromScore(float $score): string
{
    if ($score >= 80) return 'A';
    if ($score >= 75) return 'B+';
    if ($score >= 70) return 'B';
    if ($score >= 65) return 'C+';
    if ($score >= 60) return 'C';
    if ($score >= 55) return 'D+';
    if ($score >= 50) return 'D';
    return 'F';
}

function gpaPoint(float $score): float
{
    return match (true) {
        $score >= 80 => 4.0, $score >= 75 => 3.5, $score >= 70 => 3.0,
        $score >= 65 => 2.5, $score >= 60 => 2.0, $score >= 55 => 1.5,
        $score >= 50 => 1.0, default => 0.0,
    };
}

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'parent') {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อน', 'redirect' => 'login.php']);
    exit;
}

try {
    $parentId = (string) $_SESSION['user_id'];
    $parentStmt = $conn->prepare('SELECT user_id AS parents_id, full_name AS parents_name, email, phone AS tel FROM public.parents WHERE user_id = :id LIMIT 1');
    $parentStmt->execute([':id' => $parentId]);
    $parent = $parentStmt->fetch(PDO::FETCH_ASSOC);
    if (!$parent) throw new RuntimeException('ไม่พบข้อมูลผู้ปกครอง');

    $studentStmt = $conn->prepare('SELECT user_id AS student_id, full_name AS student_name, student_level, avatar_url FROM public.students WHERE parent_user_id = :id ORDER BY full_name ASC');
    $studentStmt->execute([':id' => $parentId]);
    $children = [];
    $courseStmt = $conn->prepare(
        "SELECT c.name AS subject_name, COALESCE(SUM(a.score), 0) AS score_earned, COALESCE(SUM(a.total_score), 0) AS score_possible
         FROM public.student_courses sc
         INNER JOIN public.courses c ON c.course_id = sc.course_id
         LEFT JOIN public.lessons l ON l.course_id = c.course_id
         LEFT JOIN LATERAL (
             SELECT score, total_score FROM public.quiz_attempts
             WHERE student_id = :student_id AND lesson_id = l.lesson_id
             ORDER BY submitted_at DESC LIMIT 1
         ) a ON true
         WHERE sc.student_id = :student_id AND sc.status = 'active'
         GROUP BY c.course_id, c.name ORDER BY c.name"
    );

    foreach ($studentStmt->fetchAll(PDO::FETCH_ASSOC) as $student) {
        $courseStmt->execute([':student_id' => $student['student_id']]);
        $subjects = [];
        foreach ($courseStmt->fetchAll(PDO::FETCH_ASSOC) as $course) {
            $percent = (float) $course['score_possible'] > 0 ? round(((float) $course['score_earned'] / (float) $course['score_possible']) * 100, 1) : 0.0;
            $subjects[] = ['subject_name' => $course['subject_name'], 'score_mid' => 0, 'score_final' => $percent, 'total_score' => $percent, 'grade' => gradeFromScore($percent)];
        }
        $scores = array_column($subjects, 'total_score');
        $topScore = $scores ? max($scores) : 0;
        $topSubject = '';
        foreach ($subjects as $subject) {
            if ((float) $subject['total_score'] === (float) $topScore) { $topSubject = $subject['subject_name']; break; }
        }
        $children[] = [
            'student_id' => $student['student_id'], 'student_name' => $student['student_name'],
            'student_level' => $student['student_level'] ?? '', 'avatar_url' => $student['avatar_url'] ?? null,
            'initial' => mb_substr((string) $student['student_name'], 0, 1), 'subjects' => $subjects,
            'stats' => [
                'gpa' => $scores ? round(array_sum(array_map('gpaPoint', $scores)) / count($scores), 2) : 0,
                'grade_a' => count(array_filter($subjects, static fn(array $subject): bool => str_starts_with($subject['grade'], 'A'))),
                'top_score' => $topScore, 'top_subject' => $topSubject,
            ],
        ];
    }
    echo json_encode(['status' => 'success', 'parent' => $parent, 'children' => $children], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Parent dashboard error: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถโหลดข้อมูลผู้ปกครองได้'], JSON_UNESCAPED_UNICODE);
}
