<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function tutorJson(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function tutorCompletion(string $apiKey, string $model, array $messages, int $maxTokens, ?string &$failureReason = null): ?string
{
    if (!function_exists('curl_init')) {
        $failureReason = 'PHP cURL extension is unavailable';
        error_log('Student tutor API unavailable: PHP cURL extension is missing');
        return null;
    }
    $contents = [];
    foreach ($messages as $message) {
        $role = ($message['role'] ?? '') === 'assistant' ? 'model' : 'user';
        $contents[] = ['role' => $role, 'parts' => [['text' => (string) ($message['content'] ?? '')]]];
    }
    $systemInstruction = null;
    if (($messages[0]['role'] ?? '') === 'system') {
        $systemInstruction = ['parts' => [['text' => (string) ($messages[0]['content'] ?? '')]]];
        array_shift($contents);
    }
    $payload = ['contents' => $contents, 'generationConfig' => ['temperature' => 0.2, 'maxOutputTokens' => $maxTokens]];
    if ($systemInstruction !== null) $payload['systemInstruction'] = $systemInstruction;
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($body === false) return null;
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($apiKey);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => $body, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 18]);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $transportError = curl_error($ch);
    curl_close($ch);
    if (!is_string($response) || $status < 200 || $status >= 300) {
        $decodedError = is_string($response) ? json_decode($response, true) : null;
        $apiError = trim((string) ($decodedError['error']['message'] ?? ''));
        $apiCode = trim((string) ($decodedError['error']['status'] ?? $decodedError['error']['code'] ?? ''));
        $failureReason = $status > 0 ? 'Gemini HTTP ' . $status . ($apiCode !== '' ? ' (' . $apiCode . ')' : '') : 'Network/TLS connection failed';
        error_log('Student tutor API failed: ' . $failureReason . ($transportError !== '' ? '; transport=' . $transportError : '') . ($apiError !== '' ? '; message=' . mb_substr($apiError, 0, 500, 'UTF-8') : ''));
        return null;
    }
    $decoded = json_decode($response, true);
    $answer = trim((string) ($decoded['candidates'][0]['content']['parts'][0]['text'] ?? ''));
    return $answer !== '' ? mb_substr($answer, 0, 5000, 'UTF-8') : null;
}

