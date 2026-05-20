<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

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

function normalizeAnswerLetter(string $raw): string
{
    $value = strtoupper(trim($raw));
    if ($value === '') return '';
    if (preg_match('/[ABCD]/', $value, $m) === 1) return $m[0];
    return '';
}

function ensureFiveLessons(PDO $conn, string $subjectId): array
{
    $stmt = $conn->prepare("SELECT lessons_id, lessons_name, study_hours, subjects_id FROM public.lessons WHERE subjects_id = ? ORDER BY lessons_id ASC");
    $stmt->execute([$subjectId]);
    $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $baseName = !empty($lessons[0]['lessons_name']) ? (string) $lessons[0]['lessons_name'] : 'บทที่ 1';
    $baseHours = (isset($lessons[0]['study_hours']) && is_numeric($lessons[0]['study_hours'])) ? max(1, (int) $lessons[0]['study_hours']) : 1;
    $missing = max(0, 5 - count($lessons));
    if ($missing > 0) {
        $idStmt = $conn->query("SELECT lessons_id FROM public.lessons WHERE lessons_id LIKE 'L%' ORDER BY LENGTH(lessons_id) DESC, lessons_id DESC LIMIT 1");
        $lastId = $idStmt->fetchColumn();
        $nextNum = $lastId ? (intval(substr((string) $lastId, 1)) + 1) : 1;
        $insert = $conn->prepare("INSERT INTO public.lessons (lessons_id, lessons_name, study_hours, subjects_id) VALUES (:id, :name, :hrs, :sub)");
        for ($i = 0; $i < $missing; $i++) {
            $newId = 'L' . str_pad((string) $nextNum, 3, '0', STR_PAD_LEFT);
            $nextNum++;
            $insert->execute([
                ':id' => $newId,
                ':name' => $baseName,
                ':hrs' => $baseHours,
                ':sub' => $subjectId
            ]);
        }
        $stmt->execute([$subjectId]);
        $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    return $lessons;
}

function loadFromQuizQuestions(PDO $conn, string $subjectId, int $lessonNo): array
{
    $cols = tableColumns($conn, 'public', 'quiz_questions');
    $answerCol = 'correct_answer';
    if (!in_array($answerCol, $cols, true)) {
        if (in_array('correct_option', $cols, true)) {
            $answerCol = 'correct_option';
        } elseif (in_array('answer', $cols, true)) {
            $answerCol = 'answer';
        } else {
            $answerCol = "''";
        }
    }

    $stmt = $conn->prepare(
        "SELECT quiz_id, question_text, option_a, option_b, option_c, option_d, {$answerCol} AS correct_answer
         FROM public.quiz_questions
         WHERE subjects_id = :subjects_id AND lesson_no = :lesson_no
         ORDER BY quiz_id ASC"
    );
    $stmt->execute([
        ':subjects_id' => $subjectId,
        ':lesson_no' => $lessonNo,
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function loadFromTestQuestions(PDO $conn, string $lessonId): array
{
    $stmt = $conn->prepare(
        "SELECT questions_id, questions_text, choice_a, choice_b, choice_c, choice_d, correct_answer
         FROM public.test_questions
         WHERE lessons_id = ?
         ORDER BY questions_id ASC"
    );
    $stmt->execute([$lessonId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function nextQuestionId(PDO $conn): int
{
    $stmt = $conn->query("SELECT COALESCE(MAX(questions_id), 0) + 1 FROM public.test_questions");
    return (int) $stmt->fetchColumn();
}

function buildDefaultQuestions(int $lessonNo): array
{
    return [
        ['q' => "บทที่ {$lessonNo}: ข้อใดอธิบายใจความสำคัญของบทเรียนได้ถูกต้อง", 'a' => 'แนวคิดหลักของบทเรียน', 'b' => 'ข้อมูลนอกบทเรียน', 'c' => 'คำตอบไม่เกี่ยวข้อง', 'd' => 'เดาสุ่ม', 'ans' => 'A'],
        ['q' => "บทที่ {$lessonNo}: ขั้นตอนแรกที่ควรทำคือข้อใด", 'a' => 'ทบทวนเนื้อหา', 'b' => 'ข้ามพื้นฐาน', 'c' => 'จำคำตอบอย่างเดียว', 'd' => 'ไม่ต้องตรวจสอบ', 'ans' => 'A'],
        ['q' => "บทที่ {$lessonNo}: หากต้องการความแม่นยำ ควรทำอย่างไร", 'a' => 'ตรวจคำตอบก่อนส่ง', 'b' => 'ส่งทันที', 'c' => 'ไม่อ่านโจทย์', 'd' => 'ปล่อยว่าง', 'ans' => 'A'],
        ['q' => "บทที่ {$lessonNo}: ข้อใดเป็นการประยุกต์ใช้ความรู้ที่เหมาะสม", 'a' => 'นำไปใช้แก้ปัญหาจริง', 'b' => 'ท่องจำโดยไม่ใช้', 'c' => 'ไม่ฝึกฝน', 'd' => 'ไม่วางแผน', 'ans' => 'A'],
        ['q' => "บทที่ {$lessonNo}: หลังทำแบบฝึกควรทำสิ่งใดต่อ", 'a' => 'สรุปและทบทวนข้อผิดพลาด', 'b' => 'หยุดทันที', 'c' => 'ข้ามบทโดยไม่ประเมิน', 'd' => 'ลบโน้ต', 'ans' => 'A'],
    ];
}

function ensureDefaultTestQuestions(PDO $conn, string $lessonId, int $lessonNo): void
{
    $countStmt = $conn->prepare("SELECT COUNT(*) FROM public.test_questions WHERE lessons_id = ?");
    $countStmt->execute([$lessonId]);
    if ((int) $countStmt->fetchColumn() > 0) {
        return;
    }
    $seed = buildDefaultQuestions($lessonNo);
    $insert = $conn->prepare(
        "INSERT INTO public.test_questions (questions_id, questions_text, choice_a, choice_b, choice_c, choice_d, correct_answer, lessons_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    foreach ($seed as $item) {
        $insert->execute([nextQuestionId($conn), $item['q'], $item['a'], $item['b'], $item['c'], $item['d'], $item['ans'], $lessonId]);
    }
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

    $rows = loadFromQuizQuestions($conn, $subjectId, $lessonNo);
    $fromQuizQuestions = !empty($rows);

    if (!$fromQuizQuestions) {
        $lessons = ensureFiveLessons($conn, $subjectId);
        $offset = $lessonNo - 1;
        $lessonId = isset($lessons[$offset]['lessons_id']) ? (string) $lessons[$offset]['lessons_id'] : '';
        if ($lessonId === '') {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบบทเรียน'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        ensureDefaultTestQuestions($conn, $lessonId, $lessonNo);
        $rows = loadFromTestQuestions($conn, $lessonId);
    }

    if (empty($rows)) {
        echo json_encode(['status' => 'error', 'message' => 'ยังไม่มีข้อสอบของบทนี้'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $formatted = [];
    foreach ($rows as $q) {
        $ans = normalizeAnswerLetter((string) ($q['correct_answer'] ?? ''));
        $questionText = $fromQuizQuestions ? (string) ($q['question_text'] ?? '') : (string) ($q['questions_text'] ?? '');
        $a = $fromQuizQuestions ? (string) ($q['option_a'] ?? '') : (string) ($q['choice_a'] ?? '');
        $b = $fromQuizQuestions ? (string) ($q['option_b'] ?? '') : (string) ($q['choice_b'] ?? '');
        $c = $fromQuizQuestions ? (string) ($q['option_c'] ?? '') : (string) ($q['choice_c'] ?? '');
        $d = $fromQuizQuestions ? (string) ($q['option_d'] ?? '') : (string) ($q['choice_d'] ?? '');

        $options = [$a, $b, $c, $d];
        $map = ['A' => 0, 'B' => 1, 'C' => 2, 'D' => 3];
        $answerIndex = $map[$ans] ?? 0;

        $formatted[] = [
            'question' => $questionText,
            'options' => $options,
            'answer' => $answerIndex
        ];
    }

    echo json_encode(['status' => 'success', 'questions' => $formatted], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>
