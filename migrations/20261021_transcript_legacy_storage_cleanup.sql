-- Remove legacy Supabase Storage metadata from transcript_requests.
-- Approved on 2026-10-21 after identifying one populated row per column.
-- The approved data loss is intentional; no backup is created.
BEGIN;

ALTER TABLE public.transcript_requests
    DROP COLUMN IF EXISTS storage_bucket,
    DROP COLUMN IF EXISTS storage_object_path;

COMMIT;
