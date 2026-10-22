ALTER TABLE public.lessons
    ADD COLUMN IF NOT EXISTS quiz_duration_minutes INTEGER NOT NULL DEFAULT 60;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'lessons_quiz_duration_minutes_check'
          AND conrelid = 'public.lessons'::regclass
    ) THEN
        ALTER TABLE public.lessons
            ADD CONSTRAINT lessons_quiz_duration_minutes_check
            CHECK (quiz_duration_minutes BETWEEN 1 AND 1440);
    END IF;
END $$;

CREATE TABLE IF NOT EXISTS public.quiz_attempt_sessions (
    session_id BIGSERIAL PRIMARY KEY,
    student_id VARCHAR NOT NULL REFERENCES public.students(user_id) ON DELETE CASCADE,
    lesson_id BIGINT NOT NULL REFERENCES public.lessons(lesson_id) ON DELETE CASCADE,
    attempt_number INTEGER NOT NULL,
    started_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    expires_at TIMESTAMPTZ NOT NULL,
    finished_at TIMESTAMPTZ,
    status VARCHAR(20) NOT NULL DEFAULT 'in_progress'
        CHECK (status IN ('in_progress', 'submitted', 'failed', 'timed_out')),
    tab_switch_count INTEGER NOT NULL DEFAULT 0 CHECK (tab_switch_count >= 0),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (student_id, lesson_id, attempt_number)
);

CREATE UNIQUE INDEX IF NOT EXISTS quiz_attempt_sessions_one_active_idx
    ON public.quiz_attempt_sessions (student_id, lesson_id)
    WHERE status = 'in_progress';

CREATE INDEX IF NOT EXISTS quiz_attempt_sessions_student_lesson_idx
    ON public.quiz_attempt_sessions (student_id, lesson_id, attempt_number DESC);
