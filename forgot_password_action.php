<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ตรวจสอบโครงสร้างโฟลเดอร์ PHPMailer ให้ตรงกับเครื่องของคุณ
require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

$email = strtolower(trim((string) ($_POST['email'] ?? '')));

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกอีเมล']);
    exit;
}

try {
    // Query the normalized profile tables defined by database-v2.sql.
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
        // PIN ใช้ได้ครั้งเดียวและหมดอายุใน 15 นาที
        $token = (string) random_int(100000, 999999);
        $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        // คำขอก่อนหน้าของบัญชีเดียวกันใช้ไม่ได้ทันที
        $conn->prepare('UPDATE public.password_reset_tokens SET used_at = NOW() WHERE user_id = :uid AND used_at IS NULL')
            ->execute([':uid' => $user['user_id']]);

        $stmt_token = $conn->prepare("INSERT INTO public.password_reset_tokens (user_id, token_hash, expires_at) VALUES (:uid, :token_hash, :exp)");
        $stmt_token->execute(['uid' => $user['user_id'], 'token_hash' => password_hash($token, PASSWORD_DEFAULT), 'exp' => $expires_at]);

        // 4. ตั้งค่าระบบส่งอีเมล (PHPMailer)
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->CharSet    = 'UTF-8';

        // ใส่อีเมลและรหัสผ่านแอปของคุณ
        $mail->Username   = 'markkeza123456@gmail.com'; 
        $mail->Password   = 'lprq ixqu ugye ayph';    
        $mail->setFrom('markkeza123456@gmail.com', 'Flexible Support'); 
        
        $mail->addAddress($email);

        // 5. เนื้อหาอีเมล
        $mail->isHTML(true);
        $mail->Subject = 'รีเซ็ตรหัสผ่าน - NEXORA Flexible Learning Hub';
        $mail->Body    = "สวัสดีคุณ {$user['user_name']},<br><br>รหัส PIN 6 หลักสำหรับการรีเซ็ตรหัสผ่านของคุณคือ: <h2 style='color:#ff6b1a;'>{$token}</h2><br>รหัสนี้จะหมดอายุภายใน 15 นาที<br><br>หากคุณไม่ได้ทำรายการนี้ กรุณาเพิกเฉยต่ออีเมลฉบับนี้";

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
