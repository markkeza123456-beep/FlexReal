# Database schema snapshot

Live PostgreSQL/Supabase `public` schema inspected on 2026-10-20 via `config/db.php`. This is the database state at inspection time, not a reconstruction from source code. Types, nullability, and defaults are included.

Tables found: **31** (including `course_certificates` added later on 2026-10-20).

## `activity_logs`

- `log_id` — bigint (`int8`); NOT NULL
- `actor_user_id` — character varying (`varchar`); NULL
- `entity_type` — character varying (`varchar`); NOT NULL
- `entity_id` — character varying (`varchar`); NOT NULL
- `action` — character varying (`varchar`); NOT NULL
- `detail` — jsonb (`jsonb`); NULL
- `created_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`

## `assignment_submissions`

- `submission_id` — bigint (`int8`); NOT NULL; default `nextval('assignment_submissions_submission_id_seq'::regclass)`
- `assignment_id` — bigint (`int8`); NOT NULL
- `student_id` — character varying (`varchar`); NOT NULL
- `note` — text; NOT NULL; default `''::text`
- `file_url` — text; NULL
- `status` — character varying (`varchar`); NOT NULL; default `'submitted'::character varying`
- `submitted_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`
- `reviewed_at` — timestamp with time zone (`timestamptz`); NULL

## `course_announcements`

- `announcement_id` — bigint (`int8`); NOT NULL; default `nextval('course_announcements_announcement_id_seq'::regclass)`
- `course_id` — bigint (`int8`); NOT NULL
- `teacher_id` — character varying (`varchar`); NOT NULL
- `title` — character varying (`varchar`); NOT NULL
- `body` — text; NOT NULL; default `''::text`
- `created_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`

## `course_assignments`

- `assignment_id` — bigint (`int8`); NOT NULL; default `nextval('course_assignments_assignment_id_seq'::regclass)`
- `course_id` — bigint (`int8`); NOT NULL
- `teacher_id` — character varying (`varchar`); NOT NULL
- `title` — character varying (`varchar`); NOT NULL
- `description` — text; NOT NULL; default `''::text`
- `due_at` — timestamp with time zone (`timestamptz`); NOT NULL
- `max_score` — numeric; NOT NULL; default `10`
- `created_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`

## `course_certificates`

- `certificate_id` — bigint (`int8`); NOT NULL; identity primary key
- `student_id` — character varying (`varchar`); NOT NULL; references `students.user_id`
- `course_id` — bigint (`int8`); NOT NULL; references `courses.course_id`
- `certificate_number` — character varying (`varchar`); NOT NULL; unique
- `issued_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`
- `document_bucket` — text; NULL (legacy storage fields, unused)
- `document_object_path` — text; NULL (legacy storage fields, unused)
- `document_pdf` — bytea; NOT NULL (persistent PDF bytes)
- Unique constraint: (`student_id`, `course_id`) prevents duplicate certificates per course

## `course_teachers`

- `course_id` — bigint (`int8`); NOT NULL
- `teacher_id` — character varying (`varchar`); NOT NULL
- `assigned_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`

## `courses`

- `course_id` — bigint (`int8`); NOT NULL
- `code` — character varying (`varchar`); NOT NULL
- `name` — character varying (`varchar`); NOT NULL
- `description` — text; NULL
- `credits` — numeric; NOT NULL; default `0`
- `status` — character varying (`varchar`); NOT NULL; default `'active'::character varying`
- `created_by` — character varying (`varchar`); NULL
- `created_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`
- `updated_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`
- `subject_type` — text; NOT NULL; default `'elective'::text`

## `curricula`

- `curriculum_id` — bigint (`int8`); NOT NULL
- `code` — character varying (`varchar`); NOT NULL
- `name` — character varying (`varchar`); NOT NULL
- `level` — character varying (`varchar`); NULL
- `total_credits` — numeric; NULL
- `status` — character varying (`varchar`); NOT NULL; default `'active'::character varying`
- `created_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`
- `updated_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`

## `curriculum_courses`

- `curriculum_id` — bigint (`int8`); NOT NULL
- `course_id` — bigint (`int8`); NOT NULL
- `requirement_type` — character varying (`varchar`); NOT NULL; default `'required'::character varying`

## `exam_logs`

- `exam_log_id` — bigint (`int8`); NOT NULL; default `nextval('exam_logs_exam_log_id_seq'::regclass)`
- `student_id` — character varying (`varchar`); NOT NULL
- `exam_id` — bigint (`int8`); NOT NULL
- `tab_switch_count` — integer (`int4`); NOT NULL; default `0`
- `created_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`
- `updated_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`
- `attempt_number` — integer (`int4`); NOT NULL; default `1`

