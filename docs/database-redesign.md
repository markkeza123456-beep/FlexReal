# Database redesign for Learning Hub

This is the target data model for the current learning website. The supplied
SQL is a destructive clean rebuild: it deletes the existing `public` schema
and all of its data, then creates this model from scratch. It does not back up
or migrate any legacy row.

## Decisions

| Current data | Target | Decision |
| --- | --- | --- |
| `"User"` plus passwords in `student`, `teachers`, `parents`, `executive`, `officer` | `users` | Keep one authentication record and one password hash only. |
| `student`, `teachers`, `parents`, `staff` | `students`, `teachers`, `parents`, `staff` | Keep as role-specific profiles; their ID is the matching `users.user_id`. |
| `addresses` plus address text in profile tables | `user_addresses` | Keep one address table. Migrate profile address text to `address_line` if it is meaningful. |
| `subjects` | `courses` | Rename; a course is what the website calls a subject/course. |
| `subject_teachers` plus `subjects.teachers_id` | `course_teachers` | Keep only the many-to-many table. |
| `curriculums` / `curriculums_subject` | `curricula` / `curriculum_courses` | Rename and keep. |
| `student.studcurriculums_id` plus `registrations` | `student_curricula` | Keep one relationship with status and dates. |
| `student_subject`, `course_enrollments`, `student_subject_enrollment_logs` | `student_courses` | Keep one enrollment table; `enrolled_at` belongs here. |
| lesson media columns plus `videos` | `lesson_resources` | Lessons have content; files/videos are resources and may be more than one. |
| `quiz_questions` plus `test_questions` | `questions` | Merge into one question bank linked to a real lesson ID. |
| `test`, `test_answers`, `essay_submissions` | `quiz_attempts`, `quiz_attempt_answers` | An attempt has answers. An answer can be multiple choice or essay and can be reviewed. |
| `student_learning_progress`, `student_learning_activity_logs` | `lesson_progress`, `learning_events` | Use `lesson_id`, not `lesson_index` or copied lesson title. |
| `learning_records` | `learning_events` | Migrate if it holds useful historical events; otherwise retire. |
| `password_reset_tokens`, `activity_logs`, `provinces` | same logical tables | Keep, but use consistent names and timestamps. |

## Tables removed by the rebuild

Every old table is removed, including `"User"`, `student`, `teachers`,
`subjects`, `lessons`, `test`, `quiz_questions`, `test_questions`, all
enrollment/progress tables, and the currently-unused `certificates`,
`credit_transfers`, `executive`, `officer`, `institutions`, `learning_records`,
`registrations`, and `teacher_curriculums`. The new model contains only the
tables defined in `database-v2.sql`.

## Important corrections

- Store only `password_hash` in `users`; never duplicate plaintext passwords
  in profile tables.
- Use `timestamptz` for every date-time value and default it to `now()`.
- Add a foreign key for every `*_id` that represents a relationship.  The
  current learning-progress and essay tables have no database foreign keys.
- `lesson_index` and `lesson_title` in progress are unstable copies. Replace
  them with `lesson_id`.
- Do not store both `test.answers` JSON and `test_answers` rows. Keep answer
  rows only.
- Do not store `course_name` in a quiz attempt. Derive the course through the
  lesson/question relationship.
- Prefer generated `bigint` IDs for new rows. Existing string IDs can be kept
  during the transition as `legacy_id` or used as the initial IDs.

## Rebuild order

1. Run `database-v2.sql` in Supabase SQL Editor.
2. All old tables and records are removed with the old `public` schema.
3. The new tables are created in `public` with the names in this document.
4. Update the PHP queries before using the website: it currently reads the
   legacy table/column names and will not work against this new model yet.

## Naming rules

- Tables are plural, snake_case: `student_courses`, not `Student_Subject`.
- IDs are singular: `course_id`, `lesson_id`, `user_id`.
- Use `created_at`, `updated_at`, `deleted_at` consistently.
- Use `_at` for date-times and `_on` for dates.
- A table linking two entities uses both names, e.g. `course_teachers`.
