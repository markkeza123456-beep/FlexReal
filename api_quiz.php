<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db_connect.php';

function jsonResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function ensureQuizTable(PDO $conn): void
{
    $conn->exec(
        'CREATE TABLE IF NOT EXISTS public.quiz_questions (
            quiz_id BIGSERIAL PRIMARY KEY,
            subjects_id VARCHAR(50) NOT NULL,
            lesson_no INTEGER NOT NULL DEFAULT 1,
            question_text TEXT NOT NULL,
            option_a TEXT NOT NULL,
            option_b TEXT NOT NULL,
            option_c TEXT NOT NULL,
            option_d TEXT NOT NULL,
            correct_option INTEGER NOT NULL,
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP NOT NULL DEFAULT NOW()
        )'
    );
}

$action = $_GET['action'] ?? 'get_questions';
if ($action !== 'get_questions') {
    jsonResponse(['status' => 'error', 'message' => 'Unknown action'], 400);
}

$subjectId = trim((string) ($_GET['subject_id'] ?? ''));
$lessonNoRaw = (int) ($_GET['lesson'] ?? 0);

if ($subjectId === '') {
    jsonResponse(['status' => 'error', 'message' => 'subject_id is required'], 400);
}

try {
    ensureQuizTable($conn);

    if ($lessonNoRaw > 0) {
        $stmt = $conn->prepare(
            'SELECT quiz_id, lesson_no, question_text, option_a, option_b, option_c, option_d, correct_option
             FROM public.quiz_questions
             WHERE subjects_id = :subject_id
               AND lesson_no = :lesson_no
               AND is_active = TRUE
             ORDER BY quiz_id ASC'
        );
        $stmt->execute([
            ':subject_id' => $subjectId,
            ':lesson_no' => $lessonNoRaw,
        ]);
    } else {
        $stmt = $conn->prepare(
            'SELECT quiz_id, lesson_no, question_text, option_a, option_b, option_c, option_d, correct_option
             FROM public.quiz_questions
             WHERE subjects_id = :subject_id
               AND is_active = TRUE
             ORDER BY lesson_no ASC, quiz_id ASC'
        );
        $stmt->execute([':subject_id' => $subjectId]);
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $questions = array_map(static function ($row): array {
        $correctOption = (int) $row['correct_option'];
        if ($correctOption >= 1 && $correctOption <= 4) {
            $correctOption -= 1;
        }
        if ($correctOption < 0 || $correctOption > 3) {
            $correctOption = 0;
        }

        return [
            'id' => (int) $row['quiz_id'],
            'lesson' => (int) $row['lesson_no'],
            'question' => (string) $row['question_text'],
            'options' => [
                (string) $row['option_a'],
                (string) $row['option_b'],
                (string) $row['option_c'],
                (string) $row['option_d'],
            ],
            'answer' => $correctOption,
        ];
    }, $rows);

    jsonResponse([
        'status' => 'success',
        'count' => count($questions),
        'questions' => $questions,
    ]);
} catch (Throwable $e) {
    jsonResponse([
        'status' => 'error',
        'message' => 'ไม่สามารถโหลดข้อสอบจากฐานข้อมูลได้',
    ], 500);
}
