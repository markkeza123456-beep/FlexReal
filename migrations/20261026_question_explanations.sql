BEGIN;

ALTER TABLE public.questions
    ADD COLUMN IF NOT EXISTS teacher_explanation TEXT;

ALTER TABLE public.quiz_attempt_answers
    ADD COLUMN IF NOT EXISTS ai_explanation TEXT;

COMMIT;
