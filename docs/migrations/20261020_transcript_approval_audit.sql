BEGIN;

ALTER TABLE IF EXISTS public.transcript_requests
    ADD COLUMN IF NOT EXISTS approved_by TEXT,
    ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP WITH TIME ZONE,
    ADD COLUMN IF NOT EXISTS approved_courses TEXT,
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP WITH TIME ZONE;

UPDATE public.transcript_requests
SET created_at = COALESCE(created_at, submitted_at, CURRENT_TIMESTAMP)
WHERE created_at IS NULL;

ALTER TABLE IF EXISTS public.transcript_requests
    ALTER COLUMN created_at SET DEFAULT CURRENT_TIMESTAMP,
    ALTER COLUMN created_at SET NOT NULL;

COMMIT;
