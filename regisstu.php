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
            <polygon points="20,10 30,16 30,24 20,30 10,24 10,16" fill="currentColor" opacity="0.3"/>
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
        <button type="button" class="role-tab active" data-role="student">
          <span>นักเรียน</span>
        </button>
        <button type="button" class="role-tab" data-role="teacher">
          <span>อาจารย์</span>
        </button>
        <button type="button" class="role-tab" data-role="parent">
          <span>ผู้ปกครอง</span>
        </button>
        <button type="button" class="role-tab" data-role="staff">
          <span>เจ้าหน้าที่</span>
        </button>
      </div>

      <form class="form" id="regisForm" action="regisss_action.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="role" id="roleInput" value="student">

        <div class="section-label">ข้อมูลส่วนตัว</div>

        <div class="grid-2">
          <div class="field" id="field-firstname">
            <label class="label" for="firstname">ชื่อ<span class="required">*</span></label>
            <div class="input-wrap">
              <span class="input-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              </span>
              <input type="text" id="firstname" name="fullname" placeholder="ชื่อจริง" autocomplete="given-name" />
              <span class="focus-bar"></span>
            </div>
            <span class="error-msg" id="firstname-error"></span>
          </div>

          <div class="field" id="field-lastname">
            <label class="label" for="lastname">นามสกุล<span class="required">*</span></label>
            <div class="input-wrap">
              <span class="input-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              </span>
              <input type="text" id="lastname" name="lastname" placeholder="นามสกุล" autocomplete="family-name" />
              <span class="focus-bar"></span>
            </div>
            <span class="error-msg" id="lastname-error"></span>
          </div>
        </div>

        <div class="field" id="field-idcard">
          <label class="label" for="idcard">เลขบัตรประชาชน<span class="required">*</span></label>
          <div class="input-wrap">
            <span class="input-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 8h10M7 12h6"/><circle cx="16" cy="14" r="2"/></svg>
            </span>
            <input type="text" id="idcard" name="userid" placeholder="X-XXXX-XXXXX-XX-X" maxlength="17" autocomplete="off" />
            <span class="focus-bar"></span>
          </div>
          <span class="error-msg" id="idcard-error"></span>
        </div>

        <div class="field role-field student-field" id="field-level">
          <label class="label" for="level">ระดับชั้น<span class="required">*</span></label>
          <div class="input-wrap">
            <span class="input-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
            </span>
            <select id="level" name="level">
              <option value="" disabled selected>เลือกระดับชั้น</option>
              <optgroup label="มัธยมศึกษาตอนปลาย">
                <option value="m4">มัธยมศึกษาปีที่ 4</option>
                <option value="m5">มัธยมศึกษาปีที่ 5</option>
                <option value="m6">มัธยมศึกษาปีที่ 6</option>
              </optgroup>
            </select>
            <span class="select-arrow">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </span>
            <span class="focus-bar"></span>
          </div>
          <span class="error-msg" id="level-error"></span>
        </div>

        <div class="field role-field teacher-field" id="field-subject" style="display:none;">
          <label class="label" for="subject">วิชาที่สอน<span class="required">*</span></label>
          <div class="input-wrap">
            <span class="input-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
            </span>
            <input type="text" id="subject" name="subject" placeholder="เช่น คณิตศาสตร์, ฟิสิกส์" />
            <span class="focus-bar"></span>
          </div>
          <span class="error-msg" id="subject-error"></span>
        </div>

        <div class="field role-field parent-field" id="field-relation" style="display:none;">
          <label class="label" for="relation">ความสัมพันธ์กับนักเรียน<span class="required">*</span></label>
          <div class="input-wrap">
            <span class="input-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            </span>
            <select id="relation" name="relation">
              <option value="" disabled selected>เลือกความสัมพันธ์</option>
              <option value="father">บิดา</option>
              <option value="mother">มารดา</option>
              <option value="guardian">ผู้ปกครอง</option>
              <option value="other">อื่นๆ</option>
            </select>
            <span class="select-arrow">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </span>
            <span class="focus-bar"></span>
          </div>
          <span class="error-msg" id="relation-error"></span>
        </div>

        <div class="field role-field staff-field" id="field-position" style="display:none;">
          <label class="label" for="position">ตำแหน่ง<span class="required">*</span></label>
          <div class="input-wrap">
            <span class="input-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
            </span>
            <input type="text" id="position" name="position" placeholder="เช่น เจ้าหน้าที่ธุรการ" />
            <span class="focus-bar"></span>
          </div>
          <span class="error-msg" id="position-error"></span>
        </div>

        <div class="section-label">ข้อมูลติดต่อ</div>

        <div class="field" id="field-email">
          <label class="label" for="email">อีเมล<span class="required">*</span></label>
          <div class="input-wrap">
            <span class="input-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 7L2 7"/></svg>
            </span>
            <input type="email" id="email" name="email" placeholder="example@email.com" autocomplete="email" />
            <span class="focus-bar"></span>
          </div>
          <span class="error-msg" id="email-error"></span>
        </div>

        <div class="field" id="field-phone">
          <label class="label" for="phone">เบอร์โทรศัพท์<span class="required">*</span></label>
          <div class="input-wrap">
            <span class="input-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.35 2 2 0 0 1 3.58 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.56a16 16 0 0 0 6.53 6.53l1.62-1.62a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            </span>
            <input type="tel" id="phone" name="phone" placeholder="0XX-XXX-XXXX" maxlength="12" autocomplete="tel" />
            <span class="focus-bar"></span>
          </div>
          <span class="error-msg" id="phone-error"></span>
        </div>

        <div class="section-label">ที่อยู่</div>

        <div class="field" id="field-house">
          <label class="label" for="house">บ้านเลขที่ / ถนน<span class="required">*</span></label>
          <div class="input-wrap">
            <span class="input-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            </span>
            <input type="text" id="house" name="house" placeholder="เช่น 99/1 ถ.สุขุมวิท" />
            <span class="focus-bar"></span>
          </div>
          <span class="error-msg" id="house-error"></span>
        </div>

        <div class="grid-2">
          <div class="field" id="field-tambon">
            <label class="label" for="tambon">ตำบล / แขวง<span class="required">*</span></label>
            <div class="input-wrap">
              <span class="input-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>
              </span>
              <input type="text" id="tambon" name="tambon" placeholder="ตำบล / แขวง" />
              <span class="focus-bar"></span>
            </div>
            <span class="error-msg" id="tambon-error"></span>
          </div>

          <div class="field" id="field-amphoe">
            <label class="label" for="amphoe">อำเภอ / เขต<span class="required">*</span></label>
            <div class="input-wrap">
              <span class="input-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>
              </span>
              <input type="text" id="amphoe" name="amphoe" placeholder="อำเภอ / เขต" />
              <span class="focus-bar"></span>
            </div>
            <span class="error-msg" id="amphoe-error"></span>
          </div>
        </div>

        <div class="grid-2">
          <div class="field" id="field-province">
            <label class="label" for="province">จังหวัด<span class="required">*</span></label>
            <div class="input-wrap">
              <span class="input-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
              </span>
              <select id="province" name="province">
                <option value="" disabled selected>เลือกจังหวัด</option>
                <option>กรุงเทพมหานคร</option><option>กระบี่</option><option>กาญจนบุรี</option>
                <option>กาฬสินธุ์</option><option>กำแพงเพชร</option><option>ขอนแก่น</option>
                <option>จันทบุรี</option><option>ฉะเชิงเทรา</option><option>ชลบุรี</option>
                <option>ชัยนาท</option><option>ชัยภูมิ</option><option>ชุมพร</option>
                <option>เชียงราย</option><option>เชียงใหม่</option><option>ตรัง</option>
                <option>ตราด</option><option>ตาก</option><option>นครนายก</option>
                <option>นครปฐม</option><option>นครพนม</option><option>นครราชสีมา</option>
                <option>นครศรีธรรมราช</option><option>นครสวรรค์</option><option>นนทบุรี</option>
                <option>นราธิวาส</option><option>น่าน</option><option>บึงกาฬ</option>
                <option>บุรีรัมย์</option><option>ปทุมธานี</option><option>ประจวบคีรีขันธ์</option>
                <option>ปราจีนบุรี</option><option>ปัตตานี</option><option>พระนครศรีอยุธยา</option>
                <option>พะเยา</option><option>พังงา</option><option>พัทลุง</option>
                <option>พิจิตร</option><option>พิษณุโลก</option><option>เพชรบุรี</option>
                <option>เพชรบูรณ์</option><option>แพร่</option><option>ภูเก็ต</option>
                <option>มหาสารคาม</option><option>มุกดาหาร</option><option>แม่ฮ่องสอน</option>
                <option>ยโสธร</option><option>ยะลา</option><option>ร้อยเอ็ด</option>
                <option>ระนอง</option><option>ระยอง</option><option>ราชบุรี</option>
                <option>ลพบุรี</option><option>ลำปาง</option><option>ลำพูน</option>
                <option>เลย</option><option>ศรีสะเกษ</option><option>สกลนคร</option>
                <option>สงขลา</option><option>สตูล</option><option>สมุทรปราการ</option>
                <option>สมุทรสงคราม</option><option>สมุทรสาคร</option><option>สระแก้ว</option>
                <option>สระบุรี</option><option>สิงห์บุรี</option><option>สุโขทัย</option>
                <option>สุพรรณบุรี</option><option>สุราษฎร์ธานี</option><option>สุรินทร์</option>
                <option>หนองคาย</option><option>หนองบัวลำภู</option><option>อ่างทอง</option>
                <option>อำนาจเจริญ</option><option>อุดรธานี</option><option>อุตรดิตถ์</option>
                <option>อุทัยธานี</option><option>อุบลราชธานี</option>
              </select>
              <span class="select-arrow">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
              </span>
              <span class="focus-bar"></span>
            </div>
            <span class="error-msg" id="province-error"></span>
          </div>

          <div class="field" id="field-zipcode">
            <label class="label" for="zipcode">รหัสไปรษณีย์<span class="required">*</span></label>
            <div class="input-wrap">
              <span class="input-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><path d="M22 6l-10 7L2 6"/></svg>
              </span>
              <input type="text" id="zipcode" name="zipcode" placeholder="XXXXX" maxlength="5" inputmode="numeric" />
              <span class="focus-bar"></span>
            </div>
            <span class="error-msg" id="zipcode-error"></span>
          </div>
        </div>

        <div class="section-label">เอกสารประกอบ</div>

        <div class="field" id="field-cert">
          <label class="label">แนบวุฒิการศึกษา<span class="required">*</span></label>
          <div class="file-upload" id="fileUpload">
            <input type="file" id="cert" name="cert" accept=".pdf,.jpg,.jpeg,.png" />
            <div class="file-upload-display" id="fileDisplay">
              <span class="file-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
              </span>
              <div class="file-text">
                <span class="file-name" id="fileName">คลิกเพื่อเลือกไฟล์</span>
                <span class="file-hint">PDF, JPG, PNG — ขนาดไม่เกิน 5MB</span>
              </div>
              <span class="file-badge">UPLOAD</span>
            </div>
          </div>
          <span class="error-msg" id="cert-error"></span>
        </div>

        <div class="section-label">ตั้งรหัสผ่าน</div>

        <div class="grid-2">
          <div class="field" id="field-password">
            <label class="label" for="password">รหัสผ่าน<span class="required">*</span></label>
            <div class="input-wrap">
              <span class="input-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              </span>
              <input type="password" id="password" name="password" placeholder="อย่างน้อย 6 ตัวอักษร" autocomplete="new-password" />
              <button type="button" class="toggle-pw" id="togglePw1">
                <svg id="eye1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
              <span class="focus-bar"></span>
            </div>
            <span class="error-msg" id="password-error"></span>
          </div>

          <div class="field" id="field-confirm">
            <label class="label" for="confirm">ยืนยันรหัสผ่าน<span class="required">*</span></label>
            <div class="input-wrap">
              <span class="input-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              </span>
              <input type="password" id="confirm" name="confirm" placeholder="กรอกซ้ำอีกครั้ง" autocomplete="new-password" />
              <button type="button" class="toggle-pw" id="togglePw2">
                <svg id="eye2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
              <span class="focus-bar"></span>
            </div>
            <span class="error-msg" id="confirm-error"></span>
          </div>
        </div>

        <div class="role-field student-field" id="pin-section">
          <div class="section-label">ตั้ง PIN สำหรับผู้ปกครอง</div>

          <div class="field" id="field-pin">
            <label class="label">PIN 6 หลัก<span class="required">*</span></label>
            <div class="pin-wrap" id="pinWrap">
              <input class="pin-digit" type="password" inputmode="numeric" maxlength="1" pattern="[0-9]" />
              <input class="pin-digit" type="password" inputmode="numeric" maxlength="1" pattern="[0-9]" />
              <input class="pin-digit" type="password" inputmode="numeric" maxlength="1" pattern="[0-9]" />
              <input class="pin-digit" type="password" inputmode="numeric" maxlength="1" pattern="[0-9]" />
              <input class="pin-digit" type="password" inputmode="numeric" maxlength="1" pattern="[0-9]" />
              <input class="pin-digit" type="password" inputmode="numeric" maxlength="1" pattern="[0-9]" />
            </div>
            <span class="error-msg" id="pin-error"></span>
          </div>
          <input type="hidden" name="student_pin" id="final_pin">

          <div class="field" id="field-pin-confirm">
            <label class="label">ยืนยัน PIN<span class="required">*</span></label>
            <div class="pin-wrap" id="pinConfirmWrap">
              <input class="pin-digit pin-confirm" type="password" inputmode="numeric" maxlength="1" pattern="[0-9]" />
              <input class="pin-digit pin-confirm" type="password" inputmode="numeric" maxlength="1" pattern="[0-9]" />
              <input class="pin-digit pin-confirm" type="password" inputmode="numeric" maxlength="1" pattern="[0-9]" />
              <input class="pin-digit pin-confirm" type="password" inputmode="numeric" maxlength="1" pattern="[0-9]" />
              <input class="pin-digit pin-confirm" type="password" inputmode="numeric" maxlength="1" pattern="[0-9]" />
              <input class="pin-digit pin-confirm" type="password" inputmode="numeric" maxlength="1" pattern="[0-9]" />
            </div>
            <span class="error-msg" id="pin-confirm-error"></span>
          </div>
        </div>

        <button type="submit" class="btn-submit" id="submitBtn">
          <span class="btn-text">สมัครสมาชิก</span>
          <span class="btn-arrow" id="btnArrow">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </span>
          <span class="btn-loader" id="btnLoader" style="display:none;">
            <div class="spinner"></div>
          </span>
        </button>

      </form>

      <div class="back-row">มีบัญชีแล้ว? <a href="login.php" class="link">เข้าสู่ระบบ</a></div>
    </div>
  </div>

  <div class="toast" id="toast"></div>
  <script src="regisstu.js"></script>
</body>
</html>