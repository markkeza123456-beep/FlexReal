<?php
require_once __DIR__ . '/db_connect.php';

function quizColumnTypes(PDO $conn): array {
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

function toAnswerValue(string $ans, string $answerType) {
    if (in_array($answerType, ['integer', 'bigint', 'smallint'], true)) {
        $map = ['A' => 1, 'B' => 2, 'C' => 3, 'D' => 4];
        return $map[$ans] ?? 1;
    }
    return $ans;
}

function loadSubjectSeed(string $key): callable {
    $file = __DIR__ . '/subject_quiz_seeds/' . $key . '.php';
    if (!is_file($file)) {
        $file = __DIR__ . '/subject_quiz_seeds/default.php';
    }
    $callable = require $file;
    if (!is_callable($callable)) {
        throw new Exception("seed file invalid: {$key}");
    }
    return $callable;
}

function subjectQuestionSet(string $subjectId, string $subjectName, int $lessonNo): array {
    $name = mb_strtolower($subjectName, 'UTF-8');
    $seedKey = 'default';
    if ($subjectId === 'SUB001' || str_contains($name, 'อังกฤษ')) $seedKey = 'english';
    elseif ($subjectId === 'SUB002' || str_contains($name, 'คณิต')) $seedKey = 'math';
    elseif ($subjectId === 'SUB003' || str_contains($name, 'วิทย')) $seedKey = 'science';
    elseif ($subjectId === 'SUB004' || str_contains($name, 'ประวัติ')) $seedKey = 'history';
    elseif ($subjectId === 'SUB005' || str_contains($name, 'ศิลปะ')) $seedKey = 'art';

    $fn = loadSubjectSeed($seedKey);
    return $fn($lessonNo);
}

try {
    $cols = quizColumnTypes($conn);
    if (empty($cols)) {
        throw new Exception('ไม่พบตาราง quiz_questions');
    }
    $colNames = array_keys($cols);
    $answerCol = in_array('correct_answer', $colNames, true) ? 'correct_answer' : (in_array('correct_option', $colNames, true) ? 'correct_option' : 'answer');

    $subjects = $conn->query("SELECT subjects_id, subjects_name FROM public.subjects ORDER BY subjects_id ASC")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($subjects)) {
        throw new Exception('ไม่พบวิชาในตาราง subjects');
    }

    $countStmt = $conn->prepare("SELECT COUNT(*) FROM public.quiz_questions WHERE subjects_id = ? AND lesson_no = ?");
    $conn->beginTransaction();
    $totalInserted = 0;
    $summary = [];

    foreach ($subjects as $subject) {
        $subjectId = (string)$subject['subjects_id'];
        $subjectName = (string)($subject['subjects_name'] ?? '');
        $inserted = 0;

        for ($lessonNo = 1; $lessonNo <= 5; $lessonNo++) {
            $countStmt->execute([$subjectId, $lessonNo]);
            $existing = (int)$countStmt->fetchColumn();
            if ($existing >= 5) continue;

            $seed = subjectQuestionSet($subjectId, $subjectName, $lessonNo);
            $qid = in_array('quiz_id', $colNames, true) ? nextQuizId($conn) : 0;
            $insCols = ['subjects_id','lesson_no','question_text','option_a','option_b','option_c','option_d', $answerCol];
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
                    ':' . $answerCol => toAnswerValue($ans, (string)$cols[$answerCol]),
                ];
                if (in_array('quiz_id', $colNames, true)) {
                    $params[':quiz_id'] = $qid++;
                }
                $ins->execute($params);
                $inserted++;
            }
        }

        $summary[] = ['subjects_id' => $subjectId, 'subjects_name' => $subjectName, 'inserted' => $inserted];
        $totalInserted += $inserted;
    }

    $conn->commit();
    echo json_encode(['status' => 'success', 'total_inserted' => $totalInserted, 'summary' => $summary], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit(1);
}
?>
