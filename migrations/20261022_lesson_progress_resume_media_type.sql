-- Keep media progress independently while exposing a one-row-per-lesson view
-- to existing reports, unlock checks, and graduation workflows.
BEGIN;

UPDATE public.lesson_progress
SET progress_type = 'transfer'
WHERE is_transferred OR UPPER(COALESCE(grade, '')) = 'TR';

ALTER TABLE public.lesson_progress
    DROP CONSTRAINT IF EXISTS lesson_progress_student_id_lesson_id_key;
ALTER TABLE public.lesson_progress
    ADD CONSTRAINT lesson_progress_student_lesson_type_key
    UNIQUE (student_id, lesson_id, progress_type);

DROP INDEX IF EXISTS public.idx_lesson_progress_student_lesson;
CREATE INDEX idx_lesson_progress_student_lesson
    ON public.lesson_progress (student_id, lesson_id, progress_type);

CREATE OR REPLACE VIEW public.lesson_progress_summary AS
SELECT student_id,
       lesson_id,
       (array_agg(progress_value ORDER BY updated_at DESC))[1] AS progress_value,
       (array_agg(progress_type ORDER BY updated_at DESC))[1] AS progress_type,
       BOOL_OR(is_completed) AS is_completed,
       MAX(updated_at) AS updated_at,
       COALESCE(MAX(document_progress_percent) FILTER (WHERE progress_type = 'document'), 0) AS document_progress_percent,
       COALESCE(MAX(document_page_index) FILTER (WHERE progress_type = 'document'), 0) AS document_page_index,
       COALESCE(MAX(video_progress_percent) FILTER (WHERE progress_type = 'video'), 0) AS video_progress_percent,
       COALESCE(MAX(video_position_seconds) FILTER (WHERE progress_type = 'video'), 0) AS video_position_seconds,
       COALESCE(MAX(opened_count) FILTER (WHERE progress_type = 'document'), 0) AS opened_count,
       COALESCE(MAX(video_open_count) FILTER (WHERE progress_type = 'video'), 0) AS video_open_count,
       MIN(first_opened_at) AS first_opened_at,
       MAX(last_opened_at) AS last_opened_at,
       MAX(last_activity_at) AS last_activity_at,
       BOOL_OR(is_transferred) AS is_transferred,
       MAX(grade) FILTER (WHERE progress_type = 'transfer') AS grade,
       MAX(transfer_request_id) FILTER (WHERE progress_type = 'transfer') AS transfer_request_id,
       MAX(transferred_by) FILTER (WHERE progress_type = 'transfer') AS transferred_by,
       MAX(transferred_at) FILTER (WHERE progress_type = 'transfer') AS transferred_at
FROM public.lesson_progress
GROUP BY student_id, lesson_id;

COMMIT;
