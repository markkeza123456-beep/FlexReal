BEGIN;

ALTER TABLE public.quiz_attempt_answers
    ADD COLUMN IF NOT EXISTS result_status VARCHAR(20);

ALTER TABLE public.quiz_attempt_answers
    DROP CONSTRAINT IF EXISTS quiz_attempt_answers_review_status_check;

UPDATE public.quiz_attempt_answers
SET review_status = 'graded'
WHERE review_status = 'reviewed';

UPDATE public.quiz_attempt_answers aa
SET result_status = CASE WHEN aa.score >= q.max_score THEN 'passed' ELSE 'failed' END
FROM public.questions q
WHERE q.question_id = aa.question_id
  AND q.question_type = 'essay'
  AND aa.review_status = 'graded'
  AND aa.result_status IS NULL;

ALTER TABLE public.quiz_attempt_answers
    ADD CONSTRAINT quiz_attempt_answers_review_status_check
    CHECK (review_status IN ('not_required', 'pending', 'graded'));

ALTER TABLE public.quiz_attempt_answers
    DROP CONSTRAINT IF EXISTS quiz_attempt_answers_result_status_check;

ALTER TABLE public.quiz_attempt_answers
    ADD CONSTRAINT quiz_attempt_answers_result_status_check
    CHECK (result_status IS NULL OR result_status IN ('passed', 'failed'));

COMMIT;
