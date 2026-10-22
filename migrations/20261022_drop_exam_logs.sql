BEGIN;

-- The legacy log has no active read/write flow. Use RESTRICT so unexpected
-- foreign-key dependencies stop the migration instead of being cascaded away.
DROP TABLE IF EXISTS public.exam_logs RESTRICT;

COMMIT;
