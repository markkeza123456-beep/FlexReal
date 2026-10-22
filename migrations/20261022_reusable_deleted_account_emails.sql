-- Release emails used by accounts already soft-deleted as inactive.
-- Registration now checks only profiles whose users.account_status = 'active'.
-- Keep profile rows and user IDs intact so historical enrollment and score
-- records keep their existing references.
BEGIN;

UPDATE public.students s
SET email = s.email || '_deleted_' || s.user_id
FROM public.users u
WHERE u.user_id = s.user_id
  AND u.account_status = 'inactive'
  AND s.email IS NOT NULL
  AND s.email <> ''
  AND s.email NOT LIKE '%_deleted_' || s.user_id;

UPDATE public.teachers t
SET email = t.email || '_deleted_' || t.user_id
FROM public.users u
WHERE u.user_id = t.user_id
  AND u.account_status = 'inactive'
  AND t.email IS NOT NULL
  AND t.email <> ''
  AND t.email NOT LIKE '%_deleted_' || t.user_id;

UPDATE public.parents p
SET email = p.email || '_deleted_' || p.user_id
FROM public.users u
WHERE u.user_id = p.user_id
  AND u.account_status = 'inactive'
  AND p.email IS NOT NULL
  AND p.email <> ''
  AND p.email NOT LIKE '%_deleted_' || p.user_id;

COMMIT;
