'use strict';

const transferForm = document.getElementById('transferForm');
const submitButton = document.getElementById('submitButton');
const formMessage = document.getElementById('formMessage');

if (transferForm && submitButton && formMessage) {
transferForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    formMessage.textContent = '';
    submitButton.disabled = true;
    submitButton.textContent = 'กำลังส่งคำขอ...';

    try {
        const response = await fetch(transferForm.action, {
            method: 'POST',
            body: new FormData(transferForm),
            headers: { Accept: 'application/json' },
            credentials: 'same-origin'
        });

        let result;
        try {
            result = await response.json();
        } catch {
            throw new Error('เซิร์ฟเวอร์ตอบกลับในรูปแบบที่ไม่ถูกต้อง กรุณาลองใหม่');
        }

        if (response.ok && result.status === 'success') {
            alert('ส่งคำขอสำเร็จ');
            transferForm.reset();
            return;
        }

        formMessage.textContent = result.message || 'ส่งคำขอไม่สำเร็จ กรุณาตรวจสอบข้อมูลแล้วลองอีกครั้ง';
    } catch (error) {
        formMessage.textContent = error instanceof Error
            ? error.message
            : 'เชื่อมต่อระบบไม่สำเร็จ กรุณาลองใหม่';
    } finally {
        submitButton.disabled = false;
        submitButton.textContent = 'ส่งคำขอ';
    }
});
}
