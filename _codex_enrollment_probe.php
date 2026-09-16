<?php
// Read-only probe for the normalized enrollment schema.
require_once __DIR__ . '/db_connect.php';
header('Content-Type: text/plain; charset=utf-8');

$rows = $conn->query(
    'SELECT s.user_id, s.full_name, COUNT(sc.course_id) AS enrolled_courses
     FROM public.students s
     LEFT JOIN public.student_courses sc ON sc.student_id = s.user_id
     GROUP BY s.user_id, s.full_name
     ORDER BY s.user_id
     LIMIT 20'
)->fetchAll(PDO::FETCH_ASSOC);

echo "NORMALIZED ENROLLMENT CHECK\n";
foreach ($rows as $row) {
    echo $row['user_id'] . ' | ' . $row['full_name'] . ' | ' . $row['enrolled_courses'] . "\n";
}
