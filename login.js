(() => {
  const form = document.getElementById('loginForm');
  const emailInput = document.getElementById('email');
  const pwInput = document.getElementById('password');
  const submitBtn = document.getElementById('submitBtn');
  const btnText = submitBtn.querySelector('.btn-text');
  const btnLoader = document.getElementById('btnLoader');
  const btnArrow = submitBtn.querySelector('.btn-arrow');
  const toast = document.getElementById('toast');
  const roleTabs = document.querySelectorAll('.role-tab');

  let activeRole = 'student';

  // สลับ Role
  roleTabs.forEach(tab => {
    tab.addEventListener('click', () => {
      activeRole = tab.dataset.role;
      roleTabs.forEach(t => t.classList.toggle('active', t.dataset.role === activeRole));
    });
  });

  function showToast(message, type = '') {
    toast.textContent = message;
    toast.className = 'toast show ' + type;
    setTimeout(() => toast.classList.remove('show'), 3000);
  }

  // แก้ปัญหาปุ่มโหลดค้างตอนกดย้อนกลับ
  window.addEventListener('pageshow', () => {
    submitBtn.disabled = false;
    btnText.style.display = 'inline';
    btnArrow.style.display = 'inline';
    btnLoader.style.display = 'none';
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!emailInput.value.trim() || pwInput.value.length < 6) {
      showToast('กรุณากรอกข้อมูลให้ครบถ้วน (รหัสผ่าน 6 ตัวขึ้นไป)', 'error-toast');
      return;
    }

    submitBtn.disabled = true;
    btnText.style.display = 'none';
    btnArrow.style.display = 'none';
    btnLoader.style.display = 'block';

    try {
      const formData = new FormData();
      formData.append('role', activeRole);
      formData.append('email', emailInput.value.trim());
      formData.append('password', pwInput.value);

      const response = await fetch('login_action.php', { method: 'POST', body: formData });
      const result = await response.json();

      if (result.status === 'success') {
        showToast('✓ เข้าสู่ระบบสำเร็จ', 'success');
        setTimeout(() => window.location.href = result.redirect_url, 1000);
      } else {
        throw new Error(result.message);
      }
    } catch (err) {
      showToast('✕ ' + err.message, 'error-toast');
      submitBtn.disabled = false;
      btnText.style.display = 'inline';
      btnArrow.style.display = 'inline';
      btnLoader.style.display = 'none';
    }
  });
})();