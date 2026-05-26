<?php
require_once __DIR__ . '/db_connect.php';

try {
    $rows = $conn->query("SELECT subjects_id, subjects_name, subjects_description FROM public.subjects ORDER BY subjects_id")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'rows' => $rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit(1);
}
?>
