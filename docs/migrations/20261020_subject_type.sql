-- Run once on the Supabase database before deploying the PHP changes.
ALTER TABLE public.courses
    ADD COLUMN IF NOT EXISTS subject_type text NOT NULL DEFAULT 'elective';

-- Preserve existing staff curriculum settings when initializing the global
-- course type. A course required by any curriculum is compulsory.
UPDATE public.courses c
SET subject_type = CASE WHEN EXISTS (
    SELECT 1 FROM public.curriculum_courses cc
    WHERE cc.course_id = c.course_id AND cc.requirement_type = 'required'
) THEN 'compulsory' ELSE 'elective' END;

ALTER TABLE public.courses
    DROP CONSTRAINT IF EXISTS courses_subject_type_check;
ALTER TABLE public.courses
    ADD CONSTRAINT courses_subject_type_check
    CHECK (subject_type IN ('compulsory', 'elective'));
