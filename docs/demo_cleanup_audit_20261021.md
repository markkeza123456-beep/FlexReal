# Demo cleanup — completed 2026-10-21

Cleanup was performed on branch `cleanup-for-demo`, after a live schema/data audit and approval of the destructive scope. No `.env` files or secrets were read into reports or changed.

## Backup and recovery

- Logical backup: `D:\Xampp\backups\FlexReal\flexreal_public_pre_demo_cleanup_20261021.json.gz` (outside the repository).
- The compressed JSON was decompressed and parsed successfully before cleanup. It contains the pre-cleanup public schema and row values: 30 tables, 279 columns, 1,152 rows, 19 sequences, and 1 routine.
- Migration: [20261021_demo_cleanup_non_staff_and_certificates.sql](../migrations/20261021_demo_cleanup_non_staff_and_certificates.sql).
- Rollback migration: [rollback_20261021_demo_cleanup_non_staff_and_certificates.sql](../migrations/rollback_20261021_demo_cleanup_non_staff_and_certificates.sql). For full recovery, run that migration and then [restore_demo_cleanup_backup.php](../tools/restore_demo_cleanup_backup.php) from CLI with `--restore-confirmed-pre-demo-state`. The restore tool checks for the untouched 46-row post-cleanup state before replacing public rows, then verifies every restored table count. Do not use it after adding new demo data.

## Approved removals

| Type | Removed | Evidence / scope |
|---|---|---|
| User data | 18 non-staff accounts and their profiles, addresses, password reset tokens, learning activity, enrollment, quiz, transcript, graduation and transfer data | User explicitly confirmed to retain only staff. The one staff account remains. |
| Table and feature | `course_certificates`, its 2 stored records, certificate issuance/download endpoints, PDF helper, guide/menu and certificate-only styles | User explicitly approved removing the system and both records. |
| Page and script | `teacher_video_editor.php`, `assets/js/teacher_video_editor.js` | No inbound link; duplicate video management remained available from `teacherdash.php`. |
| Helper | `db_connect.php` | No in-project import/reference; thin wrapper around `config/db.php`. |
| Stylesheet | `assets/css/regis.css` | No in-project import/reference. |

For the preserved course catalog, creator/editor IDs pointing to removed users were set to `NULL`; course rows and lessons remain. The transfer request and teacher review pages, `download_course_lesson.php`, and auth helpers remain in the codebase as requested or because they are part of auth/feature flow.

## Database after cleanup

Live `public` schema now has **29 tables, 273 columns, and 46 rows**. There is exactly one user with role `staff`; `students`, `teachers`, and `parents` profiles and all student-specific activity are empty. Courses, curriculum, lessons, lesson resources, and questions remain for staff to manage and for new users to use after registration.

| Table | Rows | Table | Rows |
|---|---:|---|---:|
| `activity_logs` | 0 | `course_announcements` | 0 |
| `course_assignments` | 0 | `course_teachers` | 0 |
| `courses` | 4 | `curricula` | 1 |
| `curriculum_courses` | 4 | `exam_logs` | 0 |
| `graduation_requests` | 0 | `learning_events` | 0 |
| `lesson_progress` | 0 | `lesson_resources` | 15 |
| `lessons` | 6 | `parents` | 0 |
| `password_reset_tokens` | 0 | `questions` | 14 |
| `quiz_attempt_answers` | 0 | `quiz_attempts` | 0 |
| `staff` | 1 | `student_courses` | 0 |
| `student_curricula` | 0 | `students` | 0 |
| `teachers` | 0 | `transcript_request_lessons` | 0 |
| `transcript_requests` | 0 | `transfer_permissions` | 0 |
| `user_addresses` | 0 | `users` | 1 |
| `video_watch_sessions` | 0 |  |  |

The cleanup migration removed the 6 columns that belonged to the dropped certificate table. No other columns were dropped during this cleanup. Three empty tables (`activity_logs`, `course_announcements`, `course_assignments`) remain because they are used by existing flows or have auth/FK cleanup duties.

## Validation

- Database migration completed in a transaction; the expected user and certificate counts were checked before applying it.
- Post-migration FK audit checked all 48 remaining constraints and found **0 orphan rows**.
- Local HTTP smoke checks: home and login pages returned 200; registration endpoint rejected an empty POST with 422; OTP page and staff dashboard redirected when unauthenticated; protected teacher-credit-transfer and lesson-download endpoints returned 401 rather than 404; removed certificate endpoints returned 404.
- Login with the retained staff demo account succeeded. All eight staff navigation destinations (dashboard, curriculum, courses, user management, staff add, graduation, transfer permissions, and transcript review) returned 200 with non-empty content in the authenticated session. Credentials were not printed in this report.
- Static local-target sweep checked 152 PHP/HTML link, source, and form targets; it found no missing files after accounting for the `action=submit` API parameter. `index.html` is an existing redirect to `index.php`.
- `git diff --check` passed. PHP syntax checks passed for all 86 first-party PHP files. Certificate-specific routes are removed.

## Limitations

- A successful new registration was not exercised because registration sends a real OTP email and the cleanup requirement is to leave only the staff account. The empty registration request was rejected before creating a user.
- Credit-transfer pages remain, but associated data was removed with the non-staff accounts; those workflows need newly registered demo users before presenting them.
- `web.html` remains as a redirect that may be used by bookmarks. `download_course_lesson.php` remains available for protected direct links.
- The complete pre-cleanup column inventory is an archival baseline at [database_column_audit_20261021.md](database_column_audit_20261021.md); its original row counts and references predate this final cleanup.
