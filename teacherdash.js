/* ===== teacherdash.js ===== */

document.addEventListener('DOMContentLoaded', () => {

    // ── Modal ──────────────────────────────────────────
    const overlay      = document.getElementById('modalOverlay');
    const openBtns     = [
        document.getElementById('openModalBtn'),
        document.getElementById('openModalBtn2'),
    ];
    const closeBtns    = [
        document.getElementById('closeModalBtn'),
        document.getElementById('closeModalBtn2'),
    ];

    function openModal()  { overlay.classList.add('open'); }
    function closeModal() { overlay.classList.remove('open'); }

    openBtns.forEach(btn  => btn && btn.addEventListener('click', openModal));
    closeBtns.forEach(btn => btn && btn.addEventListener('click', closeModal));

    overlay.addEventListener('click', e => {
        if (e.target === overlay) closeModal();
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeModal();
    });

    // ── Save lesson (demo) ─────────────────────────────
    const saveBtn = document.querySelector('.btn-save');
    if (saveBtn) {
        saveBtn.addEventListener('click', () => {
            const inputs = overlay.querySelectorAll('.form-input');
            let allFilled = true;
            inputs.forEach(inp => {
                if (!inp.value.trim()) {
                    inp.style.borderColor = '#ef4444';
                    allFilled = false;
                } else {
                    inp.style.borderColor = '';
                }
            });
            if (!allFilled) return;

            saveBtn.textContent = '✅ บันทึกแล้ว!';
            setTimeout(() => {
                saveBtn.textContent = '💾 บันทึกบทเรียน';
                inputs.forEach(inp => (inp.value = ''));
                closeModal();
            }, 1200);
        });
    }

    // ── Animated counters ──────────────────────────────
    const counters = document.querySelectorAll('.counter');
    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            const el     = entry.target;
            const target = parseInt(el.dataset.target, 10);
            const dur    = 900;
            const step   = Math.ceil(target / (dur / 16));
            let current  = 0;

            const tick = () => {
                current = Math.min(current + step, target);
                el.textContent = current;
                if (current < target) requestAnimationFrame(tick);
            };
            requestAnimationFrame(tick);
            observer.unobserve(el);
        });
    }, { threshold: 0.4 });

    counters.forEach(c => observer.observe(c));

    // ── Sidebar active nav ─────────────────────────────
    const navItems = document.querySelectorAll('.nav-item[data-section]');
    navItems.forEach(item => {
        item.addEventListener('click', e => {
            e.preventDefault();
            navItems.forEach(n => n.classList.remove('active'));
            item.classList.add('active');
        });
    });

    // ── Student search ─────────────────────────────────
    const searchInput = document.getElementById('studentSearch');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const q   = searchInput.value.toLowerCase();
            const rows = document.querySelectorAll('#studentTable tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(q) ? '' : 'none';
            });
        });
    }

    // ── Progress bar animation ─────────────────────────
    const fills = document.querySelectorAll('.progress-fill');
    const progObs = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            entry.target.style.width = entry.target.style.getPropertyValue('--pct') ||
                                       getComputedStyle(entry.target).getPropertyValue('--pct');
            progObs.unobserve(entry.target);
        });
    }, { threshold: 0.2 });
    fills.forEach(f => {
        f.style.width = '0%';
        progObs.observe(f);
    });
    // Re-trigger after tiny delay so CSS transition fires
    setTimeout(() => {
        fills.forEach(f => {
            const pct = f.style.cssText.match(/--pct:\s*([^;]+)/)?.[1] || '0%';
            f.style.width = pct;
        });
    }, 200);

    // ── Delete lesson row confirmation (demo) ──────────
    document.querySelectorAll('.btn-del').forEach(btn => {
        btn.addEventListener('click', () => {
            const row = btn.closest('tr');
            if (!row) return;
            row.style.opacity = '0.4';
            row.style.transition = 'opacity .3s';
            setTimeout(() => row.remove(), 300);
        });
    });

    // ── Notification bell (demo) ───────────────────────
    const notifBtn = document.getElementById('notifBtn');
    if (notifBtn) {
        notifBtn.addEventListener('click', () => {
            const dot = notifBtn.querySelector('.notif-dot');
            if (dot) dot.style.display = 'none';
            alert('ไม่มีการแจ้งเตือนใหม่ 📭');
        });
    }

});