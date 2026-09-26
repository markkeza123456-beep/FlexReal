<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/learning_progress_lib.php';

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
    $parentStmt = $conn->prepare('SELECT user_id AS parents_id, full_name AS parents_name, email, phone FROM public.parents WHERE user_id = :id LIMIT 1');
    $parentStmt->execute([':id' => $parentId]);
    $parent = $parentStmt->fetch(PDO::FETCH_ASSOC);
    if (!$parent) throw new RuntimeException('ไม่พบข้อมูลผู้ปกครอง');

    $studentStmt = $conn->prepare('SELECT user_id AS student_id, full_name AS student_name, student_level, avatar_url FROM public.students WHERE parent_user_id = :id ORDER BY full_name ASC');
    $studentStmt->execute([':id' => $parentId]);
    $children = [];
    $courseStmt = $conn->prepare(
        "SELECT DISTINCT c.course_id, c.name AS subject_name,
                COALESCE(cc.requirement_type, 'elective') AS subject_type
         FROM public.student_courses sc
         INNER JOIN public.courses c ON c.course_id = sc.course_id
         LEFT JOIN LATERAL (
             SELECT curriculum_id FROM public.student_curricula
             WHERE student_id = :student_id AND status = 'active'
             ORDER BY enrolled_at DESC LIMIT 1
         ) active_curriculum ON true
         LEFT JOIN public.curriculum_courses cc ON cc.curriculum_id = active_curriculum.curriculum_id AND cc.course_id = c.course_id
         WHERE sc.student_id = :student_id
         ORDER BY c.name"
    );
    $lessonStmt = $conn->prepare(
        "SELECT l.lesson_id, l.position, l.title,
                COALESCE(lp.document_progress_percent, 0) AS document_progress,
                COALESCE(lp.video_progress_percent, 0) AS video_progress,
                COALESCE(lp.last_activity_at, latest.submitted_at) AS last_activity_at,
                latest.attempt_id, latest.status AS quiz_status, latest.submitted_at,
                latest.score, latest.total_score, COALESCE(latest.attempt_count, 0) AS attempt_count,
                COALESCE(answer_scores.answer_count, 0) AS answer_count,
                COALESCE(answer_scores.essay_count, 0) AS essay_count,
                COALESCE(answer_scores.objective_score, latest.score, 0) AS objective_score,
                COALESCE(answer_scores.essay_score, 0) AS essay_score,
                COALESCE(answer_scores.essay_score_possible, 0) AS essay_score_possible,
                COALESCE(answer_scores.pending_essays, 0) AS pending_essays
         FROM public.lessons l
         LEFT JOIN public.lesson_progress lp ON lp.lesson_id = l.lesson_id AND lp.student_id = :student_id
         LEFT JOIN LATERAL (
             SELECT attempt_id, status, submitted_at, score, total_score, COUNT(*) OVER () AS attempt_count
             FROM public.quiz_attempts
             WHERE student_id = :student_id AND lesson_id = l.lesson_id
             ORDER BY submitted_at DESC, attempt_number DESC LIMIT 1
         ) latest ON true
         LEFT JOIN LATERAL (
             SELECT COUNT(*) AS answer_count,
                    COUNT(*) FILTER (WHERE q.question_type = 'essay') AS essay_count,
                    COALESCE(SUM(aa.score) FILTER (WHERE q.question_type <> 'essay'), 0) AS objective_score,
                    COALESCE(SUM(aa.score) FILTER (WHERE q.question_type = 'essay' AND aa.review_status = 'reviewed'), 0) AS essay_score,
                    COALESCE(SUM(q.max_score) FILTER (WHERE q.question_type = 'essay'), 0) AS essay_score_possible,
                    COUNT(*) FILTER (WHERE q.question_type = 'essay' AND aa.review_status = 'pending') AS pending_essays
             FROM public.quiz_attempt_answers aa
             INNER JOIN public.questions q ON q.question_id = aa.question_id
             WHERE aa.attempt_id = latest.attempt_id
         ) answer_scores ON true
         WHERE l.course_id = :course_id
         ORDER BY l.position ASC"
    );

    foreach ($studentStmt->fetchAll(PDO::FETCH_ASSOC) as $student) {
        $courseStmt->execute([':student_id' => $student['student_id']]);
        $subjects = [];
        foreach ($courseStmt->fetchAll(PDO::FETCH_ASSOC) as $course) {
            $lessonStmt->execute([':student_id' => $student['student_id'], ':course_id' => $course['course_id']]);
            $lessons = [];
            $passedLessons = 0;
            $failedLessons = 0;
            $pendingReviewLessons = 0;
            foreach ($lessonStmt->fetchAll(PDO::FETCH_ASSOC) as $lesson) {
                $hasAttempt = $lesson['attempt_id'] !== null;
                $pendingEssays = (int) $lesson['pending_essays'];
                $quizStatus = $hasAttempt ? (string) ($lesson['quiz_status'] ?? '') : '';
                $resultStatus = !$hasAttempt ? 'not_attempted'
                    : (($pendingEssays > 0 || $quizStatus === 'pending_review') ? 'pending_review'
                    : ($quizStatus === 'passed' ? 'passed'
                    : ($quizStatus === 'failed' ? 'failed' : 'unknown')));
                if ($resultStatus === 'passed') $passedLessons++;
                if ($resultStatus === 'failed') $failedLessons++;
                if ($resultStatus === 'pending_review') $pendingReviewLessons++;
                $scoreEarned = (float) $lesson['objective_score'] + (float) $lesson['essay_score'];
                if ((int) $lesson['answer_count'] === 0 && $hasAttempt) $scoreEarned = (float) ($lesson['score'] ?? 0);
                $scorePossible = $hasAttempt ? (float) ($lesson['total_score'] ?? 0) : 0.0;
                $lessons[] = [
                    'id' => (string) $lesson['lesson_id'],
                    'position' => (int) $lesson['position'],
                    'title' => (string) $lesson['title'],
                    'progress' => lessonProgressFromQuiz($scoreEarned, $scorePossible, (float) $lesson['document_progress'], (float) $lesson['video_progress']),
                    'attempt_count' => (int) $lesson['attempt_count'],
                    'has_attempt' => $hasAttempt,
                    'score_earned' => $scoreEarned,
                    'score_total' => $scorePossible,
                    'score_percent' => $scorePossible > 0 ? round(($scoreEarned / $scorePossible) * 100, 1) : null,
                    'pending_essays' => $pendingEssays,
                    'essay_count' => (int) $lesson['essay_count'],
                    'essay_score' => (float) $lesson['essay_score'],
                    'essay_score_possible' => (float) $lesson['essay_score_possible'],
                    'quiz_status' => $quizStatus ?: null,
                    'result_status' => $resultStatus,
                    'last_activity_at' => $lesson['last_activity_at'],
                ];
            }
            $lessonCount = count($lessons);
            $attemptedLessons = count(array_filter($lessons, static fn(array $lesson): bool => $lesson['attempt_count'] > 0));
            $scoreEarned = array_sum(array_column($lessons, 'score_earned'));
            $scorePossible = array_sum(array_column($lessons, 'score_total'));
            $percent = $scorePossible > 0 ? round(($scoreEarned / $scorePossible) * 100, 1) : 0.0;
            $lastActivities = array_filter(array_column($lessons, 'last_activity_at'));
            $subjects[] = [
                'subject_name' => $course['subject_name'], 'subject_type' => $course['subject_type'] ?? 'elective', 'score_mid' => 0, 'score_final' => $percent,
                'total_score' => $percent, 'grade' => gradeFromScore($percent),
                'score_earned' => $scoreEarned,
                'score_possible' => $scorePossible,
                'lesson_count' => $lessonCount,
                'attempted_lessons' => $attemptedLessons,
                'progress' => $lessonCount > 0 ? round(($attemptedLessons / $lessonCount) * 100, 1) : 0,
                'pending_essays' => array_sum(array_column($lessons, 'pending_essays')),
                'passed_lessons' => $passedLessons,
                'failed_lessons' => $failedLessons,
                'pending_review_lessons' => $pendingReviewLessons,
                'last_activity_at' => $lastActivities ? max($lastActivities) : null,
                'lessons' => $lessons,
            ];
        }
        $scoredSubjects = array_values(array_filter($subjects, static fn(array $subject): bool => $subject['score_possible'] > 0));
        $scores = array_column($scoredSubjects, 'total_score');
        $topScore = $scores ? max($scores) : null;
        $topSubject = '';
        foreach ($scoredSubjects as $subject) {
            if ((float) $subject['total_score'] === (float) $topScore) { $topSubject = $subject['subject_name']; break; }
        }
        $children[] = [
            'student_id' => $student['student_id'], 'student_name' => $student['student_name'],
            'student_level' => $student['student_level'] ?? '', 'avatar_url' => $student['avatar_url'] ?? null,
            'initial' => mb_substr((string) $student['student_name'], 0, 1), 'subjects' => $subjects,
            'stats' => [
                'gpa' => $scoredSubjects
                    ? round(array_sum(array_map(static fn(array $subject): float => gpaPoint((float) $subject['total_score']), $scoredSubjects)) / count($scoredSubjects), 2)
                    : null,
                'grade_a' => count(array_filter($subjects, static fn(array $subject): bool => str_starts_with($subject['grade'], 'A'))),
                'top_score' => $topScore, 'top_subject' => $topSubject,
                'course_count' => count($subjects),
                'attempted_lessons' => array_sum(array_column($subjects, 'attempted_lessons')),
                'total_lessons' => array_sum(array_column($subjects, 'lesson_count')),
                'score_earned' => array_sum(array_column($subjects, 'score_earned')),
                'score_possible' => array_sum(array_column($subjects, 'score_possible')),
                'pending_essays' => array_sum(array_column($subjects, 'pending_essays')),
                'last_activity_at' => max(array_filter(array_column($subjects, 'last_activity_at')) ?: [null]),
            ],
        ];
    }
    echo json_encode(['status' => 'success', 'parent' => $parent, 'children' => $children], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Parent dashboard error: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถโหลดข้อมูลผู้ปกครองได้'], JSON_UNESCAPED_UNICODE);
}
