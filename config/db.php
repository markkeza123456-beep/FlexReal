<?php

date_default_timezone_set('Asia/Bangkok');

/** Read SAPI/process environment sources without assuming variables_order includes ENV. */
function flexrealProcessEnv(string $key): ?string
{
    $candidates = [getenv($key)];
    if (isset($_ENV[$key])) $candidates[] = $_ENV[$key];
    if (isset($_SERVER[$key])) $candidates[] = $_SERVER[$key];
    if (function_exists('apache_getenv')) $candidates[] = apache_getenv($key, true);
    foreach ($candidates as $value) {
        if (is_string($value) && trim($value) !== '') return trim($value);
    }
    return null;
}

// Keep credentials outside the web root.  The old single Windows-only path
// made every database-backed page fail after the project was opened in XAMPP
// on macOS/Linux.
$envFileCandidates = array_filter([
    flexrealProcessEnv('FLEXREAL_ENV_FILE'),
    // XAMPP Windows installs Apache under C:\\xampp by default.
    'C:\\xampp\\config\\flexreal.env',
    '/Applications/XAMPP/xamppfiles/config/flexreal.env',
    dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'flexreal.env',
    'D:\\Xampp\\config\\flexreal.env', // legacy Windows installation
]);

$envFile = null;
foreach ($envFileCandidates as $candidate) {
    if (is_readable($candidate)) {
        $envFile = $candidate;
        break;
    }
}

$env = [];
if ($envFile !== null) {
    $envLines = file($envFile, FILE_IGNORE_NEW_LINES);
    if ($envLines === false) {
        throw new RuntimeException('Apache cannot read the configured FlexReal environment file. Check its path and file permissions.');
    }
    foreach ($envLines as $line) {
        // A UTF-8 BOM at the start of the file must not become part of the first key.
        $line = preg_replace('/^\\xEF\\xBB\\xBF/', '', $line);
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        if (str_starts_with($line, 'export ')) $line = substr($line, 7);
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        // Remove only a matching pair of wrapping quotes; preserve all key characters.
        if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
            $value = substr($value, 1, -1);
        }
        $env[$key] = $value;
    }
}
$runtimeEnvKeys = [
    'SUPABASE_DB_HOST', 'SUPABASE_DB_NAME', 'SUPABASE_DB_USER', 'SUPABASE_DB_PASSWORD', 'SUPABASE_DB_PORT',
    'SUPABASE_URL', 'SUPABASE_ANON_KEY', 'SUPABASE_SERVICE_ROLE_KEY', 'SUPABASE_SERVICE_KEY',
    'SUPABASE_LESSON_BUCKET', 'SUPABASE_TRANSCRIPT_BUCKET', 'BUCKET_NAME',
    'SMTP_HOST', 'SMTP_PORT', 'SMTP_ENCRYPTION', 'SMTP_USERNAME', 'SMTP_PASSWORD', 'SMTP_FROM_EMAIL', 'SMTP_FROM_NAME', 'SMTP_DEBUG',
    'RESEND_API_KEY', 'RESEND_FROM_EMAIL', 'RESEND_FROM_NAME', 'APP_BASE_URL',
    'GMAIL_CLIENT_ID', 'GMAIL_CLIENT_SECRET', 'GMAIL_REDIRECT_URI', 'GMAIL_SENDER_EMAIL', 'GMAIL_REFRESH_TOKEN',
];
foreach ($runtimeEnvKeys as $key) {
    $processValue = flexrealProcessEnv($key);
    if ($processValue !== null) $env[$key] = $processValue;
}

if ($env === []) {
    throw new RuntimeException('ระบบยังไม่ได้ตั้งค่าการเชื่อมต่อฐานข้อมูล กรุณาตั้งค่า Environment Variables หรือ FLEXREAL_ENV_FILE');
}

function requiredEnv(array $env, string $key): string
{
    $value = trim((string) ($env[$key] ?? ''));
    if ($value === '' || str_starts_with($value, 'replace_with_') || str_contains($value, 'your_project_ref')) {
        throw new RuntimeException("Missing required configuration: {$key}");
    }
    return $value;
}

$host = requiredEnv($env, 'SUPABASE_DB_HOST');
if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
    $ipv4Host = gethostbyname($host);
    if (filter_var($ipv4Host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
        $host = $ipv4Host;
    }
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
    $dbConnectStartedAt = microtime(true);
    $conn = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 8,
        // Transaction poolers cannot preserve server-side prepared statements
        // across transactions/connections. Emulate them in the client.
        PDO::ATTR_EMULATE_PREPARES => true,
    ]);
    $dbConnectMs = (microtime(true) - $dbConnectStartedAt) * 1000;
    $conn->exec('SET client_encoding TO \'UTF8\'');
    $conn->exec("SET TIME ZONE 'Asia/Bangkok'");
    $conn->exec('SET statement_timeout TO 8000');
} catch (PDOException $e) {
    error_log('Supabase database connection failed: ' . $e->getMessage());
    throw new RuntimeException('Database connection failed.', 0, $e);
}
