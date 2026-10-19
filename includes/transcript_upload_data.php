<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'student' || empty($_SESSION['user_id'])) {
    $_SESSION['after_login_return'] = 'upload_transcript.php';
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';
if (empty($_SESSION['transcript_csrf'])) {
    $_SESSION['transcript_csrf'] = bin2hex(random_bytes(32));
}
$studentId = (string) $_SESSION['user_id'];
$csrfToken = (string) $_SESSION['transcript_csrf'];

try {
    $requestsStmt = $conn->prepare(
        'SELECT request_id, status, submitted_at, reviewed_at, reviewer_note
         FROM public.transcript_requests
         WHERE student_id = :student_id
         ORDER BY submitted_at DESC'
    );
    $requestsStmt->execute([':student_id' => $studentId]);
    $requests = $requestsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $error) {
    error_log('Transcript request history load failed: ' . $error->getMessage());
    $requests = null;
}

function hTranscript(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
