-- DESTRUCTIVE Learning Hub rebuild (PostgreSQL).
-- This permanently removes every table and every row in the current public
-- schema. It does not back up or migrate legacy data. Run only when a clean
-- database is intended.

BEGIN;
DROP SCHEMA IF EXISTS app CASCADE;
CREATE SCHEMA app;

CREATE TABLE app.users (
    user_id varchar(50) PRIMARY KEY,
    password_hash text NOT NULL,
    role varchar(20) NOT NULL CHECK (role IN ('student','teacher','parent','staff','admin')),
    account_status varchar(20) NOT NULL DEFAULT 'active'
        CHECK (account_status IN ('active','inactive','suspended')),
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE app.students (
    user_id varchar(50) PRIMARY KEY REFERENCES app.users(user_id),
    full_name varchar(200) NOT NULL, email varchar(254) UNIQUE, phone varchar(30),
    education_level varchar(100), student_level varchar(100),
    parent_user_id varchar(50) REFERENCES app.users(user_id), advisor_user_id varchar(50) REFERENCES app.users(user_id),
    avatar_url text, created_at timestamptz NOT NULL DEFAULT now(), updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE app.teachers (
    user_id varchar(50) PRIMARY KEY REFERENCES app.users(user_id),
    full_name varchar(200) NOT NULL, email varchar(254) UNIQUE, phone varchar(30), avatar_url text,
    created_at timestamptz NOT NULL DEFAULT now(), updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE app.parents (
    user_id varchar(50) PRIMARY KEY REFERENCES app.users(user_id),
    full_name varchar(200) NOT NULL, email varchar(254) UNIQUE, phone varchar(30),
    created_at timestamptz NOT NULL DEFAULT now(), updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE app.staff (
    user_id varchar(50) PRIMARY KEY REFERENCES app.users(user_id),
    first_name varchar(100) NOT NULL, last_name varchar(100) NOT NULL,
    created_at timestamptz NOT NULL DEFAULT now(), updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE app.user_addresses (
    address_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, user_id varchar(50) NOT NULL REFERENCES app.users(user_id) ON DELETE CASCADE,
    address_line text, subdistrict varchar(100), district varchar(100), province varchar(100), postal_code varchar(10),
    is_primary boolean NOT NULL DEFAULT true, created_at timestamptz NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX user_addresses_one_primary ON app.user_addresses(user_id) WHERE is_primary;
CREATE TABLE app.curricula (
    curriculum_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, code varchar(50) NOT NULL UNIQUE, name varchar(255) NOT NULL,
    level varchar(100), total_credits numeric(6,2), status varchar(20) NOT NULL DEFAULT 'active' CHECK (status IN ('draft','active','archived')),
    created_at timestamptz NOT NULL DEFAULT now(), updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE app.courses (
    course_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, code varchar(50) NOT NULL UNIQUE, name varchar(255) NOT NULL,
    description text, credits numeric(5,2) NOT NULL DEFAULT 0 CHECK (credits >= 0), status varchar(20) NOT NULL DEFAULT 'active' CHECK (status IN ('draft','active','archived')),
    created_by varchar(50) REFERENCES app.users(user_id), created_at timestamptz NOT NULL DEFAULT now(), updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE app.curriculum_courses (
    curriculum_id bigint NOT NULL REFERENCES app.curricula(curriculum_id) ON DELETE CASCADE, course_id bigint NOT NULL REFERENCES app.courses(course_id),
    requirement_type varchar(20) NOT NULL DEFAULT 'required' CHECK (requirement_type IN ('required','elective')), PRIMARY KEY (curriculum_id, course_id)
);
CREATE TABLE app.course_teachers (
    course_id bigint NOT NULL REFERENCES app.courses(course_id) ON DELETE CASCADE, teacher_id varchar(50) NOT NULL REFERENCES app.teachers(user_id),
    assigned_at timestamptz NOT NULL DEFAULT now(), PRIMARY KEY (course_id, teacher_id)
);
CREATE TABLE app.student_curricula (
    student_id varchar(50) NOT NULL REFERENCES app.students(user_id), curriculum_id bigint NOT NULL REFERENCES app.curricula(curriculum_id),
    enrolled_at timestamptz NOT NULL DEFAULT now(), completed_at timestamptz, status varchar(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active','completed','withdrawn')),
    PRIMARY KEY (student_id, curriculum_id)
);
CREATE TABLE app.student_courses (
    student_id varchar(50) NOT NULL REFERENCES app.students(user_id), course_id bigint NOT NULL REFERENCES app.courses(course_id),
    enrolled_at timestamptz NOT NULL DEFAULT now(), completed_at timestamptz, status varchar(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active','completed','withdrawn')),
    PRIMARY KEY (student_id, course_id)
);
CREATE TABLE app.lessons (
    lesson_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, course_id bigint NOT NULL REFERENCES app.courses(course_id) ON DELETE CASCADE,
    title varchar(255) NOT NULL, content text, position integer NOT NULL CHECK (position > 0), study_hours numeric(5,2), pass_percentage numeric(5,2) CHECK (pass_percentage BETWEEN 0 AND 100),
    created_at timestamptz NOT NULL DEFAULT now(), updated_at timestamptz NOT NULL DEFAULT now(), UNIQUE (course_id, position)
);
CREATE TABLE app.lesson_resources (
    resource_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, lesson_id bigint NOT NULL REFERENCES app.lessons(lesson_id) ON DELETE CASCADE,
    resource_type varchar(20) NOT NULL CHECK (resource_type IN ('video','document','image','link')), title varchar(255) NOT NULL, url text NOT NULL,
    duration_seconds integer CHECK (duration_seconds >= 0), position integer NOT NULL DEFAULT 1 CHECK (position > 0), created_at timestamptz NOT NULL DEFAULT now(), UNIQUE (lesson_id, position)
);
CREATE TABLE app.questions (
    question_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, lesson_id bigint NOT NULL REFERENCES app.lessons(lesson_id) ON DELETE CASCADE,
    question_type varchar(20) NOT NULL CHECK (question_type IN ('multiple_choice','essay')), question_text text NOT NULL, options jsonb,
    correct_choice char(1), max_score numeric(6,2) NOT NULL DEFAULT 1 CHECK (max_score >= 0), is_active boolean NOT NULL DEFAULT true, created_at timestamptz NOT NULL DEFAULT now(),
    CHECK ((question_type = 'essay' AND options IS NULL AND correct_choice IS NULL) OR (question_type = 'multiple_choice' AND options IS NOT NULL AND correct_choice IN ('A','B','C','D')))
);
CREATE TABLE app.quiz_attempts (
    attempt_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, student_id varchar(50) NOT NULL REFERENCES app.students(user_id), lesson_id bigint NOT NULL REFERENCES app.lessons(lesson_id),
    attempt_number integer NOT NULL CHECK (attempt_number > 0), score numeric(7,2) NOT NULL DEFAULT 0, total_score numeric(7,2) NOT NULL DEFAULT 0,
    status varchar(20) NOT NULL DEFAULT 'submitted' CHECK (status IN ('submitted','pending_review','passed','failed')), submitted_at timestamptz NOT NULL DEFAULT now(), UNIQUE (student_id, lesson_id, attempt_number)
);
CREATE TABLE app.quiz_attempt_answers (
    attempt_answer_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, attempt_id bigint NOT NULL REFERENCES app.quiz_attempts(attempt_id) ON DELETE CASCADE,
    question_id bigint NOT NULL REFERENCES app.questions(question_id), selected_choice char(1), answer_text text, score numeric(6,2),
    review_status varchar(20) NOT NULL DEFAULT 'not_required' CHECK (review_status IN ('not_required','pending','reviewed')),
    reviewer_id varchar(50) REFERENCES app.teachers(user_id), reviewer_comment text, reviewed_at timestamptz, UNIQUE (attempt_id, question_id)
);
CREATE TABLE app.lesson_progress (
    student_id varchar(50) NOT NULL REFERENCES app.students(user_id), lesson_id bigint NOT NULL REFERENCES app.lessons(lesson_id) ON DELETE CASCADE,
    opened_count integer NOT NULL DEFAULT 0 CHECK (opened_count >= 0), video_open_count integer NOT NULL DEFAULT 0 CHECK (video_open_count >= 0),
    document_progress_percent numeric(5,2) NOT NULL DEFAULT 0 CHECK (document_progress_percent BETWEEN 0 AND 100), document_page_index integer NOT NULL DEFAULT 0 CHECK (document_page_index >= 0),
    video_progress_percent numeric(5,2) NOT NULL DEFAULT 0 CHECK (video_progress_percent BETWEEN 0 AND 100), video_position_seconds numeric(12,2) NOT NULL DEFAULT 0 CHECK (video_position_seconds >= 0),
    first_opened_at timestamptz, last_opened_at timestamptz, last_activity_at timestamptz NOT NULL DEFAULT now(), PRIMARY KEY (student_id, lesson_id)
);
CREATE TABLE app.learning_events (
    event_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, student_id varchar(50) NOT NULL REFERENCES app.students(user_id),
    lesson_id bigint REFERENCES app.lessons(lesson_id) ON DELETE SET NULL, event_type varchar(50) NOT NULL, detail jsonb, created_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE app.password_reset_tokens (
    token_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, user_id varchar(50) NOT NULL REFERENCES app.users(user_id) ON DELETE CASCADE,
    token_hash text NOT NULL UNIQUE, expires_at timestamptz NOT NULL, used_at timestamptz, created_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE app.activity_logs (
    log_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, actor_user_id varchar(50) REFERENCES app.users(user_id), entity_type varchar(50) NOT NULL,
    entity_id varchar(100) NOT NULL, action varchar(50) NOT NULL, detail jsonb, created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX quiz_attempts_student_lesson ON app.quiz_attempts(student_id, lesson_id, submitted_at DESC);
CREATE INDEX learning_events_student_time ON app.learning_events(student_id, created_at DESC);
DROP SCHEMA public CASCADE;
ALTER SCHEMA app RENAME TO public;
COMMIT;
