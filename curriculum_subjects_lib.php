<?php
declare(strict_types=1);

function normalizeSubjectType(?string $type): string { return strtolower(trim((string) $type)) === 'required' ? 'required' : 'elective'; }
function findActiveCurriculumIdByLevel(PDO $conn, string $level): ?string {
    $stmt = $conn->prepare("SELECT curriculum_id FROM public.curricula WHERE level = :level AND status = 'active' ORDER BY curriculum_id LIMIT 1");
    $stmt->execute([':level' => $level]); $id = $stmt->fetchColumn(); return $id === false ? null : (string) $id;
}
function assignCurriculumToStudentByLevel(PDO $conn, string $studentId, string $level): ?string {
    $id = findActiveCurriculumIdByLevel($conn, $level); if ($id === null) return null;
    $conn->prepare('INSERT INTO public.student_curricula (student_id, curriculum_id) VALUES (:student_id, :curriculum_id) ON CONFLICT (student_id, curriculum_id) DO NOTHING')->execute([':student_id' => $studentId, ':curriculum_id' => $id]);
    $conn->prepare("INSERT INTO public.student_courses (student_id, course_id) SELECT :student_id, course_id FROM public.curriculum_courses WHERE curriculum_id = :curriculum_id AND requirement_type = 'required' ON CONFLICT (student_id, course_id) DO NOTHING")->execute([':student_id' => $studentId, ':curriculum_id' => $id]);
    return $id;
}
function assignCurriculumAndEnrollRequiredSubjects(PDO $conn, string $studentId, string $level): ?string { return assignCurriculumToStudentByLevel($conn, $studentId, $level); }
