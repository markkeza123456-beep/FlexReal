<?php
declare(strict_types=1);

function graduationDocumentH(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Return one final grade row per course assigned to this student. */
function graduationTranscriptRows(PDO $conn, string $studentId): array
{
    $stmt = $conn->prepare(
        "SELECT c.course_id, c.code, c.name, c.credits, sc.status AS enrollment_status,
                COUNT(l.lesson_id)::integer AS total_lessons,
                COUNT(l.lesson_id) FILTER (
                    WHERE COALESCE(lp.is_transferred, FALSE) OR UPPER(COALESCE(lp.grade, '')) = 'TR'
                )::integer AS transferred_lessons,
                COUNT(l.lesson_id) FILTER (
                    WHERE COALESCE(lp.is_transferred, FALSE)
                       OR UPPER(COALESCE(lp.grade, '')) = 'TR'
                       OR qa.status = 'passed'
                       OR (qa.status <> 'pending_review' AND COALESCE(qa.total_score, 0) > 0
                           AND qa.score >= CEIL(qa.total_score))
                )::integer AS passed_lessons,
                COUNT(l.lesson_id) FILTER (WHERE qa.status = 'failed')::integer AS failed_lessons
         FROM public.student_courses sc
         JOIN public.courses c ON c.course_id = sc.course_id
         LEFT JOIN public.lessons l ON l.course_id = c.course_id
         LEFT JOIN public.lesson_progress lp ON lp.student_id = sc.student_id AND lp.lesson_id = l.lesson_id
         LEFT JOIN LATERAL (
             SELECT a.status, a.score, a.total_score
             FROM public.quiz_attempts a
             WHERE a.student_id = sc.student_id AND a.lesson_id = l.lesson_id
             ORDER BY a.submitted_at DESC, a.attempt_number DESC LIMIT 1
         ) qa ON TRUE
         WHERE sc.student_id = :student_id
         GROUP BY c.course_id, c.code, c.name, c.credits, sc.status
         ORDER BY c.code, c.name"
    );
    $stmt->execute([':student_id' => $studentId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $total = (int) $row['total_lessons'];
        $transferred = (int) $row['transferred_lessons'];
        $passed = (int) $row['passed_lessons'];
        $enrollmentStatus = strtolower((string) $row['enrollment_status']);
        $row['is_transferred'] = $enrollmentStatus === 'tr' || ($total > 0 && $transferred === $total);
        $row['is_completed'] = in_array($enrollmentStatus, ['passed', 'completed', 'tr'], true)
            || ($total > 0 && $passed === $total);
        $row['grade_code'] = $row['is_transferred'] ? 'TR' : ($row['is_completed'] ? 'P' : ((int) $row['failed_lessons'] > 0 ? 'F' : 'IP'));
        $row['grade_label'] = match ($row['grade_code']) {
            'TR' => 'TR · เทียบโอน',
            'P' => 'ผ่าน',
            'F' => 'ไม่ผ่าน',
            default => 'กำลังเรียน',
        };
        $row['transfer_summary'] = $transferred > 0 ? ($transferred . '/' . $total . ' บท') : '—';
    }
    unset($row);
    return $rows;
}

function graduationTranscriptEligible(array $courseRows): bool
{
    if (!$courseRows) return false;
    foreach ($courseRows as $row) {
        if (empty($row['is_completed'])) return false;
    }
    return true;
}

function generateGraduationTranscriptPdf(array $student, array $courseRows, float $totalCredits, string $approvedByName): string
{
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!is_file($autoload)) throw new RuntimeException('ไม่พบ mPDF กรุณารัน composer install');
    require_once $autoload;

    $fontDir = __DIR__ . '/assets/fonts/sarabun';
    $regularFont = $fontDir . '/Sarabun-Regular.ttf';
    $boldFont = $fontDir . '/Sarabun-Bold.ttf';
    if (!is_file($regularFont) || !is_file($boldFont)) throw new RuntimeException('ไม่พบไฟล์ฟอนต์ Sarabun สำหรับสร้าง PDF');

    $tempDir = __DIR__ . '/tmp/mpdf';
    if (!is_dir($tempDir) && !mkdir($tempDir, 0750, true) && !is_dir($tempDir)) {
        throw new RuntimeException('ไม่สามารถเตรียมโฟลเดอร์ชั่วคราวของ mPDF ได้');
    }
    $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
    $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'tempDir' => $tempDir,
        'fontDir' => array_merge($defaultConfig['fontDir'], [$fontDir]),
        'fontdata' => $defaultFontConfig['fontdata'] + [
            'sarabun' => [
                'R' => 'Sarabun-Regular.ttf',
                'B' => 'Sarabun-Bold.ttf',
                'useOTL' => 0xFF,
            ],
        ],
        'default_font' => 'sarabun',
        'autoScriptToLang' => true,
        'autoLangToFont' => true,
    ]);
    $mpdf->SetTitle('ใบสรุปผลการเรียน Credit Bank');
    $mpdf->SetAuthor('Flexible Learning Hub');
    $mpdf->SetHTMLFooter('<div style="text-align:center;color:#64748b;font-size:9pt">Flexible Learning Hub · หน้า {PAGENO}/{nbpg}</div>');

    $html = '<html lang="th"><head><meta charset="UTF-8"><style>
        body{font-family:sarabun;font-size:12pt;color:#243247}
        h1{text-align:center;font-size:23pt;margin:0;color:#183b56}
        .subtitle{text-align:center;color:#63758a;margin:0 0 16px}
        .student{border:1px solid #d5deea;background:#f7f9fc;padding:10px 14px;margin:14px 0}
        .student td{padding:3px 8px}.label{color:#64748b;width:115px}
        table.courses{width:100%;border-collapse:collapse;margin-top:8px}
        .courses th{background:#234a67;color:#fff;font-weight:bold;padding:8px 6px;border:1px solid #234a67}
        .courses td{padding:7px 6px;border:1px solid #d7e0e9}
        .center{text-align:center}.right{text-align:right}
        .grade-tr{color:#223850;background:#EEF4F8;font-weight:bold}
        .grade-pass{color:#166534;font-weight:bold}.grade-fail{color:#b91c1c;font-weight:bold}
        .summary{margin-top:14px;padding:10px 12px;background:#EEF4F8;border-left:4px solid #324860;font-size:13pt}
        .signatures{margin-top:40px;width:100%}.signatures td{text-align:center;width:50%;padding-top:22px}
        .signature-line{border-top:1px solid #64748b;margin:0 30px;padding-top:5px}
        .note{font-size:9pt;color:#64748b;margin-top:20px}
    </style></head><body>';
    $html .= '<h1>ใบสรุปผลการเรียน</h1><p class="subtitle">ระบบธนาคารหน่วยกิต · Flexible Learning Hub</p>';
    $html .= '<table class="student" width="100%"><tr><td class="label">ชื่อ - นามสกุล</td><td><b>' . graduationDocumentH((string) ($student['full_name'] ?? '')) . '</b></td><td class="label">รหัสนักเรียน</td><td>' . graduationDocumentH((string) $student['student_id']) . '</td></tr>';
    $html .= '<tr><td class="label">วันที่อนุมัติ</td><td>' . graduationDocumentH(date('d/m/') . (date('Y') + 543)) . '</td><td class="label">หน่วยกิตสะสม</td><td><b>' . number_format($totalCredits, 2) . '</b></td></tr></table>';
    $html .= '<table class="courses"><thead><tr><th style="width:11%">รหัสวิชา</th><th>รายวิชา</th><th style="width:12%">หน่วยกิต</th><th style="width:15%">บทเรียนที่เทียบโอน</th><th style="width:19%">ผลการเรียน</th></tr></thead><tbody>';
    foreach ($courseRows as $row) {
        $gradeClass = $row['grade_code'] === 'TR' ? 'grade-tr' : ($row['grade_code'] === 'P' ? 'grade-pass' : ($row['grade_code'] === 'F' ? 'grade-fail' : ''));
        $html .= '<tr><td class="center">' . graduationDocumentH((string) $row['code']) . '</td>';
        $html .= '<td>' . graduationDocumentH((string) $row['name']) . '</td>';
        $html .= '<td class="center">' . number_format((float) $row['credits'], 2) . '</td>';
        $html .= '<td class="center">' . graduationDocumentH((string) $row['transfer_summary']) . '</td>';
        $html .= '<td class="center ' . $gradeClass . '">' . graduationDocumentH((string) $row['grade_label']) . '</td></tr>';
    }
    $html .= '</tbody></table><div class="summary">หน่วยกิตที่นับเพื่อขอจบการศึกษา: <b>' . number_format($totalCredits, 2) . ' หน่วยกิต</b></div>';
    $html .= '<table class="signatures"><tr><td><div class="signature-line">ผู้อนุมัติ<br>' . graduationDocumentH($approvedByName) . '</div></td><td><div class="signature-line">ผู้รับรองผลการเรียน<br>Flexible Learning Hub</div></td></tr></table>';
    $html .= '<p class="note">เอกสารฉบับนี้ออกจากระบบธนาคารหน่วยกิต Flexible Learning Hub เพื่อสรุปผลการเรียนและรายการเทียบโอน</p></body></html>';
    $mpdf->WriteHTML($html);
    return $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
}
