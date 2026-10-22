<?php
declare(strict_types=1);

/** Count students in the graduation-request pipeline using the same course rules as graduation submission. */
function directorGraduationStatusCounts(PDO $conn): array
{
    $stmt = $conn->query("WITH active_curriculum AS (
    SELECT DISTINCT ON (student_id) student_id, curriculum_id
    FROM public.student_curricula
    WHERE status = 'active'
    ORDER BY student_id, enrolled_at DESC
), course_progress AS (
    SELECT s.user_id AS student_id,
           COALESCE(CASE WHEN cc.requirement_type = 'required' THEN 'compulsory'
                         WHEN cc.requirement_type = 'elective' THEN 'elective' END,
                    c.subject_type, 'elective') AS subject_type,
           sc.status AS enrollment_status,
           COUNT(l.lesson_id)::int AS lesson_count,
           COUNT(l.lesson_id) FILTER (
               WHERE COALESCE(lp.is_transferred, FALSE)
                  OR UPPER(COALESCE(lp.grade, '')) = 'TR'
                  OR qa.status = 'passed'
                  OR (qa.status <> 'pending_review'
                      AND COALESCE(qa.total_score, 0) > 0
                      AND qa.score >= CEIL(qa.total_score))
           )::int AS passed_lessons
    FROM public.students s
    CROSS JOIN public.courses c
    LEFT JOIN active_curriculum ac ON ac.student_id = s.user_id
    LEFT JOIN public.curriculum_courses cc ON cc.curriculum_id = ac.curriculum_id AND cc.course_id = c.course_id
    LEFT JOIN public.student_courses sc ON sc.course_id = c.course_id AND sc.student_id = s.user_id
    LEFT JOIN public.lessons l ON l.course_id = c.course_id
    LEFT JOIN public.lesson_progress_summary lp ON lp.student_id = s.user_id AND lp.lesson_id = l.lesson_id
    LEFT JOIN LATERAL (
        SELECT a.status, a.score, a.total_score
        FROM public.quiz_attempts a
        WHERE a.student_id = s.user_id AND a.lesson_id = l.lesson_id
        ORDER BY a.submitted_at DESC, a.attempt_number DESC
        LIMIT 1
    ) qa ON TRUE
    WHERE cc.course_id IS NOT NULL OR sc.course_id IS NOT NULL
    GROUP BY s.user_id, c.course_id, c.credits, c.subject_type, cc.requirement_type, sc.status
), course_results AS (
    SELECT student_id, subject_type,
           (LOWER(COALESCE(enrollment_status, '')) IN ('passed', 'completed', 'tr')
            OR (lesson_count > 0 AND passed_lessons = lesson_count)) AS is_completed
    FROM course_progress
), student_eligibility AS (
    SELECT s.user_id AS student_id,
           COUNT(cr.student_id) FILTER (WHERE cr.subject_type = 'compulsory')::int AS compulsory_total,
           COUNT(cr.student_id) FILTER (WHERE cr.subject_type = 'compulsory' AND cr.is_completed)::int AS compulsory_done
    FROM public.students s
    LEFT JOIN course_results cr ON cr.student_id = s.user_id
    GROUP BY s.user_id
), request_status AS (
    SELECT student_id,
           BOOL_OR(status = 'approved') AS has_approved_request,
           BOOL_OR(status = 'pending') AS has_pending_request
    FROM public.graduation_requests
    GROUP BY student_id
), student_status AS (
    SELECT e.student_id,
           CASE
               WHEN COALESCE(r.has_approved_request, FALSE) THEN 'อนุมัติแล้ว (ออกใบได้)'
               WHEN COALESCE(r.has_pending_request, FALSE) THEN 'รออนุมัติ'
               WHEN e.compulsory_total > 0 AND e.compulsory_done >= e.compulsory_total THEN 'รอยื่นจบ'
               ELSE 'นักเรียนปกติ'
           END AS status
    FROM student_eligibility e
    LEFT JOIN request_status r ON r.student_id = e.student_id
)
SELECT status, COUNT(DISTINCT student_id)::int AS student_count
FROM student_status
WHERE status IS NOT NULL
GROUP BY status");

    $counts = [
        'อนุมัติแล้ว (ออกใบได้)' => 0,
        'รออนุมัติ' => 0,
        'รอยื่นจบ' => 0,
        'นักเรียนปกติ' => 0,
    ];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $status = (string) $row['status'];
        if (array_key_exists($status, $counts)) $counts[$status] = (int) $row['student_count'];
    }
    return $counts;
}
