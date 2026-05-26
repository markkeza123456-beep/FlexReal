<?php
session_start();
require_once 'db_connect.php';
require_once __DIR__ . '/learning_progress_lib.php';
header('Content-Type: application/json; charset=utf-8');

function out(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function tableColumns(PDO $conn, string $schema, string $table): array
{
    static $cache = [];
    $key = "{$schema}.{$table}";
    if (isset($cache[$key])) return $cache[$key];

    $stmt = $conn->prepare(
        "SELECT column_name FROM information_schema.columns
         WHERE table_schema = :schema AND table_name = :table"
    );
    $stmt->execute([':schema' => $schema, ':table' => $table]);
    return $cache[$key] = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function nextTestId(PDO $conn): int
{
    $stmt = $conn->query("SELECT COALESCE(MAX(test_id), 0) + 1 FROM public.test");
    return (int) $stmt->fetchColumn();
}

function insertTestAttempt(PDO $conn, array $columns, array $data): int
{
    $available = [];
    $params    = [];
    foreach ($data as $col => $val) {
        if (in_array($col, $columns, true)) {
            $available[] = $col;
            $params[':' . $col] = $val;
        }
    }

    if (empty($available)) {
        throw new Exception('ตาราง test ไม่มีคอลัมน์รองรับการบันทึก');
    }

    $insertCols = implode(', ', $available);
    $insertVals = implode(', ', array_map(fn($c) => ':' . $c, $available));
    $hasReturning = in_array('test_id', $columns, true);
    $sql = "INSERT INTO public.test ({$insertCols}) VALUES ({$insertVals})"
         . ($hasReturning ? ' RETURNING test_id' : '');

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    if ($hasReturning) {
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : 0;
    }
    return 0;
}

function normalizeAnswerLetter(string $raw): string
{
    $value = strtoupper(trim($raw));
    if ($value === '') return '';
    if (preg_match('/[ABCD]/', $value, $m) === 1) return $m[0];
    return '';
}

function loadQuestionsForScoring(PDO $conn, string $subjectId, int $lessonIndex, string $lessonId): array
{
    $qCols     = tableColumns($conn, 'public', 'quiz_questions');
    $answerCol = 'correct_answer';
    if (!in_array($answerCol, $qCols, true)) {
        $answerCol = in_array('correct_option', $qCols, true) ? 'correct_option'
                   : (in_array('answer', $qCols, true)       ? 'answer' : "''");
    }

    $stmt = $conn->prepare(
        "SELECT quiz_id AS qid, {$answerCol} AS correct_answer
         FROM public.quiz_questions
         WHERE subjects_id = :sid AND lesson_no = :lno
         ORDER BY quiz_id ASC"
    );
    $stmt->execute([':sid' => $subjectId, ':lno' => $lessonIndex]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($rows)) return ['source' => 'quiz_questions', 'rows' => $rows];

    $stmt2 = $conn->prepare(
        "SELECT questions_id AS qid, correct_answer
         FROM public.test_questions
         WHERE lessons_id = ?
         ORDER BY questions_id ASC"
    );
    $stmt2->execute([$lessonId]);
    return ['source' => 'test_questions', 'rows' => $stmt2->fetchAll(PDO::FETCH_ASSOC)];
}

$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload)) {
    out(['status' => 'error', 'message' => 'รูปแบบข้อมูลไม่ถูกต้อง'], 400);
}

$role = strtolower((string) ($_SESSION['role'] ?? ''));
if (!isset($_SESSION['user_id']) || $role !== 'student') {
    out(['status' => 'unauthorized', 'message' => 'กรุณาเข้าสู่ระบบก่อนทำแบบทดสอบ'], 401);
}

$studentId   = (string) $_SESSION['user_id'];
$subjectId   = trim((string) ($payload['subject_id']  ?? ''));
$lessonIndex = max(1, (int) ($payload['lesson_index'] ?? $payload['lesson_no'] ?? 1));
$courseName  = trim((string) ($payload['course_name'] ?? ''));
$answers     = is_array($payload['answers'] ?? null) ? $payload['answers'] : [];

if ($subjectId === '') {
    out(['status' => 'error', 'message' => 'ไม่พบรหัสรายวิชา'], 400);
}

