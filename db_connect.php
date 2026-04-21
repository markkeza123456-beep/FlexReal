<?php
$host = "aws-1-ap-northeast-1.pooler.supabase.com";
$db   = "postgres";
$user = "postgres.gwunrmptlmfpvidrxwdf";
$pass = "lerninghub@";  
$port = "5432";

$dsn = "pgsql:host=$host;port=$port;dbname=$db;";

try {
    $conn = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $conn->exec("SET client_encoding TO 'UTF8'");
    
} catch (PDOException $e) {
    die("การเชื่อมต่อ Supabase ล้มเหลว: " . $e->getMessage());
}hghghghghghghghghghdsadsadasdsadsdad
?>