## `graduation_requests`

- `request_id` — bigint (`int8`); NOT NULL; default `nextval('graduation_requests_request_id_seq'::regclass)`
- `student_id` — character varying (`varchar`); NOT NULL
- `total_credits` — numeric; NOT NULL; default `0`
- `status` — character varying (`varchar`); NOT NULL; default `'pending'::character varying`
- `approved_by` — character varying (`varchar`); NULL
- `submitted_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`
- `reviewed_at` — timestamp with time zone (`timestamptz`); NULL
- `reviewer_note` — text; NULL
- `document_bucket` — text; NULL
- `document_object_path` — text; NULL

## `learning_events`

- `event_id` — bigint (`int8`); NOT NULL
- `student_id` — character varying (`varchar`); NOT NULL
- `lesson_id` — bigint (`int8`); NULL
- `event_type` — character varying (`varchar`); NOT NULL
- `detail` — jsonb (`jsonb`); NULL
- `created_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`

## `lesson_progress`

- `student_id` — character varying (`varchar`); NOT NULL
- `lesson_id` — bigint (`int8`); NOT NULL
- `opened_count` — integer (`int4`); NOT NULL; default `0`
- `video_open_count` — integer (`int4`); NOT NULL; default `0`
- `first_opened_at` — timestamp with time zone (`timestamptz`); NULL
- `last_opened_at` — timestamp with time zone (`timestamptz`); NULL
- `last_activity_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`
- `document_progress_percent` — numeric; NOT NULL; default `0`
- `document_page_index` — integer (`int4`); NOT NULL; default `0`
- `video_progress_percent` — numeric; NOT NULL; default `0`
- `video_position_seconds` — numeric; NOT NULL; default `0`
- `is_transferred` — boolean (`bool`); NOT NULL; default `false`
- `transfer_request_id` — bigint (`int8`); NULL
- `transferred_by` — character varying (`varchar`); NULL
- `transferred_at` — timestamp with time zone (`timestamptz`); NULL
- `grade` — character varying (`varchar`); NULL

## `lesson_resources`

- `resource_id` — bigint (`int8`); NOT NULL
- `lesson_id` — bigint (`int8`); NOT NULL
- `resource_type` — character varying (`varchar`); NOT NULL
- `title` — character varying (`varchar`); NOT NULL
- `url` — text; NOT NULL
- `duration_seconds` — integer (`int4`); NULL
- `position` — integer (`int4`); NOT NULL; default `1`
- `created_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`
- `updated_at` — timestamp with time zone (`timestamptz`); NULL
- `updated_by` — character varying (`varchar`); NULL

## `lessons`

- `lesson_id` — bigint (`int8`); NOT NULL
- `course_id` — bigint (`int8`); NOT NULL
- `title` — character varying (`varchar`); NOT NULL
- `content` — text; NULL
- `position` — integer (`int4`); NOT NULL
- `study_hours` — numeric; NULL
- `pass_percentage` — numeric; NULL
- `created_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`
- `updated_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`
- `updated_by` — character varying (`varchar`); NULL

## `parents`

- `user_id` — character varying (`varchar`); NOT NULL
- `full_name` — character varying (`varchar`); NOT NULL
- `email` — character varying (`varchar`); NULL
- `phone` — character varying (`varchar`); NULL
- `created_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`
- `updated_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`

## `password_reset_tokens`

- `token_id` — bigint (`int8`); NOT NULL
- `user_id` — character varying (`varchar`); NOT NULL
- `token_hash` — text; NOT NULL
- `expires_at` — timestamp with time zone (`timestamptz`); NOT NULL
- `used_at` — timestamp with time zone (`timestamptz`); NULL
- `created_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`

## `questions`

- `question_id` — bigint (`int8`); NOT NULL
- `lesson_id` — bigint (`int8`); NOT NULL
- `question_type` — character varying (`varchar`); NOT NULL
- `question_text` — text; NOT NULL
- `options` — jsonb (`jsonb`); NULL
- `correct_choice` — character (`bpchar`); NULL
- `max_score` — numeric; NOT NULL; default `1`
- `is_active` — boolean (`bool`); NOT NULL; default `true`
- `created_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`
- `updated_at` — timestamp with time zone (`timestamptz`); NULL

## `quiz_attempt_answers`

