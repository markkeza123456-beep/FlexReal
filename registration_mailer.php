<?php

function flexibleMailConfig(string $key, string $default = ''): string
{
    $value = getenv($key);
    if (!is_string($value) || trim($value) === '') {
        $value = (string) ($_ENV[$key] ?? $GLOBALS['env'][$key] ?? $default);
    }
    return trim($value);
}

function sendFlexibleMessage(string $recipient, string $subject, string $html, string $text): void
{
    $url = flexibleMailConfig('GOOGLE_APPS_SCRIPT_URL');
    if ($url === '') {
        throw new RuntimeException('ยังไม่ได้ตั้งค่า GOOGLE_APPS_SCRIPT_URL ใน Environment Variables');
    }
    $urlParts = parse_url($url);
    if (!is_array($urlParts)
        || strtolower((string) ($urlParts['scheme'] ?? '')) !== 'https'
        || strtolower((string) ($urlParts['host'] ?? '')) !== 'script.google.com'
        || !preg_match('~^/macros/s/[^/]+/exec/?$~', (string) ($urlParts['path'] ?? ''))
    ) {
        throw new RuntimeException('GOOGLE_APPS_SCRIPT_URL ต้องเป็น URL เว็บแอป https://script.google.com/macros/s/.../exec');
    }

    $payload = json_encode([
        'to' => $recipient,
        'subject' => $subject,
        'html' => $html,
        'text' => $text,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($payload)) throw new RuntimeException('เตรียมข้อมูลอีเมลไม่สำเร็จ');

    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8', 'Accept: application/json'],
        ]);
        $responseBody = curl_exec($curl);
        $curlError = curl_error($curl);
        $httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if (!is_string($responseBody)) {
            throw new RuntimeException('เชื่อมต่อ Google Apps Script ไม่สำเร็จ: ' . ($curlError !== '' ? $curlError : 'ไม่มีคำตอบจากเซิร์ฟเวอร์'));
        }
    } else {
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json; charset=utf-8\r\nAccept: application/json\r\n",
            'content' => $payload,
            'timeout' => 30,
            'ignore_errors' => true,
            'follow_location' => 1,
            'max_redirects' => 5,
        ]]);
        $responseBody = @file_get_contents($url, false, $context);
        $httpStatus = 0;
        foreach (($http_response_header ?? []) as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches)) $httpStatus = (int) $matches[1];
        }
        if (!is_string($responseBody)) throw new RuntimeException('เชื่อมต่อ Google Apps Script ไม่สำเร็จ กรุณาตรวจ URL และการ Deploy เว็บแอป');
    }

    $result = json_decode($responseBody, true);
    if ($httpStatus < 200 || $httpStatus >= 300 || !is_array($result) || ($result['status'] ?? '') !== 'success') {
        $providerMessage = is_array($result) ? trim((string) ($result['message'] ?? '')) : '';
        if ($providerMessage === '') $providerMessage = trim(substr(strip_tags($responseBody), 0, 300));
        throw new RuntimeException('Google Apps Script ส่งอีเมลไม่สำเร็จ' . ($providerMessage !== '' ? ': ' . $providerMessage : ' (HTTP ' . ($httpStatus ?: 'ไม่มีคำตอบ') . ')'));
    }
}

