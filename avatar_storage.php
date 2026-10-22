<?php

/** Store a cropped PNG durably and return a path that can be saved on the profile. */
function uploadPublicAvatar(string $role, string $userId, string $png): string
{
    global $env, $supabaseUrl;

    if (!in_array($role, ['teacher', 'student', 'parent', 'staff', 'director'], true)) {
        throw new InvalidArgumentException('Invalid avatar role.');
    }
    $url = rtrim((string) ($env['SUPABASE_URL'] ?? $supabaseUrl ?? ''), '/');
    $key = trim((string) ($env['SUPABASE_SERVICE_ROLE_KEY'] ?? ''));
    $bucket = trim((string) ($env['SUPABASE_LESSON_BUCKET'] ?? 'lesson-media'));
    $storeLocally = static function () use ($role, $userId, $png): string {
        $relativeDirectory = 'uploads/avatars/' . $role . '/' . hash('sha256', $userId);
        $directory = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create the local avatar directory.');
        }

        $filename = bin2hex(random_bytes(16)) . '.png';
        $path = $directory . DIRECTORY_SEPARATOR . $filename;
        if (file_put_contents($path, $png, LOCK_EX) !== strlen($png)) {
            if (is_file($path)) unlink($path);
            throw new RuntimeException('Unable to save the avatar image locally.');
        }

        return $relativeDirectory . '/' . $filename;
    };

    // The current XAMPP config has no service-role key. In that setup keep
    // avatars on this web server instead of rejecting the upload before the
    // profile URL can be written to the database.
    if ($url === '' || $key === '' || $bucket === '') return $storeLocally();

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

    // Keep each upload immutable. The profile row is switched to this URL only
    // after the upload succeeds, so a failed database write cannot replace the
    // image that is currently in use.
    $objectPath = 'avatars/' . $role . '/' . hash('sha256', $userId) . '/' . bin2hex(random_bytes(16)) . '.png';
    $objectUrl = $url . '/storage/v1/object/' . rawurlencode($bucket) . '/' . implode('/', array_map('rawurlencode', explode('/', $objectPath)));
    [$uploadStatus, $uploadResponse] = $request('POST', $objectUrl, [...$authHeaders, 'Content-Type: image/png', 'x-upsert: true'], $png);
    if ($uploadStatus < 200 || $uploadStatus >= 300) {
        throw new RuntimeException('Supabase avatar upload failed (HTTP ' . $uploadStatus . ').');
    }

    return $url . '/storage/v1/object/public/' . rawurlencode($bucket) . '/' . implode('/', array_map('rawurlencode', explode('/', $objectPath)));
}
