<?php
declare(strict_types=1);
session_start();
$role = strtolower(trim((string) ($_SESSION['role'] ?? '')));
if (!in_array($role, ['staff', 'officer', 'admin', 'president'], true)) {
    http_response_code(isset($_SESSION['user_id']) ? 403 : 401);
    if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
    exit('คุณไม่มีสิทธิ์เข้าถึงรายงานผู้บริหาร');
}
require_once __DIR__ . '/config/db.php';

$studentRows = $conn->query("WITH active_curricula AS (
    SELECT DISTINCT ON (student_id) student_id, curriculum_id FROM public.student_curricula
    WHERE status = 'active' ORDER BY student_id, enrolled_at DESC
), course_progress AS (
    SELECT sc.student_id, c.course_id, c.subject_type,
        (LOWER(COALESCE(sc.status, '')) IN ('passed','completed','tr') OR
         (COUNT(l.lesson_id) > 0 AND COUNT(l.lesson_id) FILTER (WHERE
             COALESCE(lp.is_transferred,FALSE) OR UPPER(COALESCE(lp.grade,''))='TR' OR qa.status='passed' OR
             (qa.status <> 'pending_review' AND COALESCE(qa.total_score,0)>0 AND qa.score >= CEIL(qa.total_score))) = COUNT(l.lesson_id))) AS passed,
        (UPPER(COALESCE(sc.status,''))='TR' OR (COUNT(l.lesson_id)>0 AND COUNT(l.lesson_id) FILTER (WHERE COALESCE(lp.is_transferred,FALSE) OR UPPER(COALESCE(lp.grade,''))='TR')=COUNT(l.lesson_id))) AS transferred
    FROM public.student_courses sc JOIN public.courses c ON c.course_id=sc.course_id
    LEFT JOIN public.lessons l ON l.course_id=c.course_id
    LEFT JOIN public.lesson_progress lp ON lp.student_id=sc.student_id AND lp.lesson_id=l.lesson_id
    LEFT JOIN LATERAL (SELECT status,score,total_score FROM public.quiz_attempts a WHERE a.student_id=sc.student_id AND a.lesson_id=l.lesson_id ORDER BY submitted_at DESC,attempt_number DESC LIMIT 1) qa ON TRUE
    GROUP BY sc.student_id,c.course_id,c.subject_type,sc.status
), assigned AS (
    SELECT s.user_id AS student_id, c.course_id,
        COALESCE(CASE WHEN cc.requirement_type='required' THEN 'compulsory' WHEN cc.requirement_type='elective' THEN 'elective' END,c.subject_type,'elective') AS subject_type,
        COALESCE(cp.passed,FALSE) AS passed, COALESCE(cp.transferred,FALSE) AS transferred
    FROM public.students s
    LEFT JOIN active_curricula ac ON ac.student_id=s.user_id
    LEFT JOIN public.curriculum_courses cc ON cc.curriculum_id=ac.curriculum_id
    LEFT JOIN public.courses c ON c.course_id=cc.course_id
    LEFT JOIN course_progress cp ON cp.student_id=s.user_id AND cp.course_id=c.course_id
    UNION
    SELECT s.user_id, c.course_id,
        COALESCE(CASE WHEN cc.requirement_type='required' THEN 'compulsory' WHEN cc.requirement_type='elective' THEN 'elective' END,c.subject_type,'elective'),
        COALESCE(cp.passed,FALSE), COALESCE(cp.transferred,FALSE)
    FROM public.students s JOIN public.student_courses sc ON sc.student_id=s.user_id
    JOIN public.courses c ON c.course_id=sc.course_id
    LEFT JOIN active_curricula ac ON ac.student_id=s.user_id
    LEFT JOIN public.curriculum_courses cc ON cc.curriculum_id=ac.curriculum_id AND cc.course_id=c.course_id
    LEFT JOIN course_progress cp ON cp.student_id=s.user_id AND cp.course_id=c.course_id
), totals AS (
    SELECT student_id, COUNT(course_id)::int AS total_courses,
        COUNT(*) FILTER (WHERE passed)::int AS passed_courses,
        COUNT(*) FILTER (WHERE subject_type='compulsory')::int AS compulsory_total,
        COUNT(*) FILTER (WHERE subject_type='compulsory' AND passed)::int AS compulsory_passed,
        COUNT(*) FILTER (WHERE subject_type='elective' AND passed)::int AS elective_passed
    FROM assigned GROUP BY student_id
), requests AS (
    SELECT student_id, BOOL_OR(status IN ('pending','approved')) AS has_request
    FROM public.graduation_requests GROUP BY student_id
)
SELECT s.user_id AS student_id, COALESCE(s.full_name,s.user_id::text) AS full_name,
       COALESCE(t.compulsory_passed,0) AS compulsory_passed, COALESCE(t.elective_passed,0) AS elective_passed,
       COALESCE(t.total_courses,0) AS total_courses, COALESCE(t.passed_courses,0) AS passed_courses,
       COALESCE(t.compulsory_total,0) AS compulsory_total,
       CASE WHEN COALESCE(t.total_courses,0)>0 THEN ROUND(100.0*t.passed_courses/t.total_courses,1) ELSE 0 END AS progress_percent,
       CASE WHEN COALESCE(t.compulsory_total,0)>0 AND t.compulsory_passed>=t.compulsory_total THEN 1 ELSE 0 END AS eligible,
       CASE WHEN COALESCE(r.has_request,FALSE) THEN 1 ELSE 0 END AS has_request