function sendRegistrationOtp(string $recipient, string $name, string $otp): void
{
    $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeOtp = htmlspecialchars($otp, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $subject = 'ยืนยันการสมัครสมาชิก - Flexible Learning Hub';
    $html = "<!doctype html><html lang='th'><body style='margin:0;padding:24px;background:#f4fbfc;font-family:Arial,sans-serif;color:#263238'><table role='presentation' width='100%' cellspacing='0' cellpadding='0'><tr><td align='center'><table role='presentation' width='560' cellspacing='0' cellpadding='0' style='max-width:100%;background:#fff;border:1px solid #dceef1;border-radius:12px'><tr><td style='padding:28px 32px;text-align:center;border-bottom:1px solid #e7f0f2;font-size:22px;font-weight:bold;color:#087e8b'>Flexible Learning Hub</td></tr><tr><td style='padding:28px 32px'><p>สวัสดีคุณ {$safeName},</p><p>รหัส OTP สำหรับยืนยันการสมัครสมาชิก Flexible Learning Hub คือ:</p><p style='margin:24px 0;text-align:center;font-size:28px;font-weight:bold;letter-spacing:6px;color:#087e8b'>{$safeOtp}</p><p>รหัสนี้ใช้ได้ 3 นาที หากคุณไม่ได้สมัครสมาชิก สามารถเพิกเฉยต่ออีเมลนี้ได้</p></td></tr><tr><td style='padding:18px 32px;text-align:center;border-top:1px solid #e7f0f2;color:#64748b;font-size:13px'>Flexible Learning Hub</td></tr></table></td></tr></table></body></html>";
    $text = "สวัสดีคุณ {$name},\n\nรหัส OTP ยืนยันการสมัคร Flexible Learning Hub คือ {$otp}\nรหัสนี้ใช้ได้ 3 นาที\n\nFlexible Learning Hub";
    sendFlexibleMessage($recipient, $subject, $html, $text);
}

function sendPasswordResetOtp(string $recipient, string $name, string $otp): void
{
    $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeOtp = htmlspecialchars($otp, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $subject = 'รีเซ็ตรหัสผ่านบัญชีของคุณ - Flexible Learning Hub';
    $html = "<!doctype html><html lang='th'><body style='margin:0;padding:24px;background:#f4fbfc;font-family:Arial,sans-serif;color:#263238'><table role='presentation' width='100%' cellspacing='0' cellpadding='0'><tr><td align='center'><table role='presentation' width='560' cellspacing='0' cellpadding='0' style='max-width:100%;background:#fff;border:1px solid #dceef1;border-radius:12px'><tr><td style='padding:28px 32px;text-align:center;border-bottom:1px solid #e7f0f2;font-size:22px;font-weight:bold;color:#087e8b'>Flexible Learning Hub</td></tr><tr><td style='padding:28px 32px'><p>สวัสดีคุณ {$safeName},</p><p>รหัส PIN 6 หลักสำหรับรีเซ็ตรหัสผ่านบัญชี Flexible Learning Hub ของคุณคือ:</p><p style='margin:24px 0;text-align:center;font-size:28px;font-weight:bold;letter-spacing:6px;color:#087e8b'>{$safeOtp}</p><p>รหัสนี้จะหมดอายุภายใน 15 นาที</p><p>หากคุณไม่ได้ทำรายการนี้ กรุณาเพิกเฉยต่ออีเมลฉบับนี้</p></td></tr><tr><td style='padding:18px 32px;text-align:center;border-top:1px solid #e7f0f2;color:#64748b;font-size:13px'>Flexible Learning Hub</td></tr></table></td></tr></table></body></html>";
    $text = "สวัสดีคุณ {$name},\n\nรหัส PIN สำหรับรีเซ็ตรหัสผ่าน Flexible Learning Hub คือ {$otp}\nรหัสนี้จะหมดอายุภายใน 15 นาที\n\nFlexible Learning Hub";
    sendFlexibleMessage($recipient, $subject, $html, $text);
}

function isFlexibleMailDebugEnabled(): bool
{
    $value = getenv('SMTP_DEBUG');
    if (!is_string($value) || $value === '') $value = (string) ($_ENV['SMTP_DEBUG'] ?? $GLOBALS['env']['SMTP_DEBUG'] ?? '0');
    return (int) $value > 0;
}

function flexibleMailFailureMessage(Throwable $error): string
{
    $message = trim($error->getMessage());
    return $message !== '' ? $message : 'ไม่สามารถส่งอีเมลได้ กรุณาลองใหม่อีกครั้ง';
}