try {
    $conn->beginTransaction();
    ensureLearningProgressTables($conn);

    $s = $conn->prepare('SELECT subjects_name FROM public.subjects WHERE subjects_id = ? LIMIT 1');
    $s->execute([$subjectId]);
    $subjectName = (string) ($s->fetchColumn() ?: $courseName);

    $s = $conn->prepare(
        "SELECT lessons_id, lessons_name FROM public.lessons
         WHERE subjects_id = ? ORDER BY lessons_id ASC LIMIT 1 OFFSET ?"
    );
    $s->execute([$subjectId, $lessonIndex - 1]);
    $lessonRow = $s->fetch(PDO::FETCH_ASSOC);
    if (!$lessonRow || empty($lessonRow['lessons_id'])) {
        throw new Exception('ไม่พบบทเรียนที่ต้องการบันทึกผล');
    }
    $lessonId    = (string) $lessonRow['lessons_id'];
    $lessonTitle = (string) ($lessonRow['lessons_name'] ?? "บทที่ {$lessonIndex}");

    $questionPayload = loadQuestionsForScoring($conn, $subjectId, $lessonIndex, $lessonId);
    $questions       = $questionPayload['rows'];
    $questionSource  = $questionPayload['source'];
    $totalScore      = count($questions);
    $score           = 0;
    $choiceMap       = [0 => 'A', 1 => 'B', 2 => 'C', 3 => 'D'];

    // 💥 ระบบตรวจคะแนน Server-side
    foreach ($questions as $index => $q) {
        $selected = '';
        if (array_key_exists($index, $answers) && $answers[$index] !== null) {
            // เช็คว่าเป็นตัวเลข (ปรนัย) หรือข้อความ (ข้อเขียน)
            if (is_numeric($answers[$index])) {
                $selected = $choiceMap[(int) $answers[$index]] ?? '';
            } else {
                $selected = trim((string) $answers[$index]);
            }
        }

        $correct = normalizeAnswerLetter((string) ($q['correct_answer'] ?? ''));
        
        // ถ้าเป็นข้อเขียน ระบบจะข้ามการให้คะแนนออโต้ไปก่อน
        if ($correct !== '-' && (isset($q['correct_answer']) && $q['correct_answer'] !== '-')) {
            if ($correct !== '' && $selected === $correct) {
                $score++;
            }
        }
    }

    $requiredScore = max(1, (int) ceil(max(1, $totalScore) * 0.6));
    $quizStatus    = $score >= $requiredScore ? 'pass' : 'fail';

    $attemptNo = 1;
    try {
        $s = $conn->prepare(
            "SELECT COUNT(*) FROM public.test
             WHERE student_id = :sid
               AND (subjects_id = :subid OR course_name = :cname)
               AND lesson_no = :lno"
        );
        $s->execute([':sid' => $studentId, ':subid' => $subjectId, ':cname' => $subjectName, ':lno' => $lessonIndex]);
        $attemptNo = ((int) $s->fetchColumn()) + 1;
    } catch (Throwable $ignored) {}

    $testColumns = tableColumns($conn, 'public', 'test');

    $testData = [
        'student_id'   => $studentId,
        'subjects_id'  => $subjectId,
        'course_name'  => $subjectName,
        'lesson_no'    => $lessonIndex,
        'score'        => $score,
        'total_score'  => $totalScore,
        'status'       => $quizStatus,
        'test_attempt' => $attemptNo,
    ];
    if (in_array('test_id', $testColumns, true)) {
        $testData['test_id'] = nextTestId($conn);
    }
    $testId = insertTestAttempt($conn, $testColumns, $testData);

    $answerCols   = tableColumns($conn, 'public', 'test_answers');
    $canSaveAns   = !empty($answerCols)
                  && in_array('questions_id',   $answerCols, true)
                  && in_array('selected_choice', $answerCols, true)
                  && in_array('test_id',         $answerCols, true)
                  && $testId > 0
                  && !empty($questions);

    // 💥 บันทึกคำตอบข้อเขียนหรือปรนัยลง DB
    if ($canSaveAns) {
        $stmtAns = $conn->prepare(
            "INSERT INTO public.test_answers (questions_id, test_id, selected_choice) VALUES (?, ?, ?)"
        );
        foreach ($questions as $index => $q) {
            $selected = '-';
            if (array_key_exists($index, $answers) && $answers[$index] !== null) {
                if (is_numeric($answers[$index])) {
                    $selected = $choiceMap[(int) $answers[$index]] ?? '-';
                } else {
                    $selected = mb_substr(trim((string) $answers[$index]), 0, 500); // กันพิมข้อความยาวเกิน
                }
            }
            $stmtAns->execute([(int) $q['qid'], $testId, $selected]);
        }
    }

    recordLearningActivity(
        $conn,
        $studentId,
        $subjectId,
        $lessonIndex,
        'quiz_submit',
        $lessonTitle,
        $score,
        $totalScore
    );

    $conn->commit();

    out([
        'status'         => 'success',
        'quiz_status'    => $quizStatus,
        'score'          => $score,
        'total_score'    => $totalScore,
        'required_score' => $requiredScore,
        'message'        => 'บันทึกผลสอบเรียบร้อย',
    ]);

} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    error_log('[test_submit] ' . $e->getMessage());
    out(['status' => 'error', 'message' => 'DB Error: ' . $e->getMessage()], 500);
}