- `attempt_answer_id` — bigint (`int8`); NOT NULL
- `attempt_id` — bigint (`int8`); NOT NULL
- `question_id` — bigint (`int8`); NOT NULL
- `selected_choice` — character (`bpchar`); NULL
- `answer_text` — text; NULL
- `score` — numeric; NULL
- `review_status` — character varying (`varchar`); NOT NULL; default `'not_required'::character varying`
- `reviewer_id` — character varying (`varchar`); NULL
- `reviewer_comment` — text; NULL
- `reviewed_at` — timestamp with time zone (`timestamptz`); NULL

## `quiz_attempts`

- `attempt_id` — bigint (`int8`); NOT NULL
- `student_id` — character varying (`varchar`); NOT NULL
- `lesson_id` — bigint (`int8`); NOT NULL
- `attempt_number` — integer (`int4`); NOT NULL
- `score` — numeric; NOT NULL; default `0`
- `total_score` — numeric; NOT NULL; default `0`
- `status` — character varying (`varchar`); NOT NULL; default `'submitted'::character varying`
- `submitted_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`

## `staff`

- `user_id` — character varying (`varchar`); NOT NULL
- `first_name` — character varying (`varchar`); NOT NULL
- `last_name` — character varying (`varchar`); NOT NULL
- `created_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`
- `updated_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`

## `student_courses`

- `student_id` — character varying (`varchar`); NOT NULL
- `course_id` — bigint (`int8`); NOT NULL
- `enrolled_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`
- `completed_at` — timestamp with time zone (`timestamptz`); NULL
- `status` — character varying (`varchar`); NOT NULL; default `'active'::character varying`

## `student_curricula`

- `student_id` — character varying (`varchar`); NOT NULL
- `curriculum_id` — bigint (`int8`); NOT NULL
- `enrolled_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`
- `completed_at` — timestamp with time zone (`timestamptz`); NULL
- `status` — character varying (`varchar`); NOT NULL; default `'active'::character varying`

## `students`

- `user_id` — character varying (`varchar`); NOT NULL
- `full_name` — character varying (`varchar`); NOT NULL
- `email` — character varying (`varchar`); NULL
- `phone` — character varying (`varchar`); NULL
- `education_level` — character varying (`varchar`); NULL
- `student_level` — character varying (`varchar`); NULL
- `parent_user_id` — character varying (`varchar`); NULL
- `advisor_user_id` — character varying (`varchar`); NULL
- `avatar_url` — text; NULL
- `created_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`
- `updated_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`

## `teachers`

- `user_id` — character varying (`varchar`); NOT NULL
- `full_name` — character varying (`varchar`); NOT NULL
- `email` — character varying (`varchar`); NULL
- `phone` — character varying (`varchar`); NULL
- `avatar_url` — text; NULL
- `created_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`
- `updated_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`

## `transcript_request_lessons`

- `request_id` — bigint (`int8`); NOT NULL
- `lesson_id` — bigint (`int8`); NOT NULL
- `is_approved` — boolean (`bool`); NOT NULL; default `false`
- `reviewed_by` — character varying (`varchar`); NOT NULL
- `reviewed_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`

## `transcript_requests`

- `request_id` — bigint (`int8`); NOT NULL
- `student_id` — character varying (`varchar`); NOT NULL
- `file_path` — text; NULL
- `original_file_name` — text; NOT NULL
- `mime_type` — character varying (`varchar`); NOT NULL
- `file_size` — bigint (`int8`); NOT NULL
- `status` — character varying (`varchar`); NOT NULL; default `'pending'::character varying`
- `submitted_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`
- `reviewed_by` — character varying (`varchar`); NULL
- `reviewed_at` — timestamp with time zone (`timestamptz`); NULL
- `reviewer_note` — text; NULL
- `storage_bucket` — text; NULL
- `storage_object_path` — text; NULL
- `document_url` — text; NULL

## `transfer_permissions`

- `permission_id` — bigint (`int8`); NOT NULL
- `instructor_id` — character varying (`varchar`); NOT NULL
- `granted_by` — character varying (`varchar`); NOT NULL
- `granted_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`
- `revoked_by` — character varying (`varchar`); NULL
- `revoked_at` — timestamp with time zone (`timestamptz`); NULL
- `is_active` — boolean (`bool`); NOT NULL; default `true`

## `user_addresses`

- `address_id` — bigint (`int8`); NOT NULL
- `user_id` — character varying (`varchar`); NOT NULL
- `address_line` — text; NULL
- `subdistrict` — character varying (`varchar`); NULL
- `district` — character varying (`varchar`); NULL
- `province` — character varying (`varchar`); NULL
- `postal_code` — character varying (`varchar`); NULL
- `is_primary` — boolean (`bool`); NOT NULL; default `true`
- `created_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`

