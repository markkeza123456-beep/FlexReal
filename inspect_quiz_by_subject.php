<?php
require_once __DIR__ . '/db_connect.php';

try {
    $sql = "
        SELECT subjects_id, COUNT(*) AS total
        FROM public.quiz_questions
        GROUP BY subjects_id
        ORDER BY subjects_id
    ";
    $rows = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    $sampleStmt = $conn->prepare("
        SELECT quiz_id, subjects_id, lesson_no, question_text
        FROM public.quiz_questions
        WHERE subjects_id = 'SUB005'
        ORDER BY lesson_no, quiz_id
        LIMIT 5
    ");
    $sampleStmt->execute();
    $sample = $sampleStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'counts' => $rows, 'sub005_sample' => $sample], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit(1);
}
?>
