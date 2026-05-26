<?php
require_once __DIR__ . '/db_connect.php';
$stmt=$conn->query("SELECT column_name,data_type FROM information_schema.columns WHERE table_schema='public' AND table_name='quiz_questions' ORDER BY ordinal_position");
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r){echo $r['column_name'].':'.$r['data_type'].PHP_EOL;}
?>
