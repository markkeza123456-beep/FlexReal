<?php
function ensureLearningProgressTables(PDO $conn): void {
    $tableDefinitions = [
        "CREATE TABLE IF NOT EXISTS public.lesson_progress (
            student_id VARCHAR(100) NOT NULL,
            lesson_id BIGINT NOT NULL,
            opened_count INTEGER NOT NULL DEFAULT 0,
            video_open_count INTEGER NOT NULL DEFAULT 0,
            document_progress_percent NUMERIC(5,2) NOT NULL DEFAULT 0,
            document_page_index INTEGER NOT NULL DEFAULT 0,
            video_progress_percent NUMERIC(5,2) NOT NULL DEFAULT 0,
            video_position_seconds INTEGER NOT NULL DEFAULT 0,
            first_opened_at TIMESTAMPTZ,
            last_opened_at TIMESTAMPTZ,
            last_activity_at TIMESTAMPTZ,
            PRIMARY KEY (student_id, lesson_id)
        )",
        "CREATE TABLE IF NOT EXISTS public.learning_events (
            event_id BIGSERIAL PRIMARY KEY,
            student_id VARCHAR(100) NOT NULL,
            lesson_id BIGINT NOT NULL,
            event_type VARCHAR(50) NOT NULL,
            detail JSONB NOT NULL DEFAULT '{}'::jsonb,
            created_at TIMESTAMPTZ NOT NULL DEFAULT now()
        )",
        "CREATE TABLE IF NOT EXISTS public.video_watch_sessions (
            student_id VARCHAR(100) NOT NULL,
            lesson_id BIGINT NOT NULL,
            duration_seconds INTEGER NOT NULL DEFAULT 0,
            current_seconds INTEGER NOT NULL DEFAULT 0,
            progress_percent NUMERIC(5,2) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'in_progress'
                CHECK (status IN ('in_progress', 'paused', 'completed')),
            started_at TIMESTAMPTZ NOT NULL DEFAULT now(),
            last_watched_at TIMESTAMPTZ,
            completed_at TIMESTAMPTZ,
            updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
            PRIMARY KEY (student_id, lesson_id)
        )"
    ];

    foreach ($tableDefinitions as $statement) {
        $conn->exec($statement);
    }

    $alterStatements = [
        "ALTER TABLE public.lesson_progress ADD COLUMN IF NOT EXISTS opened_count INTEGER NOT NULL DEFAULT 0",
        "ALTER TABLE public.lesson_progress ADD COLUMN IF NOT EXISTS video_open_count INTEGER NOT NULL DEFAULT 0",
        "ALTER TABLE public.lesson_progress ADD COLUMN IF NOT EXISTS document_progress_percent NUMERIC(5,2) NOT NULL DEFAULT 0",
        "ALTER TABLE public.lesson_progress ADD COLUMN IF NOT EXISTS document_page_index INTEGER NOT NULL DEFAULT 0",
        "ALTER TABLE public.lesson_progress ADD COLUMN IF NOT EXISTS video_progress_percent NUMERIC(5,2) NOT NULL DEFAULT 0",
        "ALTER TABLE public.lesson_progress ADD COLUMN IF NOT EXISTS video_position_seconds INTEGER NOT NULL DEFAULT 0",
        "ALTER TABLE public.lesson_progress ADD COLUMN IF NOT EXISTS first_opened_at TIMESTAMPTZ",
        "ALTER TABLE public.lesson_progress ADD COLUMN IF NOT EXISTS last_opened_at TIMESTAMPTZ",
        "ALTER TABLE public.lesson_progress ADD COLUMN IF NOT EXISTS last_activity_at TIMESTAMPTZ",
        "ALTER TABLE public.learning_events ADD COLUMN IF NOT EXISTS detail JSONB NOT NULL DEFAULT '{}'::jsonb",
        "ALTER TABLE public.learning_events ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ NOT NULL DEFAULT now()",
        "ALTER TABLE public.video_watch_sessions ADD COLUMN IF NOT EXISTS duration_seconds INTEGER NOT NULL DEFAULT 0",
        "ALTER TABLE public.video_watch_sessions ADD COLUMN IF NOT EXISTS current_seconds INTEGER NOT NULL DEFAULT 0",
        "ALTER TABLE public.video_watch_sessions ADD COLUMN IF NOT EXISTS progress_percent NUMERIC(5,2) NOT NULL DEFAULT 0",
        "ALTER TABLE public.video_watch_sessions ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'in_progress'",
        "ALTER TABLE public.video_watch_sessions ADD COLUMN IF NOT EXISTS started_at TIMESTAMPTZ NOT NULL DEFAULT now()",
        "ALTER TABLE public.video_watch_sessions ADD COLUMN IF NOT EXISTS last_watched_at TIMESTAMPTZ",
        "ALTER TABLE public.video_watch_sessions ADD COLUMN IF NOT EXISTS completed_at TIMESTAMPTZ",
        "ALTER TABLE public.video_watch_sessions ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ NOT NULL DEFAULT now()"
    ];

    foreach ($alterStatements as $statement) {
        try {
            $conn->exec($statement);
        } catch (PDOException $e) {
            if (!str_contains($e->getMessage(), 'already exists')) {
                throw $e;
            }
        }
    }

    $indexes = [
        'CREATE INDEX IF NOT EXISTS idx_lesson_progress_student_lesson ON public.lesson_progress (student_id, lesson_id)',
        'CREATE INDEX IF NOT EXISTS idx_learning_events_student_lesson_time ON public.learning_events (student_id, lesson_id, created_at DESC)',
        'CREATE INDEX IF NOT EXISTS idx_video_watch_sessions_student_lesson ON public.video_watch_sessions (student_id, lesson_id)'
    ];

    foreach ($indexes as $statement) {
        $conn->exec($statement);
    }
}
function lessonProgressFromQuiz(float $score, float $totalScore, float $documentPercent = 0, float $videoPercent = 0): float {
    $documentPart = min(100, max(0, $documentPercent)) * 0.30;
    $videoPart = min(100, max(0, $videoPercent)) * 0.30;
    $quizPart = $totalScore > 0 ? min(40.0, max(0.0, $score / $totalScore * 40.0)) : 0.0;
    return round($documentPart + $videoPart + $quizPart, 1);
}
function learningLessonId(PDO $conn, string $courseId, int $position): ?int {
    $stmt = $conn->prepare('SELECT lesson_id FROM public.lessons WHERE course_id = :course_id AND position = :position');
    $stmt->execute([':course_id' => $courseId, ':position' => max(1, $position)]); $id = $stmt->fetchColumn(); return $id === false ? null : (int) $id;
}
function upsertLearningProgressRow(PDO $conn, string $studentId, string $courseId, int $lessonIndex, string $lessonTitle = ''): void {
    $lessonId = learningLessonId($conn, $courseId, $lessonIndex); if ($lessonId === null) return;
    $conn->prepare('INSERT INTO public.lesson_progress (student_id, lesson_id) VALUES (:student_id, :lesson_id) ON CONFLICT (student_id, lesson_id) DO NOTHING')->execute([':student_id' => $studentId, ':lesson_id' => $lessonId]);
}
function syncReviewedQuizScore(PDO $conn, string $studentId, string $courseId, int $lessonIndex, int $score, int $totalScore): void {
    upsertLearningProgressRow($conn, $studentId, $courseId, $lessonIndex);
}
function upsertVideoWatchSession(PDO $conn, string $studentId, string $courseId, int $lessonIndex, float $progressPercent, float $resumePosition, int $durationSeconds = 0): void {
    $lessonId = learningLessonId($conn, $courseId, $lessonIndex); if ($lessonId === null) return;
    $progressPercent = min(100, max(0, $progressPercent));
    $currentSeconds = max(0, (int) floor($resumePosition));
    $durationSeconds = max(0, $durationSeconds);
    if ($durationSeconds < $currentSeconds) {
        $durationSeconds = $currentSeconds;
    }
    $status = $progressPercent >= 100 ? 'completed' : 'in_progress';

    $conn->prepare('INSERT INTO public.video_watch_sessions (student_id, lesson_id, duration_seconds, current_seconds, progress_percent, status, last_watched_at, updated_at, completed_at)
        VALUES (:student_id, :lesson_id, :duration_seconds, :current_seconds, :progress_percent, :status, now(), now(), CASE WHEN CAST(:status_insert AS text) = \'completed\' THEN now() ELSE NULL END)
        ON CONFLICT (student_id, lesson_id) DO UPDATE SET
            duration_seconds = GREATEST(video_watch_sessions.duration_seconds, EXCLUDED.duration_seconds),
            current_seconds = GREATEST(video_watch_sessions.current_seconds, EXCLUDED.current_seconds),
            progress_percent = GREATEST(video_watch_sessions.progress_percent, EXCLUDED.progress_percent),
            status = EXCLUDED.status,
            last_watched_at = now(),
            updated_at = now(),
            completed_at = CASE WHEN EXCLUDED.status = \'completed\' THEN COALESCE(video_watch_sessions.completed_at, now()) ELSE video_watch_sessions.completed_at END')->execute([
        ':student_id' => $studentId,
        ':lesson_id' => $lessonId,
        ':duration_seconds' => $durationSeconds,
        ':current_seconds' => $currentSeconds,
        ':progress_percent' => $progressPercent,
        ':status' => $status,
        ':status_insert' => $status,
    ]);
}
function recordLearningActivity(PDO $conn, string $studentId, string $courseId, int $lessonIndex, string $activityType, string $lessonTitle = '', float $progressPercent = 0, float $resumePosition = 0, int $durationSeconds = 0): void {
    ensureLearningProgressTables($conn);
    $lessonId = learningLessonId($conn, $courseId, $lessonIndex); if ($lessonId === null) throw new RuntimeException('ไม่พบบทเรียนในรายวิชานี้');
    $conn->prepare('INSERT INTO public.lesson_progress (student_id, lesson_id) VALUES (:student_id, :lesson_id) ON CONFLICT (student_id, lesson_id) DO NOTHING')->execute([':student_id' => $studentId, ':lesson_id' => $lessonId]);
    $progressPercent = min(100, max(0, $progressPercent));
    if ($activityType === 'document_complete') { $activityType = 'document_progress'; $progressPercent = 100; }
    if ($activityType === 'video_open') { $activityType = 'video_progress'; $progressPercent = 100; }
    $isDocument = $activityType === 'document_progress'; $isVideo = $activityType === 'video_progress';

    $conn->prepare('UPDATE public.lesson_progress SET document_progress_percent = CASE WHEN CAST(:is_document_progress AS boolean) THEN GREATEST(document_progress_percent, CAST(:document_progress AS numeric)) ELSE document_progress_percent END, document_page_index = CASE WHEN CAST(:is_document_page AS boolean) THEN GREATEST(document_page_index, CAST(:document_page AS integer)) ELSE document_page_index END, video_progress_percent = CASE WHEN CAST(:is_video_progress AS boolean) THEN GREATEST(video_progress_percent, CAST(:video_progress AS numeric)) ELSE video_progress_percent END, video_position_seconds = CASE WHEN CAST(:is_video_position AS boolean) THEN CASE WHEN CAST(:video_progress_position AS numeric) >= 100 THEN 0 ELSE CAST(:video_position AS integer) END ELSE video_position_seconds END, opened_count = CASE WHEN CAST(:is_document_open AS boolean) AND CAST(:document_progress_open AS numeric) >= 100 THEN GREATEST(opened_count, 1) ELSE opened_count END, video_open_count = CASE WHEN CAST(:is_video_open AS boolean) AND CAST(:video_progress_open AS numeric) >= 100 THEN GREATEST(video_open_count, 1) ELSE video_open_count END, first_opened_at = COALESCE(first_opened_at, now()), last_opened_at = CASE WHEN CAST(:is_document_last AS boolean) OR CAST(:is_video_last AS boolean) THEN now() ELSE last_opened_at END, last_activity_at = now() WHERE student_id = :student_id AND lesson_id = :lesson_id')->execute([
        ':is_document_progress' => $isDocument ? 'true' : 'false',
        ':document_progress' => $progressPercent,
        ':is_document_page' => $isDocument ? 'true' : 'false',
        ':document_page' => max(0, $resumePosition),
        ':is_video_progress' => $isVideo ? 'true' : 'false',
        ':video_progress' => $progressPercent,
        ':is_video_position' => $isVideo ? 'true' : 'false',
        ':video_progress_position' => $progressPercent,
        ':video_position' => max(0, $resumePosition),
        ':is_document_open' => $isDocument ? 'true' : 'false',
        ':document_progress_open' => $progressPercent,
        ':is_video_open' => $isVideo ? 'true' : 'false',
        ':video_progress_open' => $progressPercent,
        ':is_document_last' => $isDocument ? 'true' : 'false',
        ':is_video_last' => $isVideo ? 'true' : 'false',
        ':student_id' => $studentId,
        ':lesson_id' => $lessonId,
    ]);
    if ($isVideo) {
        upsertVideoWatchSession($conn, $studentId, $courseId, $lessonIndex, $progressPercent, $resumePosition, $durationSeconds);
    }
    $detail = json_encode(['title' => $lessonTitle, 'progress_percent' => $progressPercent, 'resume_position' => $resumePosition, 'duration_seconds' => $durationSeconds], JSON_UNESCAPED_UNICODE);
    $conn->prepare('INSERT INTO public.learning_events (student_id, lesson_id, event_type, detail) VALUES (:student_id, :lesson_id, :event_type, CAST(:detail AS jsonb))')->execute([':student_id' => $studentId, ':lesson_id' => $lessonId, ':event_type' => $activityType, ':detail' => $detail]);
}
