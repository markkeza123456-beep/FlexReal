(() => {
  const form = document.getElementById('registrationOtpForm');
  const otpInput = document.getElementById('otp');
  const verifyButton = document.getElementById('verifyOtpButton');
  const resendButton = document.getElementById('resendOtpButton');
  const message = document.getElementById('otpMessage');
  const csrfToken = form.querySelector('[name="csrf_token"]').value;
  let countdown = Number(resendButton.dataset.resendIn || 0);

  function updateVerifyButton() {
    verifyButton.disabled = !/^[0-9]{6}$/.test(otpInput.value);
  }

  function setMessage(text, isError = true) {
    message.textContent = text;
    message.style.color = isError ? '#b42318' : '#087e8b';
  }

  function renderCountdown() {
    resendButton.disabled = countdown > 0;
    resendButton.textContent = countdown > 0
      ? `ส่งรหัส OTP อีกครั้ง (${countdown})`
      : 'ส่งรหัส OTP อีกครั้ง';
  }

  renderCountdown();
  window.setInterval(() => {
    if (countdown > 0) countdown -= 1;
    renderCountdown();
  }, 1000);

  otpInput.addEventListener('input', () => {
    otpInput.value = otpInput.value.replace(/\D/g, '').slice(0, 6);
    updateVerifyButton();
  });
  updateVerifyButton();

  async function sendAction(action, otp = '') {
    const body = new FormData();
    body.set('action', action);
    body.set('csrf_token', csrfToken);
    if (otp) body.set('otp', otp);
    const response = await fetch('registration_otp_action.php', {
      method: 'POST',
      body,
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    });
    const result = await response.json();
    if (!response.ok || result.status !== 'success') {
      if (result.restart) window.location.href = 'regisstu.php';
      if (result.retry_after) {
        countdown = Number(result.retry_after);
        renderCountdown();
      }
      throw new Error(result.message || 'เกิดข้อผิดพลาด กรุณาลองใหม่');
    }
    return result;
  }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!/^[0-9]{6}$/.test(otpInput.value)) {
      setMessage('กรุณากรอกรหัส OTP 6 หลัก');
      return;
    }
    verifyButton.disabled = true;
    setMessage('กำลังตรวจสอบรหัส OTP…', false);
    try {
      const result = await sendAction('verify', otpInput.value);
      setMessage(result.message, false);
      window.setTimeout(() => { window.location.href = result.redirect_url; }, 700);
    } catch (error) {
      setMessage(error.message);
      verifyButton.disabled = false;
    }
  });

  resendButton.addEventListener('click', async () => {
    resendButton.disabled = true;
    try {
      const result = await sendAction('resend');
      countdown = Number(result.retry_after || 60);
      otpInput.value = '';
      updateVerifyButton();
      setMessage(result.message, false);
    } catch (error) {
      setMessage(error.message);
    }
    renderCountdown();
  });
})();
