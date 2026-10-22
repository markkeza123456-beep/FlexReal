<?php

use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/gmail_oauth.php';

function flexibleMailConfig(string $key, string $default = ''): string
{
    $value = getenv($key);
    if (!is_string($value) || trim($value) === '') {
        $value = (string) ($_ENV[$key] ?? $GLOBALS['env'][$key] ?? $default);
    }
    return trim($value);
}

function createFlexibleMailer(string $recipient): PHPMailer
{
    $username = flexibleMailConfig('SMTP_USERNAME');
    $password = flexibleMailConfig('SMTP_PASSWORD');
    if ($username === '' || $password === '') {
        throw new RuntimeException('SMTP_USERNAME and SMTP_PASSWORD must be configured.');
    }

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = flexibleMailConfig('SMTP_HOST', 'smtp.gmail.com');
    $mail->SMTPAuth = true;
    $mail->Port = (int) flexibleMailConfig('SMTP_PORT', '465');
    $encryption = strtoupper(flexibleMailConfig('SMTP_ENCRYPTION', $mail->Port === 465 ? 'SMTPS' : 'STARTTLS'));
    $mail->SMTPSecure = $encryption === 'SMTPS'
        ? PHPMailer::ENCRYPTION_SMTPS
        : ($encryption === 'STARTTLS' ? PHPMailer::ENCRYPTION_STARTTLS : '');
    $mail->SMTPDebug = max(0, min(4, (int) flexibleMailConfig('SMTP_DEBUG', '0')));
    // Bound SMTP connection waits so a blocked cloud-host egress port cannot
    // leave password reset and registration requests pending indefinitely.
    $mail->Timeout = 15;
    $mail->Debugoutput = static function (string $message, int $level): void {
        error_log('[mail.smtp:' . $level . '] ' . trim($message));
    };
    $mail->CharSet = 'UTF-8';
    $mail->Username = $username;
    $mail->Password = $password;
    $mail->setFrom(flexibleMailConfig('SMTP_FROM_EMAIL', $username), flexibleMailConfig('SMTP_FROM_NAME', 'Flexible Learning Hub'));
    $mail->addAddress($recipient);
    return $mail;
}

/** Prefer Gmail API HTTPS, then Resend HTTPS, then SMTP for local/paid hosts. */
function sendFlexibleMessage(string $recipient, string $subject, string $html, string $text): void
{
    if (flexibleMailConfig('GMAIL_REFRESH_TOKEN') !== '') {
        gmailApiSend($recipient, $subject, $html, $text);
        return;
    }

    $apiKey = flexibleMailConfig('RESEND_API_KEY');
    if ($apiKey === '') {
        $mail = createFlexibleMailer($recipient);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = $text;
        $mail->addEmbeddedImage(__DIR__ . '/images/flexible-learning-hub-logo-transparent.png', 'flexible-learning-hub-logo', 'flexible-learning-hub-logo.png');
        $mail->send();
        return;
    }

    $fromEmail = flexibleMailConfig('RESEND_FROM_EMAIL', 'onboarding@resend.dev');
    $fromName = flexibleMailConfig('RESEND_FROM_NAME', 'Flexible Learning Hub');
    $from = $fromName . ' <' . $fromEmail . '>';
    $publicBaseUrl = rtrim(flexibleMailConfig('APP_BASE_URL', flexibleMailConfig('RENDER_EXTERNAL_URL', 'https://flexible-learning-hub.onrender.com')), '/');
    $logoUrl = htmlspecialchars($publicBaseUrl . '/images/flexible-learning-hub-logo-transparent.png', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = str_replace('cid:flexible-learning-hub-logo', $logoUrl, $html);

    $payload = json_encode([
        'from' => $from,
        'to' => [$recipient],
        'subject' => $subject,
        'html' => $html,
        'text' => $text,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payload === false) throw new RuntimeException('Could not encode the email request for Resend.');

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Authorization: Bearer {$apiKey}\r\nContent-Type: application/json\r\nAccept: application/json\r\n",
            'content' => $payload,
            'timeout' => 20,
            'ignore_errors' => true,
        ],
    ]);
    $responseBody = @file_get_contents('https://api.resend.com/emails', false, $context);
    $status = 0;
    foreach (($http_response_header ?? []) as $header) {
        if (preg_match('/^HTTP\\/\\S+\\s+(\\d{3})/', $header, $matches)) $status = (int) $matches[1];
    }
    if (!is_string($responseBody) || $status < 200 || $status >= 300) {
        $response = is_string($responseBody) ? json_decode($responseBody, true) : null;
        $providerMessage = is_array($response) ? (string) ($response['message'] ?? $response['name'] ?? '') : '';
        $description = $providerMessage !== '' ? ': ' . substr($providerMessage, 0, 300) : '';
        throw new RuntimeException('Resend email API request failed (HTTP ' . ($status ?: 'no response') . ')' . $description);
    }
}

