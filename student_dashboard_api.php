<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/learning_progress_lib.php';
require_once __DIR__ . '/curriculum_subjects_lib.php';

function dashboardJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function dashboardScoreClass(float $score): string
{
    if ($score >= 85) {
        return 'excellent';
    }
    if ($score >= 70) {
        return 'good';
    }
    if ($score >= 50) {
        return 'average';
    }

    return 'needs-help';
}

function dashboardScoreLabel(float $score): string
{
    $class = dashboardScoreClass($score);
    $labels = [
        'excellent' => 'ดีเยี่ยม',
        'good' => 'ดี',
        'average' => 'ปานกลาง',
        'needs-help' => 'ต้องดูแล',
    ];

    return $labels[$class] ?? 'ยังไม่มีข้อมูล';
}

$role = strtolower((string) ($_SESSION['role'] ?? ''));
if (!isset($_SESSION['user_id']) || $role !== 'student') {
    dashboardJson([
        'status' => 'unauthorized',
        'message' => 'กรุณาเข้าสู่ระบบนักเรียนก่อนใช้งาน',
    ], 401);
}

$studentId = (string) $_SESSION['user_id'];

session_write_close();

try {
    ensureLearningProgressTables($conn);

    $studentStmt = $conn->prepare(
        'SELECT user_id, full_name, email, phone, avatar_url,
                COALESCE(NULLIF(TRIM(student_level), \'\'), NULLIF(TRIM(education_level), \'\'), \'-\') AS class_name
         FROM public.students
         WHERE user_id = :student_id
         LIMIT 1'
    );
    $studentStmt->execute([':student_id' => $studentId]);
    $student = $studentStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $courseStmt = $conn->prepare(
        "SELECT c.course_id, c.name, COALESCE(c.description, '') AS description,
                COALESCE(cc.requirement_type, 'elective') AS subject_type,
                COUNT(l.lesson_id) AS lesson_count,
                COUNT(qa.attempt_id) FILTER (WHERE qa.attempt_id IS NOT NULL) AS attempted_lessons,
                COALESCE(SUM(qa.score), 0) AS score_earned,
                COALESCE(SUM(qa.total_score), 0) AS score_total,
                MAX(COALESCE(qa.submitted_at, lp.last_activity_at)) AS last_activity_at
         FROM public.student_courses sc
         INNER JOIN public.courses c ON c.course_id = sc.course_id
         LEFT JOIN LATERAL (
             SELECT curriculum_id FROM public.student_curricula
             WHERE student_id = :student_id AND status = 'active'
             ORDER BY enrolled_at DESC LIMIT 1
         ) active_curriculum ON true
         LEFT JOIN public.curriculum_courses cc ON cc.curriculum_id = active_curriculum.curriculum_id AND cc.course_id = c.course_id
         LEFT JOIN public.lessons l ON l.course_id = c.course_id
         LEFT JOIN LATERAL (
             SELECT attempt_id, score, total_score, submitted_at FROM public.quiz_attempts
             WHERE student_id = :student_id AND lesson_id = l.lesson_id
             ORDER BY submitted_at DESC LIMIT 1
         ) qa ON true
         LEFT JOIN public.lesson_progress lp ON lp.student_id = :student_id AND lp.lesson_id = l.lesson_id
         WHERE sc.student_id = :student_id AND sc.status = 'active' AND c.status = 'active'
         GROUP BY c.course_id, c.name, c.description, cc.requirement_type
         ORDER BY c.name ASC"
    );
    $courseStmt->execute([':student_id' => $studentId]);

    $courses = [];
    $attemptedLessons = 0;
    $totalLessons = 0;
    $scoreEarned = 0;
    $scoreTotal = 0;
    foreach ($courseStmt->fetchAll(PDO::FETCH_ASSOC) as $index => $row) {
        $lessonCount = (int) ($row['lesson_count'] ?? 0);
        $attempted = min((int) ($row['attempted_lessons'] ?? 0), $lessonCount);
        $earned = (int) ($row['score_earned'] ?? 0);
        $possible = (int) ($row['score_total'] ?? 0);
        $progress = $lessonCount > 0 ? round(($attempted / $lessonCount) * 100, 1) : 0;
        $score = $possible > 0 ? round(($earned / $possible) * 100, 1) : 0;
        $courses[] = [
            'id' => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
            'subject_id' => (string) ($row['course_id'] ?? ''),
            'name' => (string) ($row['name'] ?? 'ไม่ระบุชื่อวิชา'),
            'description' => (string) ($row['description'] ?? ''),
            'subject_type' => (string) ($row['subject_type'] ?? 'elective'),
            'lesson_count' => $lessonCount,
            'attempted_lessons' => $attempted,
            'score_earned' => $earned,
            'score_total' => $possible,
            'progress' => $progress,
            'score' => $score,
            'status' => dashboardScoreLabel($score),
            'class' => dashboardScoreClass($score),
            'last_activity_at' => $row['last_activity_at'] ?? null,
        ];
        $attemptedLessons += $attempted;
        $totalLessons += $lessonCount;
        $scoreEarned += $earned;
        $scoreTotal += $possible;
    }

    $courseCount = count($courses);
    dashboardJson([
        'status' => 'success',
        'student' => [
            'id' => $studentId,
            'name' => (string) ($student['full_name'] ?? ($_SESSION['name'] ?? $studentId)),
            'email' => (string) ($student['email'] ?? ''),
            'phone' => (string) ($student['phone'] ?? ''),
            'class_name' => (string) ($student['class_name'] ?? '-'),
            'avatar_url' => (string) ($student['avatar_url'] ?? ''),
        ],
        'stats' => [
            'course_count' => $courseCount,
            'attempted_lessons' => $attemptedLessons,
            'total_lessons' => $totalLessons,
            'score_earned' => $scoreEarned,
            'score_total' => $scoreTotal,
        ],
        'courses' => $courses,
    ]);
} catch (Throwable $e) {
    dashboardJson([
        'status' => 'error',
        'message' => 'โหลดข้อมูลแดชบอร์ดนักเรียนไม่สำเร็จ: ' . $e->getMessage(),
    ], 500);
}
