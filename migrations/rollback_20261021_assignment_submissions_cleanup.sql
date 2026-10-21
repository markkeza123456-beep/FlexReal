-- Schema-only rollback for 20261021_assignment_submissions_cleanup.sql.
-- Any submission data created after the table is recreated is not restored.
BEGIN;

CREATE TABLE IF NOT EXISTS public.assignment_submissions (
    submission_id BIGSERIAL PRIMARY KEY,
    assignment_id BIGINT NOT NULL REFERENCES public.course_assignments(assignment_id) ON DELETE CASCADE,
    student_id VARCHAR(50) NOT NULL REFERENCES public.students(user_id),
    note TEXT NOT NULL DEFAULT '',
    file_url TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'submitted',
    submitted_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    reviewed_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (assignment_id, student_id)
);

COMMIT;
