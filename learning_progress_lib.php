<?php
function ensureLearningProgressTables(PDO $conn): void { /* Created by database-v2.sql. */ }
function lessonProgressFromQuiz(int $score, int $totalScore): float { return $totalScore > 0 ? min(100.0, 60 + ($score / $totalScore * 40)) : 60.0; }
function learningLessonId(PDO $conn, string $courseId, int $position): ?int {
    $stmt = $conn->prepare('SELECT lesson_id FROM public.lessons WHERE course_id = :course_id AND position = :position');
    $stmt->execute([':course_id' => $courseId, ':position' => max(1, $position)]); $id = $stmt->fetchColumn(); return $id === false ? null : (int) $id;
}
function upsertLearningProgressRow(PDO $conn, string $studentId, string $courseId, int $lessonIndex, string $lessonTitle = ''): void {
    $lessonId = learningLessonId($conn, $courseId, $lessonIndex); if ($lessonId === null) return;
    $conn->prepare('INSERT INTO public.lesson_progress (student_id, lesson_id) VALUES (:student_id, :lesson_id) ON CONFLICT (student_id, lesson_id) DO NOTHING')->execute([':student_id' => $studentId, ':lesson_id' => $lessonId]);
}
function syncReviewedQuizScore(PDO $conn, string $studentId, string $courseId, int $lessonIndex, int $score, int $totalScore): void {
    upsertLearningProgressRow($conn, $studentId, $courseId, $lessonIndex);
}
function recordLearningActivity(PDO $conn, string $studentId, string $courseId, int $lessonIndex, string $activityType, string $lessonTitle = '', int $score = 0, int $totalScore = 0): void {
    $lessonId = learningLessonId($conn, $courseId, $lessonIndex); if ($lessonId === null) throw new RuntimeException('ไม่พบบทเรียนในรายวิชานี้');
    $conn->prepare('INSERT INTO public.lesson_progress (student_id, lesson_id) VALUES (:student_id, :lesson_id) ON CONFLICT (student_id, lesson_id) DO NOTHING')->execute([':student_id' => $studentId, ':lesson_id' => $lessonId]);
    $opened = in_array($activityType, ['course_enter', 'lesson_open'], true) ? 1 : 0; $video = $activityType === 'video_open' ? 1 : 0;
    $conn->prepare('UPDATE public.lesson_progress SET opened_count = opened_count + :opened, video_open_count = video_open_count + :video, first_opened_at = COALESCE(first_opened_at, now()), last_opened_at = CASE WHEN :opened > 0 OR :video > 0 THEN now() ELSE last_opened_at END, last_activity_at = now() WHERE student_id = :student_id AND lesson_id = :lesson_id')->execute([':opened' => $opened, ':video' => $video, ':student_id' => $studentId, ':lesson_id' => $lessonId]);
    $detail = json_encode(['title' => $lessonTitle, 'score' => $score, 'total_score' => $totalScore], JSON_UNESCAPED_UNICODE);
    $conn->prepare('INSERT INTO public.learning_events (student_id, lesson_id, event_type, detail) VALUES (:student_id, :lesson_id, :event_type, CAST(:detail AS jsonb))')->execute([':student_id' => $studentId, ':lesson_id' => $lessonId, ':event_type' => $activityType, ':detail' => $detail]);
}
