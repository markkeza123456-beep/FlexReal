<?php
/**
 * Loads private Supabase settings from outside the web root.
 * Copy docs/flexreal.env.example to D:\Xampp\config\flexreal.env and fill it in.
 */
$envFile = 'D:\\Xampp\\config\\flexreal.env';

if (!is_readable($envFile)) {
    http_response_code(500);
    die('Missing private configuration file. See docs/flexreal.env.example.');
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
        http_response_code(500);
        die("Missing required configuration: {$key}");
    }
    return $value;
}

$host = requiredEnv($env, 'SUPABASE_DB_HOST');
$db = requiredEnv($env, 'SUPABASE_DB_NAME');
$user = requiredEnv($env, 'SUPABASE_DB_USER');
$pass = requiredEnv($env, 'SUPABASE_DB_PASSWORD');
$port = requiredEnv($env, 'SUPABASE_DB_PORT');

// Kept available for existing Storage upload endpoints.
$supabaseUrl = requiredEnv($env, 'SUPABASE_URL');
$supabaseKey = requiredEnv($env, 'SUPABASE_ANON_KEY');

$dsn = "pgsql:host={$host};port={$port};dbname={$db};";

try {
    $conn = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $conn->exec('SET client_encoding TO \'UTF8\'');
} catch (PDOException $e) {
    http_response_code(500);
    error_log('Supabase database connection failed: ' . $e->getMessage());
    die('Database connection failed.');
}
