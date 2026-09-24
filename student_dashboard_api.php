<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

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
        "SELECT DISTINCT c.course_id, c.name, COALESCE(c.description, '') AS description,
                COALESCE(cc.requirement_type, 'elective') AS subject_type
         FROM public.student_courses sc
         INNER JOIN public.courses c ON c.course_id = sc.course_id
         LEFT JOIN LATERAL (
             SELECT curriculum_id FROM public.student_curricula
             WHERE student_id = :student_id AND status = 'active'
             ORDER BY enrolled_at DESC LIMIT 1
         ) active_curriculum ON true
         LEFT JOIN public.curriculum_courses cc ON cc.curriculum_id = active_curriculum.curriculum_id AND cc.course_id = c.course_id
         WHERE sc.student_id = :student_id AND sc.status = 'active' AND c.status = 'active'
         ORDER BY c.name ASC"
    );
    $courseStmt->execute([':student_id' => $studentId]);

    $courses = [];
    $attemptedLessons = 0;
    $totalLessons = 0;
    $scoreEarned = 0.0;
    $scoreTotal = 0.0;
    foreach ($courseStmt->fetchAll(PDO::FETCH_ASSOC) as $index => $row) {
        $lessonCount = 0;
        $attempted = 0;
        $earned = 0;
        $possible = 0;
        $progress = 0;
        $score = 0;
        $lessonStmt = $conn->prepare(
            "SELECT l.lesson_id, l.position, l.title,
                    COALESCE(lp.document_progress_percent, 0) AS document_progress,
                    COALESCE(lp.video_progress_percent, 0) AS video_progress,
                    COALESCE(lp.last_activity_at, qa.submitted_at) AS last_activity_at,
                    qa.attempt_id, qa.attempt_number, qa.status AS quiz_status,
                    qa.submitted_at, qa.score, qa.total_score,
                    COALESCE(answer_scores.answer_count, 0) AS answer_count,
                    COALESCE(answer_scores.objective_score, qa.score, 0) AS objective_score,
                    COALESCE(answer_scores.objective_total, 0) AS objective_total,
                    COALESCE(answer_scores.essay_score, 0) AS essay_score,
                    COALESCE(answer_scores.essay_total, 0) AS essay_total,
                    COALESCE(answer_scores.essay_pending_count, 0) AS essay_pending_count,
                    COALESCE(answer_scores.essay_question_count, 0) AS essay_question_count,
                    COALESCE(answer_scores.essay_feedback, '') AS essay_feedback,
                    COALESCE(qa.attempt_count, 0) AS attempt_count
             FROM public.lessons l
             LEFT JOIN public.lesson_progress lp ON lp.lesson_id = l.lesson_id AND lp.student_id = :student_id
             LEFT JOIN LATERAL (
                 SELECT attempt_id, attempt_number, status, score, total_score, submitted_at,
                        COUNT(*) OVER () AS attempt_count
                 FROM public.quiz_attempts WHERE student_id = :student_id AND lesson_id = l.lesson_id
                 ORDER BY submitted_at DESC, attempt_number DESC LIMIT 1
             ) qa ON true
             LEFT JOIN LATERAL (
                 SELECT COUNT(*) AS answer_count,
                        COALESCE(SUM(aa.score) FILTER (WHERE q.question_type <> 'essay'), 0) AS objective_score,
                        COALESCE(SUM(q.max_score) FILTER (WHERE q.question_type <> 'essay'), 0) AS objective_total,
                        COALESCE(SUM(aa.score) FILTER (WHERE q.question_type = 'essay' AND aa.review_status = 'reviewed'), 0) AS essay_score,
                        COALESCE(SUM(q.max_score) FILTER (WHERE q.question_type = 'essay'), 0) AS essay_total,
                        COUNT(*) FILTER (WHERE q.question_type = 'essay' AND aa.review_status = 'pending') AS essay_pending_count,
                        COUNT(*) FILTER (WHERE q.question_type = 'essay') AS essay_question_count,
                        STRING_AGG(DISTINCT NULLIF(BTRIM(aa.reviewer_comment), ''), ' · ') FILTER (WHERE q.question_type = 'essay' AND aa.review_status = 'reviewed') AS essay_feedback
                 FROM public.quiz_attempt_answers aa
                 INNER JOIN public.questions q ON q.question_id = aa.question_id
                 WHERE aa.attempt_id = qa.attempt_id
             ) answer_scores ON true
             WHERE l.course_id = :course_id
             ORDER BY l.position ASC"
        );
        $lessonStmt->execute([':student_id' => $studentId, ':course_id' => (string) $row['course_id']]);
        $lessons = [];
        foreach ($lessonStmt->fetchAll(PDO::FETCH_ASSOC) as $lessonRow) {
            $hasAttempt = $lessonRow['attempt_id'] !== null;
            $answerCount = (int) ($lessonRow['answer_count'] ?? 0);
            $objectiveScore = (float) ($lessonRow['objective_score'] ?? 0);
            $objectiveTotal = (float) ($lessonRow['objective_total'] ?? 0);
            $essayScore = (float) ($lessonRow['essay_score'] ?? 0);
            $essayTotal = (float) ($lessonRow['essay_total'] ?? 0);
            if ($answerCount === 0 && $hasAttempt) {
                $objectiveScore = (float) ($lessonRow['score'] ?? 0);
            }
            $lessonScoreEarned = $objectiveScore + $essayScore;
            $lessonScoreTotal = $hasAttempt ? (float) ($lessonRow['total_score'] ?? 0) : 0.0;
            $lessonProgress = lessonProgressFromQuiz(
                $lessonScoreEarned,
                $lessonScoreTotal,
                (float) ($lessonRow['document_progress'] ?? 0),
                (float) ($lessonRow['video_progress'] ?? 0)
            );
            $lessons[] = [
                'id' => (string) $lessonRow['lesson_id'],
                'position' => (int) $lessonRow['position'],
                'title' => (string) $lessonRow['title'],
                'progress' => $lessonProgress,
                'attempt_count' => (int) $lessonRow['attempt_count'],
                'has_attempt' => $hasAttempt,
                'score_earned' => $lessonScoreEarned,
                'score_total' => $lessonScoreTotal,
                'score_percent' => $lessonScoreTotal > 0 ? round(($lessonScoreEarned / $lessonScoreTotal) * 100, 1) : null,
                'objective_score_earned' => $objectiveScore,
                'objective_score_total' => $objectiveTotal,
                'essay_score_earned' => $essayScore,
                'essay_score_total' => $essayTotal,
                'essay_pending_count' => (int) ($lessonRow['essay_pending_count'] ?? 0),
                'essay_question_count' => (int) ($lessonRow['essay_question_count'] ?? 0),
                'essay_feedback' => (string) ($lessonRow['essay_feedback'] ?? ''),
                'quiz_status' => $hasAttempt ? (string) ($lessonRow['quiz_status'] ?? 'submitted') : null,
                'latest_attempt_number' => $hasAttempt ? (int) $lessonRow['attempt_number'] : null,
                'last_activity_at' => $lessonRow['last_activity_at'] ?? null,
            ];
        }
        // The lesson list is the source of truth for both sides of the
        // completed/total figure. This keeps the denominator in sync with the
        // actual lesson rows returned for the student's enrolled course.
        $lessonCount = count($lessons);
        $attempted = count(array_filter($lessons, static fn (array $lesson): bool => $lesson['attempt_count'] > 0));
        $earned = array_sum(array_column($lessons, 'score_earned'));
        $possible = array_sum(array_column($lessons, 'score_total'));
        $progress = $lessonCount > 0 ? round(($attempted / $lessonCount) * 100, 1) : 0;
        $score = $possible > 0 ? round(($earned / $possible) * 100, 1) : 0;
        $attemptedLessons += $attempted;
        $totalLessons += $lessonCount;
        $scoreEarned += $earned;
        $scoreTotal += $possible;
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
            'status' => $possible > 0 ? dashboardScoreLabel($score) : 'ยังไม่มีคะแนน',
            'class' => $possible > 0 ? dashboardScoreClass($score) : 'no-score',
            'last_activity_at' => max(array_filter(array_column($lessons, 'last_activity_at')) ?: [null]),
            'lessons' => $lessons,
        ];
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
        'message' => 'โหลดข้อมูลแดชบอร์ดนักเรียนไม่สำเร็จ กรุณาลองอีกครั้ง',
    ], 500);
}