## `users`

- `user_id` — character varying (`varchar`); NOT NULL
- `password_hash` — text; NOT NULL
- `role` — character varying (`varchar`); NOT NULL
- `account_status` — character varying (`varchar`); NOT NULL; default `'active'::character varying`
- `created_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`
- `updated_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`

## `video_watch_sessions`

- `id` — bigint (`int8`); NOT NULL; default `nextval('video_watch_sessions_id_seq'::regclass)`
- `student_id` — character varying (`varchar`); NOT NULL
- `lesson_id` — bigint (`int8`); NOT NULL
- `video_url` — text; NULL
- `total_duration_seconds` — integer (`int4`); NOT NULL; default `0`
- `current_seconds` — integer (`int4`); NOT NULL; default `0`
- `progress_percent` — numeric; NOT NULL; default `0`
- `status` — character varying (`varchar`); NOT NULL; default `'in_progress'::character varying`
- `started_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`
- `last_watched_at` — timestamp with time zone (`timestamptz`); NULL
- `completed_at` — timestamp with time zone (`timestamptz`); NULL
- `created_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`
- `updated_at` — timestamp with time zone (`timestamptz`); NOT NULL; default `now()`
- `duration_seconds` — integer (`int4`); NOT NULL; default `0`

## Schema checks and likely cleanup candidates

- `courses.subject_type` exists now as `text NOT NULL DEFAULT 'elective'`; accepted values are constrained to `compulsory` and `elective`.
- No dedicated activity-points/score columns were found. `lesson_progress.document_progress_percent`, `video_progress_percent`, `opened_count`, and `video_open_count` record learning progress/usage, not activity points. `quiz_attempts.score` and `quiz_attempt_answers.score` are quiz grades and are active.
- `transcript_requests.file_path` and `document_url` overlap in purpose, but are not safe to drop: 21 rows were inspected; 18 have `document_url IS NULL`, all have `file_path` non-null, and the review/reject code still reads both (reject uses legacy local paths from `file_path`). Preserve both until a data migration and code cutover is complete.
- `students.student_level` and `education_level` are two fields for education level. In the inspected data, all 4 `education_level` values are NULL while all 4 `student_level` values are populated; code currently falls back from `student_level` to `education_level`. Candidate for later removal after the fallback and writers are retired.
- `video_watch_sessions.total_duration_seconds` appears obsolete beside `duration_seconds`: project code only reads/writes `duration_seconds`; all 10 rows have `total_duration_seconds=0` while `duration_seconds>0`. Candidate for removal after confirming no external consumer depends on it.
- `transcript_requests.storage_bucket` and `storage_object_path` appear unused by current project code; one row has both columns populated. Inspect the external storage workflow before removal.
- `curricula.total_credits` may duplicate a sum of `courses.credits`, but it can also be a manually configured target; decide whether it is authoritative before removing.
- `api_courses.php` emits aliases such as `video_url`/`video_path` and `document_path` from the same `lesson_resources.url`; these are API response aliases, not duplicate database columns.
- `credit_transfers` is referenced by `submit_transfer.php` and `teacher_credit_transfers.php` but does **not** exist in the inspected `public` schema. This needs a separate schema/code reconciliation.
- `essay_submissions` is created on demand by `teacher_api.php` but was **absent at inspection time**. It is not included in the live-table column inventory below; the PHP `CREATE TABLE IF NOT EXISTS` definition is the current source for its intended columns.

## Timestamp columns absent at inspection

- `activity_logs`: `updated_at`
- `assignment_submissions`: `created_at`, `updated_at`
- `course_announcements`: `updated_at`
- `course_assignments`: `updated_at`
- `course_teachers`: `created_at`, `updated_at`
- `curriculum_courses`: `created_at`, `updated_at`
- `graduation_requests`: `created_at`, `updated_at`
- `learning_events`: `updated_at`
- `lesson_progress`: `created_at`, `updated_at`
- `password_reset_tokens`: `updated_at`
- `quiz_attempt_answers`: `created_at`, `updated_at`
- `quiz_attempts`: `created_at`, `updated_at`
- `student_courses`: `created_at`, `updated_at`
- `student_curricula`: `created_at`, `updated_at`
- `transcript_request_lessons`: `created_at`, `updated_at`
- `transcript_requests`: `created_at`, `updated_at`
- `transfer_permissions`: `created_at`, `updated_at`
- `user_addresses`: `updated_at`

For an idempotent SQL proposal, see `docs/migrations/20261020_schema_cleanup.sql`. Do not run its optional DROP statements until application and external consumers have been checked.
