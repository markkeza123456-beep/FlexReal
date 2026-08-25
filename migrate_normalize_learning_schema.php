<?php
/**
 * One-time safe migration for the learning data model.
 * Run: /Applications/XAMPP/xamppfiles/bin/php migrate_normalize_learning_schema.php --apply
 * It creates backups and does not drop any legacy tables or columns.
 */
require_once __DIR__ . '/db_connect.php';

if (PHP_SAPI !== 'cli' || !in_array('--apply', $argv, true)) {
    exit("Dry run only. Run with --apply after backing up PostgreSQL.\n");
}

function backupTable(PDO $conn, string $table): void {
    $backup = $table . '_archive_20260825';
    $exists = $conn->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = :name");
    $exists->execute([':name' => $backup]);
    if (!$exists->fetchColumn()) {
        $conn->exec("CREATE TABLE public.\"{$backup}\" AS TABLE public.\"{$table}\"");
    }
}

try {
    $conn->beginTransaction();
    foreach (['quiz_questions', 'course_enrollments', 'registrations', 'learning_records', 'lessons'] as $table) backupTable($conn, $table);

    // 1) Move legacy questions into the canonical teacher-managed table.
    $migratedQuestions = (int) $conn->query(
        "WITH legacy AS (
            SELECT q.*, l.lessons_id
            FROM public.quiz_questions q
            JOIN LATERAL (
                SELECT lessons_id FROM public.lessons
                WHERE subjects_id = q.subjects_id
                ORDER BY lessons_id ASC
                OFFSET GREATEST(q.lesson_no - 1, 0) LIMIT 1
            ) l ON TRUE
            WHERE NOT EXISTS (
                SELECT 1 FROM public.test_questions tq
                WHERE tq.lessons_id = l.lessons_id AND tq.questions_text = q.question_text
            )
        ), numbered AS (
            SELECT *, ROW_NUMBER() OVER (ORDER BY quiz_id) AS row_num FROM legacy
        )
        INSERT INTO public.test_questions (questions_id, questions_text, choice_a, choice_b, choice_c, choice_d, correct_answer, lessons_id)
        SELECT (SELECT COALESCE(MAX(questions_id), 0) FROM public.test_questions) + row_num,
               question_text, option_a, option_b, option_c, option_d,
               CASE correct_option WHEN 0 THEN 'A' WHEN 1 THEN 'B' WHEN 2 THEN 'C' WHEN 3 THEN 'D' WHEN 4 THEN 'D' ELSE '-' END,
               lessons_id
        FROM numbered
        RETURNING questions_id"
    )->rowCount();

    // 2) Consolidate old enrollment records into student_subject.
    $conn->exec("INSERT INTO public.student_subject (student_id, subjects_id)
                 SELECT ce.student_id, s.subjects_id
                 FROM public.course_enrollments ce
                 INNER JOIN public.subjects s ON s.subjects_name = ce.course_name
                 ON CONFLICT (student_id, subjects_id) DO NOTHING");

    // 3) Move the last legacy lesson video references into Videos.
    $conn->exec("INSERT INTO public.videos (videos_title, videos_url, subjects_id, lessons_id)
                 SELECT COALESCE(NULLIF(l.lessons_name, ''), 'วิดีโอบทเรียน'), COALESCE(NULLIF(l.video_path, ''), l.video_url), l.subjects_id, l.lessons_id
                 FROM public.lessons l
                 WHERE COALESCE(NULLIF(l.video_path, ''), NULLIF(l.video_url, '')) IS NOT NULL
                   AND NOT EXISTS (SELECT 1 FROM public.videos v WHERE v.videos_url = COALESCE(NULLIF(l.video_path, ''), l.video_url))");

    // 4) Indexes for the canonical read paths.
    $conn->exec('CREATE INDEX IF NOT EXISTS idx_test_questions_lesson ON public.test_questions (lessons_id, questions_id)');
    $conn->exec('CREATE INDEX IF NOT EXISTS idx_student_subject_subject ON public.student_subject (subjects_id, student_id)');
    $conn->exec('CREATE INDEX IF NOT EXISTS idx_videos_lesson ON public.videos (lessons_id, display_order)');
    $conn->commit();
    echo "Migration complete. Migrated legacy questions: {$migratedQuestions}\n";
    echo "Legacy data remains in original tables and *_archive_20260825 tables.\n";
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    fwrite(STDERR, "Migration failed: {$e->getMessage()}\n");
    exit(1);
}
