<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/config/db.php';

// ตรวจสอบ session และยืนยัน role กับฐานข้อมูลก่อนแสดงหน้าส่งคำขอ
if (($_SESSION['role'] ?? '') !== 'student' || empty($_SESSION['user_id'])) {
    $_SESSION['after_login_return'] = 'credit_transfer.php';
    header('Location: login.php');
    exit;
}

try {
    $studentCheck = $conn->prepare("SELECT 1 FROM public.users WHERE user_id = :user_id AND role = 'student'");
    $studentCheck->execute([':user_id' => (string) $_SESSION['user_id']]);
    if (!$studentCheck->fetchColumn()) {
        http_response_code(403);
        exit('ไม่มีสิทธิ์เข้าถึงหน้านี้');
    }
} catch (Throwable $e) {
    error_log('Credit transfer page authorization failed: ' . $e->getMessage());
    http_response_code(503);
    exit('ไม่สามารถตรวจสอบสิทธิ์ได้ กรุณาลองใหม่ภายหลัง');
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ขอเทียบโอนหน่วยกิต</title>
    <link rel="stylesheet" href="assets/css/credit_transfer.css?v=20261021-login-shared-background">
    <link rel="stylesheet" href="assets/css/theme.css?v=20261022-mobile-layout-v2">
    <script src="assets/js/credit_transfer.js" defer></script>
    <link rel="stylesheet" href="assets/css/responsive.css?v=20261022-sidebar-menu-fix">
</head>
<body>
    <main class="transfer-page">
        <a class="back-link" href="index.html">← กลับหน้าหลัก</a>
        <section class="transfer-card" aria-labelledby="page-title">
            <h1 id="page-title">ขอเทียบโอนหน่วยกิต</h1>
            <p class="intro">กรอกรายวิชาที่ต้องการเทียบโอน พร้อมลิงก์เอกสารประกอบคำขอ</p>

            <form id="transferForm" action="submit_transfer.php" method="post">
                <div class="field">
                    <label for="courseId">รหัสรายวิชา</label>
                    <input id="courseId" name="course_id" type="text" required maxlength="100" autocomplete="off" placeholder="เช่น CS101">
                </div>

                <div class="field">
                    <label for="documentUrl">ลิงก์เอกสารประกอบ</label>
                    <input id="documentUrl" name="document_url" type="url" required maxlength="2048" inputmode="url" placeholder="https://example.com/document.pdf">
                    <small>ตรวจสอบให้แน่ใจว่าผู้ตรวจสอบสามารถเปิดลิงก์นี้ได้</small>
                </div>

                <button id="submitButton" type="submit">ส่งคำขอ</button>
                <p id="formMessage" class="form-message" role="status" aria-live="polite"></p>
            </form>
        </section>
    </main>
<script src="assets/js/responsive.js?v=20261022-navigation-refresh" defer></script>
</body>
</html>
