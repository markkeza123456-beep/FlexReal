-- Idempotent schema maintenance proposal generated from the live public schema
-- inspected on 2026-10-20. Review and take a database backup before production.
BEGIN;

-- Ensure the course classification column exists and has the requested type.
DO $migration$
BEGIN
    IF to_regclass('public.courses') IS NOT NULL THEN
        IF NOT EXISTS (
            SELECT 1 FROM information_schema.columns
            WHERE table_schema = 'public' AND table_name = 'courses' AND column_name = 'subject_type'
        ) THEN
            ALTER TABLE public.courses
                ADD COLUMN IF NOT EXISTS subject_type VARCHAR(20) NOT NULL DEFAULT 'elective';

            IF to_regclass('public.curriculum_courses') IS NOT NULL THEN
                UPDATE public.courses c
                SET subject_type = CASE WHEN EXISTS (
                    SELECT 1 FROM public.curriculum_courses cc
                    WHERE cc.course_id = c.course_id AND cc.requirement_type = 'required'
                ) THEN 'compulsory' ELSE 'elective' END;
            END IF;
        ELSE
            IF EXISTS (SELECT 1 FROM public.courses WHERE length(subject_type::text) > 20) THEN
                RAISE EXCEPTION 'courses.subject_type has values longer than VARCHAR(20); clean them before migration';
            END IF;
            ALTER TABLE public.courses
                ALTER COLUMN subject_type TYPE VARCHAR(20) USING subject_type::VARCHAR(20);
            UPDATE public.courses SET subject_type = 'compulsory' WHERE lower(subject_type) = 'required';
            UPDATE public.courses SET subject_type = 'elective' WHERE lower(subject_type) NOT IN ('compulsory', 'elective');
        END IF;

        IF NOT EXISTS (
            SELECT 1 FROM pg_constraint
            WHERE conrelid = 'public.courses'::regclass AND conname = 'courses_subject_type_check'
        ) THEN
            ALTER TABLE public.courses
                ADD CONSTRAINT courses_subject_type_check
                CHECK (subject_type IN ('compulsory', 'elective'));
        END IF;
    END IF;
END
$migration$;

-- Add timestamp columns to each application table if absent. Existing values,
-- types and defaults are not modified. updated_at receives an insert-time default;
-- applications must update it on writes (or add triggers) if automatic touch-time is needed.
DO $migration$
DECLARE
    table_name TEXT;
BEGIN
    FOREACH table_name IN ARRAY ARRAY[
        'activity_logs', 'assignment_submissions', 'course_announcements',
        'course_assignments', 'course_teachers', 'courses', 'curricula',
        'curriculum_courses', 'exam_logs', 'graduation_requests', 'learning_events',
        'lesson_progress', 'lesson_resources', 'lessons', 'parents',
        'password_reset_tokens', 'questions', 'quiz_attempt_answers', 'quiz_attempts',
        'staff', 'student_courses', 'student_curricula', 'students', 'teachers',
        'transcript_request_lessons', 'transcript_requests', 'transfer_permissions',
        'user_addresses', 'users', 'video_watch_sessions'
    ] LOOP
        IF to_regclass(format('public.%I', table_name)) IS NOT NULL THEN
            EXECUTE format(
                'ALTER TABLE public.%I ADD COLUMN IF NOT EXISTS created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP',
                table_name
            );
            EXECUTE format(
                'ALTER TABLE public.%I ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP',
                table_name
            );
        END IF;
    END LOOP;
END
$migration$;

-- Safe data-preserving cleanup candidate: the project uses duration_seconds;
-- live inspection found total_duration_seconds=0 on all 10 rows while the
-- corresponding duration_seconds values were positive. Copy the maximum first.
DO $migration$
BEGIN
    IF to_regclass('public.video_watch_sessions') IS NOT NULL
       AND EXISTS (
           SELECT 1 FROM information_schema.columns
           WHERE table_schema='public' AND table_name='video_watch_sessions'
             AND column_name='total_duration_seconds'
       )
       AND EXISTS (
           SELECT 1 FROM information_schema.columns
           WHERE table_schema='public' AND table_name='video_watch_sessions'
             AND column_name='duration_seconds'
       ) THEN
        UPDATE public.video_watch_sessions
        SET duration_seconds = GREATEST(COALESCE(duration_seconds, 0), COALESCE(total_duration_seconds, 0));
        ALTER TABLE public.video_watch_sessions DROP COLUMN IF EXISTS total_duration_seconds;
    END IF;
END
$migration$;

-- Deliberately retained pending a data/code cutover:
-- transcript_requests.file_path and document_url both contain live/legacy document data;
-- storage_bucket/storage_object_path have one populated row and may be used externally.
-- students.education_level is currently NULL but application code still reads it as fallback.
-- Do not drop these columns until data has been migrated and all consumers have switched.

COMMIT;
