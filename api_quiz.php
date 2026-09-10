<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

function normalizeAnswerLetter(string $raw): string
{
    $value = strtoupper(trim($raw));
    if ($value === '') return '';
    if (preg_match('/[ABCD]/', $value, $m) === 1) return $m[0];
    return '';
}

function resolveAnswerIndex(string $rawCorrect, array $options): int
{
    $raw = trim($rawCorrect);
    $letter = normalizeAnswerLetter($raw);
    $map = ['A' => 0, 'B' => 1, 'C' => 2, 'D' => 3];
    if ($letter !== '' && isset($map[$letter])) {
        return $map[$letter];
    }

    if (is_numeric($raw)) {
        $n = (int) $raw;
        if ($n >= 1 && $n <= 4) return $n - 1;
        if ($n >= 0 && $n <= 3) return $n;
    }

    $rawNorm = mb_strtolower($raw);
    foreach ($options as $idx => $opt) {
        if (mb_strtolower(trim((string) $opt)) === $rawNorm) {
            return (int) $idx;
        }
    }

    return 0;
}

function inferQuestionType(array $options, string $correctAnswer): string
{
    $normalizedOptions = array_values(array_filter(array_map(static function ($value) {
        return trim((string) $value);
    }, $options), static function ($value) {
        return $value !== '' && $value !== '-';
    }));

    if (empty($normalizedOptions)) {
        return 'essay';
    }

    if (count($normalizedOptions) === 2) {
        return 'truefalse';
    }

    return 'choice';
}

function loadLessons(PDO $conn, string $subjectId): array
{
    $stmt = $conn->prepare("SELECT lesson_id AS lessons_id, title AS lessons_name, study_hours, course_id AS subjects_id FROM public.lessons WHERE course_id = ? ORDER BY position ASC");
    $stmt->execute([$subjectId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function loadFromTestQuestions(PDO $conn, string $lessonId): array
{
    $stmt = $conn->prepare(
        "SELECT question_id AS questions_id, question_text AS questions_text,
                COALESCE(options->>0, '') AS choice_a, COALESCE(options->>1, '') AS choice_b,
                COALESCE(options->>2, '') AS choice_c, COALESCE(options->>3, '') AS choice_d,
                COALESCE(correct_choice, '') AS correct_answer
         FROM public.questions
         WHERE lesson_id = ? AND is_active = true
         ORDER BY question_id ASC"
    );
    $stmt->execute([$lessonId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

try {
    if ($action !== 'get_questions') {
        echo json_encode(['status' => 'error', 'message' => 'invalid action'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $subjectId = trim((string) ($_GET['subject_id'] ?? ''));
    $lessonNo = max(1, (int) ($_GET['lesson'] ?? 1));
    if ($subjectId === '') {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบรหัสรายวิชา'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $lessons = loadLessons($conn, $subjectId);
    $offset = $lessonNo - 1;
    $lessonId = isset($lessons[$offset]['lessons_id']) ? (string) $lessons[$offset]['lessons_id'] : '';
    if ($lessonId === '') {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบบทเรียน'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ตารางหลักเพียงตารางเดียวสำหรับคำถามที่อาจารย์เพิ่ม ทั้งปรนัยและข้อเขียน
    $rows = loadFromTestQuestions($conn, $lessonId);
    $fromQuizQuestions = false;

    if (empty($rows)) {
        echo json_encode(['status' => 'error', 'message' => 'ยังไม่มีข้อสอบของบทนี้'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $formatted = [];
    foreach ($rows as $q) {
        $questionText = $fromQuizQuestions ? (string) ($q['question_text'] ?? '') : (string) ($q['questions_text'] ?? '');
        $a = $fromQuizQuestions ? (string) ($q['option_a'] ?? '') : (string) ($q['choice_a'] ?? '');
        $b = $fromQuizQuestions ? (string) ($q['option_b'] ?? '') : (string) ($q['choice_b'] ?? '');
        $c = $fromQuizQuestions ? (string) ($q['option_c'] ?? '') : (string) ($q['choice_c'] ?? '');
        $d = $fromQuizQuestions ? (string) ($q['option_d'] ?? '') : (string) ($q['choice_d'] ?? '');

        $options = [$a, $b, $c, $d];
        $correctAnswer = (string) ($q['correct_answer'] ?? '');
        $questionType = inferQuestionType($options, $correctAnswer);
        $answerIndex = $questionType === 'essay' ? -1 : resolveAnswerIndex($correctAnswer, $options);

        $formatted[] = [
            'question' => $questionText,
            'options' => $options,
            'answer' => $answerIndex,
            'type' => $questionType,
            'answer_text' => $questionType === 'essay' ? $correctAnswer : '',
        ];
    }

    echo json_encode(['status' => 'success', 'questions' => $formatted], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>
