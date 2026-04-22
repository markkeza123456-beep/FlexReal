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