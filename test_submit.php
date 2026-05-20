<?php
session_start();
require_once 'db_connect.php';
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
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : 0;
    }
    return 0;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    out(['status' => 'error', 'message' => 'รูปแบบข้อมูลไม่ถูกต้อง'], 400);
}

$role = strtolower((string) ($_SESSION['role'] ?? ''));
if (!isset($_SESSION['user_id']) || $role !== 'student') {
    out(['status' => 'unauthorized', 'message' => 'กรุณาเข้าสู่ระบบก่อนทำแบบทดสอบ'], 401);
}

$studentId = (string) $_SESSION['user_id'];
$subjectId = trim((string) ($data['subject_id'] ?? ''));
$lessonIndex = max(1, (int) ($data['lesson_index'] ?? 1));
$score = (int) ($data['score'] ?? 0);
$totalScore = max(0, (int) ($data['total_score'] ?? 0));
$answers = is_array($data['answers'] ?? null) ? $data['answers'] : [];
$requiredScore = max(1, (int) ceil(max(1, $totalScore) * 0.6));
$quizStatus = $score >= $requiredScore ? 'pass' : 'fail';

if ($subjectId === '') {
    out(['status' => 'error', 'message' => 'ไม่พบรหัสรายวิชา'], 400);
}

try {
    $conn->beginTransaction();

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
        foreach ($questions as $index => $q) {
            $selected = '-';
            if (array_key_exists($index, $answers) && $answers[$index] !== null) {
                $selected = $choiceMap[(int) $answers[$index]] ?? '-';
            }
            $stmtAns->execute([(int) $q['questions_id'], $testId, $selected]);
        }
    }

    if ($quizStatus === 'pass') {
        $stmtRecord = $conn->prepare("INSERT INTO public.learning_records (student_id, lessons_id, activity_type) VALUES (?, ?, 'quiz_passed') ON CONFLICT DO NOTHING");
        $stmtRecord->execute([$studentId, $lessonId]);
    }

    $conn->commit();
    out([
        'status' => 'success',
        'quiz_status' => $quizStatus,
        'score' => $score,
        'total_score' => $totalScore,
        'required_score' => $requiredScore,
        'message' => 'บันทึกคำตอบสำเร็จ!'
    ]);
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    out(['status' => 'error', 'message' => 'DB Error: ' . $e->getMessage()], 500);
}
?>
