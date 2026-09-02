<?php
require_once __DIR__ . '/db_connect.php';
echo "CONSTRAINTS\n";
$q = $conn->query("SELECT tc.constraint_name, tc.constraint_type, kcu.column_name FROM information_schema.table_constraints tc LEFT JOIN information_schema.key_column_usage kcu ON kcu.constraint_name = tc.constraint_name AND kcu.table_schema = tc.table_schema WHERE tc.table_schema = 'public' AND tc.table_name = 'student_subject' ORDER BY tc.constraint_name, kcu.ordinal_position");
foreach ($q as $row) echo implode(' | ', [$row['constraint_name'], $row['constraint_type'], $row['column_name'] ?? '-']) . "\n";
echo "STUDENTS\n";
foreach ($conn->query("SELECT st.student_id, COUNT(ss.subjects_id) AS enrolled FROM public.student st LEFT JOIN public.student_subject ss ON ss.student_id = st.student_id GROUP BY st.student_id ORDER BY st.student_id LIMIT 20") as $row) echo implode(' | ', $row) . "\n";
echo "SUBJECT\n";
foreach ($conn->query("SELECT subjects_id, subjects_name FROM public.subjects WHERE subjects_id = 'SUB001'") as $row) echo implode(' | ', $row) . "\n";
$student = '1111111111112';
echo "POST_SIMULATION {$student}\n";
try {
    $conn->beginTransaction();
    $stmt = $conn->prepare('INSERT INTO public.student_subject (student_id, subjects_id) VALUES (:student_id, :subjects_id)');
    $stmt->execute([':student_id' => $student, ':subjects_id' => 'SUB001']);
    echo "INSERT_OK\n";
    $conn->rollBack();
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo "INSERT_ERROR {$e->getMessage()}\n";
}
