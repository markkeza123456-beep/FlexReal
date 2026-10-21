-- Forward migration: remove the unused legacy object-storage columns from
-- course_certificates plus the superseded students.education_level fallback.
-- The 2026-10-21 audit found all three fields empty in populated tables.
-- Student screens now use students.student_level only; course certificates
-- store/read document_pdf instead of the legacy object-storage fields.
-- Refuse to run if meaningful data appeared after the audit.
BEGIN;

DO $migration$
BEGIN
    IF EXISTS (
        SELECT 1 FROM public.course_certificates
        WHERE document_bucket IS NOT NULL AND BTRIM(document_bucket) <> ''
    ) OR EXISTS (
        SELECT 1 FROM public.course_certificates
        WHERE document_object_path IS NOT NULL AND BTRIM(document_object_path) <> ''
    ) OR EXISTS (
        SELECT 1 FROM public.students
        WHERE education_level IS NOT NULL AND BTRIM(education_level) <> ''
    ) THEN
        RAISE EXCEPTION 'legacy columns contain data; aborting cleanup';
    END IF;
END
$migration$;

ALTER TABLE public.course_certificates
    DROP COLUMN IF EXISTS document_bucket,
    DROP COLUMN IF EXISTS document_object_path;

ALTER TABLE public.students
    DROP COLUMN IF EXISTS education_level;

COMMIT;
