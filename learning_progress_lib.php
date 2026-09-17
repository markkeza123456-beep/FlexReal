<?php
function ensureLearningProgressTables(PDO $conn): void {



}
function lessonProgressFromQuiz(int $score, int $totalScore, float $documentPercent = 0, float $videoPercent = 0): float {
    $documentPart = min(100, max(0, $documentPercent)) * 0.30;
    $videoPart = min(100, max(0, $videoPercent)) * 0.30;
    $quizPart = $totalScore > 0 ? min(40.0, max(0.0, $score / $totalScore * 40.0)) : 0.0;
    return round($documentPart + $videoPart + $quizPart, 1);
}
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
function recordLearningActivity(PDO $conn, string $studentId, string $courseId, int $lessonIndex, string $activityType, string $lessonTitle = '', float $progressPercent = 0, float $resumePosition = 0): void {
    ensureLearningProgressTables($conn);
    $lessonId = learningLessonId($conn, $courseId, $lessonIndex); if ($lessonId === null) throw new RuntimeException('ไม่พบบทเรียนในรายวิชานี้');
    $conn->prepare('INSERT INTO public.lesson_progress (student_id, lesson_id) VALUES (:student_id, :lesson_id) ON CONFLICT (student_id, lesson_id) DO NOTHING')->execute([':student_id' => $studentId, ':lesson_id' => $lessonId]);
    $progressPercent = min(100, max(0, $progressPercent));
    if ($activityType === 'document_complete') { $activityType = 'document_progress'; $progressPercent = 100; }
    if ($activityType === 'video_open') { $activityType = 'video_progress'; $progressPercent = 100; }
    $isDocument = $activityType === 'document_progress'; $isVideo = $activityType === 'video_progress';


    $conn->prepare('UPDATE public.lesson_progress SET document_progress_percent = CASE WHEN CAST(:is_document AS boolean) THEN GREATEST(document_progress_percent, :progress) ELSE document_progress_percent END, document_page_index = CASE WHEN CAST(:is_document AS boolean) THEN GREATEST(document_page_index, CAST(:resume_position AS integer)) ELSE document_page_index END, video_progress_percent = CASE WHEN CAST(:is_video AS boolean) THEN GREATEST(video_progress_percent, :progress) ELSE video_progress_percent END, video_position_seconds = CASE WHEN CAST(:is_video AS boolean) THEN CASE WHEN :progress >= 100 THEN 0 ELSE GREATEST(video_position_seconds, :resume_position) END ELSE video_position_seconds END, opened_count = CASE WHEN CAST(:is_document AS boolean) AND :progress >= 100 THEN GREATEST(opened_count, 1) ELSE opened_count END, video_open_count = CASE WHEN CAST(:is_video AS boolean) AND :progress >= 100 THEN GREATEST(video_open_count, 1) ELSE video_open_count END, first_opened_at = COALESCE(first_opened_at, now()), last_opened_at = CASE WHEN CAST(:is_document AS boolean) OR CAST(:is_video AS boolean) THEN now() ELSE last_opened_at END, last_activity_at = now() WHERE student_id = :student_id AND lesson_id = :lesson_id')->execute([':is_document' => $isDocument ? 'true' : 'false', ':is_video' => $isVideo ? 'true' : 'false', ':progress' => $progressPercent, ':resume_position' => max(0, $resumePosition), ':student_id' => $studentId, ':lesson_id' => $lessonId]);
    $detail = json_encode(['title' => $lessonTitle, 'progress_percent' => $progressPercent, 'resume_position' => $resumePosition], JSON_UNESCAPED_UNICODE);
    $conn->prepare('INSERT INTO public.learning_events (student_id, lesson_id, event_type, detail) VALUES (:student_id, :lesson_id, :event_type, CAST(:detail AS jsonb))')->execute([':student_id' => $studentId, ':lesson_id' => $lessonId, ':event_type' => $activityType, ':detail' => $detail]);
}
