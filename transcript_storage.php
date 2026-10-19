<?php
/** Server-side client for the private Supabase Storage transcript bucket. */
function transcriptStorageConfig(): array
{
    global $env, $supabaseUrl;
    $url = rtrim((string) ($env['SUPABASE_URL'] ?? $supabaseUrl ?? ''), '/');
    $serviceKey = trim((string) ($env['SUPABASE_SERVICE_ROLE_KEY'] ?? ''));
    $bucket = trim((string) ($env['SUPABASE_TRANSCRIPT_BUCKET'] ?? 'transcripts'));
    if ($url === '' || $serviceKey === '' || $bucket === '') throw new RuntimeException('Transcript Storage is not configured');
    return [$url, $serviceKey, $bucket];
}

function transcriptStorageObjectUrl(string $url, string $bucket, string $objectPath, bool $authenticatedRead = false): string
{
    $segments = explode('/', ltrim($objectPath, '/'));
    if (!$segments || in_array('', $segments, true) || in_array('.', $segments, true) || in_array('..', $segments, true)) {
        throw new InvalidArgumentException('Invalid Storage object path');
    }
    $route = $authenticatedRead ? 'authenticated/' : '';
    return $url . '/storage/v1/object/' . $route . rawurlencode($bucket) . '/' . implode('/', array_map('rawurlencode', $segments));
}

function transcriptStorageRequest(string $method, string $bucket, string $objectPath, ?string $body = null, ?string $contentType = null): array
{
    [$url, $serviceKey] = transcriptStorageConfig();
    $authenticatedRead = in_array(strtoupper($method), ['GET', 'HEAD'], true);
    $curl = curl_init(transcriptStorageObjectUrl($url, $bucket, $objectPath, $authenticatedRead));
    if ($curl === false) throw new RuntimeException('Unable to initialize Storage request');
    $responseHeaders = [];
    $headers = ['Authorization: Bearer ' . $serviceKey, 'apikey: ' . $serviceKey];
    if ($contentType !== null) $headers[] = 'Content-Type: ' . $contentType;
    if ($method === 'POST') $headers[] = 'x-upsert: false';
    $options = [
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$responseHeaders): int {
            $length = strlen($line);
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            return $length;
        },
    ];
    if ($method === 'GET') $options[CURLOPT_HTTPGET] = true;
    elseif ($method === 'HEAD') $options[CURLOPT_NOBODY] = true;
    elseif ($method === 'POST') $options[CURLOPT_POST] = true;
    else $options[CURLOPT_CUSTOMREQUEST] = $method;
    curl_setopt_array($curl, $options);
    if ($body !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
    $responseBody = curl_exec($curl);
    $curlError = curl_error($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if ($responseBody === false) throw new RuntimeException('Storage request failed: ' . $curlError);
    return ['status' => $status, 'headers' => $responseHeaders, 'body' => $responseBody];
}

function transcriptStorageUpload(string $bucket, string $objectPath, string $localPath, string $mimeType): void
{
    $body = file_get_contents($localPath);
    if ($body === false) throw new RuntimeException('Unable to read uploaded transcript');
    transcriptStorageUploadBytes($bucket, $objectPath, $body, $mimeType);
}

function transcriptStorageUploadBytes(string $bucket, string $objectPath, string $body, string $mimeType): void
{
    $response = transcriptStorageRequest('POST', $bucket, $objectPath, $body, $mimeType);
    if ($response['status'] < 200 || $response['status'] >= 300) throw new RuntimeException('Storage upload rejected with HTTP ' . $response['status']);
}

function transcriptStorageDelete(string $bucket, string $objectPath): void
{
    $response = transcriptStorageRequest('DELETE', $bucket, $objectPath);
    if ($response['status'] < 200 || $response['status'] >= 300) throw new RuntimeException('Storage delete rejected with HTTP ' . $response['status']);
}
