ALTER TABLE public.curriculum_courses
    ADD COLUMN IF NOT EXISTS curriculum_order integer;

ALTER TABLE public.curriculum_courses
    DROP CONSTRAINT IF EXISTS curriculum_courses_order_positive;

ALTER TABLE public.curriculum_courses
    ADD CONSTRAINT curriculum_courses_order_positive
    CHECK (curriculum_order IS NULL OR curriculum_order > 0);

-- Backfill the initial ordering from the existing stable subject code, with course_id as a tie-breaker.
WITH ranked AS (
    SELECT cc.curriculum_id, cc.course_id,
           ROW_NUMBER() OVER (
               PARTITION BY cc.curriculum_id
               ORDER BY c.code ASC NULLS LAST, cc.course_id ASC
           )::integer AS curriculum_order
    FROM public.curriculum_courses cc
    JOIN public.courses c ON c.course_id = cc.course_id
)
UPDATE public.curriculum_courses cc
SET curriculum_order = ranked.curriculum_order
FROM ranked
WHERE ranked.curriculum_id = cc.curriculum_id
  AND ranked.course_id = cc.course_id
  AND cc.curriculum_order IS NULL;

CREATE INDEX IF NOT EXISTS curriculum_courses_order_idx
    ON public.curriculum_courses (curriculum_id, curriculum_order, course_id);

CREATE UNIQUE INDEX IF NOT EXISTS curriculum_courses_order_unique_idx
    ON public.curriculum_courses (curriculum_id, curriculum_order)
    WHERE curriculum_order IS NOT NULL;
