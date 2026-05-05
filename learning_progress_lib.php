<?php

function ensureLearningProgressTables(PDO $conn): void
{
    $conn->exec(
        'CREATE TABLE IF NOT EXISTS public.student_learning_progress (
            student_id VARCHAR(50) NOT NULL,
            subjects_id VARCHAR(50) NOT NULL,
            lesson_index INTEGER NOT NULL,
            lesson_title TEXT,
            opened_count INTEGER NOT NULL DEFAULT 0,
            video_open_count INTEGER NOT NULL DEFAULT 0,
            quiz_attempts INTEGER NOT NULL DEFAULT 0,
            best_quiz_score INTEGER NOT NULL DEFAULT 0,
            quiz_total_score INTEGER NOT NULL DEFAULT 0,
            progress_percent NUMERIC(5,2) NOT NULL DEFAULT 0,
            first_opened_at TIMESTAMPTZ,
            last_opened_at TIMESTAMPTZ,
            last_activity_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            PRIMARY KEY (student_id, subjects_id, lesson_index)
        )'
    );

    $conn->exec(
        'CREATE TABLE IF NOT EXISTS public.student_learning_activity_logs (
            activity_id BIGSERIAL PRIMARY KEY,
            student_id VARCHAR(50) NOT NULL,
            subjects_id VARCHAR(50) NOT NULL,
            lesson_index INTEGER,
            activity_type VARCHAR(50) NOT NULL,
            activity_detail TEXT,
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
        )'
    );
}

function upsertLearningProgressRow(
    PDO $conn,
    string $studentId,
    string $subjectId,
    int $lessonIndex,
    string $lessonTitle = ''
): void {
    $stmt = $conn->prepare(
        'INSERT INTO public.student_learning_progress (
            student_id, subjects_id, lesson_index, lesson_title, first_opened_at, last_opened_at, last_activity_at
         ) VALUES (
            :student_id, :subjects_id, :lesson_index, :lesson_title, NOW(), NOW(), NOW()
         )
         ON CONFLICT (student_id, subjects_id, lesson_index) DO NOTHING'
    );
    $stmt->execute([
        ':student_id' => $studentId,
        ':subjects_id' => $subjectId,
        ':lesson_index' => $lessonIndex,
        ':lesson_title' => $lessonTitle,
    ]);
}

function lessonProgressFromQuiz(int $score, int $totalScore): float
{
    if ($totalScore <= 0) {
        return 60.0;
    }

    return min(100.0, 60.0 + (($score / $totalScore) * 40.0));
}

function recordLearningActivity(
    PDO $conn,
    string $studentId,
    string $subjectId,
    int $lessonIndex,
    string $activityType,
    string $lessonTitle = '',
    int $score = 0,
    int $totalScore = 0
): void {
    ensureLearningProgressTables($conn);
    upsertLearningProgressRow($conn, $studentId, $subjectId, $lessonIndex, $lessonTitle);

    $selectStmt = $conn->prepare(
        'SELECT opened_count, video_open_count, quiz_attempts, best_quiz_score, quiz_total_score, progress_percent
         FROM public.student_learning_progress
         WHERE student_id = :student_id
           AND subjects_id = :subjects_id
           AND lesson_index = :lesson_index
         LIMIT 1'
    );
    $selectStmt->execute([
        ':student_id' => $studentId,
        ':subjects_id' => $subjectId,
        ':lesson_index' => $lessonIndex,
    ]);
    $current = $selectStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $openedCount = (int) ($current['opened_count'] ?? 0);
    $videoOpenCount = (int) ($current['video_open_count'] ?? 0);
    $quizAttempts = (int) ($current['quiz_attempts'] ?? 0);
    $bestQuizScore = (int) ($current['best_quiz_score'] ?? 0);
    $quizTotalScore = (int) ($current['quiz_total_score'] ?? 0);
    $progressPercent = (float) ($current['progress_percent'] ?? 0);

    if ($activityType === 'course_enter' || $activityType === 'lesson_open') {
        $openedCount++;
        $progressPercent = max($progressPercent, 25.0);
    } elseif ($activityType === 'video_open') {
        $videoOpenCount++;
        $progressPercent = max($progressPercent, 60.0);
    } elseif ($activityType === 'quiz_submit') {
        $quizAttempts++;
        if ($totalScore > 0) {
            if ($score > $bestQuizScore || $quizTotalScore !== $totalScore) {
                $bestQuizScore = max($bestQuizScore, $score);
                $quizTotalScore = $totalScore;
            }
            $progressPercent = max($progressPercent, lessonProgressFromQuiz($score, $totalScore));
        } else {
            $progressPercent = max($progressPercent, 70.0);
        }
    }

    $updateStmt = $conn->prepare(
        'UPDATE public.student_learning_progress
         SET lesson_title = CASE
                WHEN :lesson_title <> \'\' THEN :lesson_title
                ELSE lesson_title
             END,
             opened_count = :opened_count,
             video_open_count = :video_open_count,
             quiz_attempts = :quiz_attempts,
             best_quiz_score = :best_quiz_score,
             quiz_total_score = :quiz_total_score,
             progress_percent = :progress_percent,
             first_opened_at = COALESCE(first_opened_at, NOW()),
             last_opened_at = CASE
                WHEN :touch_opened = 1 THEN NOW()
                ELSE last_opened_at
             END,
             last_activity_at = NOW()
         WHERE student_id = :student_id
           AND subjects_id = :subjects_id
           AND lesson_index = :lesson_index'
    );
    $updateStmt->execute([
        ':lesson_title' => $lessonTitle,
        ':opened_count' => $openedCount,
        ':video_open_count' => $videoOpenCount,
        ':quiz_attempts' => $quizAttempts,
        ':best_quiz_score' => $bestQuizScore,
        ':quiz_total_score' => $quizTotalScore,
        ':progress_percent' => round($progressPercent, 2),
        ':touch_opened' => in_array($activityType, ['course_enter', 'lesson_open', 'video_open'], true) ? 1 : 0,
        ':student_id' => $studentId,
        ':subjects_id' => $subjectId,
        ':lesson_index' => $lessonIndex,
    ]);

    $detailParts = [];
    if ($lessonTitle !== '') {
        $detailParts[] = $lessonTitle;
    }
    if ($activityType === 'quiz_submit' && $totalScore > 0) {
        $detailParts[] = $score . '/' . $totalScore;
    }

    $logStmt = $conn->prepare(
        'INSERT INTO public.student_learning_activity_logs (
            student_id, subjects_id, lesson_index, activity_type, activity_detail, created_at
         ) VALUES (
            :student_id, :subjects_id, :lesson_index, :activity_type, :activity_detail, NOW()
         )'
    );
    $logStmt->execute([
        ':student_id' => $studentId,
        ':subjects_id' => $subjectId,
        ':lesson_index' => $lessonIndex,
        ':activity_type' => $activityType,
        ':activity_detail' => implode(' | ', $detailParts),
    ]);
}
