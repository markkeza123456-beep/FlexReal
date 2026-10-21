<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

$email = strtolower(trim((string) ($_POST['email'] ?? '')));

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกอีเมล']);
    exit;
}

try {

    $sql = "
        SELECT p.user_id, p.full_name AS user_name
        FROM public.students p INNER JOIN public.users u ON u.user_id = p.user_id
        WHERE LOWER(p.email) = :email AND u.account_status = 'active'
        UNION
        SELECT p.user_id, p.full_name AS user_name
        FROM public.teachers p INNER JOIN public.users u ON u.user_id = p.user_id
        WHERE LOWER(p.email) = :email AND u.account_status = 'active'
        UNION
        SELECT p.user_id, p.full_name AS user_name
        FROM public.parents p INNER JOIN public.users u ON u.user_id = p.user_id
        WHERE LOWER(p.email) = :email AND u.account_status = 'active'
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {

        $token = (string) random_int(100000, 999999);
        $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));


        $conn->prepare('UPDATE public.password_reset_tokens SET used_at = NOW() WHERE user_id = :uid AND used_at IS NULL')
            ->execute([':uid' => $user['user_id']]);

        $stmt_token = $conn->prepare("INSERT INTO public.password_reset_tokens (user_id, token_hash, expires_at) VALUES (:uid, :token_hash, :exp)");
        $stmt_token->execute(['uid' => $user['user_id'], 'token_hash' => password_hash($token, PASSWORD_DEFAULT), 'exp' => $expires_at]);


        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->CharSet    = 'UTF-8';


        $mail->Username   = 'markkeza123456@gmail.com';
        $mail->Password   = 'lprq ixqu ugye ayph';
        $mail->setFrom('markkeza123456@gmail.com', 'Flexible Learning Hub');

        $mail->addAddress($email);


        $mail->isHTML(true);
        $mail->Subject = 'รีเซ็ตรหัสผ่านบัญชีของคุณ - Flexible Learning Hub';
        $safeUserName = htmlspecialchars((string) ($user['user_name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeToken = htmlspecialchars((string) $token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $mail->addEmbeddedImage(__DIR__ . '/images/flexible-learning-hub-logo-transparent.png', 'flexible-learning-hub-logo', 'flexible-learning-hub-logo.png');
        $mail->Body = "<!doctype html><html lang='th'><body style='margin:0;padding:24px;background:#f4fbfc;font-family:Arial,sans-serif;color:#263238'><table role='presentation' width='100%' cellspacing='0' cellpadding='0'><tr><td align='center'><table role='presentation' width='560' cellspacing='0' cellpadding='0' style='max-width:100%;background:#fff;border:1px solid #dceef1;border-radius:12px'><tr><td style='padding:28px 32px;text-align:center;border-bottom:1px solid #e7f0f2'><img src='cid:flexible-learning-hub-logo' alt='Flexible Learning Hub' width='240' style='display:block;width:240px;max-width:100%;height:auto;margin:0 auto'></td></tr><tr><td style='padding:28px 32px'><p>สวัสดีคุณ {$safeUserName},</p><p>รหัส PIN 6 หลักสำหรับรีเซ็ตรหัสผ่านบัญชี Flexible Learning Hub ของคุณคือ:</p><p style='margin:24px 0;text-align:center;font-size:28px;font-weight:bold;letter-spacing:6px;color:#087e8b'>{$safeToken}</p><p>รหัสนี้จะหมดอายุภายใน 15 นาที</p><p>หากคุณไม่ได้ทำรายการนี้ กรุณาเพิกเฉยต่ออีเมลฉบับนี้</p></td></tr><tr><td style='padding:18px 32px;text-align:center;border-top:1px solid #e7f0f2;color:#64748b;font-size:13px'>Flexible Learning Hub</td></tr></table></td></tr></table></body></html>";
        $mail->AltBody = "สวัสดีคุณ {$user['user_name']},\n\nรหัส PIN สำหรับรีเซ็ตรหัสผ่าน Flexible Learning Hub คือ {$token}\nรหัสนี้จะหมดอายุภายใน 15 นาที\n\nFlexible Learning Hub";

        $mail->send();
        session_regenerate_id(true);
        $_SESSION['password_reset_user_id'] = (string) $user['user_id'];
        $_SESSION['password_reset_requested_at'] = time();
        echo json_encode(['status' => 'success', 'message' => 'ส่งรหัส PIN ไปยังอีเมลของคุณแล้ว']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบอีเมลนี้ในระบบ']);
    }
} catch (Throwable $e) {
    error_log('Password reset request failed: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถส่งรหัส PIN ได้ กรุณาลองใหม่ภายหลัง']);
}
?>
