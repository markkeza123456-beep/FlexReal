<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>เข้าสู่ระบบ - NEXORA</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@600;700;800&family=IBM+Plex+Sans+Thai:wght@300;400;500;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="login.css" />
</head>
<body>
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

  <main class="container">
    <div class="card" id="loginCard">
      <div class="card-accent"></div>
      <div class="brand">
        <div class="brand-icon">
          <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
            <polygon points="20,2 38,12 38,28 20,38 2,28 2,12" fill="none" stroke="currentColor" stroke-width="2"/>
            <polygon points="20,10 30,16 30,24 20,30 10,24 10,16" fill="currentColor" opacity="0.3"/>
            <circle cx="20" cy="20" r="4" fill="currentColor"/>
          </svg>
        </div>
        <div class="brand-text">
          <span class="brand-name">FLEXIBLE</span>
          <span class="brand-sub">LEARNING HUB</span>
        </div>
      </div>

      <h1 class="title">เข้าสู่ระบบ</h1>
      <p class="subtitle">เลือกบทบาทแล้วกรอกข้อมูลเพื่อเข้าถึงระบบ</p>

      <div class="role-tabs" id="roleTabs">
        <button class="role-tab active" data-role="student"><span>นักเรียน</span></button>
        <button class="role-tab" data-role="teacher"><span>อาจารย์</span></button>
        <button class="role-tab" data-role="parent"><span>ผู้ปกครอง</span></button>
        <button class="role-tab" data-role="staff"><span>เจ้าหน้าที่</span></button>
      </div>

      <form class="form" id="loginForm" novalidate>
        <div class="field" id="field-email">
          <label class="label" for="email">รหัสบัตรประชาชน</label>
          <div class="input-wrap">
            <span class="input-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 8h10M7 12h6"/><circle cx="16" cy="14" r="2"/>
              </svg>
            </span>
            <input type="text" id="email" name="email" placeholder="กรอกรหัสบัตรประชาชน" autocomplete="off" />
            <span class="focus-bar"></span>
          </div>
          <span class="error-msg" id="email-error"></span>
        </div>

        <div class="field" id="field-password">
          <label class="label" for="password">รหัสผ่าน</label>
          <div class="input-wrap">
            <span class="input-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
              </svg>
            </span>
            <input type="password" id="password" name="password" placeholder="••••••••" autocomplete="current-password" />
            <button type="button" class="toggle-pw" id="togglePw">
              <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
            <span class="focus-bar"></span>
          </div>
          <span class="error-msg" id="password-error"></span>
        </div>
        <div class="options-row" style="margin-top: -5px; margin-bottom: 20px;">
          <a href="forgot_password.php" class="link">ลืมรหัสผ่าน?</a>
        </div>

        <button type="submit" class="btn-submit" id="submitBtn">
          <span class="btn-text">เข้าสู่ระบบ</span>
          <span class="btn-arrow" id="btnArrow">→</span>
          <span class="btn-loader" id="btnLoader" style="display:none;"><div class="spinner"></div></span>
        </button>
      </form>
      <div class="divider"><span>หรือ</span></div>
      <p class="register-row">ยังไม่มีบัญชี? <a href="regisss.php" class="link">สมัครสมาชิก</a></p>
    </div>
  </main>
  <div class="toast" id="toast"></div>
  <script src="login.js"></script>
</body>
</html>
