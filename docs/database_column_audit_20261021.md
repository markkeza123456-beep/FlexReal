# Database column audit — 2026-10-21

Current inventory of the connected PostgreSQL `public` schema after approved cleanup: 30 tables and 279 columns. Row counts and NULL/blank percentages were measured against the live schema; no row values were exported.

Usage evidence searches literal whole-word column names in first-party PHP/JS/HTML/CSS/SQL files. A filename hit is a code reference candidate, not proof it belongs to this table when multiple tables share a column name. No literal hit does not prove dynamic SQL or external consumers do not use a field.

| Table | Column | Type | Rows | NULL/blank | Source references |
|---|---|---|---:|---:|---|
| `activity_logs` | `log_id` | `bigint` | 0 | n/a (empty table) | — no literal hit — |
| `activity_logs` | `actor_user_id` | `character varying` | 0 | n/a (empty table) | api_staff.php |
| `activity_logs` | `entity_type` | `character varying` | 0 | n/a (empty table) | — no literal hit — |
| `activity_logs` | `entity_id` | `character varying` | 0 | n/a (empty table) | — no literal hit — |
| `activity_logs` | `action` | `character varying` | 0 | n/a (empty table) | api_courses.php, api_quiz.php, api_staff.php, assets/css/regis.css, +37 more |
| `activity_logs` | `detail` | `jsonb` | 0 | n/a (empty table) | assets/css/officer-dashboard.css, assets/css/parent_dashboard.css, assets/css/regis.css, assets/css/staffdash.css, +16 more |
| `activity_logs` | `created_at` | `timestamp with time zone` | 0 | n/a (empty table) | api_staff.php, assets/js/staffdash.js, assets/js/web.js, includes/transcript_review_data.php, +7 more |
| `activity_logs` | `updated_at` | `timestamp with time zone` | 0 | n/a (empty table) | api_staff.php, assets/js/staffdash.js, course_certificate_lib.php, learning_progress_lib.php, +10 more |
| `course_announcements` | `announcement_id` | `bigint` | 0 | n/a (empty table) | teacher_api.php |
| `course_announcements` | `course_id` | `bigint` | 0 | n/a (empty table) | api_courses.php, api_quiz_progress.php, api_quiz.php, api_staff.php, +33 more |
| `course_announcements` | `teacher_id` | `character varying` | 0 | n/a (empty table) | api_courses.php, api_staff.php, assign_permission.php, get_student_detail.php, +5 more |
| `course_announcements` | `title` | `character varying` | 0 | n/a (empty table) | api_courses.php, api_quiz.php, api_staff.php, assets/css/login.css, +58 more |
| `course_announcements` | `body` | `text` | 0 | n/a (empty table) | assets/css/credit_transfer.css, assets/css/login.css, assets/css/officer-dashboard.css, assets/css/parent_dashboard.css, +62 more |
| `course_announcements` | `created_at` | `timestamp with time zone` | 0 | n/a (empty table) | api_staff.php, assets/js/staffdash.js, assets/js/web.js, includes/transcript_review_data.php, +7 more |
| `course_announcements` | `updated_at` | `timestamp with time zone` | 0 | n/a (empty table) | api_staff.php, assets/js/staffdash.js, course_certificate_lib.php, learning_progress_lib.php, +10 more |
| `course_assignments` | `assignment_id` | `bigint` | 0 | n/a (empty table) | assets/js/web.js, teacher_api.php |
| `course_assignments` | `course_id` | `bigint` | 0 | n/a (empty table) | api_courses.php, api_quiz_progress.php, api_quiz.php, api_staff.php, +33 more |
| `course_assignments` | `teacher_id` | `character varying` | 0 | n/a (empty table) | api_courses.php, api_staff.php, assign_permission.php, get_student_detail.php, +5 more |
| `course_assignments` | `title` | `character varying` | 0 | n/a (empty table) | api_courses.php, api_quiz.php, api_staff.php, assets/css/login.css, +58 more |
| `course_assignments` | `description` | `text` | 0 | n/a (empty table) | api_courses.php, api_staff.php, assets/css/student_dashboard.css, assets/css/theme.css, +11 more |
| `course_assignments` | `due_at` | `timestamp with time zone` | 0 | n/a (empty table) | teacher_api.php |
| `course_assignments` | `max_score` | `numeric` | 0 | n/a (empty table) | api_quiz.php, assets/js/parent_dashboard.js, assets/js/teacherdash.js, assets/js/test.js, +8 more |
| `course_assignments` | `created_at` | `timestamp with time zone` | 0 | n/a (empty table) | api_staff.php, assets/js/staffdash.js, assets/js/web.js, includes/transcript_review_data.php, +7 more |
| `course_assignments` | `updated_at` | `timestamp with time zone` | 0 | n/a (empty table) | api_staff.php, assets/js/staffdash.js, course_certificate_lib.php, learning_progress_lib.php, +10 more |
| `course_certificates` | `certificate_id` | `bigint` | 2 | 0.00% | course_certificate_api.php, download_course_certificate.php |
| `course_certificates` | `student_id` | `character varying` | 2 | 0.00% | api_courses.php, api_quiz_progress.php, api_staff.php, api/upload_transcript.php, +34 more |
| `course_certificates` | `course_id` | `bigint` | 2 | 0.00% | api_courses.php, api_quiz_progress.php, api_quiz.php, api_staff.php, +33 more |
| `course_certificates` | `certificate_number` | `character varying` | 2 | 0.00% | course_certificate_api.php, download_course_certificate.php |
| `course_certificates` | `issued_at` | `timestamp with time zone` | 2 | 0.00% | course_certificate_api.php |
| `course_certificates` | `document_pdf` | `bytea` | 2 | 0.00% | course_certificate_api.php, download_course_certificate.php |
| `course_teachers` | `course_id` | `bigint` | 5 | 0.00% | api_courses.php, api_quiz_progress.php, api_quiz.php, api_staff.php, +33 more |
| `course_teachers` | `teacher_id` | `character varying` | 5 | 0.00% | api_courses.php, api_staff.php, assign_permission.php, get_student_detail.php, +5 more |
| `course_teachers` | `assigned_at` | `timestamp with time zone` | 5 | 0.00% | — no literal hit — |
| `course_teachers` | `created_at` | `timestamp with time zone` | 5 | 0.00% | api_staff.php, assets/js/staffdash.js, assets/js/web.js, includes/transcript_review_data.php, +7 more |
| `course_teachers` | `updated_at` | `timestamp with time zone` | 5 | 0.00% | api_staff.php, assets/js/staffdash.js, course_certificate_lib.php, learning_progress_lib.php, +10 more |
| `courses` | `course_id` | `bigint` | 4 | 0.00% | api_courses.php, api_quiz_progress.php, api_quiz.php, api_staff.php, +33 more |
| `courses` | `code` | `character varying` | 4 | 0.00% | api_courses.php, api_staff.php, assets/css/officer-dashboard.css, assets/css/staffdash.css, +15 more |
| `courses` | `name` | `character varying` | 4 | 0.00% | api_courses.php, api_staff.php, api/upload_transcript.php, assets/css/officer-dashboard.css, +69 more |
| `courses` | `description` | `text` | 4 | 75.00% | api_courses.php, api_staff.php, assets/css/student_dashboard.css, assets/css/theme.css, +11 more |
| `courses` | `credits` | `numeric` | 4 | 0.00% | api_courses.php, api_staff.php, assets/css/student_dashboard.css, assets/js/staffdash.js, +6 more |
| `courses` | `status` | `character varying` | 4 | 0.00% | account_status_lib.php, api_courses.php, api_quiz_progress.php, api_quiz.php, +77 more |
| `courses` | `created_by` | `character varying` | 4 | 0.00% | api_staff.php, teacher_api.php, update_staff_profile.php |
| `courses` | `created_at` | `timestamp with time zone` | 4 | 0.00% | api_staff.php, assets/js/staffdash.js, assets/js/web.js, includes/transcript_review_data.php, +7 more |
| `courses` | `updated_at` | `timestamp with time zone` | 4 | 0.00% | api_staff.php, assets/js/staffdash.js, course_certificate_lib.php, learning_progress_lib.php, +10 more |
| `courses` | `subject_type` | `character varying` | 4 | 0.00% | api_courses.php, api_staff.php, assets/js/parent_dashboard.js, assets/js/staffdash.js, +7 more |
| `courses` | `image_path` | `text` | 4 | 0.00% | api_courses.php, api_staff.php, assets/js/staffdash.js, assets/js/web.js, +1 more |
| `courses` | `updated_by` | `character varying` | 4 | 0.00% | api_staff.php, reset_password_action.php, teacher_api.php, teacherdash.php, +5 more |
| `curricula` | `curriculum_id` | `bigint` | 1 | 0.00% | api_courses.php, api_staff.php, assets/js/staffdash.js, curriculum_subjects_lib.php, +6 more |
| `curricula` | `code` | `character varying` | 1 | 0.00% | api_courses.php, api_staff.php, assets/css/officer-dashboard.css, assets/css/staffdash.css, +15 more |
| `curricula` | `name` | `character varying` | 1 | 0.00% | api_courses.php, api_staff.php, api/upload_transcript.php, assets/css/officer-dashboard.css, +69 more |
| `curricula` | `level` | `character varying` | 1 | 0.00% | api_staff.php, assets/js/parent_dashboard.js, assets/js/regisstu.js, assets/js/staffdash.js, +5 more |
| `curricula` | `total_credits` | `numeric` | 1 | 100.00% | assets/js/staffdash.js, graduation_tracker_api.php, staff_graduation_api.php |
| `curricula` | `status` | `character varying` | 1 | 0.00% | account_status_lib.php, api_courses.php, api_quiz_progress.php, api_quiz.php, +77 more |
| `curricula` | `created_at` | `timestamp with time zone` | 1 | 0.00% | api_staff.php, assets/js/staffdash.js, assets/js/web.js, includes/transcript_review_data.php, +7 more |
| `curricula` | `updated_at` | `timestamp with time zone` | 1 | 0.00% | api_staff.php, assets/js/staffdash.js, course_certificate_lib.php, learning_progress_lib.php, +10 more |
| `curricula` | `created_by` | `character varying` | 1 | 100.00% | api_staff.php, teacher_api.php, update_staff_profile.php |
| `curricula` | `updated_by` | `character varying` | 1 | 0.00% | api_staff.php, reset_password_action.php, teacher_api.php, teacherdash.php, +5 more |
| `curriculum_courses` | `curriculum_id` | `bigint` | 4 | 0.00% | api_courses.php, api_staff.php, assets/js/staffdash.js, curriculum_subjects_lib.php, +6 more |
| `curriculum_courses` | `course_id` | `bigint` | 4 | 0.00% | api_courses.php, api_quiz_progress.php, api_quiz.php, api_staff.php, +33 more |
| `curriculum_courses` | `requirement_type` | `character varying` | 4 | 0.00% | api_courses.php, api_staff.php, assets/js/staffdash.js, curriculum_subjects_lib.php, +5 more |
| `curriculum_courses` | `created_at` | `timestamp with time zone` | 4 | 0.00% | api_staff.php, assets/js/staffdash.js, assets/js/web.js, includes/transcript_review_data.php, +7 more |
| `curriculum_courses` | `updated_at` | `timestamp with time zone` | 4 | 0.00% | api_staff.php, assets/js/staffdash.js, course_certificate_lib.php, learning_progress_lib.php, +10 more |
| `exam_logs` | `exam_log_id` | `bigint` | 2 | 0.00% | — no literal hit — |
| `exam_logs` | `student_id` | `character varying` | 2 | 0.00% | api_courses.php, api_quiz_progress.php, api_staff.php, api/upload_transcript.php, +34 more |
| `exam_logs` | `exam_id` | `bigint` | 2 | 0.00% | log_exam_tab_switch.php, test_submit.php |
| `exam_logs` | `tab_switch_count` | `integer` | 2 | 0.00% | assets/js/test.js, log_exam_tab_switch.php, test_submit.php |
| `exam_logs` | `created_at` | `timestamp with time zone` | 2 | 0.00% | api_staff.php, assets/js/staffdash.js, assets/js/web.js, includes/transcript_review_data.php, +7 more |
| `exam_logs` | `updated_at` | `timestamp with time zone` | 2 | 0.00% | api_staff.php, assets/js/staffdash.js, course_certificate_lib.php, learning_progress_lib.php, +10 more |
| `exam_logs` | `attempt_number` | `integer` | 2 | 0.00% | course_certificate_lib.php, get_student_detail.php, get_student_quiz_answers.php, graduation_document_lib.php, +9 more |
| `graduation_requests` | `request_id` | `bigint` | 2 | 0.00% | api_staff.php, assets/js/staffdash.js, assets/js/student_dashboard.js, download_graduation_document.php, +11 more |
| `graduation_requests` | `student_id` | `character varying` | 2 | 0.00% | api_courses.php, api_quiz_progress.php, api_staff.php, api/upload_transcript.php, +34 more |
| `graduation_requests` | `total_credits` | `numeric` | 2 | 0.00% | assets/js/staffdash.js, graduation_tracker_api.php, staff_graduation_api.php |
| `graduation_requests` | `status` | `character varying` | 2 | 0.00% | account_status_lib.php, api_courses.php, api_quiz_progress.php, api_quiz.php, +77 more |
| `graduation_requests` | `approved_by` | `character varying` | 2 | 0.00% | api_staff.php, includes/transcript_review_data.php, process_transcript.php, staff_graduation_api.php |
| `graduation_requests` | `submitted_at` | `timestamp with time zone` | 2 | 0.00% | api_quiz_progress.php, assets/js/parent_dashboard.js, assets/js/staffdash.js, assets/js/teacherdash.js, +19 more |
| `graduation_requests` | `reviewed_at` | `timestamp with time zone` | 2 | 0.00% | assets/js/teacherdash.js, graduation_tracker_api.php, includes/transcript_review_data.php, includes/transcript_upload_data.php, +6 more |
| `graduation_requests` | `reviewer_note` | `text` | 2 | 100.00% | includes/transcript_review_data.php, includes/transcript_upload_data.php, process_transcript.php, reject_transcript.php, +2 more |
| `graduation_requests` | `document_bucket` | `text` | 2 | 0.00% | download_graduation_document.php, graduation_tracker_api.php, staff_graduation_api.php |
| `graduation_requests` | `document_object_path` | `text` | 2 | 0.00% | download_graduation_document.php, graduation_tracker_api.php, staff_graduation_api.php |
| `graduation_requests` | `created_at` | `timestamp with time zone` | 2 | 0.00% | api_staff.php, assets/js/staffdash.js, assets/js/web.js, includes/transcript_review_data.php, +7 more |
| `graduation_requests` | `updated_at` | `timestamp with time zone` | 2 | 0.00% | api_staff.php, assets/js/staffdash.js, course_certificate_lib.php, learning_progress_lib.php, +10 more |
| `learning_events` | `event_id` | `bigint` | 837 | 0.00% | learning_progress_lib.php |
| `learning_events` | `student_id` | `character varying` | 837 | 0.00% | api_courses.php, api_quiz_progress.php, api_staff.php, api/upload_transcript.php, +34 more |
| `learning_events` | `lesson_id` | `bigint` | 837 | 0.00% | api_courses.php, api_quiz_progress.php, api_quiz.php, api_staff.php, +27 more |
| `learning_events` | `event_type` | `character varying` | 837 | 0.00% | learning_progress_lib.php |
| `learning_events` | `detail` | `jsonb` | 837 | 0.00% | assets/css/officer-dashboard.css, assets/css/parent_dashboard.css, assets/css/regis.css, assets/css/staffdash.css, +16 more |
| `learning_events` | `created_at` | `timestamp with time zone` | 837 | 0.00% | api_staff.php, assets/js/staffdash.js, assets/js/web.js, includes/transcript_review_data.php, +7 more |
| `learning_events` | `updated_at` | `timestamp with time zone` | 837 | 0.00% | api_staff.php, assets/js/staffdash.js, course_certificate_lib.php, learning_progress_lib.php, +10 more |
| `lesson_progress` | `student_id` | `character varying` | 17 | 0.00% | api_courses.php, api_quiz_progress.php, api_staff.php, api/upload_transcript.php, +34 more |
| `lesson_progress` | `lesson_id` | `bigint` | 17 | 0.00% | api_courses.php, api_quiz_progress.php, api_quiz.php, api_staff.php, +27 more |
| `lesson_progress` | `opened_count` | `integer` | 17 | 0.00% | learning_progress_lib.php, officer-dashboard.php, student_learning_api.php |
| `lesson_progress` | `video_open_count` | `integer` | 17 | 0.00% | learning_progress_lib.php, officer-dashboard.php, student_learning_api.php |
| `lesson_progress` | `first_opened_at` | `timestamp with time zone` | 17 | 11.76% | learning_progress_lib.php |
| `lesson_progress` | `last_opened_at` | `timestamp with time zone` | 17 | 11.76% | learning_progress_lib.php |
| `lesson_progress` | `last_activity_at` | `timestamp with time zone` | 17 | 0.00% | assets/js/parent_dashboard.js, learning_progress_lib.php, officer-dashboard.php, parent_dashboard_api.php, +3 more |
| `lesson_progress` | `document_progress_percent` | `numeric` | 17 | 0.00% | assets/js/web.js, learning_progress_lib.php, parent_dashboard_api.php, student_dashboard_api.php, +2 more |
| `lesson_progress` | `document_page_index` | `integer` | 17 | 0.00% | learning_progress_lib.php, student_learning_api.php, view_course_lesson.php |
| `lesson_progress` | `video_progress_percent` | `numeric` | 17 | 0.00% | assets/js/web.js, learning_progress_lib.php, parent_dashboard_api.php, student_dashboard_api.php, +1 more |
| `lesson_progress` | `video_position_seconds` | `numeric` | 17 | 0.00% | assets/js/web.js, learning_progress_lib.php, student_learning_api.php |
| `lesson_progress` | `is_transferred` | `boolean` | 17 | 0.00% | api_quiz_progress.php, assets/js/student_dashboard.js, course_certificate_lib.php, graduation_document_lib.php, +8 more |
| `lesson_progress` | `transfer_request_id` | `bigint` | 17 | 52.94% | process_transcript.php |
| `lesson_progress` | `transferred_by` | `character varying` | 17 | 52.94% | api_staff.php, process_transcript.php |
| `lesson_progress` | `transferred_at` | `timestamp with time zone` | 17 | 52.94% | process_transcript.php |
| `lesson_progress` | `grade` | `character varying` | 17 | 58.82% | assets/css/parent_dashboard.css, assets/css/regis.css, assets/css/responsive.css, assets/css/staffdash.css, +12 more |
| `lesson_progress` | `created_at` | `timestamp with time zone` | 17 | 0.00% | api_staff.php, assets/js/staffdash.js, assets/js/web.js, includes/transcript_review_data.php, +7 more |
| `lesson_progress` | `updated_at` | `timestamp with time zone` | 17 | 0.00% | api_staff.php, assets/js/staffdash.js, course_certificate_lib.php, learning_progress_lib.php, +10 more |
| `lesson_resources` | `resource_id` | `bigint` | 15 | 0.00% | api_courses.php, api_staff.php, index.php, teacher_api.php, +2 more |
| `lesson_resources` | `lesson_id` | `bigint` | 15 | 0.00% | api_courses.php, api_quiz_progress.php, api_quiz.php, api_staff.php, +27 more |
| `lesson_resources` | `resource_type` | `character varying` | 15 | 0.00% | api_courses.php, api_staff.php, download_course_lesson.php, index.php, +3 more |
| `lesson_resources` | `title` | `character varying` | 15 | 0.00% | api_courses.php, api_quiz.php, api_staff.php, assets/css/login.css, +58 more |
| `lesson_resources` | `url` | `text` | 15 | 0.00% | api_courses.php, api_staff.php, assets/css/staff_subject_editor.css, assets/css/staffdash.css, +28 more |
| `lesson_resources` | `duration_seconds` | `integer` | 15 | 100.00% | api_staff.php, assets/js/staff_video_editor.js, assets/js/web.js, learning_progress_lib.php, +2 more |
| `lesson_resources` | `position` | `integer` | 15 | 0.00% | api_courses.php, api_quiz_progress.php, api_quiz.php, api_staff.php, +33 more |
| `lesson_resources` | `created_at` | `timestamp with time zone` | 15 | 0.00% | api_staff.php, assets/js/staffdash.js, assets/js/web.js, includes/transcript_review_data.php, +7 more |
| `lesson_resources` | `updated_at` | `timestamp with time zone` | 15 | 20.00% | api_staff.php, assets/js/staffdash.js, course_certificate_lib.php, learning_progress_lib.php, +10 more |
| `lesson_resources` | `updated_by` | `character varying` | 15 | 60.00% | api_staff.php, reset_password_action.php, teacher_api.php, teacherdash.php, +5 more |
| `lessons` | `lesson_id` | `bigint` | 6 | 0.00% | api_courses.php, api_quiz_progress.php, api_quiz.php, api_staff.php, +27 more |
| `lessons` | `course_id` | `bigint` | 6 | 0.00% | api_courses.php, api_quiz_progress.php, api_quiz.php, api_staff.php, +33 more |
| `lessons` | `title` | `character varying` | 6 | 0.00% | api_courses.php, api_quiz.php, api_staff.php, assets/css/login.css, +58 more |
| `lessons` | `content` | `text` | 6 | 100.00% | api_courses.php, api_quiz_progress.php, api_quiz.php, api_staff.php, +86 more |
| `lessons` | `position` | `integer` | 6 | 0.00% | api_courses.php, api_quiz_progress.php, api_quiz.php, api_staff.php, +33 more |
| `lessons` | `study_hours` | `numeric` | 6 | 0.00% | api_courses.php, api_quiz.php, teacher_api.php, teacherdash.php |
| `lessons` | `pass_percentage` | `numeric` | 6 | 100.00% | api_courses.php |
| `lessons` | `created_at` | `timestamp with time zone` | 6 | 0.00% | api_staff.php, assets/js/staffdash.js, assets/js/web.js, includes/transcript_review_data.php, +7 more |
| `lessons` | `updated_at` | `timestamp with time zone` | 6 | 0.00% | api_staff.php, assets/js/staffdash.js, course_certificate_lib.php, learning_progress_lib.php, +10 more |
| `lessons` | `updated_by` | `character varying` | 6 | 66.67% | api_staff.php, reset_password_action.php, teacher_api.php, teacherdash.php, +5 more |
| `lessons` | `created_by` | `character varying` | 6 | 100.00% | api_staff.php, teacher_api.php, update_staff_profile.php |
| `parents` | `user_id` | `character varying` | 3 | 0.00% | api_courses.php, api_quiz_progress.php, api_staff.php, api/upload_transcript.php, +55 more |
| `parents` | `full_name` | `character varying` | 3 | 0.00% | api_courses.php, api_staff.php, assets/js/web.js, course_certificate_api.php, +22 more |
| `parents` | `email` | `character varying` | 3 | 0.00% | api_courses.php, api_staff.php, assets/js/login.js, assets/js/parent_dashboard.js, +25 more |
| `parents` | `phone` | `character varying` | 3 | 0.00% | api_courses.php, api_staff.php, assets/js/parent_dashboard.js, assets/js/regisstu.js, +12 more |
| `parents` | `created_at` | `timestamp with time zone` | 3 | 0.00% | api_staff.php, assets/js/staffdash.js, assets/js/web.js, includes/transcript_review_data.php, +7 more |
| `parents` | `updated_at` | `timestamp with time zone` | 3 | 0.00% | api_staff.php, assets/js/staffdash.js, course_certificate_lib.php, learning_progress_lib.php, +10 more |
| `parents` | `avatar_url` | `text` | 3 | 66.67% | api_courses.php, assets/js/parent_dashboard.js, assets/js/student_dashboard.js, assets/js/web.js, +8 more |
| `password_reset_tokens` | `token_id` | `bigint` | 11 | 0.00% | reset_password_action.php, verify_pin_action.php |
| `password_reset_tokens` | `user_id` | `character varying` | 11 | 0.00% | api_courses.php, api_quiz_progress.php, api_staff.php, api/upload_transcript.php, +55 more |
| `password_reset_tokens` | `token_hash` | `text` | 11 | 0.00% | forgot_password_action.php, verify_pin_action.php |
| `password_reset_tokens` | `expires_at` | `timestamp with time zone` | 11 | 0.00% | forgot_password_action.php, regisstu_action.php, registration_otp_action.php, reset_password_action.php, +1 more |
| `password_reset_tokens` | `used_at` | `timestamp with time zone` | 11 | 18.18% | forgot_password_action.php, reset_password_action.php, verify_pin_action.php |
| `password_reset_tokens` | `created_at` | `timestamp with time zone` | 11 | 0.00% | api_staff.php, assets/js/staffdash.js, assets/js/web.js, includes/transcript_review_data.php, +7 more |
| `password_reset_tokens` | `updated_at` | `timestamp with time zone` | 11 | 0.00% | api_staff.php, assets/js/staffdash.js, course_certificate_lib.php, learning_progress_lib.php, +10 more |
| `questions` | `question_id` | `bigint` | 14 | 0.00% | api_courses.php, api_quiz.php, assets/js/teacherdash.js, course_certificate_lib.php, +9 more |
| `questions` | `lesson_id` | `bigint` | 14 | 0.00% | api_courses.php, api_quiz_progress.php, api_quiz.php, api_staff.php, +27 more |
| `questions` | `question_type` | `character varying` | 14 | 0.00% | api_quiz.php, assets/js/parent_dashboard.js, course_certificate_lib.php, get_student_quiz_answers.php, +7 more |
| `questions` | `question_text` | `text` | 14 | 0.00% | api_quiz.php, assets/js/parent_dashboard.js, get_student_quiz_answers.php, parent_quiz_details_api.php, +3 more |
| `questions` | `options` | `jsonb` | 14 | 50.00% | api_quiz.php, assets/css/login.css, assets/css/parent_dashboard.css, assets/js/parent_dashboard.js, +17 more |
| `questions` | `correct_choice` | `character` | 14 | 50.00% | api_quiz.php, assets/js/parent_dashboard.js, get_student_quiz_answers.php, parent_quiz_details_api.php, +3 more |
| `questions` | `max_score` | `numeric` | 14 | 0.00% | api_quiz.php, assets/js/parent_dashboard.js, assets/js/teacherdash.js, assets/js/test.js, +8 more |
| `questions` | `is_active` | `boolean` | 14 | 0.00% | api_courses.php, api_quiz.php, assign_permission.php, check_auth.php, +8 more |
| `questions` | `created_at` | `timestamp with time zone` | 14 | 0.00% | api_staff.php, assets/js/staffdash.js, assets/js/web.js, includes/transcript_review_data.php, +7 more |
| `questions` | `updated_at` | `timestamp with time zone` | 14 | 57.14% | api_staff.php, assets/js/staffdash.js, course_certificate_lib.php, learning_progress_lib.php, +10 more |
| `quiz_attempt_answers` | `attempt_answer_id` | `bigint` | 38 | 0.00% | course_certificate_lib.php, teacher_api.php |
| `quiz_attempt_answers` | `attempt_id` | `bigint` | 38 | 0.00% | course_certificate_lib.php, get_student_quiz_answers.php, officer-dashboard.php, parent_dashboard_api.php, +6 more |
| `quiz_attempt_answers` | `question_id` | `bigint` | 38 | 0.00% | api_courses.php, api_quiz.php, assets/js/teacherdash.js, course_certificate_lib.php, +9 more |
| `quiz_attempt_answers` | `selected_choice` | `character` | 38 | 34.21% | assets/js/parent_dashboard.js, get_student_quiz_answers.php, parent_quiz_details_api.php, teacherdash.php, +1 more |
| `quiz_attempt_answers` | `answer_text` | `text` | 38 | 65.79% | api_quiz.php, assets/js/parent_dashboard.js, assets/js/teacherdash.js, assets/js/test.js, +6 more |
| `quiz_attempt_answers` | `score` | `numeric` | 38 | 13.16% | api_quiz_progress.php, assets/css/parent_dashboard.css, assets/css/student_dashboard.css, assets/css/teacherdash.css, +24 more |
| `quiz_attempt_answers` | `review_status` | `character varying` | 38 | 0.00% | assets/js/parent_dashboard.js, assets/js/teacherdash.js, course_certificate_lib.php, get_student_quiz_answers.php, +7 more |
| `quiz_attempt_answers` | `reviewer_id` | `character varying` | 38 | 78.95% | api_staff.php, assets/js/teacherdash.js, process_transcript.php, reject_transcript.php, +2 more |
| `quiz_attempt_answers` | `reviewer_comment` | `text` | 38 | 100.00% | student_dashboard_api.php, teacher_api.php |
| `quiz_attempt_answers` | `reviewed_at` | `timestamp with time zone` | 38 | 78.95% | assets/js/teacherdash.js, graduation_tracker_api.php, includes/transcript_review_data.php, includes/transcript_upload_data.php, +6 more |
| `quiz_attempt_answers` | `created_at` | `timestamp with time zone` | 38 | 0.00% | api_staff.php, assets/js/staffdash.js, assets/js/web.js, includes/transcript_review_data.php, +7 more |
| `quiz_attempt_answers` | `updated_at` | `timestamp with time zone` | 38 | 0.00% | api_staff.php, assets/js/staffdash.js, course_certificate_lib.php, learning_progress_lib.php, +10 more |
| `quiz_attempt_answers` | `result_status` | `character varying` | 38 | 78.95% | assets/js/parent_dashboard.js, assets/js/teacherdash.js, get_student_quiz_answers.php, parent_dashboard_api.php, +4 more |
| `quiz_attempts` | `attempt_id` | `bigint` | 24 | 0.00% | course_certificate_lib.php, get_student_quiz_answers.php, officer-dashboard.php, parent_dashboard_api.php, +6 more |
| `quiz_attempts` | `student_id` | `character varying` | 24 | 0.00% | api_courses.php, api_quiz_progress.php, api_staff.php, api/upload_transcript.php, +34 more |
| `quiz_attempts` | `lesson_id` | `bigint` | 24 | 0.00% | api_courses.php, api_quiz_progress.php, api_quiz.php, api_staff.php, +27 more |
| `quiz_attempts` | `attempt_number` | `integer` | 24 | 0.00% | course_certificate_lib.php, get_student_detail.php, get_student_quiz_answers.php, graduation_document_lib.php, +9 more |
| `quiz_attempts` | `score` | `numeric` | 24 | 0.00% | api_quiz_progress.php, assets/css/parent_dashboard.css, assets/css/student_dashboard.css, assets/css/teacherdash.css, +24 more |
| `quiz_attempts` | `total_score` | `numeric` | 24 | 0.00% | api_quiz_progress.php, assets/js/test.js, course_certificate_lib.php, get_student_detail.php, +11 more |
| `quiz_attempts` | `status` | `character varying` | 24 | 0.00% | account_status_lib.php, api_courses.php, api_quiz_progress.php, api_quiz.php, +77 more |
| `quiz_attempts` | `submitted_at` | `timestamp with time zone` | 24 | 0.00% | api_quiz_progress.php, assets/js/parent_dashboard.js, assets/js/staffdash.js, assets/js/teacherdash.js, +19 more |
| `quiz_attempts` | `created_at` | `timestamp with time zone` | 24 | 0.00% | api_staff.php, assets/js/staffdash.js, assets/js/web.js, includes/transcript_review_data.php, +7 more |
| `quiz_attempts` | `updated_at` | `timestamp with time zone` | 24 | 0.00% | api_staff.php, assets/js/staffdash.js, course_certificate_lib.php, learning_progress_lib.php, +10 more |
| `staff` | `user_id` | `character varying` | 1 | 0.00% | api_courses.php, api_quiz_progress.php, api_staff.php, api/upload_transcript.php, +55 more |
| `staff` | `first_name` | `character varying` | 1 | 0.00% | api_staff.php, assets/js/parent_dashboard.js, assets/js/student_dashboard.js, components/staff_sidebar.php, +9 more |
| `staff` | `last_name` | `character varying` | 1 | 0.00% | api_staff.php, assets/js/parent_dashboard.js, assets/js/student_dashboard.js, components/staff_sidebar.php, +9 more |
| `staff` | `created_at` | `timestamp with time zone` | 1 | 0.00% | api_staff.php, assets/js/staffdash.js, assets/js/web.js, includes/transcript_review_data.php, +7 more |
| `staff` | `updated_at` | `timestamp with time zone` | 1 | 0.00% | api_staff.php, assets/js/staffdash.js, course_certificate_lib.php, learning_progress_lib.php, +10 more |
| `staff` | `email` | `character varying` | 1 | 100.00% | api_courses.php, api_staff.php, assets/js/login.js, assets/js/parent_dashboard.js, +25 more |
| `staff` | `phone` | `character varying` | 1 | 100.00% | api_courses.php, api_staff.php, assets/js/parent_dashboard.js, assets/js/regisstu.js, +12 more |
| `staff` | `avatar_url` | `text` | 1 | 0.00% | api_courses.php, assets/js/parent_dashboard.js, assets/js/student_dashboard.js, assets/js/web.js, +8 more |
| `staff` | `created_by` | `character varying` | 1 | 100.00% | api_staff.php, teacher_api.php, update_staff_profile.php |
| `staff` | `updated_by` | `character varying` | 1 | 0.00% | api_staff.php, reset_password_action.php, teacher_api.php, teacherdash.php, +5 more |
| `student_courses` | `student_id` | `character varying` | 21 | 0.00% | api_courses.php, api_quiz_progress.php, api_staff.php, api/upload_transcript.php, +34 more |
| `student_courses` | `course_id` | `bigint` | 21 | 0.00% | api_courses.php, api_quiz_progress.php, api_quiz.php, api_staff.php, +33 more |
| `student_courses` | `enrolled_at` | `timestamp with time zone` | 21 | 0.00% | api_courses.php, graduation_tracker_api.php, index.php, officer-dashboard.php, +3 more |
| `student_courses` | `completed_at` | `timestamp with time zone` | 21 | 100.00% | course_certificate_lib.php, learning_progress_lib.php |
| `student_courses` | `status` | `character varying` | 21 | 0.00% | account_status_lib.php, api_courses.php, api_quiz_progress.php, api_quiz.php, +77 more |
| `student_courses` | `created_at` | `timestamp with time zone` | 21 | 0.00% | api_staff.php, assets/js/staffdash.js, assets/js/web.js, includes/transcript_review_data.php, +7 more |
| `student_courses` | `updated_at` | `timestamp with time zone` | 21 | 0.00% | api_staff.php, assets/js/staffdash.js, course_certificate_lib.php, learning_progress_lib.php, +10 more |
| `student_curricula` | `student_id` | `character varying` | 7 | 0.00% | api_courses.php, api_quiz_progress.php, api_staff.php, api/upload_transcript.php, +34 more |
| `student_curricula` | `curriculum_id` | `bigint` | 7 | 0.00% | api_courses.php, api_staff.php, assets/js/staffdash.js, curriculum_subjects_lib.php, +6 more |
| `student_curricula` | `enrolled_at` | `timestamp with time zone` | 7 | 0.00% | api_courses.php, graduation_tracker_api.php, index.php, officer-dashboard.php, +3 more |
| `student_curricula` | `completed_at` | `timestamp with time zone` | 7 | 100.00% | course_certificate_lib.php, learning_progress_lib.php |
| `student_curricula` | `status` | `character varying` | 7 | 0.00% | account_status_lib.php, api_courses.php, api_quiz_progress.php, api_quiz.php, +77 more |
| `student_curricula` | `created_at` | `timestamp with time zone` | 7 | 0.00% | api_staff.php, assets/js/staffdash.js, assets/js/web.js, includes/transcript_review_data.php, +7 more |
| `student_curricula` | `updated_at` | `timestamp with time zone` | 7 | 0.00% | api_staff.php, assets/js/staffdash.js, course_certificate_lib.php, learning_progress_lib.php, +10 more |
| `students` | `user_id` | `character varying` | 7 | 0.00% | api_courses.php, api_quiz_progress.php, api_staff.php, api/upload_transcript.php, +55 more |
| `students` | `full_name` | `character varying` | 7 | 0.00% | api_courses.php, api_staff.php, assets/js/web.js, course_certificate_api.php, +22 more |
| `students` | `email` | `character varying` | 7 | 0.00% | api_courses.php, api_staff.php, assets/js/login.js, assets/js/parent_dashboard.js, +25 more |
| `students` | `phone` | `character varying` | 7 | 0.00% | api_courses.php, api_staff.php, assets/js/parent_dashboard.js, assets/js/regisstu.js, +12 more |
| `students` | `student_level` | `character varying` | 7 | 0.00% | assets/js/parent_dashboard.js, login_action.php, officer-dashboard.php, parent_dashboard_api.php, +4 more |
| `students` | `parent_user_id` | `character varying` | 7 | 57.14% | api_staff.php, login_action.php, parent_dashboard_api.php, parent_quiz_details_api.php, +1 more |
| `students` | `advisor_user_id` | `character varying` | 7 | 100.00% | api_staff.php |
| `students` | `avatar_url` | `text` | 7 | 57.14% | api_courses.php, assets/js/parent_dashboard.js, assets/js/student_dashboard.js, assets/js/web.js, +8 more |
| `students` | `created_at` | `timestamp with time zone` | 7 | 0.00% | api_staff.php, assets/js/staffdash.js, assets/js/web.js, includes/transcript_review_data.php, +7 more |
| `students` | `updated_at` | `timestamp with time zone` | 7 | 0.00% | api_staff.php, assets/js/staffdash.js, course_certificate_lib.php, learning_progress_lib.php, +10 more |
| `teachers` | `user_id` | `character varying` | 7 | 0.00% | api_courses.php, api_quiz_progress.php, api_staff.php, api/upload_transcript.php, +55 more |
| `teachers` | `full_name` | `character varying` | 7 | 0.00% | api_courses.php, api_staff.php, assets/js/web.js, course_certificate_api.php, +22 more |
| `teachers` | `email` | `character varying` | 7 | 0.00% | api_courses.php, api_staff.php, assets/js/login.js, assets/js/parent_dashboard.js, +25 more |
| `teachers` | `phone` | `character varying` | 7 | 0.00% | api_courses.php, api_staff.php, assets/js/parent_dashboard.js, assets/js/regisstu.js, +12 more |
| `teachers` | `avatar_url` | `text` | 7 | 71.43% | api_courses.php, assets/js/parent_dashboard.js, assets/js/student_dashboard.js, assets/js/web.js, +8 more |
| `teachers` | `created_at` | `timestamp with time zone` | 7 | 0.00% | api_staff.php, assets/js/staffdash.js, assets/js/web.js, includes/transcript_review_data.php, +7 more |
| `teachers` | `updated_at` | `timestamp with time zone` | 7 | 0.00% | api_staff.php, assets/js/staffdash.js, course_certificate_lib.php, learning_progress_lib.php, +10 more |
| `transcript_request_lessons` | `request_id` | `bigint` | 32 | 0.00% | api_staff.php, assets/js/staffdash.js, assets/js/student_dashboard.js, download_graduation_document.php, +11 more |
| `transcript_request_lessons` | `lesson_id` | `bigint` | 32 | 0.00% | api_courses.php, api_quiz_progress.php, api_quiz.php, api_staff.php, +27 more |
| `transcript_request_lessons` | `is_approved` | `boolean` | 32 | 0.00% | includes/transcript_review_data.php, process_transcript.php, teacher_report.php, teacherdash.php |
| `transcript_request_lessons` | `reviewed_by` | `character varying` | 32 | 0.00% | api_staff.php, includes/transcript_review_data.php, process_transcript.php, reject_transcript.php |
| `transcript_request_lessons` | `reviewed_at` | `timestamp with time zone` | 32 | 0.00% | assets/js/teacherdash.js, graduation_tracker_api.php, includes/transcript_review_data.php, includes/transcript_upload_data.php, +6 more |
| `transcript_request_lessons` | `created_at` | `timestamp with time zone` | 32 | 0.00% | api_staff.php, assets/js/staffdash.js, assets/js/web.js, includes/transcript_review_data.php, +7 more |
| `transcript_request_lessons` | `updated_at` | `timestamp with time zone` | 32 | 0.00% | api_staff.php, assets/js/staffdash.js, course_certificate_lib.php, learning_progress_lib.php, +10 more |
| `transcript_requests` | `request_id` | `bigint` | 22 | 0.00% | api_staff.php, assets/js/staffdash.js, assets/js/student_dashboard.js, download_graduation_document.php, +11 more |
| `transcript_requests` | `student_id` | `character varying` | 22 | 0.00% | api_courses.php, api_quiz_progress.php, api_staff.php, api/upload_transcript.php, +34 more |
| `transcript_requests` | `file_path` | `text` | 22 | 0.00% | api/upload_transcript.php, includes/transcript_review_data.php, reject_transcript.php |
| `transcript_requests` | `original_file_name` | `text` | 22 | 0.00% | api/upload_transcript.php, includes/transcript_review_data.php, reports.php, review_transcript.php |
| `transcript_requests` | `mime_type` | `character varying` | 22 | 0.00% | api/upload_transcript.php, includes/transcript_review_data.php, review_transcript.php |
| `transcript_requests` | `file_size` | `bigint` | 22 | 0.00% | api/upload_transcript.php |
| `transcript_requests` | `status` | `character varying` | 22 | 0.00% | account_status_lib.php, api_courses.php, api_quiz_progress.php, api_quiz.php, +77 more |
| `transcript_requests` | `submitted_at` | `timestamp with time zone` | 22 | 0.00% | api_quiz_progress.php, assets/js/parent_dashboard.js, assets/js/staffdash.js, assets/js/teacherdash.js, +19 more |
| `transcript_requests` | `reviewed_by` | `character varying` | 22 | 0.00% | api_staff.php, includes/transcript_review_data.php, process_transcript.php, reject_transcript.php |
| `transcript_requests` | `reviewed_at` | `timestamp with time zone` | 22 | 0.00% | assets/js/teacherdash.js, graduation_tracker_api.php, includes/transcript_review_data.php, includes/transcript_upload_data.php, +6 more |
| `transcript_requests` | `reviewer_note` | `text` | 22 | 90.91% | includes/transcript_review_data.php, includes/transcript_upload_data.php, process_transcript.php, reject_transcript.php, +2 more |
| `transcript_requests` | `document_url` | `text` | 22 | 81.82% | api/upload_transcript.php, credit_transfer.php, includes/transcript_review_data.php, submit_transfer.php, +1 more |
| `transcript_requests` | `created_at` | `timestamp with time zone` | 22 | 0.00% | api_staff.php, assets/js/staffdash.js, assets/js/web.js, includes/transcript_review_data.php, +7 more |
| `transcript_requests` | `updated_at` | `timestamp with time zone` | 22 | 0.00% | api_staff.php, assets/js/staffdash.js, course_certificate_lib.php, learning_progress_lib.php, +10 more |
| `transcript_requests` | `approved_by` | `text` | 22 | 86.36% | api_staff.php, includes/transcript_review_data.php, process_transcript.php, staff_graduation_api.php |
| `transcript_requests` | `approved_at` | `timestamp with time zone` | 22 | 86.36% | includes/transcript_review_data.php, process_transcript.php, review_transcript.php |
| `transcript_requests` | `approved_courses` | `text` | 22 | 86.36% | includes/transcript_review_data.php, process_transcript.php |
| `transfer_permissions` | `permission_id` | `bigint` | 2 | 0.00% | assign_permission.php, staff_transfer_permissions.php |
| `transfer_permissions` | `instructor_id` | `character varying` | 2 | 0.00% | api_staff.php, assets/js/staff_transfer_permissions.js, assign_permission.php, check_auth.php, +5 more |
| `transfer_permissions` | `granted_by` | `character varying` | 2 | 0.00% | api_staff.php, assign_permission.php |
| `transfer_permissions` | `granted_at` | `timestamp with time zone` | 2 | 0.00% | staff_transfer_permissions.php |
| `transfer_permissions` | `revoked_by` | `character varying` | 2 | 100.00% | api_staff.php, assign_permission.php |
| `transfer_permissions` | `revoked_at` | `timestamp with time zone` | 2 | 100.00% | assign_permission.php |
| `transfer_permissions` | `is_active` | `boolean` | 2 | 0.00% | api_courses.php, api_quiz.php, assign_permission.php, check_auth.php, +8 more |
| `transfer_permissions` | `created_at` | `timestamp with time zone` | 2 | 0.00% | api_staff.php, assets/js/staffdash.js, assets/js/web.js, includes/transcript_review_data.php, +7 more |
| `transfer_permissions` | `updated_at` | `timestamp with time zone` | 2 | 0.00% | api_staff.php, assets/js/staffdash.js, course_certificate_lib.php, learning_progress_lib.php, +10 more |
| `user_addresses` | `address_id` | `bigint` | 18 | 0.00% | parent_dashboard_api.php, staffdash.php, student_dashboard_api.php, teacherdash.php, +1 more |
| `user_addresses` | `user_id` | `character varying` | 18 | 0.00% | api_courses.php, api_quiz_progress.php, api_staff.php, api/upload_transcript.php, +55 more |
| `user_addresses` | `address_line` | `text` | 18 | 0.00% | assets/js/parent_dashboard.js, assets/js/student_dashboard.js, parent_dashboard_api.php, registration_lib.php, +4 more |
| `user_addresses` | `subdistrict` | `character varying` | 18 | 0.00% | assets/js/parent_dashboard.js, assets/js/student_dashboard.js, parent_dashboard_api.php, registration_lib.php, +4 more |
| `user_addresses` | `district` | `character varying` | 18 | 0.00% | assets/js/parent_dashboard.js, assets/js/student_dashboard.js, parent_dashboard_api.php, registration_lib.php, +4 more |
| `user_addresses` | `province` | `character varying` | 18 | 0.00% | assets/js/parent_dashboard.js, assets/js/regisstu.js, assets/js/student_dashboard.js, parent_dashboard_api.php, +7 more |
| `user_addresses` | `postal_code` | `character varying` | 18 | 0.00% | assets/js/parent_dashboard.js, assets/js/student_dashboard.js, parent_dashboard_api.php, registration_lib.php, +4 more |
| `user_addresses` | `is_primary` | `boolean` | 18 | 0.00% | parent_dashboard_api.php, staffdash.php, student_dashboard_api.php, teacherdash.php, +1 more |
| `user_addresses` | `created_at` | `timestamp with time zone` | 18 | 0.00% | api_staff.php, assets/js/staffdash.js, assets/js/web.js, includes/transcript_review_data.php, +7 more |
| `user_addresses` | `updated_at` | `timestamp with time zone` | 18 | 0.00% | api_staff.php, assets/js/staffdash.js, course_certificate_lib.php, learning_progress_lib.php, +10 more |
| `users` | `user_id` | `character varying` | 19 | 0.00% | api_courses.php, api_quiz_progress.php, api_staff.php, api/upload_transcript.php, +55 more |
| `users` | `password_hash` | `text` | 19 | 0.00% | api_staff.php, forgot_password_action.php, login_action.php, regisstu_action.php, +7 more |
| `users` | `role` | `character varying` | 19 | 0.00% | api_courses.php, api_quiz_progress.php, api_staff.php, api/upload_transcript.php, +74 more |
| `users` | `account_status` | `character varying` | 19 | 0.00% | account_status_lib.php, api_staff.php, forgot_password_action.php, login_action.php, +1 more |
| `users` | `created_at` | `timestamp with time zone` | 19 | 0.00% | api_staff.php, assets/js/staffdash.js, assets/js/web.js, includes/transcript_review_data.php, +7 more |
| `users` | `updated_at` | `timestamp with time zone` | 19 | 0.00% | api_staff.php, assets/js/staffdash.js, course_certificate_lib.php, learning_progress_lib.php, +10 more |
| `users` | `created_by` | `character varying` | 19 | 100.00% | api_staff.php, teacher_api.php, update_staff_profile.php |
| `users` | `updated_by` | `character varying` | 19 | 89.47% | api_staff.php, reset_password_action.php, teacher_api.php, teacherdash.php, +5 more |
| `video_watch_sessions` | `id` | `bigint` | 15 | 0.00% | api_courses.php, api_staff.php, assets/js/parent_dashboard.js, assets/js/regisstu.js, +52 more |
| `video_watch_sessions` | `student_id` | `character varying` | 15 | 0.00% | api_courses.php, api_quiz_progress.php, api_staff.php, api/upload_transcript.php, +34 more |
| `video_watch_sessions` | `lesson_id` | `bigint` | 15 | 0.00% | api_courses.php, api_quiz_progress.php, api_quiz.php, api_staff.php, +27 more |
| `video_watch_sessions` | `video_url` | `text` | 15 | 100.00% | api_courses.php, assets/js/staffdash.js, assets/js/web.js, index.php |
| `video_watch_sessions` | `current_seconds` | `integer` | 15 | 0.00% | learning_progress_lib.php |
| `video_watch_sessions` | `progress_percent` | `numeric` | 15 | 0.00% | assets/js/student_dashboard.js, assets/js/web.js, course_certificate_lib.php, graduation_tracker_api.php, +4 more |
| `video_watch_sessions` | `status` | `character varying` | 15 | 0.00% | account_status_lib.php, api_courses.php, api_quiz_progress.php, api_quiz.php, +77 more |
| `video_watch_sessions` | `started_at` | `timestamp with time zone` | 15 | 0.00% | learning_progress_lib.php |
| `video_watch_sessions` | `last_watched_at` | `timestamp with time zone` | 15 | 0.00% | learning_progress_lib.php |
| `video_watch_sessions` | `completed_at` | `timestamp with time zone` | 15 | 26.67% | course_certificate_lib.php, learning_progress_lib.php |
| `video_watch_sessions` | `created_at` | `timestamp with time zone` | 15 | 0.00% | api_staff.php, assets/js/staffdash.js, assets/js/web.js, includes/transcript_review_data.php, +7 more |
| `video_watch_sessions` | `updated_at` | `timestamp with time zone` | 15 | 0.00% | api_staff.php, assets/js/staffdash.js, course_certificate_lib.php, learning_progress_lib.php, +10 more |
| `video_watch_sessions` | `duration_seconds` | `integer` | 15 | 0.00% | api_staff.php, assets/js/staff_video_editor.js, assets/js/web.js, learning_progress_lib.php, +2 more |

## Review notes

- Empty means SQL `NULL`, or an empty/whitespace-only value for text fields. Zero-row tables are marked `n/a`; they are not treated as safe deletion candidates.
- Removed after review: `students.education_level` (empty in all six rows; fallback removed from `student_dashboard_api.php`), `course_certificates.document_bucket` and `document_object_path` (empty in both rows).
- Removed with explicit user approval despite populated data: `transcript_requests.storage_bucket` and `storage_object_path` (one populated row each). These values were intentionally discarded without backup and cannot be recovered by the schema-only rollback.
- Removed `assignment_submissions` after the live database confirmed it was empty. Its assignment list now returns zero counts for submissions and pending reviews; SQL references and dynamic creation were removed from application code.
- `course_teachers.assigned_at` and `exam_logs.exam_log_id` have populated values but no literal code reference; retain them (assignment timestamp and primary key).
- `transcript_requests.file_path` and `document_url` are both referenced by the current document workflow. Keep both until a data migration and caller cutover is planned.
- Executed migrations are verified: the five listed columns and `assignment_submissions` table are absent; public schema now has 30 tables and 279 columns. Rollback files recreate definitions only; dropped values are not restored.
