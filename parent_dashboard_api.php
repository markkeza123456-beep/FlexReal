<?php
/**
 * parent_dashboard_api.php
 * ดึงข้อมูลผู้ปกครอง + ลูกทุกคน โดยใช้ student.parent_id = parents.parents_id
 */

header('Content-Type: application/json; charset=utf-8');

define('SB_URL', 'https://gwunrmptlmfpvidrxwdf.supabase.co');
define('SB_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imd3dW5ybXB0bG1mcHZpZHJ4d2RmIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzY2NDY3ODUsImV4cCI6MjA5MjIyMjc4NX0.TvvgwaVxPIRzCguAH7x58vUEi2od31QeTXypRxaFMxA');  // ← ใส่ anon key ของคุณ

session_start();

// login_action.php บันทึก session เป็น 'user_id' และ role='parent'
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'parent') {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อน', 'redirect' => 'login.php']);
    exit;
}

$parentId = $_SESSION['user_id'];

/* ── Helper ── */
function sbGet(string $table, array $params): array {
    $url = SB_URL . '/rest/v1/' . $table . '?' . http_build_query($params);
    $ctx = stream_context_create(['http' => [
        'method'  => 'GET',
        'header'  => "apikey: " . SB_KEY . "\r\nAuthorization: Bearer " . SB_KEY . "\r\nAccept: application/json",
        'timeout' => 10,
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/* ── 1. ข้อมูลผู้ปกครอง ── */
$parents = sbGet('parents', [
    'parents_id' => 'eq.' . $parentId,
    'select'     => 'parents_id,parents_name,email,tel',
    'limit'      => 1,
]);

if (empty($parents)) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลผู้ปกครอง']);
    exit;
}
$parent = $parents[0];

/* ── 2. หาลูกทุกคนโดย student.parent_id = parents_id ── */
$students = sbGet('student', [
    'parent_id' => 'eq.' . $parentId,
    'select'    => 'student_id,student_name,studcurriculums_id,avatar_url',
]);

$children = [];

foreach ($students as $stu) {
    $sid = $stu['student_id'];

    /* ── 2a. คะแนนรายวิชา ── */
    $subjectRows = sbGet('student_subject', [
        'student_id' => 'eq.' . $sid,
        'select'     => 'subject_id,score_mid,score_final,grade,total_score',
    ]);

    $subjects = [];
    if (!empty($subjectRows)) {
        $subjectIds  = array_column($subjectRows, 'subject_id');
        $subjectList = sbGet('subjects', [
            'subject_id' => 'in.(' . implode(',', $subjectIds) . ')',
            'select'     => 'subject_id,subject_name',
        ]);
        $subjectMap = array_column($subjectList, 'subject_name', 'subject_id');

        foreach ($subjectRows as $row) {
            $total = $row['total_score'] ?? ($row['score_mid'] + $row['score_final']);
            $subjects[] = [
                'subject_name' => $subjectMap[$row['subject_id']] ?? 'วิชา ' . $row['subject_id'],
                'score_mid'    => $row['score_mid']   ?? 0,
                'score_final'  => $row['score_final'] ?? 0,
                'total_score'  => $total,
                'grade'        => $row['grade'] ?? gradeFromScore($total),
            ];
        }
    }

    /* ── 2b. สถิติ ── */
    $scores      = array_column($subjects, 'total_score');
    $totalSub    = count($subjects);
    $gpa         = $totalSub > 0 ? round(array_sum(array_map('gpaPoint', $scores)) / $totalSub, 2) : 0;
    $gradeACount = count(array_filter($subjects, fn($s) => str_starts_with($s['grade'], 'A')));
    $topScore    = $totalSub > 0 ? max($scores) : 0;
    $topSubject  = '';
    foreach ($subjects as $s) {
        if ($s['total_score'] == $topScore) { $topSubject = $s['subject_name']; break; }
    }

    $children[] = [
        'student_id'    => $sid,
        'student_name'  => $stu['student_name'],
        'student_level' => $stu['studcurriculums_id'] ?? '',
        'avatar_url'    => $stu['avatar_url'] ?? null,
        'initial'       => mb_substr($stu['student_name'], 0, 1),
        'subjects'      => $subjects,
        'stats' => [
            'gpa'         => $gpa,
            'grade_a'     => $gradeACount,
            'top_score'   => $topScore,
            'top_subject' => $topSubject,
        ],
    ];
}

function gradeFromScore(float $s): string {
    if ($s >= 80) return 'A';  if ($s >= 75) return 'B+'; if ($s >= 70) return 'B';
    if ($s >= 65) return 'C+'; if ($s >= 60) return 'C';  if ($s >= 55) return 'D+';
    if ($s >= 50) return 'D';  return 'F';
}
function gpaPoint(float $s): float {
    if ($s >= 80) return 4.0; if ($s >= 75) return 3.5; if ($s >= 70) return 3.0;
    if ($s >= 65) return 2.5; if ($s >= 60) return 2.0; if ($s >= 55) return 1.5;
    if ($s >= 50) return 1.0; return 0.0;
}

echo json_encode(['status' => 'success', 'parent' => $parent, 'children' => $children], JSON_UNESCAPED_UNICODE);