function sendRegistrationOtp(string $recipient, string $name, string $otp): void
{
    $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeOtp = htmlspecialchars($otp, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $subject = 'ยืนยันการสมัครสมาชิก - Flexible Learning Hub';
    $html = "<!doctype html><html lang='th'><body style='margin:0;padding:24px;background:#f4fbfc;font-family:Arial,sans-serif;color:#263238'><table role='presentation' width='100%' cellspacing='0' cellpadding='0'><tr><td align='center'><table role='presentation' width='560' cellspacing='0' cellpadding='0' style='max-width:100%;background:#fff;border:1px solid #dceef1;border-radius:12px'><tr><td style='padding:28px 32px;text-align:center;border-bottom:1px solid #e7f0f2'><img src='cid:flexible-learning-hub-logo' alt='Flexible Learning Hub' width='240' style='display:block;width:240px;max-width:100%;height:auto;margin:0 auto'></td></tr><tr><td style='padding:28px 32px'><p>สวัสดีคุณ {$safeName},</p><p>รหัส OTP สำหรับยืนยันการสมัครสมาชิก Flexible Learning Hub คือ:</p><p style='margin:24px 0;text-align:center;font-size:28px;font-weight:bold;letter-spacing:6px;color:#087e8b'>{$safeOtp}</p><p>รหัสนี้ใช้ได้ 3 นาที หากคุณไม่ได้สมัครสมาชิก สามารถเพิกเฉยต่ออีเมลนี้ได้</p></td></tr><tr><td style='padding:18px 32px;text-align:center;border-top:1px solid #e7f0f2;color:#64748b;font-size:13px'>Flexible Learning Hub</td></tr></table></td></tr></table></body></html>";
    $text = "สวัสดีคุณ {$name},\n\nรหัส OTP ยืนยันการสมัคร Flexible Learning Hub คือ {$otp}\nรหัสนี้ใช้ได้ 3 นาที\n\nFlexible Learning Hub";
    sendFlexibleMessage($recipient, $subject, $html, $text);
}

function sendPasswordResetOtp(string $recipient, string $name, string $otp): void
{
    $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeOtp = htmlspecialchars($otp, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $subject = 'รีเซ็ตรหัสผ่านบัญชีของคุณ - Flexible Learning Hub';
    $html = "<!doctype html><html lang='th'><body style='margin:0;padding:24px;background:#f4fbfc;font-family:Arial,sans-serif;color:#263238'><table role='presentation' width='100%' cellspacing='0' cellpadding='0'><tr><td align='center'><table role='presentation' width='560' cellspacing='0' cellpadding='0' style='max-width:100%;background:#fff;border:1px solid #dceef1;border-radius:12px'><tr><td style='padding:28px 32px;text-align:center;border-bottom:1px solid #e7f0f2'><img src='cid:flexible-learning-hub-logo' alt='Flexible Learning Hub' width='240' style='display:block;width:240px;max-width:100%;height:auto;margin:0 auto'></td></tr><tr><td style='padding:28px 32px'><p>สวัสดีคุณ {$safeName},</p><p>รหัส PIN 6 หลักสำหรับรีเซ็ตรหัสผ่านบัญชี Flexible Learning Hub ของคุณคือ:</p><p style='margin:24px 0;text-align:center;font-size:28px;font-weight:bold;letter-spacing:6px;color:#087e8b'>{$safeOtp}</p><p>รหัสนี้จะหมดอายุภายใน 15 นาที</p><p>หากคุณไม่ได้ทำรายการนี้ กรุณาเพิกเฉยต่ออีเมลฉบับนี้</p></td></tr><tr><td style='padding:18px 32px;text-align:center;border-top:1px solid #e7f0f2;color:#64748b;font-size:13px'>Flexible Learning Hub</td></tr></table></td></tr></table></body></html>";
    $text = "สวัสดีคุณ {$name},\n\nรหัส PIN สำหรับรีเซ็ตรหัสผ่าน Flexible Learning Hub คือ {$otp}\nรหัสนี้จะหมดอายุภายใน 15 นาที\n\nFlexible Learning Hub";
    sendFlexibleMessage($recipient, $subject, $html, $text);
}

function sendStudentPinResetOtp(string $recipient, string $name, string $otp): void
{
    $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeOtp = htmlspecialchars($otp, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $subject = 'ยืนยันการตั้ง PIN นักเรียนใหม่ - Flexible Learning Hub';
    $html = "<!doctype html><html lang='th'><body style='margin:0;padding:24px;background:#f4fbfc;font-family:Arial,sans-serif;color:#263238'><table role='presentation' width='100%' cellspacing='0' cellpadding='0'><tr><td align='center'><table role='presentation' width='560' cellspacing='0' cellpadding='0' style='max-width:100%;background:#fff;border:1px solid #dceef1;border-radius:12px'><tr><td style='padding:28px 32px'><p>สวัสดีคุณ {$safeName},</p><p>รหัสยืนยันการตั้ง PIN นักเรียนใหม่คือ:</p><p style='margin:24px 0;text-align:center;font-size:28px;font-weight:bold;letter-spacing:6px;color:#087e8b'>{$safeOtp}</p><p>รหัสนี้ใช้ได้ 10 นาที หากคุณไม่ได้ทำรายการนี้ กรุณาเพิกเฉยต่ออีเมลฉบับนี้</p></td></tr><tr><td style='padding:18px 32px;text-align:center;border-top:1px solid #e7f0f2;color:#64748b;font-size:13px'>Flexible Learning Hub</td></tr></table></td></tr></table></body></html>";
    $text = "สวัสดีคุณ {$name},\n\nรหัสยืนยันการตั้ง PIN นักเรียนใหม่คือ {$otp}\nรหัสนี้ใช้ได้ 10 นาที\n\nFlexible Learning Hub";
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
    $message = 'ไม่สามารถส่งอีเมลได้ กรุณาลองใหม่อีกครั้ง';
    if (isFlexibleMailDebugEnabled()) {
        $detail = trim($error->getMessage());
        if ($detail !== '') $message .= ' (รายละเอียด: ' . substr($detail, 0, 400) . ')';
    }
    return $message;
}
