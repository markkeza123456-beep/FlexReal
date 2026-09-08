<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <title>ลืมรหัสผ่าน - NEXORA</title>
  <link rel="stylesheet" href="login.css" />
  <style>
    .step-2, .step-3 { display: none; }
  </style>
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
  <main class="container">
    <div class="card">
      <div class="card-accent"></div>

      <div id="step1" class="step-1">
        <h1 class="title">ลืมรหัสผ่าน</h1>
        <p class="subtitle">กรอกอีเมลเพื่อรับรหัส PIN 6 หลัก</p>
        <form id="forgotForm" class="form">
          <div class="field">
            <label class="label">อีเมล</label>
            <div class="input-wrap">
              <input type="email" id="reset_email" placeholder="example@mail.com" required />
            </div>
          </div>
          <button type="submit" class="btn-submit">ขอรหัส PIN</button>
        </form>
      </div>

      <div id="step2" class="step-2">
        <h1 class="title">ยืนยันรหัส PIN</h1>
        <p class="subtitle">กรอกรหัส PIN ที่ส่งไปยังอีเมล</p>
        <form id="verifyPinForm" class="form">
          <div class="field">
            <label class="label">รหัส PIN (6 หลัก)</label>
            <div class="input-wrap">
              <input type="text" id="pin_code" maxlength="6" placeholder="XXXXXX" required />
            </div>
          </div>
          <button type="submit" class="btn-submit">ยืนยัน PIN</button>
        </form>
      </div>

      <div id="step3" class="step-3">
        <h1 class="title">ตั้งรหัสผ่านใหม่</h1>
        <p class="subtitle">กรอกรหัสผ่านใหม่ และยืนยันรหัสผ่านใหม่</p>
        <form id="resetForm" class="form">
          <div class="field">
            <label class="label">รหัสผ่านใหม่</label>
            <div class="input-wrap">
              <input type="password" id="new_password" placeholder="อย่างน้อย 6 ตัวอักษร" required />
            </div>
          </div>
          <div class="field">
            <label class="label">ยืนยันรหัสผ่านใหม่</label>
            <div class="input-wrap">
              <input type="password" id="confirm_password" placeholder="กรอกรหัสผ่านใหม่อีกครั้ง" required />
            </div>
          </div>
          <button type="submit" class="btn-submit">เปลี่ยนรหัสผ่าน</button>
        </form>
      </div>

      <p class="register-row" style="margin-top:20px;"><a href="login.php" class="link">กลับไปหน้าเข้าสู่ระบบ</a></p>
    </div>
  </main>

  <div class="toast" id="toast"></div>

  <script>
    const toast = document.getElementById('toast');

    function showToast(msg, type = '') {
      toast.textContent = msg;
      toast.className = 'toast show ' + type;
      setTimeout(() => toast.classList.remove('show'), 3000);
    }

    document.getElementById('forgotForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const email = document.getElementById('reset_email').value.trim();
      const btn = e.target.querySelector('button');
      btn.disabled = true;
      btn.innerText = 'กำลังส่ง...';

      try {
        const res = await fetch('forgot_password_action.php', {
          method: 'POST',
          body: new URLSearchParams({ email })
        });

        const text = await res.text();
        let data;
        try {
          data = JSON.parse(text);
        } catch (err) {
          alert('พบข้อผิดพลาดจาก PHP:\n' + text);
          return;
        }

        if (data.status === 'success') {
          showToast(data.message, 'success');
          document.getElementById('step1').style.display = 'none';
          document.getElementById('step2').style.display = 'block';
        } else {
          showToast(data.message, 'error-toast');
        }
      } catch (err) {
        showToast('การเชื่อมต่อล้มเหลว', 'error-toast');
      } finally {
        btn.disabled = false;
        btn.innerText = 'ขอรหัส PIN';
      }
    });

    document.getElementById('verifyPinForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const pin = document.getElementById('pin_code').value.trim();
      const btn = e.target.querySelector('button');
      btn.disabled = true;
      btn.innerText = 'กำลังตรวจสอบ...';

      try {
        const res = await fetch('verify_pin_action.php', {
          method: 'POST',
          body: new URLSearchParams({ pin })
        });
        const data = await res.json();

        if (data.status === 'success') {
          showToast(data.message, 'success');
          document.getElementById('step2').style.display = 'none';
          document.getElementById('step3').style.display = 'block';
        } else {
          showToast(data.message, 'error-toast');
        }
      } catch (err) {
        showToast('การตรวจสอบ PIN ล้มเหลว', 'error-toast');
      } finally {
        btn.disabled = false;
        btn.innerText = 'ยืนยัน PIN';
      }
    });

    document.getElementById('resetForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const newPassword = document.getElementById('new_password').value;
      const confirmPassword = document.getElementById('confirm_password').value;

      try {
        const res = await fetch('reset_password_action.php', {
          method: 'POST',
          body: new URLSearchParams({ new_password: newPassword, confirm_password: confirmPassword })
        });
        const data = await res.json();

        if (data.status === 'success') {
          showToast(data.message, 'success');
          setTimeout(() => {
            window.location.href = 'login.php';
          }, 1500);
        } else {
          showToast(data.message, 'error-toast');
        }
      } catch (err) {
        showToast('การเปลี่ยนรหัสผ่านล้มเหลว', 'error-toast');
      }
    });
  </script>
</body>
</html>
