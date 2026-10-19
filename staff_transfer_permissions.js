'use strict';

(() => {
    const sidebar = document.getElementById('sidebar');
    const toast = document.getElementById('permissionToast');
    let toastTimer;

    document.getElementById('menuBtn')?.addEventListener('click', () => sidebar?.classList.toggle('open'));
    document.getElementById('sidebarClose')?.addEventListener('click', () => sidebar?.classList.remove('open'));

    function showToast(message, type = 'info') {
        if (!toast) return;
        toast.textContent = message;
        toast.className = `toast show ${type}`;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('show'), 3200);
    }

    document.querySelectorAll('[data-permission-action]').forEach(button => {
        button.addEventListener('click', async () => {
            const row = button.closest('[data-teacher-row]');
            if (!row) return;

            const action = button.dataset.permissionAction;
            const teacherId = row.dataset.teacherRow;
            const confirmMessage = action === 'grant'
                ? 'ยืนยันให้สิทธิ์อาจารย์จัดการเทียบโอนหน่วยกิตหรือไม่?'
                : 'ยืนยันดึงสิทธิ์จัดการเทียบโอนของอาจารย์รายนี้กลับหรือไม่?';
            if (!window.confirm(confirmMessage)) return;

            button.disabled = true;
            const formData = new URLSearchParams({
                action,
                instructor_id: teacherId,
                csrf_token: window.transferPermissionCsrf || ''
            });

            try {
                const response = await fetch('assign_permission.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                        Accept: 'application/json'
                    },
                    credentials: 'same-origin',
                    body: formData.toString()
                });
                const result = await response.json();
                if (!response.ok || result.status !== 'success') {
                    throw new Error(result.message || 'บันทึกการเปลี่ยนสิทธิ์ไม่สำเร็จ');
                }

                const enabled = action === 'grant';
                row.dataset.enabled = enabled ? '1' : '0';
                const state = row.querySelector('[data-permission-state]');
                state.textContent = enabled ? 'มีสิทธิ์' : 'ไม่มีสิทธิ์';
                state.classList.toggle('is-enabled', enabled);
                state.classList.toggle('is-disabled', !enabled);
                button.dataset.permissionAction = enabled ? 'revoke' : 'grant';
                button.textContent = enabled ? 'ดึงสิทธิ์กลับ' : 'ให้สิทธิ์';
                button.classList.toggle('is-revoke', enabled);
                button.classList.toggle('is-grant', !enabled);
                showToast(result.message || 'บันทึกสิทธิ์เรียบร้อยแล้ว', 'success');
            } catch (error) {
                showToast(error instanceof Error ? error.message : 'เชื่อมต่อระบบไม่สำเร็จ', 'error');
            } finally {
                button.disabled = false;
            }
        });
    });
})();
