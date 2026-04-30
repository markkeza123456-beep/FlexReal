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

    // ── Sidebar nav – page view switching ─────────────
    const navItems = document.querySelectorAll('.nav-item[data-view]');

    function switchView(viewName) {
        document.querySelectorAll('.page-view').forEach(v => v.style.display = 'none');
        const target = document.getElementById('view-' + viewName);
        if (target) target.style.display = 'flex';

        document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
        document.querySelector(`.nav-item[data-view="${viewName}"]`)?.classList.add('active');

        if (viewName === 'lessons') {
            document.getElementById('lessonsSection').style.display      = 'block';
            document.getElementById('lessonDetailSection').style.display = 'none';
        }
    }

    // Init: show dashboard first so counters & observers see visible elements
    switchView('dashboard');

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

    navItems.forEach(item => {
        item.addEventListener('click', e => {
            e.preventDefault();
            switchView(item.dataset.view);
        });
    });

    // Keep other nav items (no data-view) just toggle active style
    document.querySelectorAll('.nav-item[data-section]').forEach(item => {
        item.addEventListener('click', e => {
            e.preventDefault();
            document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
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

    // ── Dashboard lessons search ───────────────────────
    const dashLessonSearch = document.getElementById('dashLessonSearch');
    if (dashLessonSearch) {
        dashLessonSearch.addEventListener('input', () => {
            const q = dashLessonSearch.value.toLowerCase();
            let visible = 0;
            document.querySelectorAll('.dash-lesson-row').forEach(row => {
                const match = row.textContent.toLowerCase().includes(q);
                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            document.getElementById('dashLessonNoResult').style.display = visible === 0 ? 'block' : 'none';
        });
    }

    // ══════════════════════════════════════════════════
    // LESSONS SECTION
    // ══════════════════════════════════════════════════

    const lessonsSection     = document.getElementById('lessonsSection');
    const lessonDetailSection = document.getElementById('lessonDetailSection');

    // ── Lesson data store (mirrors PHP $lessons, extended client-side) ──
    const lessonData = {};
    document.querySelectorAll('#lessonsTableBody tr[data-id]').forEach(row => {
        const id = row.dataset.id;
        lessonData[id] = {
            id,
            title:    row.querySelector('.lesson-title-cell').textContent.trim(),
            subject:  row.querySelector('.lesson-subject').textContent.trim(),
            students: parseInt(row.cells[2].textContent),
            progress: parseInt(row.querySelector('.progress-num').textContent),
            status:   row.dataset.status,
            quizzes:  [],
        };
    });

    // ── Search + Filter ────────────────────────────────
    const lessonSearch       = document.getElementById('lessonSearch');
    const lessonFilterStatus = document.getElementById('lessonFilterStatus');
    const lessonNoResult     = document.getElementById('lessonNoResult');

    function filterLessons() {
        const q  = lessonSearch.value.toLowerCase();
        const st = lessonFilterStatus.value;
        const rows = document.querySelectorAll('#lessonsTableBody tr[data-id]');
        let visible = 0;
        rows.forEach(row => {
            const text   = row.textContent.toLowerCase();
            const status = row.dataset.status;
            const match  = text.includes(q) && (!st || status === st);
            row.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        lessonNoResult.style.display = visible === 0 ? 'block' : 'none';
    }

    lessonSearch      && lessonSearch.addEventListener('input', filterLessons);
    lessonFilterStatus && lessonFilterStatus.addEventListener('change', filterLessons);

    // ── View Detail ────────────────────────────────────
    function openLessonDetail(id) {
        const lesson = lessonData[id];
        if (!lesson) return;

        // Header
        const statusLabel = lesson.status === 'active' ? 'เผยแพร่' : 'ฉบับร่าง';
        const statusClass = lesson.status === 'active' ? 'badge-active' : 'badge-draft';
        document.getElementById('lessonDetailHeader').innerHTML = `
            <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px">
                <div>
                    <div style="font-size:20px;font-weight:700;color:#fff;margin-bottom:6px">${lesson.title}</div>
                    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;font-size:13px;color:var(--text-dim)">
                        <span>📚 ${lesson.subject}</span>
                        <span>👥 ${lesson.students} คน</span>
                        <span class="badge ${statusClass}">${statusLabel}</span>
                    </div>
                </div>
                <button class="btn-add-lesson" id="detailEditBtn"
                    style="background:var(--bg3);border:1px solid var(--border);color:var(--text);font-size:13px"
                    data-id="${id}">✏️ แก้ไข</button>
            </div>
            <div style="margin-top:14px">
                <div class="progress-wrap">
                    <div class="progress-bar" style="flex:1">
                        <div class="progress-fill" style="--pct:${lesson.progress}%"></div>
                    </div>
                    <span class="progress-num">${lesson.progress}%</span>
                </div>
            </div>
        `;
        document.getElementById('detailEditBtn').addEventListener('click', () => openEditLesson(id));

        // Overview body
        document.getElementById('lessonOverviewBody').innerHTML = `
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
                <div>
                    <div style="font-size:11.5px;color:var(--text-muted);margin-bottom:6px;font-weight:500">ข้อมูลบทเรียน</div>
                    <div style="font-size:13.5px;color:var(--text);line-height:1.7">
                        <div><span style="color:var(--text-dim)">ชื่อ:</span> ${lesson.title}</div>
                        <div><span style="color:var(--text-dim)">วิชา:</span> ${lesson.subject}</div>
                        <div><span style="color:var(--text-dim)">สถานะ:</span> <span class="badge ${statusClass}">${statusLabel}</span></div>
                    </div>
                </div>
                <div>
                    <div style="font-size:11.5px;color:var(--text-muted);margin-bottom:6px;font-weight:500">สถิติ</div>
                    <div style="display:flex;flex-direction:column;gap:8px;font-size:13.5px">
                        <div style="display:flex;justify-content:space-between">
                            <span style="color:var(--text-dim)">นักเรียน</span>
                            <span class="mono" style="color:#fff">${lesson.students} คน</span>
                        </div>
                        <div style="display:flex;justify-content:space-between">
                            <span style="color:var(--text-dim)">แบบทดสอบ</span>
                            <span class="mono" style="color:#fff" id="overviewQuizCount">${lesson.quizzes.length} ข้อ</span>
                        </div>
                        <div style="display:flex;justify-content:space-between">
                            <span style="color:var(--text-dim)">ความคืบหน้า</span>
                            <span class="mono" style="color:var(--orange)">${lesson.progress}%</span>
                        </div>
                    </div>
                </div>
            </div>
        `;

        renderQuizList(id);

        // Update topbar title
        document.getElementById('lessonsPageTitle').textContent = lesson.title;
        document.getElementById('lessonsPageSub').textContent   = lesson.subject;

        // Show detail, hide lessons list
        lessonsSection.style.display      = 'none';
        lessonDetailSection.style.display = 'flex';

        // Re-animate progress bars
        setTimeout(() => {
            lessonDetailSection.querySelectorAll('.progress-fill').forEach(f => {
                const pct = f.style.cssText.match(/--pct:\s*([^;]+)/)?.[1] || '0%';
                f.style.width = '0%';
                setTimeout(() => { f.style.width = pct; }, 50);
            });
        }, 80);

        // Switch to overview tab
        switchLessonTab('overview');
    }

    document.querySelectorAll('.btn-view-lesson').forEach(btn => {
        btn.addEventListener('click', () => openLessonDetail(btn.dataset.id));
    });

    // Dashboard shortcuts → switch to lessons view then open detail/edit
    document.querySelectorAll('.btn-dash-view-lesson').forEach(btn => {
        btn.addEventListener('click', () => {
            switchView('lessons');
            openLessonDetail(btn.dataset.id);
        });
    });
    document.querySelectorAll('.btn-dash-edit-lesson').forEach(btn => {
        btn.addEventListener('click', () => {
            switchView('lessons');
            openEditLesson(btn.dataset.id);
        });
    });

    // ── Back to lessons list ───────────────────────────
    document.getElementById('backToLessonsBtn')?.addEventListener('click', () => {
        document.getElementById('lessonDetailSection').style.display = 'none';
        document.getElementById('lessonsSection').style.display      = 'block';
        document.getElementById('lessonsPageTitle').textContent = 'บทเรียน';
        document.getElementById('lessonsPageSub').textContent   = 'จัดการบทเรียนทั้งหมดของคุณ';
    });

    // ── Tabs ───────────────────────────────────────────
    function switchLessonTab(tab) {
        document.querySelectorAll('.lesson-tab-btn').forEach(btn => {
            const isActive = btn.dataset.tab === tab;
            btn.classList.toggle('active', isActive);
            btn.style.background = isActive ? 'var(--orange-dim)' : '';
            btn.style.color      = isActive ? 'var(--orange)' : 'var(--text-dim)';
            btn.style.fontWeight = isActive ? '600' : '500';
        });
        document.querySelectorAll('.lesson-tab-content').forEach(el => {
            el.style.display = el.id === `lessonTab-${tab}` ? 'block' : 'none';
        });
    }

    document.querySelectorAll('.lesson-tab-btn').forEach(btn => {
        btn.addEventListener('click', () => switchLessonTab(btn.dataset.tab));
    });

    // ── Student search inside detail ───────────────────
    const detailStudentSearch = document.getElementById('detailStudentSearch');
    if (detailStudentSearch) {
        detailStudentSearch.addEventListener('input', () => {
            const q = detailStudentSearch.value.toLowerCase();
            document.querySelectorAll('.detail-student-row').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }

    // ── Edit Lesson Modal ──────────────────────────────
    const editLessonOverlay = document.getElementById('editLessonOverlay');

    function openEditLesson(id) {
        const lesson = lessonData[id];
        if (!lesson) return;
        document.getElementById('editLessonId').value       = id;
        document.getElementById('editLessonName').value     = lesson.title;
        document.getElementById('editLessonSubject').value  = lesson.subject;
        document.getElementById('editLessonProgress').value = lesson.progress;
        document.getElementById('editLessonStatus').value   = lesson.status;
        editLessonOverlay.classList.add('open');
    }

    function closeEditLesson() { editLessonOverlay.classList.remove('open'); }

    document.querySelectorAll('.btn-edit-lesson').forEach(btn => {
        btn.addEventListener('click', () => openEditLesson(btn.dataset.id));
    });
    document.getElementById('closeEditLessonBtn') ?.addEventListener('click', closeEditLesson);
    document.getElementById('closeEditLessonBtn2')?.addEventListener('click', closeEditLesson);
    editLessonOverlay.addEventListener('click', e => { if (e.target === editLessonOverlay) closeEditLesson(); });

    document.getElementById('saveEditLessonBtn')?.addEventListener('click', () => {
        const id       = document.getElementById('editLessonId').value;
        const name     = document.getElementById('editLessonName').value.trim();
        const subject  = document.getElementById('editLessonSubject').value.trim();
        const progress = parseInt(document.getElementById('editLessonProgress').value) || 0;
        const status   = document.getElementById('editLessonStatus').value;

        if (!name || !subject) {
            [document.getElementById('editLessonName'), document.getElementById('editLessonSubject')].forEach(inp => {
                if (!inp.value.trim()) inp.style.borderColor = '#ef4444';
            });
            return;
        }

        // Update data store
        lessonData[id].title    = name;
        lessonData[id].subject  = subject;
        lessonData[id].progress = Math.min(100, Math.max(0, progress));
        lessonData[id].status   = status;

        // Update table row
        const row = document.querySelector(`#lessonsTableBody tr[data-id="${id}"]`);
        if (row) {
            row.querySelector('.lesson-title-cell').textContent = name;
            row.querySelector('.lesson-subject').textContent    = subject;
            row.querySelector('.progress-num').textContent      = progress + '%';
            row.querySelector('.progress-fill').style.setProperty('--pct', progress + '%');
            row.querySelector('.progress-fill').style.width = progress + '%';
            row.dataset.status = status;
            const badge = row.querySelector('.badge');
            badge.className   = `badge badge-${status}`;
            badge.textContent = status === 'active' ? 'เผยแพร่' : 'ฉบับร่าง';
        }

        closeEditLesson();
    });

    // ── Delete lesson ──────────────────────────────────
    // (already handled by existing .btn-del logic above — just extra rows now have the class)

    // ── Quiz Modal ─────────────────────────────────────
    const quizModalOverlay = document.getElementById('quizModalOverlay');
    const quizTypeSelect   = document.getElementById('quizType');
    const quizChoicesGroup = document.getElementById('quizChoicesGroup');

    function closeQuizModal() { quizModalOverlay.classList.remove('open'); }

    document.getElementById('openQuizModalBtn')   ?.addEventListener('click', () => quizModalOverlay.classList.add('open'));
    document.getElementById('closeQuizModalBtn')  ?.addEventListener('click', closeQuizModal);
    document.getElementById('closeQuizModalBtn2') ?.addEventListener('click', closeQuizModal);
    quizModalOverlay?.addEventListener('click', e => { if (e.target === quizModalOverlay) closeQuizModal(); });

    quizTypeSelect?.addEventListener('change', () => {
        const needChoices = quizTypeSelect.value === 'choice';
        quizChoicesGroup.style.display = needChoices ? 'flex' : 'none';
    });

    function getCurrentLessonId() {
        return document.getElementById('lessonDetailHeader')
            ?.querySelector('[data-id]')?.dataset.id;
    }

    function renderQuizList(id) {
        const lesson   = lessonData[id];
        const list     = document.getElementById('quizList');
        const empty    = document.getElementById('quizEmpty');
        const existing = list.querySelectorAll('.quiz-item');
        existing.forEach(el => el.remove());

        if (!lesson.quizzes.length) {
            empty.style.display = 'block';
            return;
        }
        empty.style.display = 'none';

        const typeLabels = { choice:'ตัวเลือก (MCQ)', truefalse:'ถูก/ผิด', short:'เติมคำสั้น', essay:'อัตนัย' };

        lesson.quizzes.forEach((q, i) => {
            const item = document.createElement('div');
            item.className = 'quiz-item';
            item.style.cssText = 'background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px 16px;display:flex;align-items:flex-start;gap:12px';
            item.innerHTML = `
                <div class="mono" style="font-size:11px;color:var(--text-muted);width:24px;flex-shrink:0;padding-top:2px">${String(i+1).padStart(2,'0')}</div>
                <div style="flex:1;min-width:0">
                    <div style="font-size:13.5px;color:var(--text);line-height:1.5">${q.question}</div>
                    <div style="display:flex;align-items:center;gap:8px;margin-top:4px;flex-wrap:wrap">
                        <span class="badge badge-draft">${typeLabels[q.type] || q.type}</span>
                        ${q.answer ? `<span style="font-size:11.5px;color:var(--text-dim)">เฉลย: <span style="color:#10b981">${q.answer}</span></span>` : ''}
                        <span style="font-size:11.5px;color:var(--text-muted)">${q.score} คะแนน</span>
                    </div>
                </div>
                <button class="btn-icon btn-del" data-quiz-index="${i}" style="flex-shrink:0" title="ลบ">🗑</button>
            `;
            item.querySelector('.btn-del').addEventListener('click', () => {
                lesson.quizzes.splice(i, 1);
                renderQuizList(id);
                const oc = document.getElementById('overviewQuizCount');
                if (oc) oc.textContent = lesson.quizzes.length + ' ข้อ';
            });
            list.appendChild(item);
        });
    }

    document.getElementById('saveQuizBtn')?.addEventListener('click', () => {
        const question = document.getElementById('quizQuestion').value.trim();
        const type     = document.getElementById('quizType').value;
        const score    = parseInt(document.getElementById('quizScore').value) || 1;
        const answer   = document.getElementById('quizAnswer').value.trim();

        if (!question) {
            document.getElementById('quizQuestion').style.borderColor = '#ef4444';
            return;
        }

        const id = getCurrentLessonId();
        if (!id || !lessonData[id]) return;

        lessonData[id].quizzes.push({ question, type, score, answer });
        renderQuizList(id);

        const oc = document.getElementById('overviewQuizCount');
        if (oc) oc.textContent = lessonData[id].quizzes.length + ' ข้อ';

        // Reset form
        document.getElementById('quizQuestion').value = '';
        document.getElementById('quizAnswer').value   = '';
        document.getElementById('quizScore').value    = '1';
        closeQuizModal();
    });

    // ── Save Profile (settings) ────────────────────────
    document.getElementById('saveProfileBtn')?.addEventListener('click', () => {
        const btn = document.getElementById('saveProfileBtn');
        btn.textContent = '✅ บันทึกแล้ว!';
        setTimeout(() => { btn.textContent = '💾 บันทึกข้อมูล'; }, 1500);
    });

});