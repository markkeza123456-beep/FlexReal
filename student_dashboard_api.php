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
            COUNT(DISTINCT l.lessons_id) AS lesson_count,
            COALESCE(AVG(lp.progress_percent), 0) AS progress_percent,
            COALESCE(MAX(
                CASE
                    WHEN lp.quiz_total_score > 0 THEN (lp.best_quiz_score::numeric / lp.quiz_total_score) * 100
                    ELSE 0
                END
            ), 0) AS score_percent,
            COALESCE(MAX(lp.last_activity_at), NULL) AS last_activity_at
         FROM public.student_subject ss
         INNER JOIN public.subjects s ON s.subjects_id = ss.subjects_id
         LEFT JOIN public.curriculums_subject cs
            ON cs.subject_id = s.subjects_id
           AND cs.curriculums_id = :curriculum_id
         LEFT JOIN public.lessons l ON l.subjects_id = s.subjects_id
         LEFT JOIN public.student_learning_progress lp
            ON lp.student_id = ss.student_id
           AND lp.subjects_id = ss.subjects_id
         WHERE ss.student_id = :student_id
         GROUP BY s.subjects_id, s.subjects_name, s.subjects_description, s.subject_type, cs.subject_type
         ORDER BY s.subjects_name ASC'
    );
    $courseStmt->execute([
        ':student_id' => $studentId,
        ':curriculum_id' => (string) ($student['studcurriculums_id'] ?? ''),
    ]);

    $courses = [];
    $totalProgress = 0.0;
    $totalScore = 0.0;
    foreach ($courseStmt->fetchAll(PDO::FETCH_ASSOC) as $index => $row) {
        $progress = round((float) ($row['progress_percent'] ?? 0), 1);
        $score = round((float) ($row['score_percent'] ?? 0), 1);
        $courses[] = [
            'id' => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
            'subject_id' => (string) ($row['subjects_id'] ?? ''),
            'name' => (string) ($row['subjects_name'] ?? 'ไม่ระบุชื่อวิชา'),
            'description' => (string) ($row['subjects_description'] ?? ''),
            'subject_type' => (string) ($row['subject_type'] ?? 'elective'),
            'lesson_count' => (int) ($row['lesson_count'] ?? 0),
            'progress' => $progress,
            'score' => $score,
            'status' => dashboardScoreLabel($score),
            'class' => dashboardScoreClass($score),
            'last_activity_at' => $row['last_activity_at'] ?? null,
        ];
        $totalProgress += $progress;
        $totalScore += $score;
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
            'avg_progress' => $courseCount > 0 ? round($totalProgress / $courseCount, 1) : 0,
            'avg_score' => $courseCount > 0 ? round($totalScore / $courseCount, 1) : 0,
        ],
        'courses' => $courses,
        'assignments' => [],
    ]);
} catch (Throwable $e) {
    dashboardJson([
        'status' => 'error',
        'message' => 'โหลดข้อมูลแดชบอร์ดนักเรียนไม่สำเร็จ: ' . $e->getMessage(),
    ], 500);
}