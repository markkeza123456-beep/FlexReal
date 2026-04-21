/* ============================================
   NEXORA LOGIN — login.js
   ============================================ */

(() => {
  // ── Elements ──────────────────────────────
  const form          = document.getElementById('loginForm');
  const emailInput    = document.getElementById('email');
  const pwInput       = document.getElementById('password');
  const togglePw      = document.getElementById('togglePw');
  const eyeIcon       = document.getElementById('eyeIcon');
  const submitBtn     = document.getElementById('submitBtn');
  const btnText       = submitBtn.querySelector('.btn-text');
  const btnLoader     = document.getElementById('btnLoader');
  const btnArrow      = submitBtn.querySelector('.btn-arrow');
  const toast         = document.getElementById('toast');
  const fieldPassword = document.getElementById('field-password');
  const fieldPin      = document.getElementById('field-pin');
  const pinDigits     = document.querySelectorAll('.pin-digit');
  const roleTabs      = document.querySelectorAll('.role-tab');

  let activeRole = 'student';

  // ── Role Config ───────────────────────────
  const roleConfig = {
    student: { label: 'รหัสบัตรประชาชน', placeholder: 'กรอกรหัสบัตรประชาชน', pin: false },
    teacher: { label: 'รหัสบัตรประชาชน', placeholder: 'กรอกรหัสบัตรประชาชน', pin: false },
    parent:  { label: 'รหัสบัตรประชาชน', placeholder: 'กรอกรหัสบัตรประชาชน', pin: true  },
    staff:   { label: 'รหัสบัตรประชาชน', placeholder: 'กรอกรหัสบัตรประชาชน', pin: false },
  };

  // ── Switch Role ───────────────────────────
  function switchRole(role) {
    activeRole = role;
    roleTabs.forEach(t => t.classList.toggle('active', t.dataset.role === role));

    const cfg = roleConfig[role];
    document.querySelector('#field-email .label').textContent = cfg.label;
    emailInput.placeholder = cfg.placeholder;

    // รหัสผ่านแสดงทุก role เสมอ
    fieldPassword.style.cssText = 'display:flex; flex-direction:column; gap:7px;';

    // PIN แสดงเฉพาะผู้ปกครอง
    if (cfg.pin) {
      fieldPin.style.cssText = 'display:flex; flex-direction:column; gap:7px;';
      clearPin();
    } else {
      fieldPin.style.display = 'none';
    }

    // Clear all errors
    clearErrors();
  }

  function clearErrors() {
    setFieldError('field-email',    'email-error',    '');
    setFieldError('field-password', 'password-error', '');
    document.getElementById('pin-error').textContent = '';
    pinDigits.forEach(d => d.classList.remove('pin-error'));
  }

  roleTabs.forEach(tab => {
    tab.addEventListener('click', () => switchRole(tab.dataset.role));
  });

  // เรียก switchRole ทันทีเพื่อ init state ให้ถูก
  switchRole('student');

  // ── Toggle Password Visibility ────────────
  const eyeOpen = `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`;
  const eyeOff  = `<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>`;

  let pwVisible = false;
  togglePw.addEventListener('click', () => {
    pwVisible = !pwVisible;
    pwInput.type = pwVisible ? 'text' : 'password';
    eyeIcon.innerHTML = pwVisible ? eyeOff : eyeOpen;
    togglePw.style.color = pwVisible ? 'var(--orange)' : '';
  });

  // ── Validation ────────────────────────────
  function validateId(value) {
    if (!value.trim()) return 'กรุณากรอก' + roleConfig[activeRole].label;
    return '';
  }

  function validatePassword(value) {
    if (!value) return 'กรุณากรอกรหัสผ่าน';
    if (value.length < 6) return 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
    return '';
  }

  function setFieldError(fieldId, errorId, message) {
    const field   = document.getElementById(fieldId);
    const errorEl = document.getElementById(errorId);
    if (!field || !errorEl) return !message;
    if (message) {
      field.classList.add('has-error');
      errorEl.textContent = message;
    } else {
      field.classList.remove('has-error');
      errorEl.textContent = '';
    }
    return !message;
  }

  // Real-time validation on blur
  emailInput.addEventListener('blur', () =>
    setFieldError('field-email', 'email-error', validateId(emailInput.value)));
  pwInput.addEventListener('blur', () =>
    setFieldError('field-password', 'password-error', validatePassword(pwInput.value)));

  emailInput.addEventListener('focus', () =>
    setFieldError('field-email', 'email-error', ''));
  pwInput.addEventListener('focus', () =>
    setFieldError('field-password', 'password-error', ''));

  // ── PIN Input Logic ───────────────────────
  function clearPin() {
    pinDigits.forEach(d => {
      d.value = '';
      d.classList.remove('filled', 'pin-error');
    });
  }

  function getPinValue() {
    return [...pinDigits].map(d => d.value).join('');
  }

  pinDigits.forEach((digit, idx) => {
    digit.addEventListener('keydown', (e) => {
      if (e.key === 'Backspace' && !digit.value && idx > 0) {
        e.preventDefault();
        pinDigits[idx - 1].focus();
        pinDigits[idx - 1].value = '';
        pinDigits[idx - 1].classList.remove('filled');
      }
      if (!/^\d$/.test(e.key) && !['Backspace','Delete','Tab','ArrowLeft','ArrowRight'].includes(e.key)) {
        e.preventDefault();
      }
    });

    digit.addEventListener('input', () => {
      digit.value = digit.value.replace(/\D/g, '').slice(-1);
      digit.classList.toggle('filled', digit.value !== '');
      digit.classList.remove('pin-error');
      if (digit.value && idx < pinDigits.length - 1) pinDigits[idx + 1].focus();
    });

    digit.addEventListener('focus', () => digit.select());

    digit.addEventListener('paste', (e) => {
      e.preventDefault();
      const text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
      [...text].slice(0, pinDigits.length).forEach((ch, i) => {
        if (pinDigits[i]) { pinDigits[i].value = ch; pinDigits[i].classList.add('filled'); }
      });
      pinDigits[Math.min(text.length, pinDigits.length - 1)].focus();
    });
  });

  // ── Toast ─────────────────────────────────
  let toastTimer;
  function showToast(message, type = '') {
    clearTimeout(toastTimer);
    toast.textContent = message;
    toast.className = 'toast';
    if (type) toast.classList.add(type);
    void toast.offsetWidth;
    toast.classList.add('show');
    toastTimer = setTimeout(() => toast.classList.remove('show'), 3200);
  }

  // ── Loading State ─────────────────────────
  function setLoading(loading) {
  submitBtn.disabled = loading;
  btnText.hidden = loading;
  if (btnArrow) btnArrow.style.display = loading ? 'none' : '';
  if (btnLoader) btnLoader.style.display = loading ? 'flex' : 'none';
}

  // ── Form Submit ───────────────────────────
  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const idVal  = emailInput.value.trim();
    const pwVal  = pwInput.value;
    const pinVal = getPinValue();

    let valid = true;

    // Validate ID
    if (!setFieldError('field-email', 'email-error', validateId(idVal))) valid = false;

    // Validate Password (ทุก role)
    if (!setFieldError('field-password', 'password-error', validatePassword(pwVal))) valid = false;

    // Validate PIN (ผู้ปกครองเท่านั้น)
    if (activeRole === 'parent') {
      const pinErr = document.getElementById('pin-error');
      if (pinVal.length < 6) {
        pinErr.textContent = 'กรุณากรอก PIN ให้ครบ 6 หลัก';
        pinDigits.forEach(d => d.classList.add('pin-error'));
        setTimeout(() => pinDigits.forEach(d => d.classList.remove('pin-error')), 500);
        valid = false;
      } else {
        pinErr.textContent = '';
      }
    }

    if (!valid) {
      const card = document.getElementById('loginCard');
      card.style.animation = 'none';
      void card.offsetWidth;
      card.style.animation = 'shake .4s ease';
      return;
    }

    setLoading(true);
    try {
      await new Promise((res, rej) =>
        setTimeout(() => pwVal.length >= 6 ? res() : rej(new Error('รหัสผ่านไม่ถูกต้อง')), 1600));
      showToast('✓ เข้าสู่ระบบสำเร็จ', 'success');
      setTimeout(() => { form.reset(); clearPin(); setLoading(false); }, 1000);
    } catch (err) {
      showToast(`✕ ${err.message}`, 'error-toast');
      setLoading(false);
      const pwWrap = pwInput.closest('.input-wrap');
      pwWrap.style.animation = 'none';
      void pwWrap.offsetWidth;
      pwWrap.style.animation = 'shake .4s ease';
    }
  });

  // ── Inject Keyframes ──────────────────────
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