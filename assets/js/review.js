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
    const rejectButton = document.getElementById('rejectTranscriptButton');
    const message = document.getElementById('reviewMessage');

    rejectButton?.addEventListener('click', async () => {
        if (!window.confirm('ยืนยันไม่อนุมัติคำขอนี้และลบไฟล์ทรานสคริปต์ที่อัปโหลด?')) return;
        message.textContent = '';
        rejectButton.disabled = true;
        if (button) button.disabled = true;
        rejectButton.textContent = 'กำลังไม่อนุมัติและลบไฟล์...';
        try {
            const response = await fetch('reject_transcript.php', {
                method: 'POST',
                body: new FormData(form),
                headers: { Accept: 'application/json' },
                credentials: 'same-origin'
            });
            const result = await response.json();
            if (!response.ok || result.status !== 'success') throw new Error(result.message || 'ไม่สามารถปฏิเสธคำขอได้');
            window.alert(result.message || 'ไม่อนุมัติคำขอเรียบร้อยแล้ว');
            window.location.assign('review_transcript.php');
        } catch (error) {
            message.textContent = error instanceof Error ? error.message : 'เชื่อมต่อระบบไม่สำเร็จ';
        } finally {
            rejectButton.disabled = false;
            if (button) button.disabled = false;
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
            if (!response.ok || result.status !== 'success') throw new Error(result.message || 'บันทึกผลเทียบโอนไม่สำเร็จ');
            window.alert(result.message || 'อนุมัติผลเทียบโอนเรียบร้อยแล้ว');
            window.location.assign('review_transcript.php');
        } catch (error) {
            message.textContent = error instanceof Error ? error.message : 'เชื่อมต่อระบบไม่สำเร็จ';
        } finally {
            button.disabled = false;
            button.textContent = 'อนุมัติผลเทียบโอน';
        }
    });

    const pdfViewer = document.getElementById('transcriptPdfViewer');
    const imageViewer = document.getElementById('transcriptImageViewer');
    const documentViewer = pdfViewer || imageViewer;
    const openLink = document.getElementById('transcriptOpenLink');
    const dataUri = documentViewer?.dataset.document || (imageViewer?.src.startsWith('data:image/') ? imageViewer.src : '');
    if (dataUri) {
        const blobReady = fetch(dataUri)
            .then(response => {
                if (!response.ok) throw new Error('อ่านเอกสารไม่สำเร็จ');
                return response.blob();
            })
            .then(blob => {
                const blobUrl = URL.createObjectURL(blob);
                documentViewer.src = blobUrl;
                if (openLink) {
                    openLink.href = blobUrl;
                    openLink.removeAttribute('aria-disabled');
                }
                window.addEventListener('pagehide', () => URL.revokeObjectURL(blobUrl), { once: true });
                return blobUrl;
            })
            .catch(error => {
                console.error('Transcript document load failed:', error);
                const errorMessage = document.createElement('p');
                errorMessage.className = 'transcript-file-error';
                errorMessage.textContent = 'โหลดเอกสารไม่สำเร็จ กรุณาลองโหลดหน้าใหม่';
                documentViewer.parentElement.insertAdjacentElement('afterend', errorMessage);
                return null;
            });

        openLink?.addEventListener('click', event => {
            event.preventDefault();
            const tab = window.open('about:blank', '_blank');
            if (!tab) {
                window.alert('เบราว์เซอร์บล็อกแท็บใหม่ กรุณาอนุญาตป๊อปอัปแล้วลองอีกครั้ง');
                return;
            }
            blobReady.then(blobUrl => {
                if (!blobUrl) {
                    tab.close();
                    return;
                }
                tab.opener = null;
                tab.location.replace(blobUrl);
            });
        });
    }
})();
