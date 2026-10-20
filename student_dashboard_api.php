<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

require_once __DIR__ . '/config/db.php';
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

function dashboardActivityProgress(float $quizScore, float $quizTotal, float $documentPercent, float $videoPercent): float
{
    $videoPart = min(100.0, max(0.0, $videoPercent)) * 0.30;
    $documentPart = min(100.0, max(0.0, $documentPercent)) * 0.30;
    $quizPart = $quizTotal > 0
        ? min(40.0, max(0.0, ($quizScore / $quizTotal) * 40.0))
        : 0.0;

    return round($videoPart + $documentPart + $quizPart, 1);
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
        "SELECT DISTINCT c.course_id, c.code, c.name, COALESCE(c.description, '') AS description,
                COALESCE(sc.status, 'active') AS enrollment_status,
                COALESCE(CASE WHEN cc.requirement_type = 'required' THEN 'compulsory' WHEN cc.requirement_type = 'elective' THEN 'elective' END, c.subject_type, 'elective') AS subject_type
         FROM public.student_courses sc
         INNER JOIN public.courses c ON c.course_id = sc.course_id
         LEFT JOIN LATERAL (
             SELECT curriculum_id FROM public.student_curricula
             WHERE student_id = :student_id AND status = 'active'
             ORDER BY enrolled_at DESC LIMIT 1
         ) active_curriculum ON true
         LEFT JOIN public.curriculum_courses cc ON cc.curriculum_id = active_curriculum.curriculum_id AND cc.course_id = c.course_id
         -- Show every course linked to this student, including historical enrollments
         -- and courses hidden from the active catalog, so dashboard totals match DB links.
         WHERE sc.student_id = :student_id
         ORDER BY c.name ASC"
    );
    $courseStmt->execute([':student_id' => $studentId]);

    $courses = [];
    $attemptedLessons = 0;
    $quizAttempts = 0;
    $totalLessons = 0;
    $scoreEarned = 0.0;
    $scoreTotal = 0.0;
    $completedCourses = 0;
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
                    CASE WHEN COALESCE(lp.is_transferred, FALSE) OR UPPER(COALESCE(lp.grade, '')) = 'TR' THEN 1 ELSE 0 END AS is_transferred,
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
                        COUNT(*) FILTER (WHERE q.question_type <> 'essay') AS objective_question_count,
                        COUNT(*) FILTER (WHERE q.question_type <> 'essay' AND aa.score >= q.max_score) AS objective_correct_count,
                        COALESCE(SUM(aa.score) FILTER (WHERE q.question_type = 'essay' AND aa.review_status = 'graded'), 0) AS essay_score,
                        COALESCE(SUM(q.max_score) FILTER (WHERE q.question_type = 'essay'), 0) AS essay_total,
                        COUNT(*) FILTER (WHERE q.question_type = 'essay' AND aa.review_status = 'pending') AS essay_pending_count,
                        COUNT(*) FILTER (WHERE q.question_type = 'essay' AND aa.review_status = 'graded') AS essay_reviewed_count,
                        COUNT(*) FILTER (WHERE q.question_type = 'essay') AS essay_question_count,
                        STRING_AGG(DISTINCT NULLIF(BTRIM(aa.reviewer_comment), ''), ' · ') FILTER (WHERE q.question_type = 'essay' AND aa.review_status = 'graded') AS essay_feedback
                 FROM public.quiz_attempt_answers aa
                 INNER JOIN public.questions q ON q.question_id = aa.question_id
                 WHERE aa.attempt_id = qa.attempt_id
             ) answer_scores ON true
             WHERE l.course_id = :course_id
             ORDER BY l.position ASC"
        );
        $lessonStmt->execute([':student_id' => $studentId, ':course_id' => (string) $row['course_id']]);
        $essayAnswersStmt = $conn->prepare(
            "SELECT q.question_id, q.question_text AS question, aa.answer_text AS answer,
                    aa.review_status, aa.result_status AS status,
                    aa.score, q.max_score, aa.reviewer_comment AS feedback, a.submitted_at, aa.reviewed_at,
                    aa.reviewer_id, t.full_name AS reviewer_name
             FROM public.quiz_attempt_answers aa
             INNER JOIN public.quiz_attempts a ON a.attempt_id = aa.attempt_id
             INNER JOIN public.questions q ON q.question_id = aa.question_id
             LEFT JOIN public.teachers t ON t.user_id = aa.reviewer_id
             WHERE aa.attempt_id = :attempt_id AND a.student_id = :student_id AND q.question_type = 'essay' AND aa.review_status IN ('pending', 'graded')
             ORDER BY q.question_id"
        );
        $lessons = [];
        foreach ($lessonStmt->fetchAll(PDO::FETCH_ASSOC) as $lessonRow) {
            $hasAttempt = $lessonRow['attempt_id'] !== null;
            $isTransferred = (int) $lessonRow['is_transferred'] === 1;
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
            $lessonProgress = $isTransferred ? 100 : dashboardActivityProgress(
                $lessonScoreEarned,
                $lessonScoreTotal,
                (float) ($lessonRow['document_progress'] ?? 0),
                (float) ($lessonRow['video_progress'] ?? 0)
            );
            $isPassed = $isTransferred
                || (string) ($lessonRow['quiz_status'] ?? '') === 'passed'
                || ((string) ($lessonRow['quiz_status'] ?? '') !== 'pending_review'
                    && $lessonScoreTotal > 0
                    && $lessonScoreEarned >= ceil($lessonScoreTotal)
                    && (int) ($lessonRow['essay_pending_count'] ?? 0) === 0);
            $lessons[] = [
                'id' => (string) $lessonRow['lesson_id'],
                'position' => (int) $lessonRow['position'],
                'title' => (string) $lessonRow['title'],
                'progress' => $lessonProgress,
                'attempt_count' => (int) $lessonRow['attempt_count'],
                'has_attempt' => $hasAttempt || $isTransferred,
                'is_transferred' => $isTransferred,
                'is_passed' => $isPassed,
                'score_earned' => $lessonScoreEarned,
                'score_total' => $lessonScoreTotal,
                'score_percent' => $lessonScoreTotal > 0 ? round(($lessonScoreEarned / $lessonScoreTotal) * 100, 1) : null,
                'objective_score_earned' => $objectiveScore,
                'objective_score_total' => $objectiveTotal,
                'objective_question_count' => (int) ($lessonRow['objective_question_count'] ?? 0),
                'objective_correct_count' => (int) ($lessonRow['objective_correct_count'] ?? 0),
                'essay_score_earned' => $essayScore,
                'essay_score_total' => $essayTotal,
                'essay_pending_count' => (int) ($lessonRow['essay_pending_count'] ?? 0),
                'essay_reviewed_count' => (int) ($lessonRow['essay_reviewed_count'] ?? 0),
                'essay_question_count' => (int) ($lessonRow['essay_question_count'] ?? 0),
                'essay_feedback' => (string) ($lessonRow['essay_feedback'] ?? ''),
                'essay_answers' => [],
                'quiz_status' => $isTransferred ? 'passed' : ($hasAttempt ? (string) ($lessonRow['quiz_status'] ?? 'submitted') : null),
                'latest_attempt_number' => $hasAttempt ? (int) $lessonRow['attempt_number'] : null,
                'last_activity_at' => $lessonRow['last_activity_at'] ?? null,
            ];
            if ($hasAttempt && (int) ($lessonRow['essay_question_count'] ?? 0) > 0) {
                $essayAnswersStmt->execute([':attempt_id' => $lessonRow['attempt_id'], ':student_id' => $studentId]);
                $lessons[array_key_last($lessons)]['essay_answers'] = $essayAnswersStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
        // The lesson list is the source of truth for both sides of the
        // completed/total figure. This keeps the denominator in sync with the
        // actual lesson rows returned for the student's enrolled course.
        $lessonCount = count($lessons);
        $attempted = count(array_filter($lessons, static fn (array $lesson): bool => $lesson['attempt_count'] > 0 || $lesson['is_transferred']));
        $passedLessonCount = count(array_filter($lessons, static fn (array $lesson): bool => $lesson['is_passed']));
        $isCourseTransferred = strtoupper(trim((string) $row['enrollment_status'])) === 'TR'
            || ($lessonCount > 0 && count(array_filter($lessons, static fn (array $lesson): bool => $lesson['is_transferred'])) === $lessonCount);
        $isCoursePassed = in_array(strtolower(trim((string) $row['enrollment_status'])), ['passed', 'completed', 'tr'], true)
            || ($lessonCount > 0 && $passedLessonCount === $lessonCount);
        $earned = array_sum(array_column($lessons, 'score_earned'));
        $possible = array_sum(array_column($lessons, 'score_total'));
        $progress = $isCoursePassed ? 100 : ($lessonCount > 0 ? round(array_sum(array_column($lessons, 'progress')) / $lessonCount, 1) : 0);
        $score = $possible > 0 ? round(($earned / $possible) * 100, 1) : 0;
        $attemptedLessons += $attempted;
        $quizAttempts += array_sum(array_column($lessons, 'attempt_count'));
        $totalLessons += $lessonCount;
        $scoreEarned += $earned;
        $scoreTotal += $possible;
        if ($isCoursePassed) {
            $completedCourses++;
        }
        $courses[] = [
            'id' => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
            'subject_id' => (string) ($row['course_id'] ?? ''),
            'code' => (string) ($row['code'] ?? ''),
            'name' => (string) ($row['name'] ?? 'ไม่ระบุชื่อวิชา'),
            'description' => (string) ($row['description'] ?? ''),
            'subject_type' => (string) ($row['subject_type'] ?? 'elective'),
            'lesson_count' => $lessonCount,
            'attempted_lessons' => $attempted,
            'passed_lessons' => $passedLessonCount,
            'is_transferred' => $isCourseTransferred,
            'is_passed' => $isCoursePassed,
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
            'completed_courses' => $completedCourses,
            'course_progress_percent' => $courseCount > 0 ? round(($completedCourses / $courseCount) * 100, 1) : 0,
            'attempted_lessons' => $attemptedLessons,
            'quiz_attempts' => $quizAttempts,
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
