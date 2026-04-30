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