if (($_SESSION['role'] ?? '') !== 'student' || empty($_SESSION['user_id'])) tutorJson(['ok' => false, 'message' => 'กรุณาเข้าสู่ระบบนักเรียน'], 401);
$now = time();
$recentTutorRequests = array_values(array_filter($_SESSION['tutor_request_times'] ?? [], static fn($time): bool => is_int($time) && $time > $now - 60));
if (count($recentTutorRequests) >= 8) tutorJson(['ok' => false, 'message' => 'ส่งคำถามถี่เกินไป กรุณารอสักครู่'], 429);
$recentTutorRequests[] = $now;
$_SESSION['tutor_request_times'] = $recentTutorRequests;
$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload)) tutorJson(['ok' => false, 'message' => 'ข้อมูลไม่ถูกต้อง'], 400);
$attemptId = filter_var($payload['attempt_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$questionId = filter_var($payload['question_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$action = strtolower(trim((string) ($payload['action'] ?? 'chat')));
$history = is_array($payload['history'] ?? null) ? array_slice($payload['history'], -8) : [];
if ($attemptId === false || $questionId === false || !in_array($action, ['chat', 'explain'], true)) tutorJson(['ok' => false, 'message' => 'ไม่พบข้อสอบหรือคำถาม'], 400);
$messages = [];
foreach ($history as $message) {
    if (!is_array($message) || !in_array($message['role'] ?? '', ['user', 'assistant'], true)) continue;
    $content = trim((string) ($message['content'] ?? ''));
    if ($content !== '') $messages[] = ['role' => $message['role'], 'content' => mb_substr($content, 0, 700, 'UTF-8')];
}
if ($action === 'chat' && (!$messages || end($messages)['role'] !== 'user')) tutorJson(['ok' => false, 'message' => 'กรุณาพิมพ์คำถามก่อนส่ง'], 400);

try {
    require_once __DIR__ . '/config/db.php';
    $contextStmt = $conn->prepare("SELECT a.attempt_id, q.question_text, q.options, q.correct_choice, q.teacher_explanation,
            aa.selected_choice, aa.ai_explanation
        FROM public.quiz_attempts a
        JOIN public.quiz_attempt_answers aa ON aa.attempt_id = a.attempt_id
        JOIN public.questions q ON q.question_id = aa.question_id
        WHERE a.attempt_id = :attempt_id AND a.student_id = :student_id AND a.status IN ('passed', 'pending_review')
          AND aa.question_id = :question_id
          AND (SELECT COALESCE(SUM(aa2.score) FILTER (WHERE q2.question_type <> 'essay'), 0)
                      >= COALESCE(SUM(q2.max_score) FILTER (WHERE q2.question_type <> 'essay'), 0) * 0.6
               FROM public.quiz_attempt_answers aa2 JOIN public.questions q2 ON q2.question_id = aa2.question_id
               WHERE aa2.attempt_id = a.attempt_id)
        LIMIT 1");
    $contextStmt->execute([':attempt_id' => $attemptId, ':student_id' => (string) $_SESSION['user_id'], ':question_id' => $questionId]);
    $context = $contextStmt->fetch(PDO::FETCH_ASSOC);
    if (!$context) tutorJson(['ok' => false, 'message' => 'ไม่พบผลสอบที่ผ่านหรือไม่มีสิทธิ์ดูข้อนี้'], 404);
    $options = is_array($context['options']) ? $context['options'] : (json_decode((string) $context['options'], true) ?: []);
    $correct = strtoupper(trim((string) $context['correct_choice']));
    $correctText = $options[ord($correct) - 65] ?? '';
    $apiKey = trim((string) (getenv('GEMINI_API_KEY') ?: ''));
    if ($apiKey === '') tutorJson(['ok' => false, 'message' => 'ยังไม่ได้ตั้งค่า GEMINI_API_KEY ใน Render'], 503);
    $model = trim((string) (getenv('GEMINI_MODEL') ?: 'gemini-2.5-flash-lite'));
    if ($action === 'explain') {
        $existingExplanation = trim((string) ($context['ai_explanation'] ?? ''));
        if ($existingExplanation !== '' && !str_contains($existingExplanation, 'ยังไม่มีคำอธิบาย') && !str_contains($existingExplanation, 'AI ยังไม่พร้อม')) {
            tutorJson(['ok' => true, 'answer' => $existingExplanation]);
        }
        $selected = strtoupper(trim((string) ($context['selected_choice'] ?? '')));
        $selectedText = $selected !== '' ? ($options[ord($selected) - 65] ?? '') : '';
        $prompt = "คุณคือติวเตอร์ผู้เชี่ยวชาญ จงอธิบายเป็นภาษาไทยว่าทำไมเฉลยที่อาจารย์เลือกจึงถูกต้อง พร้อมแสดงวิธีคิดเป็นขั้นตอนที่นักเรียนเข้าใจง่าย หากนักเรียนตอบผิดให้ชี้จุดที่พลาดอย่างสุภาพ ห้ามเปลี่ยนเฉลยและห้ามแต่งข้อมูลที่ไม่มีในโจทย์.\nโจทย์: {$context['question_text']}\nตัวเลือก: " . implode(' | ', array_map('strval', $options)) . "\nนักเรียนเลือก: {$selected}. {$selectedText}\nเฉลยอาจารย์: {$correct}. {$correctText}\nคำอธิบายอาจารย์: " . trim((string) ($context['teacher_explanation'] ?? ''));
        $failureReason = null;
        $answer = tutorCompletion($apiKey, $model, [['role' => 'user', 'content' => $prompt]], 700, $failureReason);
        if ($answer === null) {
            $userMessage = match (true) {
                str_contains((string) $failureReason, 'HTTP 401') || str_contains((string) $failureReason, 'HTTP 403') => 'Gemini ไม่ยอมรับ API key หรือไม่มีสิทธิ์ใช้ API ตรวจคีย์และการเปิดใช้ Gemini API',
                str_contains((string) $failureReason, 'HTTP 429') => 'Gemini ถึงโควตาหรือเรียกถี่เกินไป กรุณารอสักครู่หรือตรวจ Free Tier limit',
                str_contains((string) $failureReason, 'HTTP 404') => 'ไม่พบ Gemini model ที่ตั้งไว้ ตรวจค่า GEMINI_MODEL',
                str_contains((string) $failureReason, 'HTTP 400') => 'Gemini ปฏิเสธรูปแบบคำขอ (400) ตรวจชื่อโมเดลหรือ request parameters',
                default => 'เชื่อมต่อ Gemini ไม่สำเร็จ ตรวจ Logs ของ Render เพื่อดูรายละเอียด (ห้ามแชร์ API key)',
            };
            tutorJson(['ok' => false, 'message' => $userMessage], 502);
        }
        $save = $conn->prepare('UPDATE public.quiz_attempt_answers aa SET ai_explanation = :explanation FROM public.quiz_attempts a WHERE aa.attempt_id = a.attempt_id AND aa.attempt_id = :attempt_id AND aa.question_id = :question_id AND a.student_id = :student_id');
        $save->execute([':explanation' => $answer, ':attempt_id' => $attemptId, ':question_id' => $questionId, ':student_id' => (string) $_SESSION['user_id']]);
        tutorJson(['ok' => true, 'answer' => $answer]);
    }

    $instructions = "คุณเป็นติวเตอร์ส่วนตัวภาษาไทยสำหรับนักเรียน ช่วยตอบคำถามต่อเนื่องอย่างใจเย็นและเข้าใจง่าย ยึดโจทย์และเฉลยที่ครูกำหนดเป็นหลัก ห้ามเปลี่ยนเฉลย หากข้อมูลไม่พอให้บอกตรงๆ และขอรายละเอียดเพิ่ม ห้ามเปิดเผยข้อมูลส่วนตัวใดๆ.\nโจทย์: {$context['question_text']}\nตัวเลือก: " . implode(' | ', array_map('strval', $options)) . "\nเฉลยครู: {$correct}. {$correctText}\nคำอธิบายครู: " . trim((string) ($context['teacher_explanation'] ?? '')) . "\nคำอธิบายตั้งต้น: " . trim((string) ($context['ai_explanation'] ?? ''));
    $failureReason = null;
    $answer = tutorCompletion($apiKey, $model, array_merge([['role' => 'system', 'content' => $instructions]], $messages), 500, $failureReason);
    if ($answer === null) tutorJson(['ok' => false, 'message' => 'AI ตอบคำถามไม่สำเร็จ ตรวจ Logs ของ Render เพื่อดู status code'], 502);
    tutorJson(['ok' => true, 'answer' => $answer]);
} catch (Throwable $error) {
    error_log('Student tutor request failed: ' . $error->getMessage());
    tutorJson(['ok' => false, 'message' => 'เกิดข้อผิดพลาดในการตอบคำถาม'], 500);
}
