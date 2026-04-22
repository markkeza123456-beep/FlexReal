/* ============================================
   FLEXIBLE LEARNING HUB — regis.js
   ============================================ */

(() => {
  // ── File Upload Display ───────────────────
  const fileInput   = document.getElementById('cert');
  const fileDisplay = document.getElementById('fileDisplay');
  const fileName    = document.getElementById('fileName');

  fileInput.addEventListener('change', () => {
    const file = fileInput.files[0];
    if (file) {
      fileName.textContent = file.name;
      fileDisplay.classList.add('has-file');
    } else {
      fileName.textContent = 'คลิกเพื่อเลือกไฟล์';
      fileDisplay.classList.remove('has-file');
    }
  });

  // ── ID Card Auto-format X-XXXX-XXXXX-XX-X ─
  const idInput = document.getElementById('idcard');
  idInput.addEventListener('input', () => {
    let v = idInput.value.replace(/\D/g, '').slice(0, 13);
    let out = '';
    if (v.length > 0)  out += v.slice(0, 1);
    if (v.length > 1)  out += '-' + v.slice(1, 5);
    if (v.length > 5)  out += '-' + v.slice(5, 10);
    if (v.length > 10) out += '-' + v.slice(10, 12);
    if (v.length > 12) out += '-' + v.slice(12, 13);
    idInput.value = out;
  });

  // ── Phone Auto-format 0XX-XXX-XXXX ────────
  const phoneInput = document.getElementById('phone');
  phoneInput.addEventListener('input', () => {
    let v = phoneInput.value.replace(/\D/g, '').slice(0, 10);
    let out = '';
    if (v.length > 0) out += v.slice(0, 3);
    if (v.length > 3) out += '-' + v.slice(3, 6);
    if (v.length > 6) out += '-' + v.slice(6, 10);
    phoneInput.value = out;
  });

  // ── Zipcode numbers only ───────────────────
  const zipcodeInput = document.getElementById('zipcode');
  zipcodeInput.addEventListener('input', () => {
    zipcodeInput.value = zipcodeInput.value.replace(/\D/g, '').slice(0, 5);
  });

  // ── Password Toggle ────────────────────────
  const eyeOpen = `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`;
  const eyeOff  = `<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>`;

  function makeToggle(btnId, inputId, eyeId) {
    let visible = false;
    document.getElementById(btnId).addEventListener('click', () => {
      visible = !visible;
      document.getElementById(inputId).type = visible ? 'text' : 'password';
      document.getElementById(eyeId).innerHTML = visible ? eyeOff : eyeOpen;
    });
  }
  makeToggle('togglePw1', 'password', 'eye1');
  makeToggle('togglePw2', 'confirm',  'eye2');

  // ── PIN Input Logic ────────────────────────
  function initPin(digits) {
    digits.forEach((digit, idx) => {
      digit.addEventListener('keydown', (e) => {
        if (e.key === 'Backspace' && !digit.value && idx > 0) {
          e.preventDefault();
          digits[idx - 1].focus();
          digits[idx - 1].value = '';
          digits[idx - 1].classList.remove('filled');
        }
        if (!/^\d$/.test(e.key) && !['Backspace','Delete','Tab','ArrowLeft','ArrowRight'].includes(e.key)) {
          e.preventDefault();
        }
      });
      digit.addEventListener('input', () => {
        digit.value = digit.value.replace(/\D/g, '').slice(-1);
        digit.classList.toggle('filled', digit.value !== '');
        digit.classList.remove('pin-error');
        if (digit.value && idx < digits.length - 1) digits[idx + 1].focus();
      });
      digit.addEventListener('focus', () => digit.select());
      digit.addEventListener('paste', (e) => {
        e.preventDefault();
        const text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
        [...text].slice(0, digits.length).forEach((ch, i) => {
          if (digits[i]) { digits[i].value = ch; digits[i].classList.add('filled'); }
        });
        digits[Math.min(text.length, digits.length - 1)].focus();
      });
    });
  }

  const pinDigits        = [...document.querySelectorAll('#pinWrap .pin-digit')];
  const pinConfirmDigits = [...document.querySelectorAll('#pinConfirmWrap .pin-digit')];
  initPin(pinDigits);
  initPin(pinConfirmDigits);

  const getPinValue = (digits) => digits.map(d => d.value).join('');

  function setPinError(digits, errorId, msg) {
    const e = document.getElementById(errorId);
    if (msg) {
      digits.forEach(d => d.classList.add('pin-error'));
      if (e) e.textContent = msg;
      setTimeout(() => digits.forEach(d => d.classList.remove('pin-error')), 500);
    } else {
      digits.forEach(d => d.classList.remove('pin-error'));
      if (e) e.textContent = '';
    }
    return !msg;
  }

  // ── Validation Helpers ────────────────────
  function setError(fieldId, errorId, msg) {
    const f = document.getElementById(fieldId);
    const e = document.getElementById(errorId);
    if (!f || !e) return !msg;
    if (msg) { f.classList.add('has-error'); e.textContent = msg; }
    else     { f.classList.remove('has-error'); e.textContent = ''; }
    return !msg;
  }

  function clearOnFocus(inputId, fieldId, errorId) {
    const el = document.getElementById(inputId);
    if (el) el.addEventListener('focus', () => setError(fieldId, errorId, ''));
  }

  clearOnFocus('firstname', 'field-firstname', 'firstname-error');
  clearOnFocus('lastname',  'field-lastname',  'lastname-error');
  clearOnFocus('idcard',    'field-idcard',    'idcard-error');
  clearOnFocus('level',     'field-level',     'level-error');
  clearOnFocus('email',     'field-email',     'email-error');
  clearOnFocus('phone',     'field-phone',     'phone-error');
  clearOnFocus('house',     'field-house',     'house-error');
  clearOnFocus('tambon',    'field-tambon',    'tambon-error');
  clearOnFocus('amphoe',    'field-amphoe',    'amphoe-error');
  clearOnFocus('province',  'field-province',  'province-error');
  clearOnFocus('zipcode',   'field-zipcode',   'zipcode-error');
  clearOnFocus('password',  'field-password',  'password-error');
  clearOnFocus('confirm',   'field-confirm',   'confirm-error');

  // ── Toast ─────────────────────────────────
  const toast = document.getElementById('toast');
  let toastTimer;
  function showToast(msg, type = '') {
    clearTimeout(toastTimer);
    toast.textContent = msg;
    toast.className = 'toast' + (type ? ' ' + type : '');
    void toast.offsetWidth;
    toast.classList.add('show');
    toastTimer = setTimeout(() => toast.classList.remove('show'), 3200);
  }

  // ── Loading State ─────────────────────────
  const submitBtn = document.getElementById('submitBtn');
  const btnText   = submitBtn.querySelector('.btn-text');
  const btnArrow  = document.getElementById('btnArrow');
  const btnLoader = document.getElementById('btnLoader');

  function setLoading(loading) {
    submitBtn.disabled      = loading;
    btnText.hidden          = loading;
    btnArrow.style.display  = loading ? 'none' : '';
    btnLoader.style.display = loading ? 'flex' : 'none';
  }

  // ── Form Submit ───────────────────────────
  document.getElementById('regisForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    let valid = true;

    const v = (id) => document.getElementById(id).value.trim();

    if (!v('firstname'))
      valid = setError('field-firstname', 'firstname-error', 'กรุณากรอกชื่อ') && valid;
    if (!v('lastname'))
      valid = setError('field-lastname', 'lastname-error', 'กรุณากรอกนามสกุล') && valid;

    const id13 = v('idcard').replace(/\D/g, '');
    if (id13.length !== 13)
      valid = setError('field-idcard', 'idcard-error', 'เลขบัตรประชาชนต้องมี 13 หลัก') && valid;

    if (!v('level'))
      valid = setError('field-level', 'level-error', 'กรุณาเลือกระดับชั้น') && valid;

    const emailVal = v('email');
    if (!emailVal || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal))
      valid = setError('field-email', 'email-error', 'กรุณากรอกอีเมลให้ถูกต้อง') && valid;

    const phoneVal = v('phone').replace(/\D/g, '');
    if (phoneVal.length !== 10)
      valid = setError('field-phone', 'phone-error', 'เบอร์โทรต้องมี 10 หลัก') && valid;

    // ที่อยู่
    if (!v('house'))
      valid = setError('field-house', 'house-error', 'กรุณากรอกบ้านเลขที่') && valid;
    if (!v('tambon'))
      valid = setError('field-tambon', 'tambon-error', 'กรุณากรอกตำบล/แขวง') && valid;
    if (!v('amphoe'))
      valid = setError('field-amphoe', 'amphoe-error', 'กรุณากรอกอำเภอ/เขต') && valid;
    if (!v('province'))
      valid = setError('field-province', 'province-error', 'กรุณาเลือกจังหวัด') && valid;
    const zip = v('zipcode').replace(/\D/g, '');
    if (zip.length !== 5)
      valid = setError('field-zipcode', 'zipcode-error', 'รหัสไปรษณีย์ต้องมี 5 หลัก') && valid;

    // ไฟล์
    if (!fileInput.files[0])
      valid = setError('field-cert', 'cert-error', 'กรุณาแนบไฟล์วุฒิการศึกษา') && valid;
    else if (fileInput.files[0].size > 5 * 1024 * 1024)
      valid = setError('field-cert', 'cert-error', 'ไฟล์ต้องมีขนาดไม่เกิน 5MB') && valid;

    // รหัสผ่าน
    const pw  = document.getElementById('password').value;
    const cpw = document.getElementById('confirm').value;
    if (!pw || pw.length < 6)
      valid = setError('field-password', 'password-error', 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร') && valid;
    if (pw !== cpw)
      valid = setError('field-confirm', 'confirm-error', 'รหัสผ่านไม่ตรงกัน') && valid;

    // PIN
    const pinVal        = getPinValue(pinDigits);
    const pinConfirmVal = getPinValue(pinConfirmDigits);
    if (pinVal.length < 4) {
      valid = setPinError(pinDigits, 'pin-error', 'กรุณากรอก PIN ให้ครบ 4 หลัก') && valid;
    } else {
      setPinError(pinDigits, 'pin-error', '');
    }
    if (pinVal.length === 4 && pinConfirmVal !== pinVal) {
      valid = setPinError(pinConfirmDigits, 'pin-confirm-error', 'PIN ไม่ตรงกัน') && valid;
    } else if (pinConfirmVal.length < 4) {
      valid = setPinError(pinConfirmDigits, 'pin-confirm-error', 'กรุณายืนยัน PIN ให้ครบ 4 หลัก') && valid;
    } else {
      setPinError(pinConfirmDigits, 'pin-confirm-error', '');
    }

    if (!valid) {
      const firstErr = document.querySelector('.has-error, .pin-error');
      if (firstErr) firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }

    setLoading(true);
    try {
      // ── ส่งข้อมูลไป PHP backend ──
      // const formData = new FormData(e.target);
      // formData.append('pin', pinVal);
      // const res = await fetch('regis_process.php', { method: 'POST', body: formData });
      // const result = await res.json();
      // if (!res.ok) throw new Error(result.message || 'เกิดข้อผิดพลาด');

      await new Promise(r => setTimeout(r, 1800)); // mock — ลบออกเมื่อเชื่อม backend จริง
      showToast('✓ สมัครสมาชิกสำเร็จ!', 'success');
      setTimeout(() => { window.location.href = 'login.php'; }, 1200);
    } catch (err) {
      showToast('✕ ' + (err.message || 'เกิดข้อผิดพลาด กรุณาลองใหม่'), 'error-toast');
      setLoading(false);
    }
  });

  // ── Inject shake keyframe ─────────────────
  const s = document.createElement('style');
  s.textContent = `
    @keyframes shake {
      0%,100%{ transform:translateX(0); }
      20%    { transform:translateX(-6px); }
      40%    { transform:translateX(6px); }
      60%    { transform:translateX(-4px); }
      80%    { transform:translateX(4px); }
    }
  `;
  document.head.appendChild(s);

})();