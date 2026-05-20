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
    $stmt = $conn->prepare(
        "SELECT column_name
         FROM information_schema.columns
         WHERE table_schema = :schema AND table_name = :table"
    );
    $stmt->execute([':schema' => $schema, ':table' => $table]);
    return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

<<<<<<< Updated upstream
function dynamicInsert(PDO $conn, string $table, array $columns, array $data): int
{
    $usable = [];
    $params = [];
    foreach ($data as $column => $value) {
        if (in_array($column, $columns, true)) {
            $usable[] = $column;
            $params[':' . $column] = $value;
        }
    }

    if (empty($usable)) {
        throw new Exception("ไม่มีคอลัมน์รองรับการบันทึกในตาราง {$table}");
    }

    $cols = implode(', ', $usable);
    $vals = implode(', ', array_map(fn($c) => ':' . $c, $usable));
    $returning = in_array('test_id', $columns, true) ? ' RETURNING test_id' : '';
    $sql = "INSERT INTO public.{$table} ({$cols}) VALUES ({$vals}){$returning}";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    if ($returning !== '') {
=======
function insertTestAttempt(PDO $conn, array $columns, array $data): int
{
    $available = [];
    $params = [];
    foreach ($data as $column => $value) {
        if (in_array($column, $columns, true)) {
            $available[] = $column;
            $params[":" . $column] = $value;
        }
    }

    if (empty($available)) {
        throw new Exception('ตาราง test ไม่มีคอลัมน์ที่รองรับการบันทึกผลสอบ');
    }

    $insertCols = implode(', ', $available);
    $insertVals = implode(', ', array_map(fn($c) => ":" . $c, $available));
    $sql = "INSERT INTO public.test ($insertCols) VALUES ($insertVals)";

    $supportsReturning = in_array('test_id', $columns, true);
    if ($supportsReturning) {
        $sql .= " RETURNING test_id";
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    if ($supportsReturning) {
>>>>>>> Stashed changes
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : 0;
    }
    return 0;
}

<<<<<<< Updated upstream
$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
=======
function normalizeAnswerLetter(string $raw): string
{
    $value = strtoupper(trim($raw));
    if ($value === '') {
        return '';
    }
    if (preg_match('/[ABCD]/', $value, $m) === 1) {
        return $m[0];
    }
    return '';
}

function loadQuestionsForScoring(PDO $conn, string $subjectId, int $lessonIndex, string $lessonId): array
{
    $qCols = tableColumns($conn, 'public', 'quiz_questions');
    $answerCol = 'correct_answer';
    if (!in_array($answerCol, $qCols, true)) {
        if (in_array('correct_option', $qCols, true)) {
            $answerCol = 'correct_option';
        } elseif (in_array('answer', $qCols, true)) {
            $answerCol = 'answer';
        } else {
            $answerCol = "''";
        }
    }

    $stmtQuiz = $conn->prepare(
        "SELECT quiz_id AS qid, {$answerCol} AS correct_answer
         FROM public.quiz_questions
         WHERE subjects_id = :subjects_id AND lesson_no = :lesson_no
         ORDER BY quiz_id ASC"
    );
    $stmtQuiz->execute([
        ':subjects_id' => $subjectId,
        ':lesson_no' => $lessonIndex,
    ]);
    $quizRows = $stmtQuiz->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($quizRows)) {
        return ['source' => 'quiz_questions', 'rows' => $quizRows];
    }

    $stmtTest = $conn->prepare(
        "SELECT questions_id AS qid, correct_answer
         FROM public.test_questions
         WHERE lessons_id = ?
         ORDER BY questions_id ASC"
    );
    $stmtTest->execute([$lessonId]);
    return ['source' => 'test_questions', 'rows' => $stmtTest->fetchAll(PDO::FETCH_ASSOC)];
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
>>>>>>> Stashed changes
    out(['status' => 'error', 'message' => 'รูปแบบข้อมูลไม่ถูกต้อง'], 400);
}

$role = strtolower((string) ($_SESSION['role'] ?? ''));
if (!isset($_SESSION['user_id']) || $role !== 'student') {
    out(['status' => 'unauthorized', 'message' => 'กรุณาเข้าสู่ระบบก่อนทำแบบทดสอบ'], 401);
}

$studentId = (string) $_SESSION['user_id'];
<<<<<<< Updated upstream
$subjectId = trim((string) ($data['subject_id'] ?? ''));
$lessonIndex = max(1, (int) ($data['lesson_index'] ?? 1));
$score = (int) ($data['score'] ?? 0);
$totalScore = max(0, (int) ($data['total_score'] ?? 0));
$answers = is_array($data['answers'] ?? null) ? $data['answers'] : [];
$requiredScore = max(1, (int) ceil(max(1, $totalScore) * 0.6));
$quizStatus = $score >= $requiredScore ? 'pass' : 'fail';
=======
$subjectId = trim((string) ($payload['subject_id'] ?? ''));
$lessonIndex = max(1, (int) ($payload['lesson_index'] ?? 1));
$score = 0;
$totalScore = 0;
$answers = is_array($payload['answers'] ?? null) ? $payload['answers'] : [];
$courseName = trim((string) ($payload['course_name'] ?? ''));
>>>>>>> Stashed changes

if ($subjectId === '') {
    out(['status' => 'error', 'message' => 'ไม่พบรหัสรายวิชา'], 400);
}

try {
    $conn->beginTransaction();
    ensureLearningProgressTables($conn);

<<<<<<< Updated upstream
    $offset = $lessonIndex - 1;
    $stmtLesson = $conn->prepare("SELECT lessons_id FROM public.lessons WHERE subjects_id = ? ORDER BY lessons_id ASC LIMIT 1 OFFSET ?");
    $stmtLesson->execute([$subjectId, $offset]);
    $lessonId = (string) ($stmtLesson->fetchColumn() ?: '');
    if ($lessonId === '') {
        throw new Exception('ไม่พบบทเรียน');
    }

    $testColumns = tableColumns($conn, 'public', 'test');
    $testData = [
        'student_id' => $studentId,
        'lessons_id' => $lessonId,
        'subjects_id' => $subjectId,
        'lesson_no' => $lessonIndex,
        'score' => $score,
        'total_score' => $totalScore,
        'status' => $quizStatus,
    ];
    $testId = dynamicInsert($conn, 'test', $testColumns, $testData);

    $stmtQ = $conn->prepare("SELECT questions_id FROM public.test_questions WHERE lessons_id = ? ORDER BY questions_id ASC");
    $stmtQ->execute([$lessonId]);
    $questions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);

    $answerColumns = tableColumns($conn, 'public', 'test_answers');
    $canSaveAnswers = in_array('questions_id', $answerColumns, true) && in_array('selected_choice', $answerColumns, true);
    $hasTestId = in_array('test_id', $answerColumns, true);
    $choiceMap = [0 => 'A', 1 => 'B', 2 => 'C', 3 => 'D'];

    if ($canSaveAnswers && $hasTestId && $testId > 0) {
        $stmtAns = $conn->prepare("INSERT INTO public.test_answers (questions_id, test_id, selected_choice) VALUES (?, ?, ?)");
=======
    $subjectStmt = $conn->prepare('SELECT subjects_name FROM public.subjects WHERE subjects_id = ? LIMIT 1');
    $subjectStmt->execute([$subjectId]);
    $subjectName = (string) ($subjectStmt->fetchColumn() ?: $courseName);

    $offset = $lessonIndex - 1;
    $lessonStmt = $conn->prepare("SELECT lessons_id, lessons_name FROM public.lessons WHERE subjects_id = ? ORDER BY lessons_id ASC LIMIT 1 OFFSET ?");
    $lessonStmt->execute([$subjectId, $offset]);
    $lessonRow = $lessonStmt->fetch(PDO::FETCH_ASSOC);
    if (!$lessonRow || empty($lessonRow['lessons_id'])) {
        throw new Exception('ไม่พบบทเรียนที่ต้องการบันทึกผล');
    }
    $lessonId = (string) $lessonRow['lessons_id'];
    $lessonTitle = (string) ($lessonRow['lessons_name'] ?? ("บทที่ " . $lessonIndex));

    $questionPayload = loadQuestionsForScoring($conn, $subjectId, $lessonIndex, $lessonId);
    $questions = $questionPayload['rows'];
    $questionSource = $questionPayload['source'];
    $totalScore = count($questions);
    $choiceMap = [0 => 'A', 1 => 'B', 2 => 'C', 3 => 'D'];

    foreach ($questions as $index => $q) {
        $selected = '';
        if (array_key_exists($index, $answers) && $answers[$index] !== null) {
            $selected = $choiceMap[(int) $answers[$index]] ?? '';
        }
        $correct = normalizeAnswerLetter((string) ($q['correct_answer'] ?? ''));
        if ($correct !== '' && $selected === $correct) {
            $score++;
        }
    }

    $requiredScore = max(1, (int) ceil(max(1, $totalScore) * 0.6));
    $quizStatus = $score >= $requiredScore ? 'pass' : 'fail';

    $testColumns = tableColumns($conn, 'public', 'test');
    $testData = [
        'student_id' => $studentId,
        'subjects_id' => $subjectId,
        'course_name' => $subjectName,
        'lesson_no' => $lessonIndex,
        'score' => $score,
        'total_score' => $totalScore,
        'status' => $quizStatus,
        'test_attempt' => 1,
    ];

    $attemptCountStmt = $conn->prepare(
        "SELECT COUNT(*) FROM public.test WHERE student_id = :student_id AND (subjects_id = :subjects_id OR course_name = :course_name) AND lesson_no = :lesson_no"
    );
    try {
        $attemptCountStmt->execute([
            ':student_id' => $studentId,
            ':subjects_id' => $subjectId,
            ':course_name' => $subjectName,
            ':lesson_no' => $lessonIndex,
        ]);
        $testData['test_attempt'] = ((int) $attemptCountStmt->fetchColumn()) + 1;
    } catch (Throwable $ignored) {
        $testData['test_attempt'] = 1;
    }

    $testId = insertTestAttempt($conn, $testColumns, $testData);

    $answerColumns = tableColumns($conn, 'public', 'test_answers');
    $canSaveAnswers = !empty($answerColumns) && in_array('questions_id', $answerColumns, true) && in_array('selected_choice', $answerColumns, true);
    $hasTestIdCol = in_array('test_id', $answerColumns, true);

    if ($canSaveAnswers && $hasTestIdCol && $testId > 0 && !empty($questions)) {
        $insertAnswer = $conn->prepare("INSERT INTO public.test_answers (questions_id, test_id, selected_choice) VALUES (?, ?, ?)");
>>>>>>> Stashed changes
        foreach ($questions as $index => $q) {
            $selected = '-';
            if (array_key_exists($index, $answers) && $answers[$index] !== null) {
                $selected = $choiceMap[(int) $answers[$index]] ?? '-';
<<<<<<< Updated upstream
=======
            }
            if ($questionSource === 'test_questions') {
                $insertAnswer->execute([(int) $q['qid'], $testId, $selected]);
>>>>>>> Stashed changes
            }
            $stmtAns->execute([(int) $q['questions_id'], $testId, $selected]);
        }
    }

<<<<<<< Updated upstream
    if ($quizStatus === 'pass') {
        $stmtRecord = $conn->prepare("INSERT INTO public.learning_records (student_id, lessons_id, activity_type) VALUES (?, ?, 'quiz_passed') ON CONFLICT DO NOTHING");
        $stmtRecord->execute([$studentId, $lessonId]);
    }
=======
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
>>>>>>> Stashed changes

    $conn->commit();
    out([
        'status' => 'success',
        'quiz_status' => $quizStatus,
        'score' => $score,
        'total_score' => $totalScore,
        'required_score' => $requiredScore,
<<<<<<< Updated upstream
        'message' => 'บันทึกคำตอบสำเร็จ!'
=======
        'message' => 'บันทึกผลสอบเรียบร้อย'
>>>>>>> Stashed changes
    ]);
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    out(['status' => 'error', 'message' => 'DB Error: ' . $e->getMessage()], 500);
}
?>
