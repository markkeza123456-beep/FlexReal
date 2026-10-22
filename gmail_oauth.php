<?php

function gmailOAuthConfig(string $key, string $default = ''): string
{
    $value = getenv($key);
    if (!is_string($value) || trim($value) === '') {
        $value = (string) ($_ENV[$key] ?? $GLOBALS['env'][$key] ?? $default);
    }
    return trim($value);
}

/** Make a short HTTPS request and return its status and response body. */
function gmailHttpsRequest(string $url, string $method, array $headers, string $body): array
{
    $headerText = '';
    foreach ($headers as $name => $value) $headerText .= $name . ': ' . $value . "\r\n";
    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => $headerText,
            'content' => $body,
            'timeout' => 20,
            'ignore_errors' => true,
        ],
    ]);
    $responseBody = @file_get_contents($url, false, $context);
    $status = 0;
    foreach (($http_response_header ?? []) as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches)) $status = (int) $matches[1];
    }
    if (!is_string($responseBody)) throw new RuntimeException('Google HTTPS request failed before receiving a response.');
    return [$status, $responseBody];
}

function gmailOAuthExchangeCode(string $code): array
{
    $form = http_build_query([
        'code' => $code,
        'client_id' => gmailOAuthConfig('GMAIL_CLIENT_ID'),
        'client_secret' => gmailOAuthConfig('GMAIL_CLIENT_SECRET'),
        'redirect_uri' => gmailOAuthConfig('GMAIL_REDIRECT_URI'),
        'grant_type' => 'authorization_code',
    ]);
    [$status, $body] = gmailHttpsRequest('https://oauth2.googleapis.com/token', 'POST', [
        'Content-Type' => 'application/x-www-form-urlencoded',
        'Accept' => 'application/json',
    ], $form);
    $data = json_decode($body, true);
    if ($status < 200 || $status >= 300 || !is_array($data)) {
        $error = is_array($data) ? (string) ($data['error_description'] ?? $data['error'] ?? '') : '';
        throw new RuntimeException('Google OAuth token exchange failed (HTTP ' . $status . ')' . ($error !== '' ? ': ' . $error : ''));
    }
    return $data;
}

function gmailOAuthAccessToken(): string
{
    $clientId = gmailOAuthConfig('GMAIL_CLIENT_ID');
    $clientSecret = gmailOAuthConfig('GMAIL_CLIENT_SECRET');
    $refreshToken = gmailOAuthConfig('GMAIL_REFRESH_TOKEN');
    if ($clientId === '' || $clientSecret === '' || $refreshToken === '') {
        throw new RuntimeException('Gmail API needs GMAIL_CLIENT_ID, GMAIL_CLIENT_SECRET, and GMAIL_REFRESH_TOKEN.');
    }
    $form = http_build_query([
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'refresh_token' => $refreshToken,
        'grant_type' => 'refresh_token',
    ]);
    [$status, $body] = gmailHttpsRequest('https://oauth2.googleapis.com/token', 'POST', [
        'Content-Type' => 'application/x-www-form-urlencoded',
        'Accept' => 'application/json',
    ], $form);
    $data = json_decode($body, true);
    if ($status < 200 || $status >= 300 || !is_array($data) || empty($data['access_token'])) {
        $error = is_array($data) ? (string) ($data['error_description'] ?? $data['error'] ?? '') : '';
        throw new RuntimeException('Google OAuth token refresh failed (HTTP ' . $status . ')' . ($error !== '' ? ': ' . $error : ''));
    }
    return (string) $data['access_token'];
}

function gmailApiSend(string $recipient, string $subject, string $html, string $text): void
{
    $sender = gmailOAuthConfig('GMAIL_SENDER_EMAIL');
    if (!filter_var($sender, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('GMAIL_SENDER_EMAIL must be a valid Gmail address.');

    $publicBaseUrl = rtrim(gmailOAuthConfig('APP_BASE_URL', gmailOAuthConfig('RENDER_EXTERNAL_URL', 'https://flexible-learning-hub.onrender.com')), '/');
    $logoUrl = htmlspecialchars($publicBaseUrl . '/images/flexible-learning-hub-logo-transparent.png', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = str_replace('cid:flexible-learning-hub-logo', $logoUrl, $html);
    $boundary = '=_FLH_' . bin2hex(random_bytes(16));
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $encodedName = '=?UTF-8?B?' . base64_encode('Flexible Learning Hub') . '?=';
    $mime = "To: {$recipient}\r\nFrom: {$encodedName} <{$sender}>\r\nSubject: {$encodedSubject}\r\nMIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n\r\n";
    $mime .= "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text), 76, "\r\n");
    $mime .= "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html), 76, "\r\n");
    $mime .= "--{$boundary}--\r\n";
    $raw = rtrim(strtr(base64_encode($mime), '+/', '-_'), '=');
    $payload = json_encode(['raw' => $raw], JSON_UNESCAPED_SLASHES);
    if ($payload === false) throw new RuntimeException('Could not encode the Gmail API email request.');

    $accessToken = gmailOAuthAccessToken();
    [$status, $body] = gmailHttpsRequest('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', 'POST', [
        'Authorization' => 'Bearer ' . $accessToken,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ], $payload);
    if ($status < 200 || $status >= 300) {
        $data = json_decode($body, true);
        $message = is_array($data) ? (string) ($data['error']['message'] ?? '') : '';
        throw new RuntimeException('Gmail API send failed (HTTP ' . $status . ')' . ($message !== '' ? ': ' . substr($message, 0, 300) : ''));
    }
}
