<?php
session_start();
header('Cache-Control: no-store, private');
$pending = $_SESSION['pending_registration'] ?? null;
if (!is_array($pending) || empty($pending['data']['email']) || empty($pending['csrf_token'])) {
    header('Location: regisstu.php');
    exit;
}
$email = (string) $pending['data']['email'];
$parts = explode('@', $email, 2);
$maskedEmail = count($parts) === 2
    ? substr($parts[0], 0, 1) . str_repeat('*', max(2, min(8, strlen($parts[0]) - 1))) . '@' . $parts[1]
    : $email;
$resendIn = max(0, (int) ($pending['resend_at'] ?? time()) - time());
$csrfToken = htmlspecialchars((string) $pending['csrf_token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$maskedEmail = htmlspecialchars($maskedEmail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ยืนยันอีเมล - Flexible Learning Hub</title>
  <link rel="stylesheet" href="assets/css/login.css?v=20261021-home-palette">
  <link rel="stylesheet" href="assets/css/responsive.css">
  <style>
    .otp-code { width:100%; letter-spacing:.35em; text-align:center; font-size:1.35rem; }
    .otp-actions { display:flex; flex-direction:column; gap:12px; margin-top:18px; }
    .otp-resend { border:0; background:none; color:#087e8b; cursor:pointer; padding:10px; font:inherit; }
    .otp-resend:disabled { color:#89969a; cursor:not-allowed; }
    .otp-message { min-height:1.4em; margin:8px 0 0; text-align:center; color:#b42318; }
  </style>
</head>
<body>
  <main class="container">
    <div class="card">
      <div class="brand brand-flh">
        <img class="brand-flh-logo" src="images/flexible-learning-hub-logo-transparent.png" alt="Flexible Learning Hub" width="606" height="199">
      </div>
      <h1 class="title">ยืนยันอีเมล</h1>
      <p class="subtitle">กรอกรหัส OTP 6 หลักที่ส่งไปยัง <?= $maskedEmail ?></p>
      <form class="form" id="registrationOtpForm">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <div class="field">
          <label class="label" for="otp">รหัส OTP</label>
          <div class="input-wrap">
            <input class="otp-code" type="text" id="otp" name="otp" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required>
          </div>
        </div>
        <div class="otp-actions">
          <button type="submit" class="btn-submit" id="verifyOtpButton">ยืนยันและสมัครสมาชิก</button>
          <button type="button" class="otp-resend" id="resendOtpButton" data-resend-in="<?= $resendIn ?>">ส่งรหัส OTP อีกครั้ง</button>
        </div>
        <p class="otp-message" id="otpMessage" role="status" aria-live="polite"></p>
      </form>
      <p class="register-row"><a class="link" href="regisstu.php">กลับไปแก้ไขข้อมูลสมัคร</a></p>
    </div>
  </main>
  <script src="assets/js/registration_otp.js?v=20261021-registration-otp"></script>
</body>
</html>
