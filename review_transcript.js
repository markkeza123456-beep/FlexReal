'use strict';

(() => {
    const picker = document.getElementById('requestPicker');
    picker?.addEventListener('change', () => {
        const url = new URL(window.location.href);
        url.searchParams.set('request_id', picker.value);
        window.location.assign(url);
    });

    const form = document.getElementById('transcriptReviewForm');
    const button = form?.querySelector('.approve-button');
    const message = document.getElementById('reviewMessage');
    form?.addEventListener('submit', async event => {
        event.preventDefault();
        if (!form.querySelector('input[name="lesson_ids[]"]:checked')) {
            message.textContent = 'กรุณาเลือกอย่างน้อยหนึ่งบทเรียนที่ให้ผ่าน';
            return;
        }
        message.textContent = '';
        button.disabled = true;
        button.textContent = 'กำลังบันทึกผล...';
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { Accept: 'application/json' },
                credentials: 'same-origin'
            });
            const result = await response.json();
            if (!response.ok || result.status !== 'success') {
                throw new Error(result.message || 'บันทึกผลเทียบโอนไม่สำเร็จ');
            }
            window.alert(result.message || 'อนุมัติผลเทียบโอนเรียบร้อยแล้ว');
            window.location.assign('review_transcript.php');
        } catch (error) {
            message.textContent = error instanceof Error ? error.message : 'เชื่อมต่อระบบไม่สำเร็จ';
        } finally {
            button.disabled = false;
            button.textContent = 'อนุมัติผลเทียบโอน';
        }
    });
})();
