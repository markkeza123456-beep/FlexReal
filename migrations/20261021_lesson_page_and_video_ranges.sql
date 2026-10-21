-- Track unique pages read and non-overlapping video intervals per student/lesson.
-- Existing cumulative percentages and resume positions remain in lesson_progress.
ALTER TABLE public.lesson_progress
    ADD COLUMN IF NOT EXISTS document_total_pages INTEGER NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS document_read_pages JSONB NOT NULL DEFAULT '[]'::jsonb,
    ADD COLUMN IF NOT EXISTS video_watched_ranges JSONB NOT NULL DEFAULT '[]'::jsonb;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'lesson_progress_document_total_pages_nonnegative'
          AND conrelid = 'public.lesson_progress'::regclass
    ) THEN
        ALTER TABLE public.lesson_progress
            ADD CONSTRAINT lesson_progress_document_total_pages_nonnegative
            CHECK (document_total_pages >= 0) NOT VALID;
        ALTER TABLE public.lesson_progress
            VALIDATE CONSTRAINT lesson_progress_document_total_pages_nonnegative;
    END IF;
END $$;
