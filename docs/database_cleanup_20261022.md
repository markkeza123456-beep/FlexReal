# Database and code cleanup — 2026-10-22

## Evidence used

- Counted rows in all tables in the connected PostgreSQL `public` schema.
- Searched first-party PHP, JavaScript, HTML, and CSS for table references and API action callers.
- Checked foreign-key dependencies before dropping the legacy tables.
- Kept the audit to schema and row counts; no row contents or credentials were exported.

## Removed

These tables each contained zero rows and had no active application read/write flow. Their remaining orphaned API/deletion code was removed in the same change.

| Table | Rows before cleanup | Code cleanup |
|---|---:|---|
| `activity_logs` | 0 | Removed account-deletion cleanup reference; no app writer or reader existed. |
| `course_assignments` | 0 | Removed unused database API actions and table-creation helper. |
| `course_announcements` | 0 | Removed unused database API actions and table-creation helper. |
| `learning_events` | 0 | No first-party runtime references existed. |
| `video_watch_sessions` | 0 | Superseded by typed records in `lesson_progress`; no first-party runtime references existed. |

`assets/js/web.js` contains a separate browser-local assignment UI. It does not call the removed database actions and was left intact.

The orphaned `essay_submissions` table-creation helper and the uncalled `check_storage_config` debug API action were also removed. Video/PDF upload and save actions remain.

The five dropped relations used about 88 KiB combined according to `pg_total_relation_size` before removal. Actual storage savings can vary as PostgreSQL reclaims space.

The `course_teachers.assigned_at` column was also removed: all four existing values matched `created_at`, no application code referenced it, and its only database dependency was its default value.

## Retained

- `exam_logs` has three historical rows. Current exam logic uses `quiz_attempt_sessions`; the old table was retained to preserve its existing records.
- Other empty tables with active reads/writes remain; being empty alone does not make a table unused.
- Other populated columns without direct source-code references were retained unless their values were proven redundant. A source-code search alone cannot rule out SQL consumers, reports, or integrations outside this repository.

## Frontend cleanup

- Removed progress `console.log` payload/response diagnostics and a successful-save `console.info`.
- Kept `console.error`/`console.warn` calls for actual request and storage failures.
- Removed `assets/css/regis.css`, which had no references in project source files, and added `.DS_Store` to `.gitignore`.
- No user uploads, lesson media, registration files, or external library files were deleted.
