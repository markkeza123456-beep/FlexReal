BEGIN;

ALTER TABLE public.questions
    ADD COLUMN IF NOT EXISTS teacher_explanation TEXT;

COMMIT;
