<?php
/** The database already uses the normalized schema in docs/database-v2.sql. */
require_once __DIR__ . '/db_connect.php';

$required = ['users', 'students', 'courses', 'lessons', 'questions', 'student_courses', 'quiz_attempts'];
$statement = $conn->prepare(
    "SELECT table_name FROM information_schema.tables
     WHERE table_schema = 'public' AND table_name = ANY(:tables)"
);
$statement->execute([':tables' => '{' . implode(',', $required) . '}']);
$missing = array_values(array_diff($required, $statement->fetchAll(PDO::FETCH_COLUMN)));
if ($missing !== []) {
    fwrite(STDERR, 'Schema is incomplete: ' . implode(', ', $missing) . PHP_EOL);
    exit(1);
}
echo "Normalized Learning Hub schema is already active; no migration is required.\n";
