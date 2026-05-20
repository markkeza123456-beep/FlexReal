<?php
require_once __DIR__ . '/db_connect.php';

try {
    $sql = "
        SELECT s.subjects_id, s.subjects_name,
               COALESCE(COUNT(q.quiz_id), 0) AS quiz_count
        FROM public.subjects s
        LEFT JOIN public.quiz_questions q ON q.subjects_id = s.subjects_id
        GROUP BY s.subjects_id, s.subjects_name
        ORDER BY s.subjects_id
    ";
    $rows = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'rows' => $rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit(1);
}
?>
