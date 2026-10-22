-- Hard-delete support and cleanup for the current public schema.
-- Uses catalog discovery so existing foreign keys are upgraded idempotently.
BEGIN;

-- Existing FK definitions are deliberately left intact here. The project has
-- multiple FK naming/layout variants across deployments; changing them by
-- guessed table/column pairs caused duplicate constraints. Runtime DELETE
-- handlers explicitly remove children in a transaction. Use this read-only
-- query to inspect live FK targets and delete actions before adding any CASCADE:
-- SELECT conrelid::regclass AS child_table, conname,
--        pg_get_constraintdef(oid) AS definition
-- FROM pg_constraint WHERE contype = 'f' AND connamespace = 'public'::regnamespace
-- ORDER BY conrelid::regclass::text, conname;

-- Remove existing inactive accounts and all direct profile/dependent rows.
-- The accompanying API migration performs the same cleanup transactionally at runtime.
DO $cleanup$
DECLARE
    user_row RECORD;
BEGIN
    FOR user_row IN SELECT user_id, role FROM public.users WHERE account_status = 'inactive'
    LOOP
        IF to_regclass('public.students') IS NOT NULL THEN
            UPDATE public.students SET advisor_user_id = NULL WHERE advisor_user_id = user_row.user_id;
        END IF;
        IF to_regclass('public.graduation_requests') IS NOT NULL THEN
            UPDATE public.graduation_requests SET approved_by = NULL WHERE approved_by = user_row.user_id;
        END IF;
        IF to_regclass('public.transfer_permissions') IS NOT NULL THEN
            DELETE FROM public.transfer_permissions WHERE instructor_id = user_row.user_id OR granted_by = user_row.user_id;
        END IF;
        IF to_regclass('public.transcript_requests') IS NOT NULL THEN
            DELETE FROM public.transcript_request_lessons WHERE request_id IN (SELECT request_id FROM public.transcript_requests WHERE student_id = user_row.user_id);
            DELETE FROM public.transcript_requests WHERE student_id = user_row.user_id;
        END IF;
        IF to_regclass('public.transcript_request_lessons') IS NOT NULL THEN DELETE FROM public.transcript_request_lessons WHERE reviewed_by = user_row.user_id; END IF;
        IF to_regclass('public.quiz_attempt_answers') IS NOT NULL THEN UPDATE public.quiz_attempt_answers SET reviewer_id = NULL WHERE reviewer_id = user_row.user_id; END IF;
        IF to_regclass('public.lesson_progress') IS NOT NULL THEN
            UPDATE public.lesson_progress SET transferred_by = NULL WHERE transferred_by = user_row.user_id;
            DELETE FROM public.lesson_progress WHERE student_id = user_row.user_id;
        END IF;
        IF to_regclass('public.student_curricula') IS NOT NULL THEN DELETE FROM public.student_curricula WHERE student_id = user_row.user_id; END IF;
        IF to_regclass('public.student_courses') IS NOT NULL THEN DELETE FROM public.student_courses WHERE student_id = user_row.user_id; END IF;
        IF to_regclass('public.course_certificates') IS NOT NULL THEN DELETE FROM public.course_certificates WHERE student_id = user_row.user_id; END IF;
        IF to_regclass('public.quiz_attempt_sessions') IS NOT NULL THEN DELETE FROM public.quiz_attempt_sessions WHERE student_id = user_row.user_id; END IF;
        IF to_regclass('public.quiz_attempts') IS NOT NULL THEN DELETE FROM public.quiz_attempts WHERE student_id = user_row.user_id; END IF;
        IF to_regclass('public.course_teachers') IS NOT NULL THEN DELETE FROM public.course_teachers WHERE teacher_id = user_row.user_id; END IF;
        IF to_regclass('public.staff') IS NOT NULL THEN DELETE FROM public.staff WHERE user_id = user_row.user_id; END IF;
        IF user_row.role = 'student' AND to_regclass('public.students') IS NOT NULL THEN DELETE FROM public.students WHERE user_id = user_row.user_id; END IF;
        IF user_row.role = 'teacher' AND to_regclass('public.teachers') IS NOT NULL THEN DELETE FROM public.teachers WHERE user_id = user_row.user_id; END IF;
        IF user_row.role = 'parent' AND to_regclass('public.parents') IS NOT NULL THEN DELETE FROM public.parents WHERE user_id = user_row.user_id; END IF;
        IF to_regclass('public.user_addresses') IS NOT NULL THEN DELETE FROM public.user_addresses WHERE user_id = user_row.user_id; END IF;
        IF to_regclass('public.site_visits') IS NOT NULL THEN DELETE FROM public.site_visits WHERE user_id = user_row.user_id; END IF;
        IF to_regclass('public.password_reset_tokens') IS NOT NULL THEN DELETE FROM public.password_reset_tokens WHERE user_id = user_row.user_id; END IF;
        DELETE FROM public.users WHERE user_id = user_row.user_id;
    END LOOP;
END
$cleanup$;

COMMIT;
