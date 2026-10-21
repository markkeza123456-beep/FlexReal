-- Approved demo cleanup: retain the single staff account, remove all other
-- user accounts and their role/activity data, and remove the certificate feature.
-- The pre-cleanup logical backup is stored outside the repository at
-- D:/Xampp/backups/FlexReal/flexreal_public_pre_demo_cleanup_20261021.json.gz.
BEGIN;

DO $migration$
DECLARE
    non_staff_count BIGINT;
    staff_count BIGINT;
    certificate_count BIGINT;
BEGIN
    SELECT COUNT(*) INTO non_staff_count FROM public.users WHERE role <> 'staff';
    SELECT COUNT(*) INTO staff_count FROM public.users WHERE role = 'staff';
    SELECT COUNT(*) INTO certificate_count FROM public.course_certificates;

    IF non_staff_count <> 18 OR staff_count <> 1 THEN
        RAISE EXCEPTION 'Expected 18 non-staff users and 1 staff user; found % and %', non_staff_count, staff_count;
    END IF;
    IF certificate_count <> 2 THEN
        RAISE EXCEPTION 'Expected 2 course certificates for approved removal; found %', certificate_count;
    END IF;
END
$migration$;

CREATE TEMP TABLE demo_cleanup_users ON COMMIT DROP AS
SELECT user_id, role FROM public.users WHERE role <> 'staff';
CREATE TEMP TABLE demo_cleanup_students ON COMMIT DROP AS
SELECT user_id FROM demo_cleanup_users WHERE role = 'student';
CREATE TEMP TABLE demo_cleanup_teachers ON COMMIT DROP AS
SELECT user_id FROM demo_cleanup_users WHERE role = 'teacher';

-- Preserve catalog content while removing references to the deleted creators.
UPDATE public.courses SET created_by = NULL
WHERE created_by IN (SELECT user_id FROM demo_cleanup_users);
UPDATE public.courses SET updated_by = NULL
WHERE updated_by IN (SELECT user_id FROM demo_cleanup_users);
UPDATE public.curricula SET created_by = NULL
WHERE created_by IN (SELECT user_id FROM demo_cleanup_users);
UPDATE public.curricula SET updated_by = NULL
WHERE updated_by IN (SELECT user_id FROM demo_cleanup_users);
UPDATE public.lessons SET created_by = NULL
WHERE created_by IN (SELECT user_id FROM demo_cleanup_users);
UPDATE public.lessons SET updated_by = NULL
WHERE updated_by IN (SELECT user_id FROM demo_cleanup_users);
UPDATE public.lesson_resources SET updated_by = NULL
WHERE updated_by IN (SELECT user_id FROM demo_cleanup_users);
UPDATE public.staff SET created_by = NULL
WHERE created_by IN (SELECT user_id FROM demo_cleanup_users);
UPDATE public.staff SET updated_by = NULL
WHERE updated_by IN (SELECT user_id FROM demo_cleanup_users);
UPDATE public.users SET created_by = NULL
WHERE created_by IN (SELECT user_id FROM demo_cleanup_users);
UPDATE public.users SET updated_by = NULL
WHERE updated_by IN (SELECT user_id FROM demo_cleanup_users);

DELETE FROM public.activity_logs
WHERE actor_user_id IN (SELECT user_id FROM demo_cleanup_users);
DELETE FROM public.transfer_permissions
WHERE instructor_id IN (SELECT user_id FROM demo_cleanup_users)
   OR granted_by IN (SELECT user_id FROM demo_cleanup_users)
   OR revoked_by IN (SELECT user_id FROM demo_cleanup_users);

-- Remove teacher-owned rows and reviewer references before deleting profiles.
DELETE FROM public.course_teachers
WHERE teacher_id IN (SELECT user_id FROM demo_cleanup_teachers);
DELETE FROM public.course_announcements
WHERE teacher_id IN (SELECT user_id FROM demo_cleanup_teachers);
DELETE FROM public.course_assignments
WHERE teacher_id IN (SELECT user_id FROM demo_cleanup_teachers);
UPDATE public.quiz_attempt_answers SET reviewer_id = NULL
WHERE reviewer_id IN (SELECT user_id FROM demo_cleanup_teachers);

-- Remove student records in FK-safe order. Deleting attempts cascades answer rows.
DELETE FROM public.lesson_progress
WHERE student_id IN (SELECT user_id FROM demo_cleanup_students);
DELETE FROM public.learning_events
WHERE student_id IN (SELECT user_id FROM demo_cleanup_students);
DELETE FROM public.student_curricula
WHERE student_id IN (SELECT user_id FROM demo_cleanup_students);
DELETE FROM public.student_courses
WHERE student_id IN (SELECT user_id FROM demo_cleanup_students);
DELETE FROM public.quiz_attempts
WHERE student_id IN (SELECT user_id FROM demo_cleanup_students);
DELETE FROM public.transcript_requests
WHERE student_id IN (SELECT user_id FROM demo_cleanup_students);
DELETE FROM public.video_watch_sessions
WHERE student_id IN (SELECT user_id FROM demo_cleanup_students);

-- Remove the approved two certificate records and feature table.
DROP TABLE public.course_certificates;

-- Student exam and graduation rows cascade from users; account-address and
-- reset-token rows also cascade. Remove role profiles before users due NO ACTION.
DELETE FROM public.students WHERE user_id IN (SELECT user_id FROM demo_cleanup_students);
DELETE FROM public.teachers WHERE user_id IN (SELECT user_id FROM demo_cleanup_teachers);
DELETE FROM public.parents
WHERE user_id IN (SELECT user_id FROM demo_cleanup_users WHERE role = 'parent');
DELETE FROM public.users WHERE user_id IN (SELECT user_id FROM demo_cleanup_users);

DO $migration$
BEGIN
    IF (SELECT COUNT(*) FROM public.users) <> 1
       OR EXISTS (SELECT 1 FROM public.users WHERE role <> 'staff') THEN
        RAISE EXCEPTION 'Demo user cleanup verification failed';
    END IF;
    IF to_regclass('public.course_certificates') IS NOT NULL THEN
        RAISE EXCEPTION 'Course certificate table still exists';
    END IF;
END
$migration$;

COMMIT;
