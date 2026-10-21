-- Rollback for 20261021_course_certificate_legacy_storage_cleanup.sql.
-- The audit verified all dropped fields were empty before the forward migration.
BEGIN;

ALTER TABLE public.course_certificates
    ADD COLUMN IF NOT EXISTS document_bucket TEXT,
    ADD COLUMN IF NOT EXISTS document_object_path TEXT;

ALTER TABLE public.students
    ADD COLUMN IF NOT EXISTS education_level VARCHAR;

COMMIT;
