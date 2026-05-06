<?php
session_start();
require_once 'db_connect.php'; 

try {
    // 1. ดึงข้อมูลระดับชั้นแบบแยกกลุ่มอัตโนมัติ (คงเดิมตามไฟล์ source)[cite: 11]
    $sql = "SELECT level, curriculums_id, curriculums_name 
            FROM public.curriculums 
            WHERE status = 'active' AND level = 'ม.ปลาย' 
            ORDER BY level DESC, curriculums_id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $levels = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $groupedLevels = [];
    foreach ($levels as $row) {
        $groupedLevels[$row['level']][] = $row;
    }

    // 2. 💥 ดึงข้อมูลจังหวัดจากฐานข้อมูลตามที่คุณต้องการ
    $stmtProvince = $conn->prepare("SELECT name FROM public.provinces ORDER BY name ASC");
    $stmtProvince->execute();
    $provincesFromDb = $stmtProvince->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . " <br>กรุณาตรวจสอบว่ามีตาราง 'provinces' และคอลัมน์ที่จำเป็นในตาราง 'curriculums' แล้วหรือยังครับ");
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
  <link rel="stylesheet" href="regisstu.css" />
</head>
<body>
  <div class="bg-grid"></div>
  <div class="glow-orb orb-1"></div>
  <div class="glow-orb orb-2"></div>

  <div class="container">
    <div class="card">
      <div class="card-accent"></div>

      <div class="brand">
        <div class="brand-icon">
          <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
            <polygon points="20,2 38,12 38,28 20,38 2,28 2,12" fill="none" stroke="currentColor" stroke-width="2"/>
            <circle cx="20" cy="20" r="4" fill="currentColor"/>
          </svg>
        </div>
        <div class="brand-text">
          <span class="brand-name">FLEXIBLE</span>
          <span class="brand-sub">LEARNING HUB</span>
        </div>
      </div>

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
          <label class="label">เลขบัตรประชาชน<span class="required">*</span></label>
          <div class="input-wrap">
            <span class="input-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 8h10M7 12h6"/><circle cx="16" cy="14" r="2"/></svg></span>
            <input type="text" id="idcard" name="userid" placeholder="X-XXXX-XXXXX-XX-X" maxlength="17" autocomplete="off" required />
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
                <optgroup label="<?= htmlspecialchars($levelGroup) ?>">
                  <?php foreach ($items as $item): ?>
                    <option value="<?= htmlspecialchars($item['curriculums_id']) ?>">
                      <?= htmlspecialchars($item['curriculums_name']) ?>
                    </option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endforeach; ?>
            </select>
            <span class="select-arrow"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></span>
            <span class="focus-bar"></span>
          </div>
          <span class="error-msg" id="level-error"></span>
        </div>

        <div class="section-label">ข้อมูลติดต่อ</div>
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
          <div class="field" id="field-tambon">
            <label class="label" for="tambon">ตำบล / แขวง<span class="required">*</span></label>
            <div class="input-wrap">
              <input type="text" id="tambon" name="tambon" placeholder="ตำบล / แขวง" required />
              <span class="focus-bar"></span>
            </div>
            <span class="error-msg" id="tambon-error"></span>
          </div>

          <div class="field" id="field-amphoe">
            <label class="label" for="amphoe">อำเภอ / เขต<span class="required">*</span></label>
            <div class="input-wrap">
              <input type="text" id="amphoe" name="amphoe" placeholder="อำเภอ / เขต" required />
              <span class="focus-bar"></span>
            </div>
            <span class="error-msg" id="amphoe-error"></span>
          </div>
        </div>

        <div class="grid-2">
          <div class="field" id="field-province">
            <label class="label" for="province">จังหวัด<span class="required">*</span></label>
            <div class="input-wrap">
              <select id="province" name="province" required>
                <option value="" disabled selected>เลือกจังหวัด</option>
                <!-- 💥 วนลูปแสดงรายชื่อจังหวัดจากฐานข้อมูล -->
                <?php foreach ($provincesFromDb as $prov): ?>
                  <option value="<?= htmlspecialchars($prov['name']) ?>">
                    <?= htmlspecialchars($prov['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <span class="focus-bar"></span>
            </div>
            <span class="error-msg" id="province-error"></span>
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
          <div class="field"><label class="label">รหัสผ่าน</label><div class="input-wrap"><input type="password" name="password" id="password" required /><span class="focus-bar"></span></div></div>
          <div class="field"><label class="label">ยืนยันรหัสผ่าน</label><div class="input-wrap"><input type="password" name="confirm" id="confirm" required /><span class="focus-bar"></span></div></div>
        </div>

        <div class="role-field student-field" id="pin-section">
          <div class="section-label">ตั้ง PIN สำหรับผู้ปกครอง</div>
          <div class="field"><label class="label">PIN 6 หลัก</label><div class="input-wrap"><input type="password" name="student_pin" maxlength="6" required /><span class="focus-bar"></span></div></div>
        </div>

        <button type="submit" class="btn-submit" id="submitBtn">
          <span class="btn-text">สมัครสมาชิก</span>
          <span class="btn-loader" id="btnLoader" style="display:none;"><div class="spinner"></div></span>
        </button>
      </form>

      <div class="back-row">มีบัญชีแล้ว? <a href="login.php" class="link">เข้าสู่ระบบ</a></div>
    </div>
  </div>
  <div class="toast" id="toast"></div>
  <script src="regisstu.js"></script>
</body>
</html>