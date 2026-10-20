<?php

/** Store a cropped PNG in the shared public Supabase bucket and return its URL. */
function uploadPublicAvatar(string $role, string $userId, string $png): string
{
    global $env, $supabaseUrl;

    if (!in_array($role, ['teacher', 'student', 'parent', 'staff'], true)) {
        throw new InvalidArgumentException('Invalid avatar role.');
    }
    $url = rtrim((string) ($env['SUPABASE_URL'] ?? $supabaseUrl ?? ''), '/');
    $key = trim((string) ($env['SUPABASE_SERVICE_ROLE_KEY'] ?? ''));
    $bucket = trim((string) ($env['SUPABASE_LESSON_BUCKET'] ?? 'lesson-media'));
    if ($url === '' || $key === '' || $bucket === '') {
        throw new RuntimeException('Shared Supabase Storage is not configured.');
    }

    $authHeaders = ['apikey: ' . $key];
    if (!str_starts_with($key, 'sb_secret_')) $authHeaders[] = 'Authorization: Bearer ' . $key;
    $request = static function (string $method, string $endpoint, array $headers, ?string $body = null): array {
        $curl = curl_init($endpoint);
        if ($curl === false) throw new RuntimeException('Unable to initialize Supabase Storage request.');
        $options = [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 30];
        if ($body !== null) $options[CURLOPT_POSTFIELDS] = $body;
        curl_setopt_array($curl, $options);
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if ($response === false) throw new RuntimeException('Supabase Storage connection failed: ' . $error);
        return [$status, $response];
    };

    $jsonHeaders = [...$authHeaders, 'Content-Type: application/json'];
    $bucketEndpoint = $url . '/storage/v1/bucket/' . rawurlencode($bucket);
    [$bucketStatus, $bucketResponse] = $request('GET', $bucketEndpoint, $jsonHeaders);
    if ($bucketStatus === 404 || stripos($bucketResponse, 'bucket not found') !== false) {
        [$createStatus, $createResponse] = $request('POST', $url . '/storage/v1/bucket', $jsonHeaders, json_encode(['id' => $bucket, 'name' => $bucket, 'public' => true]));
        if ($createStatus < 200 || $createStatus >= 300) throw new RuntimeException('Supabase avatar bucket setup failed (HTTP ' . $createStatus . ').');
    } elseif ($bucketStatus < 200 || $bucketStatus >= 300) {
        throw new RuntimeException('Supabase avatar bucket check failed (HTTP ' . $bucketStatus . ').');
    }
    [$publicStatus] = $request('PUT', $bucketEndpoint, $jsonHeaders, json_encode(['public' => true]));
    if ($publicStatus < 200 || $publicStatus >= 300) throw new RuntimeException('Supabase avatar access setup failed (HTTP ' . $publicStatus . ').');

    $objectPath = 'avatars/' . $role . '/' . hash('sha256', $userId) . '.png';
    $objectUrl = $url . '/storage/v1/object/' . rawurlencode($bucket) . '/' . implode('/', array_map('rawurlencode', explode('/', $objectPath)));
    [$uploadStatus, $uploadResponse] = $request('POST', $objectUrl, [...$authHeaders, 'Content-Type: image/png', 'x-upsert: true'], $png);
    if ($uploadStatus < 200 || $uploadStatus >= 300) {
        throw new RuntimeException('Supabase avatar upload failed (HTTP ' . $uploadStatus . ').');
    }

    return $url . '/storage/v1/object/public/' . rawurlencode($bucket) . '/' . implode('/', array_map('rawurlencode', explode('/', $objectPath))) . '?t=' . (string) round(microtime(true) * 1000);
}
