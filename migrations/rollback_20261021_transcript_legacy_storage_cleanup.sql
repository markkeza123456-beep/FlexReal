-- Schema-only rollback for 20261021_transcript_legacy_storage_cleanup.sql.
-- Values from the dropped columns are not recoverable because no backup was requested.
BEGIN;

ALTER TABLE public.transcript_requests
    ADD COLUMN IF NOT EXISTS storage_bucket TEXT,
    ADD COLUMN IF NOT EXISTS storage_object_path TEXT;

COMMIT;
