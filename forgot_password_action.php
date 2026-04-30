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

$email = trim($_POST['email'] ?? '');

if (empty($email)) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกอีเมล']);
    exit;
}

try {
    // 1. ค้นหาอีเมลจากทุกตารางที่มีคอลัมน์ Email
    $sql = "
        SELECT Student_ID AS user_id, Student_Name AS user_name FROM public.student WHERE email = :email
        UNION
        SELECT Teachers_ID AS user_id, Teachers_Name AS user_name FROM public.teachers WHERE email = :email
        UNION
        SELECT Parents_ID AS user_id, Parents_Name AS user_name FROM public.parents WHERE email = :email
        UNION
        SELECT Executive_ID AS user_id, Executive_Name AS user_name FROM public.executive WHERE email = :email
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // 2. สร้าง PIN 6 หลัก และตั้งเวลาหมดอายุ 15 นาที
        $token = sprintf("%06d", mt_rand(1, 999999));
        $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        // 3. บันทึก PIN ลงตาราง password_reset_tokens
        $stmt_token = $conn->prepare("INSERT INTO public.password_reset_tokens (user_id, token, expires_at) VALUES (:uid, :tk, :exp)");
        $stmt_token->execute(['uid' => $user['user_id'], 'tk' => $token, 'exp' => $expires_at]);

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
        echo json_encode(['status' => 'success', 'message' => 'ส่งรหัส PIN ไปยังอีเมลของคุณแล้ว']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบอีเมลนี้ในระบบ']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'ระบบส่งอีเมลผิดพลาด: ' . $e->getMessage()]);
}
?>