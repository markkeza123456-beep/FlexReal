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
// คำขอนี้อ่าน session อย่างเดียว: ปลดล็อกทันทีเพื่อไม่ให้การออกจากระบบต้องรอ query หน้าแดชบอร์ด
session_write_close();

try {
    ensureLearningProgressTables($conn);
    ensureCurriculumSubjectTypeColumn($conn);

    $studentStmt = $conn->prepare(
        'SELECT student_id, student_name, email, tel, studcurriculums_id, avatar_url,
                COALESCE(NULLIF(TRIM(student_level), \'\'), NULLIF(TRIM(education_level), \'\'), \'-\') AS class_name
         FROM public.student
         WHERE student_id = :student_id
         LIMIT 1'
    );
    $studentStmt->execute([':student_id' => $studentId]);
    $student = $studentStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $courseStmt = $conn->prepare(
        'SELECT
            s.subjects_id,
            s.subjects_name,
            s.subjects_description,
            COALESCE(NULLIF(TRIM(cs.subject_type), \'\'), COALESCE(NULLIF(TRIM(s.subject_type), \'\'), \'elective\')) AS subject_type,
            (SELECT COUNT(*) FROM public.lessons l WHERE l.subjects_id = s.subjects_id) AS lesson_count,
            (SELECT COUNT(*) FROM public.student_learning_progress lp
             WHERE lp.student_id = ss.student_id
               AND lp.subjects_id = ss.subjects_id
               AND lp.quiz_attempts > 0) AS attempted_lessons,
            COALESCE((SELECT SUM(lp.best_quiz_score) FROM public.student_learning_progress lp
             WHERE lp.student_id = ss.student_id AND lp.subjects_id = ss.subjects_id), 0) AS score_earned,
            COALESCE((SELECT SUM(lp.quiz_total_score) FROM public.student_learning_progress lp
             WHERE lp.student_id = ss.student_id AND lp.subjects_id = ss.subjects_id), 0) AS score_total,
            (SELECT MAX(lp.last_activity_at) FROM public.student_learning_progress lp
             WHERE lp.student_id = ss.student_id AND lp.subjects_id = ss.subjects_id) AS last_activity_at
         FROM public.student_subject ss
         INNER JOIN public.subjects s ON s.subjects_id = ss.subjects_id
         LEFT JOIN public.curriculums_subject cs
            ON cs.subject_id = s.subjects_id
           AND cs.curriculums_id = :curriculum_id
         WHERE ss.student_id = :student_id
         GROUP BY ss.student_id, ss.subjects_id, s.subjects_id, s.subjects_name, s.subjects_description, s.subject_type, cs.subject_type
         ORDER BY s.subjects_name ASC'
    );
    $courseStmt->execute([
        ':student_id' => $studentId,
        ':curriculum_id' => (string) ($student['studcurriculums_id'] ?? ''),
    ]);

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
            'subject_id' => (string) ($row['subjects_id'] ?? ''),
            'name' => (string) ($row['subjects_name'] ?? 'ไม่ระบุชื่อวิชา'),
            'description' => (string) ($row['subjects_description'] ?? ''),
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
            'name' => (string) ($student['student_name'] ?? ($_SESSION['name'] ?? $studentId)),
            'email' => (string) ($student['email'] ?? ''),
            'phone' => (string) ($student['tel'] ?? ''),
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
