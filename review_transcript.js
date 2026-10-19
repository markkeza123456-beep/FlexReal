'use strict';

(() => {
    const transcriptViewer = document.querySelector('.document-pane iframe, .document-pane img');
    const transcriptError = document.getElementById('transcriptViewerError');
    if (transcriptViewer && transcriptError) {
        const showTranscriptError = message => {
            transcriptViewer.hidden = true;
            transcriptError.textContent = message;
            transcriptError.hidden = false;
        };

        transcriptViewer.addEventListener('error', () => {
            showTranscriptError('ไม่พบไฟล์ทรานสคริปต์บนเซิร์ฟเวอร์นี้ กรุณาให้นักเรียนอัปโหลดไฟล์อีกครั้ง');
        });

        fetch(transcriptViewer.getAttribute('src'), {
            method: 'HEAD',
            credentials: 'same-origin',
            cache: 'no-store'
        }).then(response => {
            if (response.ok) return;
            if (response.status === 403) throw new Error('บัญชีนี้ไม่มีสิทธิ์เปิดไฟล์ทรานสคริปต์');
            if (response.status === 401) throw new Error('เซสชันหมดอายุ กรุณาเข้าสู่ระบบใหม่');
            if (response.status === 404) throw new Error('ไม่พบไฟล์บนเซิร์ฟเวอร์นี้ กรุณาให้นักเรียนอัปโหลดไฟล์อีกครั้ง');
            throw new Error('ไม่สามารถเปิดไฟล์ทรานสคริปต์ได้ กรุณาลองอีกครั้ง');
        }).catch(error => {
            showTranscriptError(error instanceof Error ? error.message : 'ไม่สามารถเปิดไฟล์ทรานสคริปต์ได้');
        });
    }

    const picker = document.getElementById('requestPicker');
    picker?.addEventListener('change', () => {
        const url = new URL(window.location.href);
        url.searchParams.set('request_id', picker.value);
        window.location.assign(url);
    });

    const form = document.getElementById('transcriptReviewForm');
    const button = form?.querySelector('.approve-button');
    const rejectButton = document.getElementById('rejectTranscriptButton');
    const message = document.getElementById('reviewMessage');
    rejectButton?.addEventListener('click', async () => {
        if (!window.confirm('ยืนยันไม่อนุมัติคำขอนี้และลบไฟล์ทรานสคริปต์ที่อัปโหลด?')) return;
        message.textContent = '';
        rejectButton.disabled = true;
        button.disabled = true;
        rejectButton.textContent = 'กำลังไม่อนุมัติและลบไฟล์...';
        try {
            const response = await fetch('reject_transcript.php', {
                method: 'POST',
                body: new FormData(form),
                headers: { Accept: 'application/json' },
                credentials: 'same-origin'
            });
            const result = await response.json();
            if (!response.ok || result.status !== 'success') {
                throw new Error(result.message || 'ไม่สามารถปฏิเสธคำขอได้');
            }
            window.alert(result.message || 'ไม่อนุมัติคำขอและลบไฟล์แล้ว');
            window.location.assign('review_transcript.php');
        } catch (error) {
            message.textContent = error instanceof Error ? error.message : 'เชื่อมต่อระบบไม่สำเร็จ';
        } finally {
            rejectButton.disabled = false;
            button.disabled = false;
            rejectButton.textContent = 'ไม่อนุมัติและลบไฟล์';
        }
    });

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
