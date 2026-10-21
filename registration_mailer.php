<?php

use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

function createFlexibleMailer(string $recipient): PHPMailer
{
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;
    $mail->CharSet = 'UTF-8';
    $mail->Username = 'markkeza123456@gmail.com';
    $mail->Password = 'lprq ixqu ugye ayph';
    $mail->setFrom('markkeza123456@gmail.com', 'Flexible Learning Hub');
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
