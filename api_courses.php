<?php
session_start();
require_once 'db_connect.php';
require_once 'curriculum_subjects_lib.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

function nextLessonId(PDO $conn): string
{
    $stmtId = $conn->query("SELECT lessons_id FROM public.lessons WHERE lessons_id LIKE 'L%' ORDER BY LENGTH(lessons_id) DESC, lessons_id DESC LIMIT 1");
    $lastId = $stmtId->fetchColumn();
    $nextNum = $lastId ? (intval(substr((string) $lastId, 1)) + 1) : 1;
    return 'L' . str_pad((string) $nextNum, 3, '0', STR_PAD_LEFT);
}

function ensureFiveLessons(PDO $conn, string $subjectId): array
{
    $stmtL = $conn->prepare("SELECT * FROM public.lessons WHERE subjects_id = ? ORDER BY lessons_id ASC");
    $stmtL->execute([$subjectId]);
    $lessons = $stmtL->fetchAll(PDO::FETCH_ASSOC);

    $baseName = 'บทที่ 1';
    if (!empty($lessons[0]['lessons_name'])) {
        $baseName = (string) $lessons[0]['lessons_name'];
    }

    $baseHours = 1;
    if (isset($lessons[0]['study_hours']) && is_numeric($lessons[0]['study_hours'])) {
        $baseHours = max(1, (int) $lessons[0]['study_hours']);
    }

    $missing = max(0, 5 - count($lessons));
    if ($missing > 0) {
        $insertStmt = $conn->prepare("INSERT INTO public.lessons (lessons_id, lessons_name, study_hours, subjects_id) VALUES (:id, :name, :hrs, :sub)");
        for ($i = 0; $i < $missing; $i++) {
            $insertStmt->execute([
                ':id' => nextLessonId($conn),
                ':name' => $baseName,
                ':hrs' => $baseHours,
                ':sub' => $subjectId
            ]);
        }

        $stmtL->execute([$subjectId]);
        $lessons = $stmtL->fetchAll(PDO::FETCH_ASSOC);
    }

    return $lessons;
}

try {
    ensureSubjectTypeColumn($conn);
    ensureCurriculumSubjectTypeColumn($conn);

    // 1. ดึงรายวิชาทั้งหมด (หน้าแรก)
    if ($action === 'get_all') {
        $studentCurriculumId = null;
        $studentId = isset($_SESSION['user_id']) ? trim((string) $_SESSION['user_id']) : '';
        $sessionRole = strtolower(trim((string) ($_SESSION['role'] ?? '')));

        if ($studentId !== '' && $sessionRole === 'student') {
            $curriculumStmt = $conn->prepare(
                "SELECT studcurriculums_id
                 FROM public.student
                 WHERE student_id = :student_id
                 LIMIT 1"
            );
            $curriculumStmt->execute([':student_id' => $studentId]);
            $studentCurriculumId = $curriculumStmt->fetchColumn() ?: null;
        }

        if ($studentCurriculumId) {
            $stmt = $conn->prepare(
                "SELECT
                    s.subjects_id,
                    s.subjects_name,
                    s.subjects_description,
                    COALESCE(NULLIF(TRIM(cs.subject_type), ''), COALESCE(NULLIF(TRIM(s.subject_type), ''), 'elective')) AS subject_type,
                    CASE WHEN cs.subject_id IS NOT NULL THEN true ELSE false END AS in_curriculum,
                    CASE
                        WHEN cs.subject_id IS NOT NULL
                         AND COALESCE(NULLIF(TRIM(cs.subject_type), ''), COALESCE(NULLIF(TRIM(s.subject_type), ''), 'elective')) = 'required'
                        THEN true
                        ELSE false
                    END AS is_curriculum_required,
                    CASE
                        WHEN ss.subjects_id IS NOT NULL THEN true
                        ELSE false
                    END AS is_enrolled
                 FROM public.subjects s
                 LEFT JOIN public.curriculums_subject cs
                    ON cs.subject_id = s.subjects_id
                   AND cs.curriculums_id = :curriculum_id
                 LEFT JOIN public.student_subject ss
                    ON ss.subjects_id = s.subjects_id
                   AND ss.student_id = :student_id
                 WHERE s.deleted_at IS NULL
                 ORDER BY
                    CASE WHEN cs.subject_id IS NOT NULL THEN 0 ELSE 1 END,
                    s.subjects_name ASC"
            );
            $stmt->execute([
                ':curriculum_id' => $studentCurriculumId,
                ':student_id' => $studentId,
            ]);
        } else {
            $stmt = $conn->query(
                "SELECT
                    subjects_id,
                    subjects_name,
                    subjects_description,
                    COALESCE(NULLIF(TRIM(subject_type), ''), 'elective') AS subject_type,
                    false AS in_curriculum,
                    false AS is_curriculum_required,
                    false AS is_enrolled
                 FROM public.subjects
                 WHERE deleted_at IS NULL
                 ORDER BY subjects_name ASC"
            );
        }

        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'data' => $courses]);
    }
    // 2. ดึงรายละเอียดวิชา และ บทเรียนย่อย
    elseif ($action === 'get_detail') {
        $id = $_GET['id'] ?? '';
        
        $stmt = $conn->prepare("SELECT * FROM public.subjects WHERE subjects_id = ?");
        $stmt->execute([$id]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$course) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบรายวิชานี้']);
            exit;
        }

        // ดึงบทเรียนของวิชานี้
        $lessons = ensureFiveLessons($conn, (string) $id);

        // ดึงชื่ออาจารย์ (เขียนดัก Error ป้องกันเว็บพัง)
        $course['teachers_name'] = 'ไม่ระบุอาจารย์ผู้สอน';
        if (!empty($course['teachers_id'])) {
            try {
                $stmtT = $conn->prepare("SELECT teachers_name FROM public.teachers WHERE teachers_id = ?");
                $stmtT->execute([$course['teachers_id']]);
                $teacher = $stmtT->fetch(PDO::FETCH_ASSOC);
                if ($teacher && !empty($teacher['teachers_name'])) {
                    $course['teachers_name'] = $teacher['teachers_name'];
                }
            } catch (Exception $ex) {
                // ข้ามไปถ้าไม่มีตาราง
            }
        }

        echo json_encode(['status' => 'success', 'course' => $course, 'lessons' => $lessons]);
    }
} catch(Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
