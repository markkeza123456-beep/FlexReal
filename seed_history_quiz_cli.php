<?php
require_once __DIR__ . '/db_connect.php';

function ensureHistorySubject(PDO $conn): array {
    $name = 'ประวัติศาสตร์';
    $stmt = $conn->prepare("SELECT subjects_id FROM public.subjects WHERE subjects_name = ? LIMIT 1");
    $stmt->execute([$name]);
    $id = $stmt->fetchColumn();
    if ($id) return ['id'=>(string)$id,'created'=>false];

    $idStmt = $conn->query("SELECT subjects_id FROM public.subjects WHERE subjects_id LIKE 'SUB%' ORDER BY LENGTH(subjects_id) DESC, subjects_id DESC LIMIT 1");
    $last = (string)($idStmt->fetchColumn() ?: 'SUB000');
    $num = intval(substr($last, 3)) + 1;
    $newId = 'SUB' . str_pad((string)$num, 3, '0', STR_PAD_LEFT);

    $ins = $conn->prepare("INSERT INTO public.subjects (subjects_id, subjects_name, subjects_description) VALUES (?, ?, ?)");
    $ins->execute([$newId, $name, 'เรียนรู้เรื่องราวและเหตุการณ์สำคัญในอดีต']);
    return ['id'=>$newId,'created'=>true];
}

function quizCols(PDO $conn): array {
    $stmt = $conn->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_schema='public' AND table_name='quiz_questions'");
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $out[(string)$row['column_name']] = (string)$row['data_type'];
    }
    return $out;
}

function nextQuizId(PDO $conn): int {
    $stmt = $conn->query("SELECT COALESCE(MAX(quiz_id),0)+1 FROM public.quiz_questions");
    return (int)$stmt->fetchColumn();
}

$seed = [
    ['ประเทศไทยเปลี่ยนแปลงการปกครองเป็นระบอบประชาธิปไตยในปีใด?', 'พ.ศ. 2475', 'พ.ศ. 2489', 'พ.ศ. 2453', 'พ.ศ. 2500', 'A'],
    ['กรุงศรีอยุธยาเป็นราชธานีของไทยนานประมาณกี่ปี?', '117 ปี', '220 ปี', '417 ปี', '500 ปี', 'C'],
    ['พ่อขุนรามคำแหงมหาราชทรงมีความสำคัญด้านใด?', 'การประดิษฐ์อักษรไทย', 'การสร้างรถไฟ', 'การตั้งกรุงเทพฯ', 'การเลิกทาส', 'A'],
    ['เหตุการณ์ใดเกิดขึ้นก่อนที่สุด?', 'ตั้งกรุงรัตนโกสินทร์', 'เสียกรุงศรีอยุธยาครั้งที่ 2', 'สุโขทัยเป็นราชธานี', 'เปลี่ยนแปลงการปกครอง', 'C'],
    ['ข้อใดคือหลักฐานทางประวัติศาสตร์ชั้นต้น?', 'หนังสือเรียนสังคม', 'ภาพยนตร์สารคดี', 'ศิลาจารึก', 'บทความสรุป', 'C'],
];

try {
    $conn->beginTransaction();
    $subject = ensureHistorySubject($conn);
    $subjectId = $subject['id'];

    $cols = quizCols($conn);
    if (empty($cols)) throw new Exception('ไม่พบตาราง quiz_questions');

    $countStmt = $conn->prepare("SELECT COUNT(*) FROM public.quiz_questions WHERE subjects_id = ? AND lesson_no = ?");

    $inserted = 0;
    for ($lessonNo = 1; $lessonNo <= 5; $lessonNo++) {
        $countStmt->execute([$subjectId, $lessonNo]);
        $existing = (int)$countStmt->fetchColumn();
        if ($existing >= 5) {
            continue;
        }
        $colNames = array_keys($cols);
        $qid = in_array('quiz_id', $colNames, true) ? nextQuizId($conn) : 0;
        $insCols = ['subjects_id','lesson_no','question_text','option_a','option_b','option_c','option_d'];
        $ansCol = in_array('correct_answer',$colNames,true) ? 'correct_answer' : (in_array('correct_option',$colNames,true) ? 'correct_option' : 'answer');
        $insCols[] = $ansCol;
        if (in_array('quiz_id', $colNames, true)) array_unshift($insCols, 'quiz_id');

        $sql = "INSERT INTO public.quiz_questions (" . implode(',', $insCols) . ") VALUES (" . implode(',', array_map(fn($c)=>':' . $c, $insCols)) . ")";
        $ins = $conn->prepare($sql);

        for ($i=$existing; $i<5; $i++) {
            [$q,$a,$b,$c,$d,$ans] = $seed[$i % count($seed)];
            $answerValue = $ans;
            if (($cols[$ansCol] ?? '') === 'integer' || ($cols[$ansCol] ?? '') === 'bigint' || ($cols[$ansCol] ?? '') === 'smallint') {
                $map = ['A' => 1, 'B' => 2, 'C' => 3, 'D' => 4];
                $answerValue = $map[$ans] ?? 1;
            }
            $p = [
                ':subjects_id'=>$subjectId, ':lesson_no'=>$lessonNo, ':question_text'=>$q,
                ':option_a'=>$a, ':option_b'=>$b, ':option_c'=>$c, ':option_d'=>$d,
                ':' . $ansCol => $answerValue
            ];
            if (in_array('quiz_id',$colNames,true)) $p[':quiz_id'] = $qid++;
            $ins->execute($p);
            $inserted++;
        }
    }

    $conn->commit();
    echo json_encode(['status'=>'success','subject_id'=>$subjectId,'subject_created'=>$subject['created'],'inserted_quiz_questions'=>$inserted], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['status'=>'error','message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit(1);
}
?>
