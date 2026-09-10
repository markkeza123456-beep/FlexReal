-- Optional feature required only if staff profile-picture upload is enabled.
-- Run this once in Supabase; it does not delete or change existing data.
ALTER TABLE public.staff
    ADD COLUMN IF NOT EXISTS avatar_url text;
