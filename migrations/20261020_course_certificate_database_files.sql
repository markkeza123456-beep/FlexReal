BEGIN;

ALTER TABLE public.course_certificates
    ADD COLUMN IF NOT EXISTS document_pdf BYTEA;

ALTER TABLE public.course_certificates
    ALTER COLUMN document_bucket DROP NOT NULL,
    ALTER COLUMN document_object_path DROP NOT NULL;

ALTER TABLE public.course_certificates
    ALTER COLUMN document_pdf SET NOT NULL;

COMMIT;
