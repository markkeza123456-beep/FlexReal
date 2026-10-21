<?php

function ensureLearningProgressTables(PDO $conn): void
{
    $tableDefinitions = [
        "CREATE TABLE IF NOT EXISTS public.lesson_progress (
            student_id VARCHAR(100) NOT NULL,
            lesson_id BIGINT NOT NULL,
            opened_count INTEGER NOT NULL DEFAULT 0,
            video_open_count INTEGER NOT NULL DEFAULT 0,
            document_progress_percent NUMERIC(5,2) NOT NULL DEFAULT 0,
            document_page_index INTEGER NOT NULL DEFAULT 0,
            document_total_pages INTEGER NOT NULL DEFAULT 0,
            document_read_pages JSONB NOT NULL DEFAULT '[]'::jsonb,
            video_progress_percent NUMERIC(5,2) NOT NULL DEFAULT 0,
            video_position_seconds INTEGER NOT NULL DEFAULT 0,
            video_watched_ranges JSONB NOT NULL DEFAULT '[]'::jsonb,
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
    foreach ($tableDefinitions as $statement) $conn->exec($statement);

    $alterStatements = [
        "ALTER TABLE public.lesson_progress ADD COLUMN IF NOT EXISTS opened_count INTEGER NOT NULL DEFAULT 0",
        "ALTER TABLE public.lesson_progress ADD COLUMN IF NOT EXISTS video_open_count INTEGER NOT NULL DEFAULT 0",
        "ALTER TABLE public.lesson_progress ADD COLUMN IF NOT EXISTS document_progress_percent NUMERIC(5,2) NOT NULL DEFAULT 0",
        "ALTER TABLE public.lesson_progress ADD COLUMN IF NOT EXISTS document_page_index INTEGER NOT NULL DEFAULT 0",
        "ALTER TABLE public.lesson_progress ADD COLUMN IF NOT EXISTS document_total_pages INTEGER NOT NULL DEFAULT 0",
        "ALTER TABLE public.lesson_progress ADD COLUMN IF NOT EXISTS document_read_pages JSONB NOT NULL DEFAULT '[]'::jsonb",
        "ALTER TABLE public.lesson_progress ADD COLUMN IF NOT EXISTS video_progress_percent NUMERIC(5,2) NOT NULL DEFAULT 0",
        "ALTER TABLE public.lesson_progress ADD COLUMN IF NOT EXISTS video_position_seconds INTEGER NOT NULL DEFAULT 0",
        "ALTER TABLE public.lesson_progress ADD COLUMN IF NOT EXISTS video_watched_ranges JSONB NOT NULL DEFAULT '[]'::jsonb",
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
        try { $conn->exec($statement); }
        catch (PDOException $error) {
            if (!str_contains($error->getMessage(), 'already exists')) throw $error;
        }
    }

    $indexes = [
        'CREATE INDEX IF NOT EXISTS idx_lesson_progress_student_lesson ON public.lesson_progress (student_id, lesson_id)',
        'CREATE INDEX IF NOT EXISTS idx_learning_events_student_lesson_time ON public.learning_events (student_id, lesson_id, created_at DESC)',
        'CREATE INDEX IF NOT EXISTS idx_video_watch_sessions_student_lesson ON public.video_watch_sessions (student_id, lesson_id)'
    ];
    foreach ($indexes as $statement) $conn->exec($statement);
}

function lessonProgressFromQuiz(float $score, float $totalScore, float $documentPercent = 0, float $videoPercent = 0): float
{
    $documentPart = min(100, max(0, $documentPercent)) * 0.30;
    $videoPart = min(100, max(0, $videoPercent)) * 0.30;
    $quizPart = $totalScore > 0 ? min(40.0, max(0.0, $score / $totalScore * 40.0)) : 0.0;
    return round($documentPart + $videoPart + $quizPart, 1);
}

function learningLessonId(PDO $conn, string $courseId, int $position): ?int
{
    $stmt = $conn->prepare('SELECT lesson_id FROM public.lessons WHERE course_id = :course_id AND position = :position');
    $stmt->execute([':course_id' => $courseId, ':position' => max(1, $position)]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int) $id;
}

function upsertLearningProgressRow(PDO $conn, string $studentId, string $courseId, int $lessonIndex, string $lessonTitle = ''): void
{
    $lessonId = learningLessonId($conn, $courseId, $lessonIndex);
    if ($lessonId === null) return;
    $conn->prepare('INSERT INTO public.lesson_progress (student_id, lesson_id) VALUES (:student_id, :lesson_id) ON CONFLICT (student_id, lesson_id) DO NOTHING')
        ->execute([':student_id' => $studentId, ':lesson_id' => $lessonId]);
}

function syncReviewedQuizScore(PDO $conn, string $studentId, string $courseId, int $lessonIndex, int $score, int $totalScore): void
{
    upsertLearningProgressRow($conn, $studentId, $courseId, $lessonIndex);
}

function decodeProgressJson(mixed $value): array
{
    if (is_array($value)) return $value;
    if (!is_string($value) || $value === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function normalizeReadPages(array $pages, int $totalPages): array
{
    $normalized = [];
    foreach ($pages as $page) {
        if (!is_numeric($page)) continue;
        $page = (int) $page;
        if ($page >= 1 && $page <= $totalPages) $normalized[$page] = $page;
    }
    sort($normalized, SORT_NUMERIC);
    return array_values($normalized);
}

function normalizeWatchedRanges(array $ranges, float $duration): array
{
    if ($duration <= 0) return [];
    $normalized = [];
    foreach ($ranges as $range) {
        if (!is_array($range) || count($range) < 2 || !is_numeric($range[0]) || !is_numeric($range[1])) continue;
        $start = min($duration, max(0.0, (float) $range[0]));
        $end = min($duration, max(0.0, (float) $range[1]));
        if ($end - $start > 0.05) $normalized[] = [$start, $end];
    }
    usort($normalized, static fn(array $a, array $b): int => $a[0] <=> $b[0]);
    $merged = [];
    foreach ($normalized as [$start, $end]) {
        $lastIndex = count($merged) - 1;
        if ($lastIndex >= 0 && $start <= $merged[$lastIndex][1] + 0.05) {
            $merged[$lastIndex][1] = max($merged[$lastIndex][1], $end);
        } else {
            $merged[] = [$start, $end];
        }
    }
    return $merged;
}

function watchedRangeSeconds(array $ranges): float
{
    $seconds = 0.0;
    foreach ($ranges as $range) $seconds += max(0.0, (float) $range[1] - (float) $range[0]);
    return $seconds;
}

function upsertVideoWatchSession(PDO $conn, string $studentId, int $lessonId, float $progressPercent, float $resumePosition, int $durationSeconds = 0): void
{
    $progressPercent = min(100, max(0, $progressPercent));
    $currentSeconds = max(0, (int) floor($resumePosition));
    $durationSeconds = max($currentSeconds, $durationSeconds);
    $status = $progressPercent >= 100 ? 'completed' : 'in_progress';
    $conn->prepare("INSERT INTO public.video_watch_sessions (student_id, lesson_id, duration_seconds, current_seconds, progress_percent, status, last_watched_at, updated_at, completed_at)
        VALUES (:student_id, :lesson_id, :duration_seconds, :current_seconds, :progress_percent, :status, now(), now(), CASE WHEN CAST(:status_insert AS text) = 'completed' THEN now() ELSE NULL END)
        ON CONFLICT (student_id, lesson_id) DO UPDATE SET
            duration_seconds = GREATEST(video_watch_sessions.duration_seconds, EXCLUDED.duration_seconds),
            current_seconds = EXCLUDED.current_seconds,
            progress_percent = GREATEST(video_watch_sessions.progress_percent, EXCLUDED.progress_percent),
            status = CASE WHEN GREATEST(video_watch_sessions.progress_percent, EXCLUDED.progress_percent) >= 100 THEN 'completed' ELSE EXCLUDED.status END,
            last_watched_at = now(), updated_at = now(),
            completed_at = CASE WHEN GREATEST(video_watch_sessions.progress_percent, EXCLUDED.progress_percent) >= 100 THEN COALESCE(video_watch_sessions.completed_at, now()) ELSE video_watch_sessions.completed_at END")
        ->execute([
            ':student_id' => $studentId,
            ':lesson_id' => $lessonId,
            ':duration_seconds' => $durationSeconds,
            ':current_seconds' => $currentSeconds,
            ':progress_percent' => $progressPercent,
            ':status' => $status,
            ':status_insert' => $status,
        ]);
}

function recordLearningActivity(
    PDO $conn,
    string $studentId,
    string $courseId,
    int $lessonIndex,
    string $activityType,
    string $lessonTitle = '',
    float $progressPercent = 0,
    float $resumePosition = 0,
    int $durationSeconds = 0,
    array $documentReadPages = [],
    int $incomingDocumentPageCount = 0,
    array $videoWatchedRanges = []
): void {
    ensureLearningProgressTables($conn);
    $lessonId = learningLessonId($conn, $courseId, $lessonIndex);
    if ($lessonId === null) throw new RuntimeException('ไม่พบบทเรียนในรายวิชานี้');
    $conn->prepare('INSERT INTO public.lesson_progress (student_id, lesson_id) VALUES (:student_id, :lesson_id) ON CONFLICT (student_id, lesson_id) DO NOTHING')
        ->execute([':student_id' => $studentId, ':lesson_id' => $lessonId]);

    $activityType = trim($activityType);
    $progressPercent = min(100, max(0, $progressPercent));
    if ($activityType === 'document_complete') { $activityType = 'document_progress'; $progressPercent = 100; }
    if ($activityType === 'video_open') { $activityType = 'video_progress'; $progressPercent = 100; }
    $isDocument = $activityType === 'document_progress';
    $isVideo = $activityType === 'video_progress';

    $lock = $conn->prepare('SELECT document_progress_percent, document_page_index, document_total_pages, document_read_pages, video_progress_percent, video_position_seconds, video_watched_ranges FROM public.lesson_progress WHERE student_id = :student_id AND lesson_id = :lesson_id FOR UPDATE');
    $lock->execute([':student_id' => $studentId, ':lesson_id' => $lessonId]);
    $existing = $lock->fetch(PDO::FETCH_ASSOC) ?: [];
    $documentPercent = (float) ($existing['document_progress_percent'] ?? 0);
    $documentPageIndex = (int) ($existing['document_page_index'] ?? 0);
    $documentTotalPages = (int) ($existing['document_total_pages'] ?? 0);
    $readPages = decodeProgressJson($existing['document_read_pages'] ?? null);
    $videoPercent = (float) ($existing['video_progress_percent'] ?? 0);
    $videoPosition = (int) ($existing['video_position_seconds'] ?? 0);
    $watchedRanges = decodeProgressJson($existing['video_watched_ranges'] ?? null);

    if ($isDocument) {
        $documentTotalPages = max($documentTotalPages, max(0, $incomingDocumentPageCount));
        if ($documentTotalPages > 0) $readPages = normalizeReadPages($readPages, $documentTotalPages);
        if ($documentTotalPages > 0 && $documentTotalPages > count($readPages)) {
            // Preserve progress recorded before page-level tracking was introduced.
            $legacyReadCount = min($documentTotalPages, (int) floor($documentPercent / 100 * $documentTotalPages));
            for ($page = 1; $page <= $legacyReadCount; $page++) $readPages[] = $page;
        }
        if ($documentTotalPages > 0) {
            $readPages = normalizeReadPages([...$readPages, ...normalizeReadPages($documentReadPages, $documentTotalPages)], $documentTotalPages);
            $calculatedDocumentPercent = count($readPages) >= $documentTotalPages
                ? 100.0
                : floor(count($readPages) / $documentTotalPages * 10000) / 100;
            $documentPercent = max($documentPercent, $calculatedDocumentPercent);
            $documentPageIndex = max(0, (int) floor($resumePosition));
        } else {
            $documentPercent = max($documentPercent, $progressPercent);
            $documentPageIndex = max(0, (int) floor($resumePosition));
        }
    }

    if ($isVideo) {
        $duration = max(0, $durationSeconds);
        if ($duration > 0) {
            $watchedRanges = normalizeWatchedRanges($watchedRanges, $duration);
            if ($watchedRanges === [] && $videoPercent > 0) {
                $legacyEnd = min((float) $duration, max((float) $videoPosition, $duration * $videoPercent / 100));
                if ($legacyEnd > 0) $watchedRanges = [[0.0, $legacyEnd]];
            }
            $incomingRanges = normalizeWatchedRanges($videoWatchedRanges, $duration);
            $watchedRanges = normalizeWatchedRanges([...$watchedRanges, ...$incomingRanges], $duration);
            $watchedDuration = min((float) $duration, watchedRangeSeconds($watchedRanges));
            $rangePercent = $watchedDuration >= $duration ? 100.0 : floor($watchedDuration / $duration * 10000) / 100;
            $videoPercent = max($videoPercent, $rangePercent);
        } else {
            $videoPercent = max($videoPercent, $progressPercent);
        }
        $videoPosition = max(0, (int) floor($resumePosition));
    }

    $documentJson = json_encode(array_values($readPages), JSON_THROW_ON_ERROR);
    $videoJson = json_encode(array_values($watchedRanges), JSON_THROW_ON_ERROR);
    $update = $conn->prepare('UPDATE public.lesson_progress SET
        document_progress_percent = :document_percent,
        document_page_index = :document_page_index,
        document_total_pages = :document_total_pages,
        document_read_pages = CAST(:document_read_pages AS jsonb),
        video_progress_percent = :video_percent,
        video_position_seconds = :video_position,
        video_watched_ranges = CAST(:video_watched_ranges AS jsonb),
        opened_count = CASE WHEN :is_document_open AND :document_open_percent >= 100 THEN GREATEST(opened_count, 1) ELSE opened_count END,
        video_open_count = CASE WHEN :is_video_open AND :video_open_percent >= 100 THEN GREATEST(video_open_count, 1) ELSE video_open_count END,
        first_opened_at = COALESCE(first_opened_at, now()),
        last_opened_at = CASE WHEN :is_document_last OR :is_video_last THEN now() ELSE last_opened_at END,
        last_activity_at = now(), updated_at = now()
        WHERE student_id = :student_id AND lesson_id = :lesson_id');
    $update->execute([
        ':document_percent' => $documentPercent,
        ':document_page_index' => $documentPageIndex,
        ':document_total_pages' => $documentTotalPages,
        ':document_read_pages' => $documentJson,
        ':video_percent' => $videoPercent,
        ':video_position' => $videoPosition,
        ':video_watched_ranges' => $videoJson,
        ':is_document_open' => $isDocument ? 'true' : 'false',
        ':document_open_percent' => $documentPercent,
        ':is_video_open' => $isVideo ? 'true' : 'false',
        ':video_open_percent' => $videoPercent,
        ':is_document_last' => $isDocument ? 'true' : 'false',
        ':is_video_last' => $isVideo ? 'true' : 'false',
        ':student_id' => $studentId,
        ':lesson_id' => $lessonId,
    ]);

    if ($isVideo) upsertVideoWatchSession($conn, $studentId, $lessonId, $videoPercent, $resumePosition, $durationSeconds);
    $detail = json_encode([
        'title' => $lessonTitle,
        'progress_percent' => $progressPercent,
        'resume_position' => $resumePosition,
        'duration_seconds' => $durationSeconds,
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $conn->prepare('INSERT INTO public.learning_events (student_id, lesson_id, event_type, detail) VALUES (:student_id, :lesson_id, :event_type, CAST(:detail AS jsonb))')
        ->execute([':student_id' => $studentId, ':lesson_id' => $lessonId, ':event_type' => $activityType, ':detail' => $detail]);
}

function lessonQuizUnlockStatus(PDO $conn, string $studentId, string $courseId, int $lessonIndex): array
{
    ensureLearningProgressTables($conn);
    $enrollment = $conn->prepare("SELECT 1 FROM public.student_courses WHERE student_id = :student_id AND course_id = :course_id AND status = 'active'");
    $enrollment->execute([':student_id' => $studentId, ':course_id' => $courseId]);
    if (!$enrollment->fetchColumn()) return ['allowed' => false, 'message' => 'กรุณาลงรายวิชาก่อนทำแบบทดสอบ'];

    $stmt = $conn->prepare('SELECT l.lesson_id, COALESCE(p.document_progress_percent, 0) AS document_progress_percent, COALESCE(p.document_total_pages, 0) AS document_total_pages, COALESCE(p.document_read_pages, \'[]\'::jsonb) AS document_read_pages, COALESCE(p.video_progress_percent, 0) AS video_progress_percent FROM public.lessons l LEFT JOIN public.lesson_progress p ON p.lesson_id = l.lesson_id AND p.student_id = :student_id WHERE l.course_id = :course_id AND l.position = :position LIMIT 1');
    $stmt->execute([':student_id' => $studentId, ':course_id' => $courseId, ':position' => max(1, $lessonIndex)]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return ['allowed' => false, 'message' => 'ไม่พบบทเรียนที่ต้องการทำแบบทดสอบ'];

    $documentPercent = min(100, max(0, (float) $row['document_progress_percent']));
    $videoPercent = min(100, max(0, (float) $row['video_progress_percent']));
    $readPages = decodeProgressJson($row['document_read_pages'] ?? null);
    $totalPages = (int) $row['document_total_pages'];
    $messages = [];
    if ($documentPercent < 100) {
        $messages[] = $totalPages > 0
            ? 'ยังอ่านเอกสารไม่ครบ (' . count(normalizeReadPages($readPages, $totalPages)) . '/' . $totalPages . ' หน้า)'
            : 'ยังอ่านเอกสารไม่ครบ (' . round($documentPercent) . '%)';
    }
    if ($videoPercent < 100) $messages[] = 'ยังดูวิดีโอไม่ครบ (' . min(99.9, round($videoPercent, 1)) . '%)';
    if ($messages !== []) return ['allowed' => false, 'message' => implode(' • ', $messages)];
    return ['allowed' => true, 'message' => ''];
}
