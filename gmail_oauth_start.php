<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$clientId = trim((string) (getenv('GMAIL_CLIENT_ID') ?: ''));
$redirectUri = trim((string) (getenv('GMAIL_REDIRECT_URI') ?: ''));
if ($clientId === '' || $redirectUri === '') {
    http_response_code(500);
    exit('ตั้งค่า GMAIL_CLIENT_ID และ GMAIL_REDIRECT_URI ใน Render ก่อน');
}

$state = bin2hex(random_bytes(32));
$_SESSION['gmail_oauth_state'] = $state;
$params = [
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => 'https://www.googleapis.com/auth/gmail.send',
    'access_type' => 'offline',
    'prompt' => 'consent',
    'state' => $state,
];
header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));
exit;
