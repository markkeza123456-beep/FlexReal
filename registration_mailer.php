<?php

use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

function createFlexibleMailer(string $recipient): PHPMailer
{
    $config = static function (string $key, string $default = ''): string {
        $value = getenv($key);
        if (!is_string($value) || trim($value) === '') {
            $value = (string) ($_ENV[$key] ?? $GLOBALS['env'][$key] ?? $default);
        }
        return trim($value);
    };

    $username = $config('SMTP_USERNAME');
    $password = $config('SMTP_PASSWORD');
    if ($username === '' || $password === '') {
        throw new RuntimeException('SMTP_USERNAME and SMTP_PASSWORD must be configured.');
    }

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $config('SMTP_HOST', 'smtp.gmail.com');
    $mail->SMTPAuth = true;
    $mail->Port = (int) $config('SMTP_PORT', '465');
    $encryption = strtoupper($config('SMTP_ENCRYPTION', $mail->Port === 465 ? 'SMTPS' : 'STARTTLS'));
    $mail->SMTPSecure = $encryption === 'SMTPS'
        ? PHPMailer::ENCRYPTION_SMTPS
        : ($encryption === 'STARTTLS' ? PHPMailer::ENCRYPTION_STARTTLS : '');
    $mail->SMTPDebug = max(0, min(4, (int) $config('SMTP_DEBUG', '0')));
    $mail->Debugoutput = static function (string $message, int $level): void {
        error_log('[mail.smtp:' . $level . '] ' . trim($message));
    };
    $mail->CharSet = 'UTF-8';
    $mail->Username = $username;
    $mail->Password = $password;
    $mail->setFrom($config('SMTP_FROM_EMAIL', $username), $config('SMTP_FROM_NAME', 'Flexible Learning Hub'));
    $mail->addAddress($recipient);
    return $mail;
}

function sendRegistrationOtp(string $recipient, string $name, string $otp): void
{
    $mail = createFlexibleMailer($recipient);
    $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeOtp = htmlspecialchars($otp, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $mail->isHTML(true);
    $mail->Subject = 'ยืนยันการสมัครสมาชิก - Flexible Learning Hub';
    $mail->addEmbeddedImage(__DIR__ . '/images/flexible-learning-hub-logo-transparent.png', 'flexible-learning-hub-logo', 'flexible-learning-hub-logo.png');
    $mail->Body = "<!doctype html><html lang='th'><body style='margin:0;padding:24px;background:#f4fbfc;font-family:Arial,sans-serif;color:#263238'><table role='presentation' width='100%' cellspacing='0' cellpadding='0'><tr><td align='center'><table role='presentation' width='560' cellspacing='0' cellpadding='0' style='max-width:100%;background:#fff;border:1px solid #dceef1;border-radius:12px'><tr><td style='padding:28px 32px;text-align:center;border-bottom:1px solid #e7f0f2'><img src='cid:flexible-learning-hub-logo' alt='Flexible Learning Hub' width='240' style='display:block;width:240px;max-width:100%;height:auto;margin:0 auto'></td></tr><tr><td style='padding:28px 32px'><p>สวัสดีคุณ {$safeName},</p><p>รหัส OTP สำหรับยืนยันการสมัครสมาชิก Flexible Learning Hub คือ:</p><p style='margin:24px 0;text-align:center;font-size:28px;font-weight:bold;letter-spacing:6px;color:#087e8b'>{$safeOtp}</p><p>รหัสนี้ใช้ได้ 3 นาที หากคุณไม่ได้สมัครสมาชิก สามารถเพิกเฉยต่ออีเมลนี้ได้</p></td></tr><tr><td style='padding:18px 32px;text-align:center;border-top:1px solid #e7f0f2;color:#64748b;font-size:13px'>Flexible Learning Hub</td></tr></table></td></tr></table></body></html>";
    $mail->AltBody = "สวัสดีคุณ {$name},\n\nรหัส OTP ยืนยันการสมัคร Flexible Learning Hub คือ {$otp}\nรหัสนี้ใช้ได้ 3 นาที\n\nFlexible Learning Hub";
    $mail->send();
}

function sendPasswordResetOtp(string $recipient, string $name, string $otp): void
{
    $mail = createFlexibleMailer($recipient);
    $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeOtp = htmlspecialchars($otp, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $mail->isHTML(true);
    $mail->Subject = 'รีเซ็ตรหัสผ่านบัญชีของคุณ - Flexible Learning Hub';
    $mail->addEmbeddedImage(__DIR__ . '/images/flexible-learning-hub-logo-transparent.png', 'flexible-learning-hub-logo', 'flexible-learning-hub-logo.png');
    $mail->Body = "<!doctype html><html lang='th'><body style='margin:0;padding:24px;background:#f4fbfc;font-family:Arial,sans-serif;color:#263238'><table role='presentation' width='100%' cellspacing='0' cellpadding='0'><tr><td align='center'><table role='presentation' width='560' cellspacing='0' cellpadding='0' style='max-width:100%;background:#fff;border:1px solid #dceef1;border-radius:12px'><tr><td style='padding:28px 32px;text-align:center;border-bottom:1px solid #e7f0f2'><img src='cid:flexible-learning-hub-logo' alt='Flexible Learning Hub' width='240' style='display:block;width:240px;max-width:100%;height:auto;margin:0 auto'></td></tr><tr><td style='padding:28px 32px'><p>สวัสดีคุณ {$safeName},</p><p>รหัส PIN 6 หลักสำหรับรีเซ็ตรหัสผ่านบัญชี Flexible Learning Hub ของคุณคือ:</p><p style='margin:24px 0;text-align:center;font-size:28px;font-weight:bold;letter-spacing:6px;color:#087e8b'>{$safeOtp}</p><p>รหัสนี้จะหมดอายุภายใน 15 นาที</p><p>หากคุณไม่ได้ทำรายการนี้ กรุณาเพิกเฉยต่ออีเมลฉบับนี้</p></td></tr><tr><td style='padding:18px 32px;text-align:center;border-top:1px solid #e7f0f2;color:#64748b;font-size:13px'>Flexible Learning Hub</td></tr></table></td></tr></table></body></html>";
    $mail->AltBody = "สวัสดีคุณ {$name},\n\nรหัส PIN สำหรับรีเซ็ตรหัสผ่าน Flexible Learning Hub คือ {$otp}\nรหัสนี้จะหมดอายุภายใน 15 นาที\n\nFlexible Learning Hub";
    $mail->send();
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
