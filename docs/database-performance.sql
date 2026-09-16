-- Run once in the Supabase SQL Editor during a low-traffic window.
-- Supabase SQL Editor executes a transaction block, so CREATE INDEX
-- CONCURRENTLY cannot be used here. For a no-lock build, run each statement
-- with psql (outside a transaction) and add CONCURRENTLY after INDEX.

-- Required by lesson lookup, progress summaries, and resource lookup.
CREATE INDEX IF NOT EXISTS lessons_course_position_idx
    ON public.lessons (course_id, position);
CREATE INDEX IF NOT EXISTS lesson_resources_lesson_type_position_idx
    ON public.lesson_resources (lesson_id, resource_type, position);

-- Required by the dashboard and enrolment checks.
CREATE INDEX IF NOT EXISTS student_courses_student_status_course_idx
    ON public.student_courses (student_id, status, course_id);
CREATE INDEX IF NOT EXISTS student_curricula_student_status_enrolled_idx
    ON public.student_curricula (student_id, status, enrolled_at DESC);

-- Required by the latest-attempt lateral joins used on student pages.
CREATE INDEX IF NOT EXISTS quiz_attempts_student_lesson_submitted_idx
    ON public.quiz_attempts (student_id, lesson_id, submitted_at DESC);

-- The primary key covers (student_id, lesson_id). This index supports recent
-- activity lists without scanning every learner's history.
CREATE INDEX IF NOT EXISTS lesson_progress_recent_activity_idx
    ON public.lesson_progress (last_activity_at DESC);
CREATE INDEX IF NOT EXISTS learning_events_student_created_idx
    ON public.learning_events (student_id, created_at DESC);
