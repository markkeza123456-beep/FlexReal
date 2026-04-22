<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ตรวจสอบโครงสร้างโฟลเดอร์ให้ถูกต้อง (ปกติไฟล์จะอยู่ใน src/)
require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

$email = $_POST['email'] ?? '';

try {
    $stmt = $conn->prepare("SELECT student_id, student_name FROM public.student WHERE email = :email");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $token = sprintf("%06d", mt_rand(1, 999999));
        $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $stmt_token = $conn->prepare("INSERT INTO public.password_reset_tokens (user_id, token, expires_at) VALUES (:uid, :tk, :exp)");
        $stmt_token->execute(['uid' => $user['student_id'], 'tk' => $token, 'exp' => $expires_at]);

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'YOUR_EMAIL@gmail.com'; // อีเมล Gmail ของคุณ
        $mail->Password   = 'YOUR_APP_PASSWORD';    // รหัสผ่านแอป 16 หลัก
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom('YOUR_EMAIL@gmail.com', 'NEXORA Support');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'NEXORA Reset Password PIN';
        $mail->Body    = "รหัส PIN ของคุณคือ: <b>$token</b> (หมดอายุใน 15 นาที)";

        $mail->send();
        echo json_encode(['status' => 'success', 'message' => 'ส่ง PIN สำเร็จ']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบอีเมลนี้']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'ระบบผิดพลาด: ' . $mail->ErrorInfo]);
}