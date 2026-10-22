BEGIN;

-- Audited on 2026-10-22: these tables had no rows and no active application
-- read/write flow. Related orphaned API actions were removed in the same change.
DROP TABLE IF EXISTS public.activity_logs CASCADE;
DROP TABLE IF EXISTS public.course_assignments CASCADE;
DROP TABLE IF EXISTS public.course_announcements CASCADE;
DROP TABLE IF EXISTS public.learning_events CASCADE;
DROP TABLE IF EXISTS public.video_watch_sessions CASCADE;

-- Redundant with created_at in every existing course_teachers row and unused by application code.
ALTER TABLE public.course_teachers DROP COLUMN IF EXISTS assigned_at;

COMMIT;
