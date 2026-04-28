/* ============================================
   FLEXIBLE LEARNING HUB - regisstu.js
   ============================================ */

(() => {
  const tabs = [...document.querySelectorAll('.role-tab')];
  const roleInput = document.getElementById('roleInput');
  const form = document.getElementById('regisForm');

  const roleActions = {
    student: 'regisstu_action.php',
    teacher: 'regisstu_action.php',
    parent: 'regisstu_action.php',
  };

  const roleFieldMap = {
    student: ['student-field', 'cert-field'],
    teacher: ['teacher-field'],
    parent: ['parent-field'],
  };

  const roleSectionMap = {
    student: ['pin-section'],
    teacher: [],
    parent: ['parent-link-section'],
  };

  const allSections = ['pin-section', 'parent-link-section'];
  const slider = document.querySelector('.role-slider');

  function moveSlider(activeTab) {
    if (!slider || !activeTab) return;
    slider.style.left = activeTab.offsetLeft + 'px';
    slider.style.width = activeTab.offsetWidth + 'px';
  }

  function switchRole(role) {
    tabs.forEach((tab) => {
      tab.classList.toggle('active', tab.dataset.role === role);
    });

    moveSlider(tabs.find((tab) => tab.dataset.role === role));
    roleInput.value = role;

    if (roleActions[role]) {
      form.action = roleActions[role];
    }

    document.querySelectorAll('.role-field').forEach((element) => {
      const visible = roleFieldMap[role]?.some((cls) => element.classList.contains(cls));
      element.style.display = visible ? '' : 'none';
    });

    allSections.forEach((id) => {
      const element = document.getElementById(id);
      if (element) {
        element.style.display = roleSectionMap[role]?.includes(id) ? '' : 'none';
      }
    });
  }

  window.addEventListener('load', () => {
    switchRole(roleInput.value || 'student');
  });

  tabs.forEach((tab) => {
    tab.addEventListener('click', () => switchRole(tab.dataset.role));
  });

  const fileInput = document.getElementById('cert');
  const fileDisplay = document.getElementById('fileDisplay');
  const fileName = document.getElementById('fileName');

  fileInput?.addEventListener('change', () => {
    const file = fileInput.files[0];
    if (file) {
      fileName.textContent = file.name;
      fileDisplay.classList.add('has-file');
    } else {
      fileName.textContent = 'คลิกเพื่อเลือกไฟล์';
      fileDisplay.classList.remove('has-file');
    }
  });

  function bindDigitsOnly(input, maxLength) {
    input?.addEventListener('input', () => {
      input.value = input.value.replace(/\D/g, '').slice(0, maxLength);
    });
  }

  function bindIdCardFormat(input) {
    input?.addEventListener('input', () => {
      let value = input.value.replace(/\D/g, '').slice(0, 13);
      let output = '';
      if (value.length > 0) output += value.slice(0, 1);
      if (value.length > 1) output += '-' + value.slice(1, 5);
      if (value.length > 5) output += '-' + value.slice(5, 10);
      if (value.length > 10) output += '-' + value.slice(10, 12);
      if (value.length > 12) output += '-' + value.slice(12, 13);
      input.value = output;
    });
  }

  function bindPhoneFormat(input) {
    input?.addEventListener('input', () => {
      let value = input.value.replace(/\D/g, '').slice(0, 10);
      let output = '';
      if (value.length > 0) output += value.slice(0, 3);
      if (value.length > 3) output += '-' + value.slice(3, 6);
      if (value.length > 6) output += '-' + value.slice(6, 10);
      input.value = output;
    });
  }

  bindIdCardFormat(document.getElementById('idcard'));
  bindPhoneFormat(document.getElementById('phone'));
  bindIdCardFormat(document.getElementById('link_student_id'));
  bindDigitsOnly(document.getElementById('zipcode'), 5);
  bindDigitsOnly(document.getElementById('student_pin'), 6);
  bindDigitsOnly(document.getElementById('student_pin_confirm'), 6);
  bindDigitsOnly(document.getElementById('link_student_pin'), 6);

  const eyeOpen = `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`;
  const eyeOff = `<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>`;

  function makeToggle(buttonId, inputId, iconId) {
    const button = document.getElementById(buttonId);
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (!button || !input || !icon) return;

    let visible = false;
    button.addEventListener('click', () => {
      visible = !visible;
      input.type = visible ? 'text' : 'password';
      icon.innerHTML = visible ? eyeOff : eyeOpen;
    });
  }

  makeToggle('togglePw1', 'password', 'eye1');
  makeToggle('togglePw2', 'confirm', 'eye2');

  function setError(fieldId, errorId, message) {
    const field = document.getElementById(fieldId);
    const error = document.getElementById(errorId);
    if (!field || !error) return !message;

    if (message) {
      field.classList.add('has-error');
      error.textContent = message;
    } else {
      field.classList.remove('has-error');
      error.textContent = '';
    }

    return !message;
  }

  function clearOnFocus(inputId, fieldId, errorId) {
    const input = document.getElementById(inputId);
    input?.addEventListener('focus', () => setError(fieldId, errorId, ''));
  }

  [
    ['firstname', 'field-firstname', 'firstname-error'],
    ['lastname', 'field-lastname', 'lastname-error'],
    ['idcard', 'field-idcard', 'idcard-error'],
    ['level', 'field-level', 'level-error'],
    ['subject', 'field-subject', 'subject-error'],
    ['relation', 'field-relation', 'relation-error'],
    ['email', 'field-email', 'email-error'],
    ['phone', 'field-phone', 'phone-error'],
    ['house', 'field-house', 'house-error'],
    ['tambon', 'field-tambon', 'tambon-error'],
    ['amphoe', 'field-amphoe', 'amphoe-error'],
    ['province', 'field-province', 'province-error'],
    ['zipcode', 'field-zipcode', 'zipcode-error'],
    ['password', 'field-password', 'password-error'],
    ['confirm', 'field-confirm', 'confirm-error'],
    ['student_pin', 'field-pin', 'pin-error'],
    ['student_pin_confirm', 'field-pin-confirm', 'pin-confirm-error'],
    ['link_student_id', 'field-link-student-id', 'link-student-id-error'],
    ['link_student_pin', 'field-link-student-pin', 'link-student-pin-error'],
  ].forEach(([inputId, fieldId, errorId]) => clearOnFocus(inputId, fieldId, errorId));

  const toast = document.getElementById('toast');
  let toastTimer;

  function showToast(message, type = '') {
    window.clearTimeout(toastTimer);
    toast.textContent = message;
    toast.className = 'toast' + (type ? ' ' + type : '');
    void toast.offsetWidth;
    toast.classList.add('show');
    toastTimer = window.setTimeout(() => toast.classList.remove('show'), 3200);
  }

  const submitBtn = document.getElementById('submitBtn');
  const btnText = submitBtn.querySelector('.btn-text');
  const btnArrow = document.getElementById('btnArrow');
  const btnLoader = document.getElementById('btnLoader');

  function setLoading(loading) {
    submitBtn.disabled = loading;
    btnText.hidden = loading;
    btnArrow.style.display = loading ? 'none' : '';
    btnLoader.style.display = loading ? 'flex' : 'none';
  }

  form.addEventListener('submit', (event) => {
    event.preventDefault();

    let valid = true;
    const currentRole = roleInput.value;
    const valueOf = (id) => document.getElementById(id)?.value.trim() || '';

    if (!valueOf('firstname')) {
      valid = setError('field-firstname', 'firstname-error', 'กรุณากรอกชื่อ') && valid;
    }
    if (!valueOf('lastname')) {
      valid = setError('field-lastname', 'lastname-error', 'กรุณากรอกนามสกุล') && valid;
    }

    const idCard = valueOf('idcard').replace(/\D/g, '');
    if (idCard.length !== 13) {
      valid = setError('field-idcard', 'idcard-error', 'เลขบัตรประชาชนต้องมี 13 หลัก') && valid;
    }

    if (currentRole === 'student' && !valueOf('level')) {
      valid = setError('field-level', 'level-error', 'กรุณาเลือกระดับชั้น') && valid;
    }
    if (currentRole === 'teacher' && !valueOf('subject')) {
      valid = setError('field-subject', 'subject-error', 'กรุณากรอกวิชาที่สอน') && valid;
    }
    if (currentRole === 'parent' && !valueOf('relation')) {
      valid = setError('field-relation', 'relation-error', 'กรุณาเลือกความสัมพันธ์') && valid;
    }

    const email = valueOf('email');
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      valid = setError('field-email', 'email-error', 'กรุณากรอกอีเมลให้ถูกต้อง') && valid;
    }

    const phone = valueOf('phone').replace(/\D/g, '');
    if (phone.length !== 10) {
      valid = setError('field-phone', 'phone-error', 'เบอร์โทรต้องมี 10 หลัก') && valid;
    }

    if (!valueOf('house')) {
      valid = setError('field-house', 'house-error', 'กรุณากรอกบ้านเลขที่') && valid;
    }
    if (!valueOf('tambon')) {
      valid = setError('field-tambon', 'tambon-error', 'กรุณากรอกตำบล/แขวง') && valid;
    }
    if (!valueOf('amphoe')) {
      valid = setError('field-amphoe', 'amphoe-error', 'กรุณากรอกอำเภอ/เขต') && valid;
    }
    if (!valueOf('province')) {
      valid = setError('field-province', 'province-error', 'กรุณาเลือกจังหวัด') && valid;
    }

    const zip = valueOf('zipcode').replace(/\D/g, '');
    if (zip.length !== 5) {
      valid = setError('field-zipcode', 'zipcode-error', 'รหัสไปรษณีย์ต้องมี 5 หลัก') && valid;
    }

    if (currentRole === 'student') {
      if (!fileInput.files[0]) {
        valid = setError('field-cert', 'cert-error', 'กรุณาแนบไฟล์วุฒิการศึกษา') && valid;
      } else if (fileInput.files[0].size > 5 * 1024 * 1024) {
        valid = setError('field-cert', 'cert-error', 'ไฟล์ต้องมีขนาดไม่เกิน 5MB') && valid;
      }
    }

    const password = valueOf('password');
    const confirmPassword = valueOf('confirm');
    if (!password || password.length < 6) {
      valid = setError('field-password', 'password-error', 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร') && valid;
    }
    if (password !== confirmPassword) {
      valid = setError('field-confirm', 'confirm-error', 'รหัสผ่านไม่ตรงกัน') && valid;
    }

    if (currentRole === 'student') {
      const studentPin = valueOf('student_pin').replace(/\D/g, '');
      const studentPinConfirm = valueOf('student_pin_confirm').replace(/\D/g, '');

      if (studentPin.length !== 6) {
        valid = setError('field-pin', 'pin-error', 'กรุณากรอก PIN ให้ครบ 6 หลัก') && valid;
      } else {
        setError('field-pin', 'pin-error', '');
      }

      if (studentPinConfirm.length !== 6) {
        valid = setError('field-pin-confirm', 'pin-confirm-error', 'กรุณายืนยัน PIN ให้ครบ 6 หลัก') && valid;
      } else if (studentPin !== studentPinConfirm) {
        valid = setError('field-pin-confirm', 'pin-confirm-error', 'PIN ไม่ตรงกัน') && valid;
      } else {
        setError('field-pin-confirm', 'pin-confirm-error', '');
      }
    }

    if (currentRole === 'parent') {
      const linkedStudentId = valueOf('link_student_id').replace(/\D/g, '');
      const linkedStudentPin = valueOf('link_student_pin').replace(/\D/g, '');

      if (linkedStudentId.length !== 13) {
        valid = setError('field-link-student-id', 'link-student-id-error', 'รหัสบัตรประชาชนนักเรียนต้องมี 13 หลัก') && valid;
      }
      if (linkedStudentPin.length !== 6) {
        valid = setError('field-link-student-pin', 'link-student-pin-error', 'PIN นักเรียนต้องมี 6 หลัก') && valid;
      } else {
        setError('field-link-student-pin', 'link-student-pin-error', '');
      }
    }

    if (!valid) {
      const firstError = document.querySelector('.has-error');
      firstError?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }

    setLoading(true);
    try {
      form.submit();
    } catch (error) {
      showToast('เกิดข้อผิดพลาดในการส่งฟอร์ม', 'error-toast');
      setLoading(false);
    }
  });
})();
