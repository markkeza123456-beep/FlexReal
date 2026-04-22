<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>สมัครสมาชิก - Flexible Learning Hub</title>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@600;700&family=IBM+Plex+Sans+Thai:wght@300;400;500;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="login.css" />
  <style>
    .field-hidden { display: none; }
    .divider { display: flex; align-items: center; gap: 10px; margin: 20px 0; color: var(--text-muted); font-size: 0.8rem; }
    .divider::before, .divider::after { content: ""; flex: 1; height: 1px; background: var(--border); }
  </style>
</head>
<body>
  <div class="bg-grid"></div>
  <main class="container">
    <div class="card">
      <div class="card-accent"></div>
      <h1 class="title">สมัครสมาชิก</h1>
      <p class="subtitle">สร้างบัญชีใหม่เพื่อเชื่อมโยงการเรียนรู้</p>

      <div class="role-tabs">
        <button class="role-tab active" onclick="switchRegRole('student')">นักเรียน</button>
        <button class="role-tab" onclick="switchRegRole('parent')">ผู้ปกครอง</button>
      </div>

      <form class="form" action="regisss_action.php" method="POST">
        <input type="hidden" name="role" id="regRole" value="student">
        
        <div class="field">
          <label class="label">รหัสบัตรประชาชน (ของคุณ)</label>
          <div class="input-wrap"><input type="text" name="userid" required maxlength="13"></div>
        </div>

        <div class="field">
          <label class="label">ชื่อ-นามสกุล</label>
          <div class="input-wrap"><input type="text" name="fullname" required></div>
        </div>

        <div class="field">
          <label class="label">รหัสผ่าน</label>
          <div class="input-wrap"><input type="password" name="password" required></div>
        </div>

        <div id="student-only">
          <div class="field">
            <label class="label" style="color: var(--orange);">ตั้งรหัส PIN (6 หลัก)</label>
            <div class="input-wrap"><input type="password" name="student_pin" maxlength="6" placeholder="รหัสสำหรับให้ผู้ปกครองผูกบัญชี"></div>
          </div>
        </div>

        <div id="parent-only" class="field-hidden">
          <div class="divider"><span>ข้อมูลนักเรียนที่ต้องการผูก</span></div>
          <div class="field">
            <label class="label">รหัสบัตรประชาชนนักเรียน</label>
            <div class="input-wrap"><input type="text" name="link_student_id" placeholder="รหัส 13 หลักของนักเรียน"></div>
          </div>
          <div class="field">
            <label class="label">PIN ของนักเรียน</label>
            <div class="input-wrap"><input type="password" name="link_student_pin" maxlength="6" placeholder="รหัส PIN 6 หลักของนักเรียน"></div>
          </div>
        </div>

        <button type="submit" class="btn-submit">ลงทะเบียน</button>
      </form>
      <p class="register-row" style="margin-top:20px;">มีบัญชีอยู่แล้ว? <a href="login.php" class="link">เข้าสู่ระบบ</a></p>
    </div>
  </main>

  <script>
    function switchRegRole(role) {
      document.getElementById('regRole').value = role;
      const tabs = document.querySelectorAll('.role-tab');
      tabs[0].classList.toggle('active', role === 'student');
      tabs[1].classList.toggle('active', role === 'parent');
      document.getElementById('student-only').style.display = (role === 'student') ? 'block' : 'none';
      document.getElementById('parent-only').style.display = (role === 'parent') ? 'block' : 'none';
    }
  </script>
</body>
</html>