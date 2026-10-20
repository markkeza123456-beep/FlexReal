<?php
declare(strict_types=1);

/** Completion rules match student_dashboard_api.php: all course lessons passed/transferred,
 * or the existing enrollment itself is explicitly completed/passed/transferred. */
function courseCertificateProgress(PDO $conn, string $studentId): array
{
    $stmt = $conn->prepare(
        "SELECT c.course_id, c.name AS course_name, c.code AS course_code,
                COALESCE(sc.completed_at, MAX(GREATEST(qa.submitted_at, qa.updated_at, lp.updated_at)) FILTER (WHERE
                    COALESCE(lp.is_transferred, FALSE) OR UPPER(COALESCE(lp.grade, '')) = 'TR'
                    OR qa.status = 'passed'
                    OR (qa.status <> 'pending_review' AND COALESCE(qa.total_score, 0) > 0
                        AND CASE WHEN COALESCE(answers.answer_count, 0) = 0
                                 THEN COALESCE(qa.score, 0) ELSE COALESCE(answers.earned_score, 0) END >= CEIL(qa.total_score)
                        AND COALESCE(answers.essay_pending, 0) = 0))) AS passed_at,
                COALESCE(sc.status, 'active') AS enrollment_status,
                COUNT(l.lesson_id)::integer AS lesson_count,
                COUNT(l.lesson_id) FILTER (WHERE
                    COALESCE(lp.is_transferred, FALSE) OR UPPER(COALESCE(lp.grade, '')) = 'TR'
                    OR qa.status = 'passed'
                    OR (qa.status <> 'pending_review' AND COALESCE(qa.total_score, 0) > 0
                        AND CASE WHEN COALESCE(answers.answer_count, 0) = 0
                                 THEN COALESCE(qa.score, 0) ELSE COALESCE(answers.earned_score, 0) END >= CEIL(qa.total_score)
                        AND COALESCE(answers.essay_pending, 0) = 0)
                )::integer AS passed_lessons,
                COUNT(l.lesson_id) FILTER (WHERE qa.attempt_id IS NOT NULL)::integer AS attempted_lessons,
                COUNT(l.lesson_id) FILTER (WHERE COALESCE(answers.essay_pending, 0) > 0)::integer AS pending_essay_lessons,
                COALESCE(SUM(CASE WHEN COALESCE(answers.answer_count, 0) = 0
                                  THEN COALESCE(qa.score, 0) ELSE COALESCE(answers.earned_score, 0) END), 0)::numeric AS score_earned,
                COALESCE(SUM(qa.total_score), 0)::numeric AS score_total
         FROM public.student_courses sc
         JOIN public.courses c ON c.course_id = sc.course_id
         LEFT JOIN public.lessons l ON l.course_id = c.course_id
         LEFT JOIN public.lesson_progress lp ON lp.student_id = sc.student_id AND lp.lesson_id = l.lesson_id
         LEFT JOIN LATERAL (
             SELECT a.attempt_id, a.status, a.score, a.total_score, a.submitted_at, a.updated_at
             FROM public.quiz_attempts a
             WHERE a.student_id = sc.student_id AND a.lesson_id = l.lesson_id
             ORDER BY a.submitted_at DESC, a.attempt_number DESC LIMIT 1
         ) qa ON TRUE
         LEFT JOIN LATERAL (
             SELECT COUNT(aa.attempt_answer_id)::integer AS answer_count,
                    COALESCE(SUM(aa.score), 0)::numeric AS earned_score,
                    COUNT(*) FILTER (WHERE q.question_type = 'essay' AND aa.review_status = 'pending')::integer AS essay_pending
             FROM public.quiz_attempt_answers aa
             JOIN public.questions q ON q.question_id = aa.question_id
             WHERE aa.attempt_id = qa.attempt_id
         ) answers ON TRUE
         WHERE sc.student_id = :student_id
         GROUP BY c.course_id, c.name, c.code, sc.status, sc.completed_at
         ORDER BY c.name"
    );
    $stmt->execute([':student_id' => $studentId]);
    $courses = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $lessonCount = (int) $row['lesson_count'];
        $passed = (int) $row['passed_lessons'];
        $status = strtolower(trim((string) $row['enrollment_status']));
        $eligible = in_array($status, ['passed', 'completed', 'tr'], true)
            || ($lessonCount > 0 && $passed === $lessonCount);
        $row['is_eligible'] = $eligible;
        $row['remaining_lessons'] = max(0, $lessonCount - $passed);
        $row['progress_percent'] = $lessonCount > 0 ? round($passed * 100 / $lessonCount, 1) : ($eligible ? 100 : 0);
        $courses[] = $row;
    }
    return $courses;
}

function generateCourseCertificatePdf(array $student, array $course, string $number, string $issuedAt): string
{
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!is_file($autoload)) throw new RuntimeException('ไม่พบ mPDF สำหรับสร้างใบประกาศ');
    require_once $autoload;
    $fontDir = __DIR__ . '/assets/fonts/sarabun';
    if (!is_file($fontDir . '/Sarabun-Regular.ttf') || !is_file($fontDir . '/Sarabun-Bold.ttf')) {
        throw new RuntimeException('ไม่พบฟอนต์สำหรับสร้างใบประกาศ');
    }
    $tempDir = __DIR__ . '/tmp/mpdf';
    if (!is_dir($tempDir) && !mkdir($tempDir, 0750, true) && !is_dir($tempDir)) throw new RuntimeException('เตรียมพื้นที่สร้าง PDF ไม่สำเร็จ');
    $config = (new \Mpdf\Config\ConfigVariables())->getDefaults();
    $fontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8', 'format' => 'A4-LANDSCAPE', 'tempDir' => $tempDir,
        'fontDir' => array_merge($config['fontDir'], [$fontDir]),
        'fontdata' => $fontConfig['fontdata'] + ['sarabun' => ['R' => 'Sarabun-Regular.ttf', 'B' => 'Sarabun-Bold.ttf', 'useOTL' => 0xFF]],
        'default_font' => 'sarabun', 'autoScriptToLang' => true, 'autoLangToFont' => true,
    ]);
    $h = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $date = date('d/m/') . ((int) date('Y') + 543);
    $html = '<html lang="th"><head><meta charset="UTF-8"><style>body{font-family:sarabun;color:#26364a;text-align:center}.frame{border:5px double #ff6b00;padding:48px;margin:12px}h1{font-size:34pt;color:#e85d04;margin:12px}p{font-size:17pt;margin:14px}.name{font-size:28pt;font-weight:bold;color:#243b53}.course{font-size:24pt;font-weight:bold;color:#e85d04}.meta{font-size:11pt;color:#64748b}</style></head><body><div class="frame"><p>FLEXIBLE LEARNING HUB</p><h1>ใบประกาศนียบัตร</h1><p>มอบให้แก่</p><p class="name">' . $h($student['full_name'] ?? '') . '</p><p>เพื่อแสดงว่าได้เรียนและผ่านเกณฑ์ของรายวิชา</p><p class="course">' . $h($course['course_name'] ?? '') . '</p><p>ออกให้ ณ วันที่ ' . $h($date) . '</p><p class="meta">เลขที่ ' . $h($number) . ' · ออกเมื่อ ' . $h($issuedAt) . '</p></div></body></html>';
    $mpdf->WriteHTML($html);
    return $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
}
