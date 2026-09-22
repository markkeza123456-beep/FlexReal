<?php

// Keep credentials outside the web root.  The old single Windows-only path
// made every database-backed page fail after the project was opened in XAMPP
// on macOS/Linux.
$envFileCandidates = array_filter([
    getenv('FLEXREAL_ENV_FILE') ?: null,
    '/Applications/XAMPP/xamppfiles/config/flexreal.env',
    dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'flexreal.env',
    'D:\\Xampp\\config\\flexreal.env', // legacy Windows installation
]);

$envFile = null;
foreach ($envFileCandidates as $candidate) {
    if (is_readable($candidate)) {
        $envFile = $candidate;
        break;
    }
}

if ($envFile === null) {
    throw new RuntimeException('ระบบยังไม่ได้ตั้งค่าการเชื่อมต่อฐานข้อมูล กรุณาติดต่อผู้ดูแลระบบ');
}

$env = [];
foreach (file($envFile, FILE_IGNORE_NEW_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
        continue;
    }
    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim($value);
}

function requiredEnv(array $env, string $key): string
{
    $value = trim((string) ($env[$key] ?? ''));
if ($value === '') {
        throw new RuntimeException("Missing required configuration: {$key}");
    }
    return $value;
}

$host = requiredEnv($env, 'SUPABASE_DB_HOST');
if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
    $ipv4Host = gethostbyname($host);
    if (filter_var($ipv4Host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
        http_response_code(500);
        die('Database host does not have an IPv4 address.');
    }
    $host = $ipv4Host;
}
$db = requiredEnv($env, 'SUPABASE_DB_NAME');
$user = requiredEnv($env, 'SUPABASE_DB_USER');
$pass = requiredEnv($env, 'SUPABASE_DB_PASSWORD');
$port = requiredEnv($env, 'SUPABASE_DB_PORT');


$supabaseUrl = requiredEnv($env, 'SUPABASE_URL');
$supabaseKey = requiredEnv($env, 'SUPABASE_ANON_KEY');

// XAMPP starts Apache as root before dropping privileges to `daemon`.
// libpq then looks for a client certificate under /var/root and fails before
// it can connect to Supabase. Point HOME at PHP's writable temp directory;
// Supabase uses server-side TLS and does not require a client certificate.
if (PHP_SAPI !== 'cli') {
    putenv('HOME=' . sys_get_temp_dir());
}

$dsn = "pgsql:host={$host};port={$port};dbname={$db};sslmode=require;sslcert=;connect_timeout=8;";

try {
    $conn = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 8,
    ]);
    $conn->exec('SET client_encoding TO \'UTF8\'');
    $conn->exec('SET statement_timeout TO 8000');
} catch (PDOException $e) {
    error_log('Supabase database connection failed: ' . $e->getMessage());
    throw new RuntimeException('Database connection failed.', 0, $e);
}
