'use strict';

const MAX_TRANSCRIPT_BYTES = 2 * 1024 * 1024;
const TRANSCRIPT_TOO_LARGE = 'ไฟล์ใหญ่เกินไป กรุณาใช้อัปโหลดไฟล์ขนาดไม่เกิน 2MB';

async function compressTranscriptImage(file) {
    if (!file.type.startsWith('image/')) return file;

    let bitmap;
    if ('createImageBitmap' in window) {
        bitmap = await createImageBitmap(file);
    } else {
        bitmap = await new Promise((resolve, reject) => {
            const image = new Image();
            const sourceUrl = URL.createObjectURL(file);
            image.onload = () => {
                URL.revokeObjectURL(sourceUrl);
                resolve(image);
            };
            image.onerror = () => {
                URL.revokeObjectURL(sourceUrl);
                reject(new Error('อ่านรูปภาพไม่สำเร็จ'));
            };
            image.src = sourceUrl;
        });
    }
    const sourceWidth = bitmap.width || bitmap.naturalWidth;
    const sourceHeight = bitmap.height || bitmap.naturalHeight;
    const scale = Math.min(1, 1200 / Math.max(sourceWidth, sourceHeight));
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(sourceWidth * scale));
    canvas.height = Math.max(1, Math.round(sourceHeight * scale));
    const context = canvas.getContext('2d');
    if (!context) throw new Error('เตรียมรูปภาพสำหรับอัปโหลดไม่สำเร็จ');
    context.fillStyle = '#fff';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close?.();

    const compressed = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.7));
    if (!compressed) throw new Error('บีบอัดรูปภาพไม่สำเร็จ');
    const baseName = file.name.replace(/\.[^.]+$/, '') || 'transcript';
    return new File([compressed], `${baseName}.jpg`, { type: 'image/jpeg', lastModified: Date.now() });
}

function appendPendingRequest() {
    const historyCard = document.querySelector('.history-card');
    if (!historyCard) return;
    let list = historyCard.querySelector('.history-list');
    if (!list) {
        historyCard.querySelector('.muted')?.remove();
        list = document.createElement('div');
        list.className = 'history-list';
        historyCard.append(list);
    }
    const row = document.createElement('article');
    row.className = 'history-row';
    const label = document.createElement('strong');
    label.textContent = 'ส่งคำขอใหม่แล้ว';
    const status = document.createElement('span');
    status.className = 'request-status status-pending';
    status.textContent = 'รอตรวจสอบ';
    row.append(label, status);
    list.prepend(row);
}

document.getElementById('transcriptUploadForm')?.addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    const fileInput = form.querySelector('input[type="file"]');
    const file = fileInput?.files?.[0];
    if (!file) return;

    const button = form.querySelector('button[type="submit"]');
    if (button) button.disabled = true;
    try {
        const preparedFile = await compressTranscriptImage(file);
        if (preparedFile.size > MAX_TRANSCRIPT_BYTES) {
            throw new Error(TRANSCRIPT_TOO_LARGE);
        }

        const formData = new FormData(form);
        formData.set('transcript', preparedFile, preparedFile.name);
        const response = await fetch(form.action, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const result = await response.json();
        if (!response.ok || result.status !== 'success') {
            throw new Error(result.message || 'อัปโหลดไม่สำเร็จ');
        }

        window.alert(result.message || 'ส่งทรานสคริปต์เรียบร้อยแล้ว');
        form.reset();
        appendPendingRequest();
    } catch (error) {
        window.alert(error instanceof Error ? error.message : 'ส่งข้อมูลไม่สำเร็จ กรุณาลองใหม่');
    } finally {
        if (button) button.disabled = false;
    }
});
