(() => {
  const form = document.getElementById('loginForm');
  const loginInput = document.getElementById('email');
  const loginLabel = document.querySelector('label[for="email"]');
  const pwInput = document.getElementById('password');
  const submitBtn = document.getElementById('submitBtn');
  const btnText = submitBtn.querySelector('.btn-text');
  const btnLoader = document.getElementById('btnLoader');
  const btnArrow = submitBtn.querySelector('.btn-arrow');
  const toast = document.getElementById('toast');
  const roleTabs = document.querySelectorAll('.role-tab');
  const returnUrl = new URLSearchParams(window.location.search).get('return');

  let activeRole = 'student';

  const idCardLabel = 'รหัสบัตรประชาชน';
  const idCardPlaceholder = 'กรอกรหัสบัตรประชาชน';

  function showToast(message, type = '') {
    toast.textContent = message;
    toast.className = 'toast show ' + type;
    setTimeout(() => toast.classList.remove('show'), 3000);
  }

  function formatIdCard(value) {
    const digits = value.replace(/\D/g, '').slice(0, 13);
    let output = '';
    if (digits.length > 0) output += digits.slice(0, 1);
    if (digits.length > 1) output += '-' + digits.slice(1, 5);
    if (digits.length > 5) output += '-' + digits.slice(5, 10);
    if (digits.length > 10) output += '-' + digits.slice(10, 12);
    if (digits.length > 12) output += '-' + digits.slice(12, 13);
    return output;
  }

  function applyRoleCopy() {
    if (loginLabel) loginLabel.textContent = idCardLabel;
    loginInput.placeholder = idCardPlaceholder;
    loginInput.value = '';
  }

  function getSafeReturnUrl() {
    if (!returnUrl) return '';

    try {
      const url = new URL(returnUrl, window.location.href);
      if (url.origin !== window.location.origin) return '';
      return url.href;
    } catch (error) {
      return '';
    }
  }

  roleTabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      activeRole = tab.dataset.role;
      roleTabs.forEach((item) => item.classList.toggle('active', item.dataset.role === activeRole));
      applyRoleCopy();
    });
  });

  window.addEventListener('pageshow', () => {
    submitBtn.disabled = false;
    btnText.style.display = 'inline';
    btnArrow.style.display = 'inline';
    btnLoader.style.display = 'none';
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (!loginInput.value.trim() || pwInput.value.length < 6) {
      showToast('กรุณากรอกข้อมูลให้ครบถ้วน (รหัสผ่านอย่างน้อย 6 ตัว)', 'error-toast');
      return;
    }

    submitBtn.disabled = true;
    btnText.style.display = 'none';
    btnArrow.style.display = 'none';
    btnLoader.style.display = 'block';

    try {
      const formData = new FormData();
      formData.append('role', activeRole);
      formData.append('email', loginInput.value.trim());
      formData.append('password', pwInput.value);

      const response = await fetch('login_action.php', { method: 'POST', body: formData });
      const result = await response.json();

      if (result.status === 'success') {
        showToast('เข้าสู่ระบบสำเร็จ', 'success');
        setTimeout(() => {
          window.location.href = getSafeReturnUrl() || result.redirect_url;
        }, 1000);
      } else {
        throw new Error(result.message);
      }
    } catch (error) {
      showToast('✕ ' + error.message, 'error-toast');
      submitBtn.disabled = false;
      btnText.style.display = 'inline';
      btnArrow.style.display = 'inline';
      btnLoader.style.display = 'none';
    }
  });

  const togglePwBtn = document.getElementById('togglePw');
  if (togglePwBtn && pwInput) {
    togglePwBtn.addEventListener('click', (event) => {
      event.preventDefault();
      const isPassword = pwInput.type === 'password';
      pwInput.type = isPassword ? 'text' : 'password';
      togglePwBtn.style.opacity = isPassword ? '1' : '0.5';
    });
  }

  loginInput.addEventListener('input', () => {
    loginInput.value = formatIdCard(loginInput.value);
  });

  applyRoleCopy();
})();
