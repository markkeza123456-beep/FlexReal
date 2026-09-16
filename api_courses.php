<?php
session_start(); require_once __DIR__ . '/db_connect.php'; header('Content-Type: application/json; charset=utf-8'); $action = $_GET['action'] ?? ''; session_write_close();
function loadLessons(PDO $conn, string $courseId): array {
    $stmt = $conn->prepare("SELECT l.lesson_id, l.course_id, l.title, l.content, l.position, l.study_hours, l.pass_percentage,
        COALESCE(video.url, '') AS video_url, COALESCE(video.url, '') AS video_path, COALESCE(video.title, '') AS video_name,
        COALESCE(document.url, '') AS document_path, COALESCE(document.title, '') AS document_name
        FROM public.lessons l
        LEFT JOIN LATERAL (SELECT url, title FROM public.lesson_resources WHERE lesson_id = l.lesson_id AND resource_type = 'video' ORDER BY position DESC, resource_id DESC LIMIT 1) video ON true
        LEFT JOIN LATERAL (SELECT url, title FROM public.lesson_resources WHERE lesson_id = l.lesson_id AND resource_type = 'document' ORDER BY position DESC, resource_id DESC LIMIT 1) document ON true
        WHERE l.course_id = :course_id ORDER BY l.position");
    $stmt->execute([':course_id' => $courseId]); return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
try {
    $studentId = (($_SESSION['role'] ?? '') === 'student') ? (string) ($_SESSION['user_id'] ?? '') : '';
    if ($action === 'get_all') {
        $stmt = $conn->prepare("SELECT c.course_id, c.code, c.name, c.description, COALESCE(cc.requirement_type, 'elective') AS subject_type, (cc.course_id IS NOT NULL) AS in_curriculum, (cc.requirement_type = 'required') AS is_curriculum_required, (sc.course_id IS NOT NULL) AS is_enrolled FROM public.courses c LEFT JOIN LATERAL (SELECT curriculum_id FROM public.student_curricula WHERE student_id = :student_id AND status = 'active' ORDER BY enrolled_at DESC LIMIT 1) active_curriculum ON true LEFT JOIN public.curriculum_courses cc ON cc.curriculum_id = active_curriculum.curriculum_id AND cc.course_id = c.course_id LEFT JOIN public.student_courses sc ON sc.course_id = c.course_id AND sc.student_id = :student_id WHERE c.status = 'active' ORDER BY (cc.course_id IS NULL), c.name");
        $stmt->execute([':student_id' => $studentId]); $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($courses as &$course) { $course['subjects_id'] = $course['course_id']; $course['subjects_name'] = $course['name']; $course['subjects_description'] = $course['description']; } unset($course);
        echo json_encode(['status' => 'success', 'data' => $courses], JSON_UNESCAPED_UNICODE); exit;
    }
    if ($action === 'get_detail') {
        $id = (string) ($_GET['id'] ?? ''); $stmt = $conn->prepare('SELECT course_id, code, name, description, credits FROM public.courses WHERE course_id = :id AND status = :status'); $stmt->execute([':id' => $id, ':status' => 'active']); $course = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$course) throw new RuntimeException('ไม่พบรายวิชานี้');
        $teachers = $conn->prepare("SELECT COALESCE(string_agg(t.full_name, ', ' ORDER BY t.full_name), 'ไม่ระบุอาจารย์ผู้สอน') FROM public.course_teachers ct JOIN public.teachers t ON t.user_id = ct.teacher_id WHERE ct.course_id = :id"); $teachers->execute([':id' => $id]);
        $course['subjects_id'] = $course['course_id']; $course['subjects_name'] = $course['name']; $course['subjects_description'] = $course['description']; $course['teachers_name'] = $teachers->fetchColumn();
        echo json_encode(['status' => 'success', 'course' => $course, 'lessons' => loadLessons($conn, $id)], JSON_UNESCAPED_UNICODE); exit;
    }
    throw new RuntimeException('ไม่รองรับคำขอนี้');
} catch (Throwable $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE); }