FROM public.students s LEFT JOIN totals t ON t.student_id=s.user_id LEFT JOIN requests r ON r.student_id=s.user_id
ORDER BY s.full_name,s.user_id")->fetchAll(PDO::FETCH_ASSOC);

$transferCounts = ['pending'=>0,'approved'=>0,'rejected'=>0];
foreach ($conn->query("SELECT status, COUNT(DISTINCT student_id) AS count FROM public.transcript_requests GROUP BY status")->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (array_key_exists((string)$row['status'], $transferCounts)) $transferCounts[(string)$row['status']] = (int)$row['count'];
}
$studentCount = count($studentRows);
$readyCount = count(array_filter($studentRows, static fn($r) => (int)$r['eligible'] === 1 || (int)$r['has_request'] === 1));
$overallProgress = $studentCount ? round(array_sum(array_map(static fn($r) => (float)$r['progress_percent'], $studentRows))/$studentCount,1) : 0;

$subjectRows = $conn->query("SELECT c.course_id,c.code,c.name,c.subject_type,
    COUNT(DISTINCT sc.student_id) FILTER (WHERE LOWER(COALESCE(sc.status,'')) IN ('passed','completed','tr') OR COALESCE(lp.course_passed,FALSE)) AS passed_count,
    COUNT(DISTINCT sc.student_id) FILTER (WHERE UPPER(COALESCE(sc.status,''))='TR' OR COALESCE(lp.course_transferred,FALSE)) AS transferred_count,
    CASE WHEN COUNT(DISTINCT sc.student_id)=0 THEN 0 ELSE ROUND(100.0*COUNT(DISTINCT sc.student_id) FILTER (WHERE LOWER(COALESCE(sc.status,'')) IN ('passed','completed','tr') OR COALESCE(lp.course_passed,FALSE))/COUNT(DISTINCT sc.student_id),1) END AS pass_rate
FROM public.courses c LEFT JOIN public.student_courses sc ON sc.course_id=c.course_id
LEFT JOIN LATERAL (SELECT BOOL_AND(COALESCE(p.is_transferred,FALSE) OR UPPER(COALESCE(p.grade,''))='TR' OR a.status='passed' OR (a.status <> 'pending_review' AND COALESCE(a.total_score,0)>0 AND a.score>=CEIL(a.total_score))) AS course_passed,
    BOOL_AND(COALESCE(p.is_transferred,FALSE) OR UPPER(COALESCE(p.grade,''))='TR') AS course_transferred
    FROM public.lessons l LEFT JOIN public.lesson_progress p ON p.lesson_id=l.lesson_id AND p.student_id=sc.student_id
    LEFT JOIN LATERAL (SELECT status,score,total_score FROM public.quiz_attempts a WHERE a.student_id=sc.student_id AND a.lesson_id=l.lesson_id ORDER BY submitted_at DESC,attempt_number DESC LIMIT 1) a ON TRUE
    WHERE l.course_id=c.course_id) lp ON TRUE
GROUP BY c.course_id,c.code,c.name,c.subject_type ORDER BY c.code,c.name")->fetchAll(PDO::FETCH_ASSOC);
$pendingRequests = $conn->query("SELECT r.request_id,r.student_id,COALESCE(s.full_name,r.student_id::text) AS full_name,r.original_file_name,r.submitted_at
    FROM public.transcript_requests r LEFT JOIN public.students s ON s.user_id=r.student_id WHERE r.status='pending' ORDER BY r.submitted_at ASC")->fetchAll(PDO::FETCH_ASSOC);
function reportEscape($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>รายงานผู้บริหาร | Flexible Learning Hub</title><link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/css/theme.css?v=20261020-toa-blue-theme"><style>
body{font-family:Kanit,sans-serif;background:#f4f7fb;color:#172033;margin:0}.wrap{max-width:1440px;margin:auto;padding:28px}.top{display:flex;justify-content:space-between;align-items:center;gap:12px}.top a{color:#324860}.kpis{display:grid;grid-template-columns:repeat(4,minmax(180px,1fr));gap:16px;margin:22px 0}.card,.panel{background:white;border:1px solid #e2e8f0;border-radius:14px;padding:18px;box-shadow:0 5px 16px #0f172a0a}.kpi-label{color:#64748b;margin:0}.kpi-value{font-size:1.8rem;font-weight:600;margin:.35rem 0}.kpi-sub{font-size:.8rem;color:#64748b}.panel{margin:18px 0}.panel-head{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}.panel h2{font-size:1.15rem;margin:0 0 12px}.search{padding:9px 12px;border:1px solid #cbd5e1;border-radius:8px;font:inherit;min-width:250px}.scroll{overflow:auto}table{width:100%;border-collapse:collapse;min-width:800px}th,td{text-align:left;padding:11px;border-bottom:1px solid #e8edf3;vertical-align:middle}th{font-weight:500;color:#526179;white-space:nowrap}.badge{padding:3px 9px;border-radius:99px;background:#dcfce7;color:#15803d}.badge.elective{background:#dbeafe;color:#1d4ed8}.progress{white-space:nowrap}.empty{text-align:center;color:#64748b;padding:22px}@media(max-width:850px){.kpis{grid-template-columns:repeat(2,minmax(140px,1fr))}.wrap{padding:16px}}@media(max-width:480px){.kpis{grid-template-columns:1fr 1fr}.kpi-value{font-size:1.35rem}}
</style></head><body><main class="wrap">
<header class="top"><div><h1>รายงานภาพรวมการศึกษา</h1><p>แดชบอร์ดสำหรับประธานและผู้บริหาร</p></div><a href="officer-dashboard.php">กลับหน้าหลัก</a></header>
<section class="kpis">
<article class="card"><p class="kpi-label">นักเรียนทั้งหมด</p><div class="kpi-value"><?= $studentCount ?></div><span class="kpi-sub">คน</span></article>
<article class="card"><p class="kpi-label">ผู้พร้อมจบการศึกษา</p><div class="kpi-value"><?= $readyCount ?></div><span class="kpi-sub">ผ่านวิชาบังคับครบหรือส่งคำขอแล้ว (คน)</span></article>
<article class="card"><p class="kpi-label">คำขอเทียบโอนทรานสคริปต์</p><div class="kpi-value"><?= $transferCounts['pending'] ?> / <?= $transferCounts['approved'] ?> / <?= $transferCounts['rejected'] ?></div><span class="kpi-sub">รออนุมัติ / อนุมัติแล้ว / ปฏิเสธ (คน)</span></article>
<article class="card"><p class="kpi-label">อัตราการจบการศึกษารวม</p><div class="kpi-value"><?= $overallProgress ?>%</div><span class="kpi-sub">ค่าเฉลี่ยความคืบหน้าหลักสูตรของนักเรียน</span></article>
</section>
<section class="panel"><div class="panel-head"><h2>ความคืบหน้านักเรียนรายบุคคล</h2><input id="studentSearch" class="search" type="search" placeholder="ค้นหาชื่อหรือรหัสนักเรียน"></div><div class="scroll"><table id="studentsTable"><thead><tr><th>รหัสนักเรียน</th><th>ชื่อ-นามสกุล</th><th>วิชาบังคับที่ผ่าน</th><th>วิชาเลือกที่ผ่าน</th><th>สถานะหลักสูตร</th><th>สิทธิ์การขอจบ</th></tr></thead><tbody>
<?php foreach($studentRows as $r): ?><tr><td><?= reportEscape($r['student_id']) ?></td><td><?= reportEscape($r['full_name']) ?></td><td><?= (int)$r['compulsory_passed'] ?> / <?= (int)$r['compulsory_total'] ?> วิชา</td><td><?= (int)$r['elective_passed'] ?> วิชา</td><td class="progress"><?= reportEscape($r['progress_percent']) ?>%</td><td><?= $r['eligible']?'พร้อมจบ':'กำลังศึกษา' ?><?= $r['has_request']?' · ส่งคำขอแล้ว':'' ?></td></tr><?php endforeach; ?>
<?php if(!$studentRows): ?><tr><td class="empty" colspan="6">ยังไม่มีข้อมูลนักเรียน</td></tr><?php endif; ?></tbody></table></div></section>
<section class="panel"><h2>สถิติรายวิชา</h2><div class="scroll"><table><thead><tr><th>รหัสวิชา</th><th>ชื่อวิชา</th><th>ประเภทวิชา</th><th>เรียนผ่าน</th><th>เทียบโอน (TR)</th><th>อัตราการผ่าน</th></tr></thead><tbody>
<?php foreach($subjectRows as $r): ?><tr><td><?= reportEscape($r['code'] ?: $r['course_id']) ?></td><td><?= reportEscape($r['name']) ?></td><td><span class="badge <?= $r['subject_type']==='elective'?'elective':'' ?>"><?= $r['subject_type']==='compulsory'?'วิชาบังคับ':'วิชาเลือก' ?></span></td><td><?= (int)$r['passed_count'] ?> คน</td><td><?= (int)$r['transferred_count'] ?> คน</td><td><?= reportEscape($r['pass_rate']) ?>%</td></tr><?php endforeach; ?>
<?php if(!$subjectRows): ?><tr><td class="empty" colspan="6">ยังไม่มีข้อมูลรายวิชา</td></tr><?php endif; ?></tbody></table></div></section>
<section class="panel"><h2>คำขอเทียบโอนทรานสคริปต์ที่รออนุมัติ (<?= count($pendingRequests) ?>)</h2><div class="scroll"><table><thead><tr><th>รหัสนักเรียน</th><th>ชื่อ-นามสกุล</th><th>ไฟล์</th><th>วันที่ส่ง</th><th>สถานะ</th></tr></thead><tbody><?php foreach($pendingRequests as $r): ?><tr><td><?= reportEscape($r['student_id']) ?></td><td><?= reportEscape($r['full_name']) ?></td><td><?= reportEscape($r['original_file_name']) ?></td><td><?= reportEscape($r['submitted_at']) ?></td><td>รออนุมัติ</td></tr><?php endforeach; ?><?php if(!$pendingRequests): ?><tr><td class="empty" colspan="5">ไม่มีคำขอค้างอนุมัติ</td></tr><?php endif; ?></tbody></table></div></section>
</main><script>document.getElementById('studentSearch').addEventListener('input',function(){const q=this.value.trim().toLocaleLowerCase();document.querySelectorAll('#studentsTable tbody tr').forEach(r=>{r.hidden=!r.textContent.toLocaleLowerCase().includes(q)})});</script></body></html>
