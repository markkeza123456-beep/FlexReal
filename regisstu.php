<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/config/db.php';

try {

    $sql = "SELECT DISTINCT level
            FROM public.curricula
            WHERE status = 'active' AND level IS NOT NULL AND BTRIM(level) <> ''
            ORDER BY level ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $levels = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $groupedLevels = [];
    foreach ($levels as $row) {
        $groupedLevels[$row['level']][] = $row;
    }



} catch (PDOException $e) {
    die('ไม่สามารถโหลดข้อมูลหลักสูตรได้ กรุณาลองใหม่ภายหลัง');
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>สมัครสมาชิก — Flexible Learning Hub</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@600;700;800&family=IBM+Plex+Sans+Thai:wght@300;400;500;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/regisstu.css?v=20261021-registration-password-toggle" />
  <link rel="stylesheet" href="assets/css/login.css?v=20261021-brighter-success-green" />
  <link rel="stylesheet" href="assets/css/regisstu.css?v=20261021-login-shared-background" />
  <link rel="stylesheet" href="assets/css/login.css?v=20261021-brighter-success-green" />
    <link rel="stylesheet" href="assets/css/responsive.css?v=20261022-sidebar-menu-fix">
  <link rel="stylesheet" href="assets/css/theme.css?v=20261022-mobile-layout-v2" />
</head>
<body class="register-page">
  <div class="bg-grid"></div>
  <div class="edu-pattern" aria-hidden="true">
    <svg class="edu-illustration book book-one" viewBox="0 0 160 140" fill="none"><path d="M24 34h42c13 0 24 11 24 24v52c0-13-11-24-24-24H24zM136 34H94c-13 0-24 11-24 24v52c0-13 11-24 24-24h42z"/><path d="M43 54h27M43 72h18M117 54H90M117 72H99"/><path d="M50 15l7 13 15 2-11 10 3 15-14-7-14 7 3-15-11-10 15-2z"/></svg>
    <svg class="edu-illustration cap cap-one" viewBox="0 0 160 140" fill="none"><path d="M22 58 80 28l58 30-58 30zM47 72v22c18 16 48 16 66 0V72M138 59v36"/><path d="M138 95c0 7-11 7-11 0 0-6 11-6 11 0zM80 88v31M64 119h32"/></svg>
    <svg class="edu-illustration bulb bulb-one" viewBox="0 0 160 140" fill="none"><path d="M80 23c-25 0-44 20-44 45 0 16 9 29 21 37 5 3 7 9 7 15h32c0-6 2-12 7-15 12-8 21-21 21-37 0-25-19-45-44-45zM64 120h32M68 132h24"/><path d="M80 5v11M38 20l8 8M122 20l-8 8M20 62h11M129 62h11"/></svg>
    <svg class="edu-illustration globe globe-one" viewBox="0 0 160 140" fill="none"><circle cx="80" cy="66" r="42"/><path d="M38 66h84M80 24c16 12 24 27 24 42s-8 30-24 42c-16-12-24-27-24-42s8-30 24-42zM54 116h52M80 108v20M55 136h50"/><path d="M18 32l16 7M126 100l16 7"/></svg>
    <svg class="edu-illustration network network-one" viewBox="0 0 160 140" fill="none"><path d="m32 90 28-40 37 18 25-35M32 90l41 22 49-20M60 50l13 62M97 68l25 24"/><circle cx="32" cy="90" r="8"/><circle cx="60" cy="50" r="8"/><circle cx="97" cy="68" r="8"/><circle cx="122" cy="33" r="8"/><circle cx="73" cy="112" r="8"/><circle cx="122" cy="92" r="8"/></svg>
    <svg class="edu-illustration pencil pencil-one" viewBox="0 0 160 140" fill="none"><path d="m36 101 10-32 56-56 20 20-56 56zM92 23l20 20M36 101l24-14-10-10z"/><path d="M22 116h96M36 126h68"/></svg>
  </div>
  <div class="glow-orb orb-1"></div>
  <div class="glow-orb orb-2"></div>

  <div class="container">
    <div class="brand brand-flh">
      <img class="brand-flh-logo" src="images/flexible-learning-hub-logo-transparent.png?v=20261021-transparent-horizontal-cropped" alt="Flexible Learning Hub" width="606" height="199" fetchpriority="high" />
    </div>
    <div class="card">
      <div class="card-accent"></div>

      <h1 class="title">สมัครสมาชิก</h1>
      <p class="subtitle">เลือกบทบาทแล้วกรอกข้อมูลเพื่อสร้างบัญชี</p>

      <div class="role-tabs" id="roleTabs">
        <button type="button" class="role-tab active" data-role="student"><span>นักเรียน</span></button>
        <button type="button" class="role-tab" data-role="teacher"><span>อาจารย์</span></button>
        <button type="button" class="role-tab" data-role="parent"><span>ผู้ปกครอง</span></button>
      </div>

      <form class="form" id="regisForm" action="regisstu_action.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="role" id="roleInput" value="student">

        <div class="section-label">ข้อมูลส่วนตัว</div>

        <div class="grid-2">
          <div class="field" id="field-firstname">
            <label class="label">ชื่อ<span class="required">*</span></label>
            <div class="input-wrap">
              <span class="input-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
              <input type="text" id="firstname" name="fullname" placeholder="ชื่อจริง" required />
              <span class="focus-bar"></span>
            </div>
            <span class="error-msg" id="firstname-error"></span>
          </div>
          <div class="field" id="field-lastname">
            <label class="label">นามสกุล<span class="required">*</span></label>
            <div class="input-wrap">
              <span class="input-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
              <input type="text" id="lastname" name="lastname" placeholder="นามสกุล" required />
              <span class="focus-bar"></span>
            </div>
            <span class="error-msg" id="lastname-error"></span>
          </div>
        </div>

        <div class="field" id="field-idcard">
          <label class="label" for="idcard">เลขบัตรประจำตัวประชาชน<span class="required">*</span></label>
          <div class="input-wrap">
            <span class="input-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 8h10M7 12h6"/><circle cx="16" cy="14" r="2"/></svg></span>
            <input type="text" id="idcard" name="userid" placeholder="X-XXXX-XXXXX-XX-X" maxlength="17" inputmode="numeric" autocomplete="off" required />
            <span class="focus-bar"></span>
          </div>
          <span class="error-msg" id="idcard-error"></span>
        </div>

        <div class="field role-field student-field" id="field-level">
          <label class="label">ระดับชั้น<span class="required">*</span></label>
          <div class="input-wrap">
            <span class="input-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg></span>
            <select id="level" name="level" required>
              <option value="" disabled selected>เลือกระดับชั้น</option>
              <?php foreach ($groupedLevels as $levelGroup => $items): ?>
                <option value="<?= htmlspecialchars($levelGroup) ?>">
                  <?= htmlspecialchars($levelGroup) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <span class="select-arrow"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></span>
            <span class="focus-bar"></span>
          </div>
          <span class="error-msg" id="level-error"></span>
        </div>

        <div class="role-field parent-field" id="parent-link-section" style="display:none">
          <div class="section-label">เชื่อมโยงบัญชีนักเรียน</div>
          <div class="field" id="field-link-student-id">
            <label class="label" for="link_student_id">เลขประจำตัวนักเรียน<span class="required">*</span></label>
            <div class="input-wrap">
              <input type="text" id="link_student_id" name="link_student_id" placeholder="X-XXXX-XXXXX-XX-X" maxlength="17" inputmode="numeric" autocomplete="off" />
              <span class="focus-bar"></span>
            </div>
            <span class="error-msg" id="link-student-id-error"></span>
          </div>
        </div>

        <div class="section-label">ข้อมูลติดต่อ</div>
        <div class="field">
          <label class="label" for="email">อีเมล</label>
          <div class="input-wrap">
            <input type="email" id="email" name="email" placeholder="example@email.com" autocomplete="email" required />
            <span class="focus-bar"></span>
          </div>
        </div>
        <div class="field">
          <label class="label">เบอร์โทรศัพท์<span class="required">*</span></label>
          <div class="input-wrap">
            <span class="input-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.35 2 2 0 0 1 3.58 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.56a16 16 0 0 0 6.53 6.53l1.62-1.62a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg></span>
            <input type="tel" id="phone" name="phone" placeholder="0XX-XXX-XXXX" required />
            <span class="focus-bar"></span>
          </div>
        </div>

        <div class="section-label">ที่อยู่</div>

        <div class="field" id="field-house">
          <label class="label" for="house">บ้านเลขที่ / ถนน<span class="required">*</span></label>
          <div class="input-wrap">
            <span class="input-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            </span>
            <input type="text" id="house" name="house" placeholder="เช่น 99/1 ถ.สุขุมวิท" required />
            <span class="focus-bar"></span>
          </div>
          <span class="error-msg" id="house-error"></span>
        </div>

        <div class="grid-2">
          <div class="field" id="field-province">
            <label class="label" for="province">จังหวัด<span class="required">*</span></label>
            <div class="input-wrap">
              <input type="text" id="province" name="province" list="province-options" placeholder="พิมพ์หรือเลือกจังหวัด" autocomplete="address-level1" required />
              <datalist id="province-options"></datalist>
              <span class="focus-bar"></span>
            </div>
            <span class="error-msg" id="province-error"></span>
          </div>

          <div class="field" id="field-amphoe">
            <label class="label" for="amphoe">อำเภอ / เขต<span class="required">*</span></label>
            <div class="input-wrap">
              <input type="text" id="amphoe" name="amphoe" list="amphoe-options" placeholder="เลือกจังหวัดก่อน" autocomplete="address-level2" required disabled />
              <datalist id="amphoe-options"></datalist>
              <span class="focus-bar"></span>
            </div>
            <span class="error-msg" id="amphoe-error"></span>
          </div>
        </div>

        <div class="grid-2">
          <div class="field" id="field-tambon">
            <label class="label" for="tambon">ตำบล / แขวง<span class="required">*</span></label>
            <div class="input-wrap">
              <input type="text" id="tambon" name="tambon" list="tambon-options" placeholder="เลือกอำเภอก่อน" autocomplete="address-level3" required disabled />
              <datalist id="tambon-options"></datalist>
              <span class="focus-bar"></span>
            </div>
            <span class="error-msg" id="tambon-error"></span>
          </div>

          <div class="field" id="field-zipcode">
            <label class="label" for="zipcode">รหัสไปรษณีย์<span class="required">*</span></label>
            <div class="input-wrap">
              <input type="text" id="zipcode" name="zipcode" placeholder="XXXXX" maxlength="5" required />
              <span class="focus-bar"></span>
            </div>
            <span class="error-msg" id="zipcode-error"></span>
          </div>
        </div>

        <div class="section-label">ตั้งรหัสผ่าน</div>
        <div class="grid-2">
          <div class="field"><label class="label" for="password">รหัสผ่าน</label><div class="input-wrap"><input type="password" name="password" id="password" minlength="8" maxlength="15" pattern="(?=.*[A-Za-z])(?=.*[0-9])[A-Za-z0-9]{8,15}" required /><button type="button" class="toggle-pw" data-toggle-password="password" aria-label="แสดงรหัสผ่าน" title="แสดงรหัสผ่าน"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/><path class="eye-slash" d="m3 3 18 18"/></svg></button><span class="focus-bar"></span></div><small class="password-hint">ใช้ตัวอักษรภาษาอังกฤษและตัวเลข 8-15 ตัว</small></div>
          <div class="field"><label class="label" for="confirm">ยืนยันรหัสผ่าน</label><div class="input-wrap"><input type="password" name="confirm" id="confirm" minlength="8" maxlength="15" pattern="(?=.*[A-Za-z])(?=.*[0-9])[A-Za-z0-9]{8,15}" required /><button type="button" class="toggle-pw" data-toggle-password="confirm" aria-label="แสดงรหัสผ่าน" title="แสดงรหัสผ่าน"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/><path class="eye-slash" d="m3 3 18 18"/></svg></button><span class="focus-bar"></span></div><small class="password-hint">กรอกรหัสผ่านเดิมอีกครั้ง</small></div>
        </div>

        <div class="role-field student-field" id="pin-section">
          <div class="section-label">ตั้ง PIN สำหรับผู้ปกครอง</div>
          <div class="field"><label class="label" for="student_pin">PIN 6 หลัก</label><div class="input-wrap"><input type="password" name="student_pin" id="student_pin" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" /><button type="button" class="toggle-pw" data-toggle-password="student_pin" aria-label="แสดง PIN" title="แสดง PIN"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/><path class="eye-slash" d="m3 3 18 18"/></svg></button><span class="focus-bar"></span></div></div>
        </div>

        <button type="submit" class="btn-submit" id="submitBtn">
          <span class="btn-text">สมัครสมาชิก</span>
          <span class="btn-loader" id="btnLoader" style="display:none;"><div class="spinner"></div></span>
        </button>
      </form>

    </div>
    <div class="back-row">มีบัญชีอยู่แล้ว? <a href="login.php" class="link">เข้าสู่ระบบ</a></div>
  </div>
  <div class="toast" id="toast"></div>
  <script src="assets/js/thai-address.js?v=20261022-cascading-search"></script>
  <script src="assets/js/regisstu.js?v=20261021-registration-password-toggle"></script>
<script src="assets/js/responsive.js?v=20261022-navigation-refresh" defer></script>
</body>
</html>
