(() => {
  const root = document.getElementById('studentPinResetPage');
  const toast = document.getElementById('toast');
  const showToast = (text, type = 'error-toast') => {
    toast.textContent = text;
    toast.className = `toast show ${type}`;
    window.setTimeout(() => toast.classList.remove('show'), 3500);
  };
  async function call(action, fields = {}) {
    const body = new URLSearchParams({ csrf_token: root.dataset.csrf || '', action, ...fields });
    const response = await fetch('student_pin_reset_action.php', { method: 'POST', body, credentials: 'same-origin' });
    const result = await response.json();
    if (!response.ok || result.status !== 'success') throw new Error(result.message || 'ดำเนินการไม่สำเร็จ');
    return result;
  }
  async function submit(form, buttonText, task) {
    const button = form.querySelector('button[type="submit"]');
    button.disabled = true;
    const original = button.textContent;
    button.textContent = buttonText;
    try { await task(); }
    catch (error) { showToast(error.message || 'ดำเนินการไม่สำเร็จ'); }
    finally { button.disabled = false; button.textContent = original; }
  }
  document.getElementById('forgotPinForm').addEventListener('submit', event => {
    event.preventDefault();
    submit(event.currentTarget, 'กำลังส่ง...', async () => {
      const email = document.getElementById('reset_email').value.trim();
      const result = await call('send', { email });
      showToast(result.message, 'success');
      document.getElementById('step1').style.display = 'none';
      document.getElementById('step2').style.display = 'block';
    });
  });
  document.getElementById('verifyPinForm').addEventListener('submit', event => {
    event.preventDefault();
    submit(event.currentTarget, 'กำลังตรวจสอบ...', async () => {
      const otp = document.getElementById('pin_code').value.trim();
      const result = await call('verify', { otp });
      showToast(result.message, 'success');
      document.getElementById('step2').style.display = 'none';
      document.getElementById('step3').style.display = 'block';
    });
  });
  document.getElementById('resetPinForm').addEventListener('submit', event => {
    event.preventDefault();
    submit(event.currentTarget, 'กำลังบันทึก...', async () => {
      const pin = document.getElementById('new_pin').value.trim();
      const confirm = document.getElementById('confirm_pin').value.trim();
      if (!/^\d{6}$/.test(pin) || pin !== confirm) throw new Error('PIN ต้องเป็นตัวเลข 6 หลักและกรอกยืนยันให้ตรงกัน');
      const result = await call('reset', { pin, pin_confirm: confirm });
      showToast(result.message, 'success');
      window.setTimeout(() => { window.location.href = 'student_dashboard.php'; }, 1200);
    });
  });
})();
