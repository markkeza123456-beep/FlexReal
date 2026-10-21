-- Remove the unused assignment submission table.
-- The live audit found zero rows. Abort if rows appear before deployment.
BEGIN;

DO $migration$
BEGIN
    IF EXISTS (SELECT 1 FROM public.assignment_submissions LIMIT 1) THEN
        RAISE EXCEPTION 'assignment_submissions contains data; aborting cleanup';
    END IF;
END
$migration$;

DROP TABLE IF EXISTS public.assignment_submissions;

COMMIT;
