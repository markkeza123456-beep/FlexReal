<?php
require_once __DIR__ . '/db_connect.php';

function ensureScienceSubject(PDO $conn): array {
    $name = 'วิทยาศาสตร์';
    $stmt = $conn->prepare("SELECT subjects_id FROM public.subjects WHERE subjects_name = ? LIMIT 1");
    $stmt->execute([$name]);
    $id = $stmt->fetchColumn();
    if ($id) return ['id' => (string)$id, 'created' => false];

    $idStmt = $conn->query("SELECT subjects_id FROM public.subjects WHERE subjects_id LIKE 'SUB%' ORDER BY LENGTH(subjects_id) DESC, subjects_id DESC LIMIT 1");
    $last = (string)($idStmt->fetchColumn() ?: 'SUB000');
    $num = intval(substr($last, 3)) + 1;
    $newId = 'SUB' . str_pad((string)$num, 3, '0', STR_PAD_LEFT);

    $ins = $conn->prepare("INSERT INTO public.subjects (subjects_id, subjects_name, subjects_description) VALUES (?, ?, ?)");
    $ins->execute([$newId, $name, 'เจาะลึกกระบวนการคิดทางวิทยาศาสตร์']);
    return ['id' => $newId, 'created' => true];
}

function quizColumnTypes(PDO $conn): array {
    $stmt = $conn->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_schema='public' AND table_name='quiz_questions'");
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $out[(string)$row['column_name']] = (string)$row['data_type'];
    return $out;
}

function nextQuizId(PDO $conn): int {
    $stmt = $conn->query("SELECT COALESCE(MAX(quiz_id),0)+1 FROM public.quiz_questions");
    return (int)$stmt->fetchColumn();
}

function answerValue(string $ans, string $type) {
    if (in_array($type, ['integer', 'bigint', 'smallint'], true)) {
        $map = ['A' => 1, 'B' => 2, 'C' => 3, 'D' => 4];
        return $map[$ans] ?? 1;
    }
    return $ans;
}

function scienceQuestions(int $lessonNo): array {
    return [
        ["บทที่ {$lessonNo}: แหล่งพลังงานหลักของโลกคือข้อใด", 'ดวงอาทิตย์', 'ดวงจันทร์', 'ลม', 'น้ำใต้ดิน', 'A'],
        ["บทที่ {$lessonNo}: น้ำเดือดที่อุณหภูมิเท่าไร", '0°C', '50°C', '100°C', '120°C', 'A'],
        ["บทที่ {$lessonNo}: อวัยวะใดสูบฉีดเลือด", 'ปอด', 'หัวใจ', 'ตับ', 'ไต', 'A'],
        ["บทที่ {$lessonNo}: ของเหลวเปลี่ยนเป็นไอเรียกว่าอะไร", 'ควบแน่น', 'ระเหย', 'แข็งตัว', 'หลอมเหลว', 'A'],
        ["บทที่ {$lessonNo}: พืชสร้างอาหารด้วยกระบวนการใด", 'ย่อยอาหาร', 'หายใจ', 'สังเคราะห์ด้วยแสง', 'คายน้ำ', 'A'],
    ];
}

try {
    $conn->beginTransaction();
    $subject = ensureScienceSubject($conn);
    $subjectId = $subject['id'];

    $cols = quizColumnTypes($conn);
    if (empty($cols)) throw new Exception('ไม่พบตาราง quiz_questions');
    $colNames = array_keys($cols);
    $answerCol = in_array('correct_answer', $colNames, true) ? 'correct_answer' : (in_array('correct_option', $colNames, true) ? 'correct_option' : 'answer');

    $countStmt = $conn->prepare("SELECT COUNT(*) FROM public.quiz_questions WHERE subjects_id = ? AND lesson_no = ?");
    $inserted = 0;

    for ($lessonNo = 1; $lessonNo <= 5; $lessonNo++) {
        $countStmt->execute([$subjectId, $lessonNo]);
        $existing = (int)$countStmt->fetchColumn();
        if ($existing >= 5) continue;

        $seed = scienceQuestions($lessonNo);
        $qid = in_array('quiz_id', $colNames, true) ? nextQuizId($conn) : 0;
        $insCols = ['subjects_id', 'lesson_no', 'question_text', 'option_a', 'option_b', 'option_c', 'option_d', $answerCol];
        if (in_array('quiz_id', $colNames, true)) array_unshift($insCols, 'quiz_id');
        $sql = "INSERT INTO public.quiz_questions (" . implode(',', $insCols) . ") VALUES (" . implode(',', array_map(fn($c) => ':' . $c, $insCols)) . ")";
        $ins = $conn->prepare($sql);

        for ($i = $existing; $i < 5; $i++) {
            [$q,$a,$b,$c,$d,$ans] = $seed[$i % count($seed)];
            $params = [
                ':subjects_id' => $subjectId,
                ':lesson_no' => $lessonNo,
                ':question_text' => $q,
                ':option_a' => $a,
                ':option_b' => $b,
                ':option_c' => $c,
                ':option_d' => $d,
                ':' . $answerCol => answerValue($ans, (string)$cols[$answerCol]),
            ];
            if (in_array('quiz_id', $colNames, true)) $params[':quiz_id'] = $qid++;
            $ins->execute($params);
            $inserted++;
        }
    }

    $conn->commit();
    echo json_encode(['status' => 'success', 'subject_id' => $subjectId, 'subject_created' => $subject['created'], 'inserted_quiz_questions' => $inserted], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit(1);
}
?>
