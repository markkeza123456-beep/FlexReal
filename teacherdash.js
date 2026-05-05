/* ===== teacherdash.js ===== */

document.addEventListener('DOMContentLoaded', () => {
    let currentDetailLessonId = '';

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

    function openModal()  { if (overlay) overlay.classList.add('open'); }
    function closeModal() { if (overlay) overlay.classList.remove('open'); }

    openBtns.forEach(btn  => btn && btn.addEventListener('click', openModal));
    closeBtns.forEach(btn => btn && btn.addEventListener('click', closeModal));

    if (overlay) {
        overlay.addEventListener('click', e => {
            if (e.target === overlay) closeModal();
        });
    }

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeModal();
    });

    // ── Save lesson (ส่งเข้าฐานข้อมูล) ─────────────────────────────
    const saveBtn = overlay?.querySelector('.btn-save');
    if (saveBtn && overlay) {
        saveBtn.addEventListener('click', () => {
            const inputs = overlay.querySelectorAll('.form-input');
            const lessonName = inputs[0].value.trim(); // ช่องชื่อบทเรียน
            const subjectId = inputs[1].value.trim();  // รหัสวิชา หรือ ชื่อวิชา

            if (!lessonName) {
                inputs[0].style.borderColor = '#ef4444';
                return;
            }
            inputs[0].style.borderColor = '';

            saveBtn.textContent = '⏳ กำลังบันทึก...';
            saveBtn.disabled = true;

            const formData = new FormData();
            formData.append('action', 'add_lesson');
            formData.append('lesson_name', lessonName);
            formData.append('subject_id', currentDetailLessonId || subjectId || 'SUB001'); // ส่ง ID วิชาไป

            fetch('teacher_api.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if(data.success) {
                        saveBtn.textContent = '✅ บันทึกแล้ว!';
                        setTimeout(() => {
                            saveBtn.textContent = '💾 บันทึกบทเรียน';
                            saveBtn.disabled = false;
                            inputs.forEach(inp => (inp.value = ''));
                            closeModal();
                            location.reload(); // รีเฟรชเพื่อดึงข้อมูลใหม่มาแสดง
                        }, 1200);
                    } else {
                        alert('เกิดข้อผิดพลาด: ' + data.message);
                        saveBtn.textContent = '💾 บันทึกบทเรียน';
                        saveBtn.disabled = false;
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้');
                    saveBtn.textContent = '💾 บันทึกบทเรียน';
                    saveBtn.disabled = false;
                });
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

    // Init: show dashboard first
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
    setTimeout(() => {
        fills.forEach(f => {
            const pct = f.style.cssText.match(/--pct:\s*([^;]+)/)?.[1] || '0%';
            f.style.width = pct;
        });
    }, 200);

    // ── Delete lesson row confirmation (demo) ──────────
    document.querySelectorAll('.btn-del').forEach(btn => {
        btn.addEventListener('click', () => {
            if(!confirm("ต้องการลบข้อมูลนี้หรือไม่?")) return;
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

    // ── Lesson data store (mirrors PHP $lessons) ──
    const lessonData = {};
    document.querySelectorAll('#lessonsTableBody tr[data-id]').forEach(row => {
        const id = row.dataset.id;
        lessonData[id] = {
            id,
            subjectId: row.dataset.subjectId || id,
            title:    row.querySelector('.lesson-title-cell').textContent.trim(),
            subject:  row.querySelector('.lesson-subject').textContent.trim(),
            students: parseInt(row.dataset.studentCount || row.cells[2].textContent, 10) || 0,
            progress: parseInt(row.querySelector('.progress-num').textContent) || 0,
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
        const rows = document.querySelectorAll('#lessonsTableBody tr.lesson-main-row');
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
        currentDetailLessonId = lesson.subjectId || id;

        const statusLabel = lesson.status === 'active' ? 'เผยแพร่' : 'ฉบับร่าง';
        const statusClass = lesson.status === 'active' ? 'badge-active' : 'badge-draft';
        const detailHeader = document.getElementById('lessonDetailHeader');
        detailHeader.dataset.id = id;
        detailHeader.innerHTML = `
            <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px">
                <div>
                    <div style="font-size:20px;font-weight:700;color:#fff;margin-bottom:6px">${lesson.title}</div>
                    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;font-size:13px;color:var(--text-dim)">
                        <span>📚 ${lesson.subject}</span>
                        <span>👥 ${lesson.students} คน</span>
                        <span class="badge ${statusClass}">${statusLabel}</span>
                    </div>
                </div>
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
        
        document.getElementById('lessonOverviewBody').innerHTML = `
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
                <div>
                    <div style="font-size:11.5px;color:var(--text-muted);margin-bottom:6px;font-weight:500">ข้อมูลรายวิชา/บทเรียน</div>
                    <div style="font-size:13.5px;color:var(--text);line-height:1.7">
                        <div><span style="color:var(--text-dim)">ชื่อ:</span> ${lesson.title}</div>
                        <div><span style="color:var(--text-dim)">วิชา:</span> ${lesson.subject}</div>
                        <div><span style="color:var(--text-dim)">สถานะ:</span> <span class="badge ${statusClass}">${statusLabel}</span></div>
                    </div>
                </div>
                <div>
                    <div style="font-size:11.5px;color:var(--text-muted);margin-bottom:6px;font-weight:500">สถิติเบื้องต้น</div>
                    <div style="display:flex;flex-direction:column;gap:8px;font-size:13.5px">
                        <div style="display:flex;justify-content:space-between">
                            <span style="color:var(--text-dim)">นักเรียน</span>
                            <span class="mono" style="color:#fff">${lesson.students} คน</span>
                        </div>
                        <div style="display:flex;justify-content:space-between">
                            <span style="color:var(--text-dim)">ความคืบหน้าเฉลี่ย</span>
                            <span class="mono" style="color:var(--orange)">${lesson.progress}%</span>
                        </div>
                    </div>
                </div>
            </div>
        `;

        renderQuizList(id);
        applyDetailStudentFilter();

        document.getElementById('lessonsPageTitle').textContent = lesson.title;
        document.getElementById('lessonsPageSub').textContent   = lesson.subject;

        lessonsSection.style.display      = 'none';
        lessonDetailSection.style.display = 'flex';

        setTimeout(() => {
            lessonDetailSection.querySelectorAll('.progress-fill').forEach(f => {
                const pct = f.style.cssText.match(/--pct:\s*([^;]+)/)?.[1] || '0%';
                f.style.width = '0%';
                setTimeout(() => { f.style.width = pct; }, 50);
            });
        }, 80);

        switchLessonTab('overview');
    }

    document.querySelectorAll('.btn-view-lesson').forEach(btn => {
        btn.addEventListener('click', () => openLessonDetail(btn.dataset.id));
    });

    document.querySelectorAll('.btn-dash-view-lesson').forEach(btn => {
        btn.addEventListener('click', () => {
            switchView('lessons');
            openLessonDetail(btn.dataset.id);
        });
    });

    // ── Back to lessons list ───────────────────────────
    document.getElementById('backToLessonsBtn')?.addEventListener('click', () => {
        currentDetailLessonId = '';
        if (detailStudentSearch) detailStudentSearch.value = '';
        document.getElementById('lessonDetailSection').style.display = 'none';
        document.getElementById('lessonsSection').style.display      = 'block';
        document.getElementById('lessonsPageTitle').textContent = 'บทเรียน';
        document.getElementById('lessonsPageSub').textContent   = 'จัดการบทเรียนที่อยู่ในความดูแลของคุณ';
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
    function applyDetailStudentFilter() {
        const q = (detailStudentSearch?.value || '').toLowerCase();
        document.querySelectorAll('.detail-student-row').forEach(row => {
            const subjectIds = (row.dataset.subjectIds || '').split(',').map(item => item.trim()).filter(Boolean);
            const matchSubject = !currentDetailLessonId || subjectIds.includes(currentDetailLessonId);
            const matchSearch = row.textContent.toLowerCase().includes(q);
            row.style.display = matchSubject && matchSearch ? '' : 'none';
        });
    }
    if (detailStudentSearch) {
        detailStudentSearch.addEventListener('input', applyDetailStudentFilter);
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
    document.querySelectorAll('.btn-dash-edit-lesson').forEach(btn => {
        btn.addEventListener('click', () => openEditLesson(btn.dataset.id));
    });

    document.getElementById('closeEditLessonBtn') ?.addEventListener('click', closeEditLesson);
    document.getElementById('closeEditLessonBtn2')?.addEventListener('click', closeEditLesson);
    editLessonOverlay?.addEventListener('click', e => { if (e.target === editLessonOverlay) closeEditLesson(); });

    document.getElementById('saveEditLessonBtn')?.addEventListener('click', () => {
        closeEditLesson();
        alert('ระบบจำลองการแก้ไขสำเร็จ');
    });

    // ── Quiz Modal (ส่งเข้าฐานข้อมูล) ─────────────────────────────────────
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
        return document.getElementById('lessonDetailHeader')?.dataset.id;
    }

    function renderQuizList(id) {
        const lesson   = lessonData[id];
        const list     = document.getElementById('quizList');
        const empty    = document.getElementById('quizEmpty');
        const existing = list.querySelectorAll('.quiz-item');
        existing.forEach(el => el.remove());

        if (!lesson || !lesson.quizzes.length) {
            if(empty) empty.style.display = 'block';
            return;
        }
        if(empty) empty.style.display = 'none';

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
                        <span style="font-size:11.5px;color:var(--text-muted)">${q.score || 1} คะแนน</span>
                    </div>
                </div>
            `;
            list.appendChild(item);
        });
    }

    // ส่งคำถามเข้า API
    document.getElementById('saveQuizBtn')?.addEventListener('click', () => {
        const question = document.getElementById('quizQuestion').value.trim();
        const choices  = document.getElementById('quizChoices').value.trim();
        const answer   = document.getElementById('quizAnswer').value.trim();
        const btn      = document.getElementById('saveQuizBtn');

        if (!question) {
            document.getElementById('quizQuestion').style.borderColor = '#ef4444';
            return;
        }
        document.getElementById('quizQuestion').style.borderColor = '';

        const lessonId = getCurrentLessonId(); 
        if (!lessonId) {
            alert('กรุณาเลือกวิชา/บทเรียนก่อนเพิ่มคำถาม');
            return;
        }

        btn.textContent = '⏳ กำลังบันทึก...';
        btn.disabled = true;

        const formData = new FormData();
        formData.append('action', 'add_quiz');
        formData.append('lesson_id', lessonId); 
        formData.append('question', question);
        formData.append('choices', choices);
        formData.append('answer', answer);

        fetch('teacher_api.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    btn.textContent = '✅ สำเร็จ!';
                    // จำลองการเพิ่มลงในหน้าจอทันทีเพื่อให้เห็นผล
                    if(lessonData[lessonId]) {
                        lessonData[lessonId].quizzes.push({ question, type: 'choice', score: 1, answer });
                        renderQuizList(lessonId);
                    }
                    setTimeout(() => {
                        document.getElementById('quizQuestion').value = '';
                        document.getElementById('quizChoices').value   = '';
                        document.getElementById('quizAnswer').value    = '';
                        btn.textContent = '➕ เพิ่มคำถาม';
                        btn.disabled = false;
                        closeQuizModal();
                    }, 1000);
                } else {
                    alert('Error: ' + data.message);
                    btn.textContent = '➕ เพิ่มคำถาม';
                    btn.disabled = false;
                }
            })
            .catch(err => {
                console.error(err);
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
                btn.textContent = '➕ เพิ่มคำถาม';
                btn.disabled = false;
            });
    });

    // ── Sub-lesson toggle ──
    document.querySelectorAll('.btn-expand-sub').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const tr = btn.closest('tr.lesson-main-row');
            const parentId = tr.dataset.id;
            const subs = document.querySelectorAll(`tr.sub-lesson-row[data-parent="${parentId}"]`);
            const isExpanded = tr.dataset.expanded === 'true';
            
            tr.dataset.expanded = !isExpanded;
            btn.style.transform = isExpanded ? 'rotate(0deg)' : 'rotate(90deg)';
            
            subs.forEach(sub => {
                sub.style.display = isExpanded ? 'none' : 'table-row';
            });
        });
    });
});

// ── Save Profile via AJAX (global scope) ───────────
function saveProfile() {
    const btn      = document.getElementById('saveProfileBtn');
    const feedback = document.getElementById('profileFeedback');
    const name     = document.getElementById('profileName')?.value.trim();
    const current  = document.getElementById('pwdCurrent')?.value ?? '';
    const newPwd   = document.getElementById('pwdNew')?.value ?? '';
    const confirm  = document.getElementById('pwdConfirm')?.value ?? '';

    function showFeedback(type, msg) {
        feedback.style.display    = 'block';
        feedback.textContent      = msg;
        feedback.style.background = type === 'success' ? 'rgba(16,185,129,.15)' : 'rgba(239,68,68,.15)';
        feedback.style.color      = type === 'success' ? '#10b981' : '#ef4444';
        feedback.style.border     = `1px solid ${type === 'success' ? '#10b98133' : '#ef444433'}`;
        setTimeout(() => { feedback.style.display = 'none'; }, 4000);
    }

    if (newPwd && newPwd !== confirm) {
        showFeedback('error', '✗ รหัสผ่านใหม่ไม่ตรงกัน');
        return;
    }
    if (newPwd && newPwd.length < 6) {
        showFeedback('error', '✗ รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร');
        return;
    }
    if (newPwd && !current) {
        showFeedback('error', '✗ กรุณาใส่รหัสผ่านปัจจุบันก่อน');
        return;
    }

    btn.disabled    = true;
    btn.textContent = '⏳ กำลังบันทึก...';

    const body = new FormData();
    body.append('name',        name);
    body.append('pwd_current', current);
    body.append('pwd_new',     newPwd);

    fetch('update_profile.php', { method: 'POST', body })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showFeedback('success', '✓ ' + data.message);
                ['pwdCurrent','pwdNew','pwdConfirm'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.value = '';
                });
                document.getElementById('pwdStrengthWrap').style.display = 'none';
                document.getElementById('pwdMatchMsg').textContent = '';
                const nameEl = document.querySelector('.sidebar-profile .profile-name');
                if (nameEl && name) nameEl.textContent = name;
            } else {
                showFeedback('error', '✗ ' + data.message);
            }
        })
        .catch(err => {
            console.error('saveProfile error:', err);
            showFeedback('error', '✗ เกิดข้อผิดพลาด กรุณาลองใหม่');
        })
        .finally(() => {
            btn.disabled    = false;
            btn.textContent = '💾 บันทึกข้อมูล';
        });
}

// ═══════════════════════════════════════════════════
// CROP MODAL (Avatar)
// ═══════════════════════════════════════════════════
(function () {
    let srcDataUrl = '';
    let imgNatW = 0, imgNatH = 0;
    let posX = 0, posY = 0, scale = 1;
    let dragging = false, startX = 0, startY = 0, startPX = 0, startPY = 0;
    const CROP_SIZE = 240; 

    const modalHTML = `
<div id="cropOverlay" style="display:none;position:fixed;inset:0;z-index:9999;
     background:rgba(0,0,0,.72);align-items:center;justify-content:center">
  <div style="background:var(--bg2,#1e2130);border-radius:16px;padding:28px 24px 22px;
       width:min(92vw,360px);box-shadow:0 24px 60px rgba(0,0,0,.5);text-align:center">
    <div style="font-size:15px;font-weight:700;color:var(--text,#f1f5f9);margin-bottom:18px">
      ✂️ ครอปรูปโปรไฟล์
    </div>
    <div id="cropViewport" style="
         position:relative;width:${CROP_SIZE}px;height:${CROP_SIZE}px;margin:0 auto 14px;
         border-radius:50%;overflow:hidden;cursor:grab;
         box-shadow:0 0 0 4px var(--orange,#f97316),0 0 0 7px rgba(249,115,22,.2);
         background:#000;touch-action:none">
      <img id="cropImg" draggable="false" style="
           position:absolute;transform-origin:top left;user-select:none;
           -webkit-user-select:none;pointer-events:none">
    </div>
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px">
      <span style="font-size:13px;color:var(--text-muted,#94a3b8)">🔍</span>
      <input type="range" id="cropZoom" min="1" max="3" step="0.01" value="1"
             style="flex:1;accent-color:var(--orange,#f97316)">
      <span style="font-size:13px;color:var(--text-muted,#94a3b8)">🔎</span>
    </div>
    <div style="display:flex;gap:10px">
      <button id="cropCancelBtn" style="
              flex:1;padding:10px;border-radius:8px;border:1px solid var(--border,#334155);
              background:transparent;color:var(--text,#f1f5f9);cursor:pointer;font-size:13px">
        ยกเลิก
      </button>
      <button id="cropConfirmBtn" style="
              flex:1;padding:10px;border-radius:8px;border:none;
              background:var(--orange,#f97316);color:#fff;cursor:pointer;
              font-size:13px;font-weight:600">
        ✓ ใช้รูปนี้
      </button>
    </div>
    <div id="cropUploadMsg" style="font-size:12px;margin-top:10px;color:var(--text-muted,#94a3b8);
         min-height:18px"></div>
  </div>
</div>`;

    document.body.insertAdjacentHTML('beforeend', modalHTML);

    const overlay     = document.getElementById('cropOverlay');
    const viewport    = document.getElementById('cropViewport');
    const cropImg     = document.getElementById('cropImg');
    const zoomSlider  = document.getElementById('cropZoom');
    const cancelBtn   = document.getElementById('cropCancelBtn');
    const confirmBtn  = document.getElementById('cropConfirmBtn');
    const uploadMsg   = document.getElementById('cropUploadMsg');

    window.openCropModal = function (dataUrl) {
        srcDataUrl = dataUrl;
        scale = 1;
        zoomSlider.value = 1;
        uploadMsg.textContent = '';

        const tmp = new Image();
        tmp.onload = () => {
            imgNatW = tmp.naturalWidth;
            imgNatH = tmp.naturalHeight;
            const minFit = CROP_SIZE / Math.min(imgNatW, imgNatH);
            scale = minFit;
            zoomSlider.min = minFit.toFixed(3);
            zoomSlider.value = minFit;

            cropImg.src = dataUrl;
            applyTransform();
            centerImage();
            overlay.style.display = 'flex';
        };
        tmp.src = dataUrl;
    };

    function applyTransform() {
        cropImg.style.width  = (imgNatW * scale) + 'px';
        cropImg.style.height = (imgNatH * scale) + 'px';
        cropImg.style.transform = `translate(${posX}px, ${posY}px)`;
    }

    function centerImage() {
        posX = (CROP_SIZE - imgNatW * scale) / 2;
        posY = (CROP_SIZE - imgNatH * scale) / 2;
        applyTransform();
    }

    function clamp() {
        const w = imgNatW * scale;
        const h = imgNatH * scale;
        if (posX > 0) posX = 0;
        if (posY > 0) posY = 0;
        if (posX + w < CROP_SIZE) posX = CROP_SIZE - w;
        if (posY + h < CROP_SIZE) posY = CROP_SIZE - h;
    }

    zoomSlider.addEventListener('input', () => {
        const newScale = parseFloat(zoomSlider.value);
        const cx = CROP_SIZE / 2;
        const cy = CROP_SIZE / 2;
        const ratio = newScale / scale;
        posX = cx - ratio * (cx - posX);
        posY = cy - ratio * (cy - posY);
        scale = newScale;
        clamp();
        applyTransform();
    });

    viewport.addEventListener('mousedown', e => {
        dragging = true;
        startX = e.clientX; startY = e.clientY;
        startPX = posX;     startPY = posY;
        viewport.style.cursor = 'grabbing';
        e.preventDefault();
    });
    document.addEventListener('mousemove', e => {
        if (!dragging) return;
        posX = startPX + (e.clientX - startX);
        posY = startPY + (e.clientY - startY);
        clamp();
        applyTransform();
    });
    document.addEventListener('mouseup', () => {
        dragging = false;
        viewport.style.cursor = 'grab';
    });

    viewport.addEventListener('touchstart', e => {
        if (e.touches.length !== 1) return;
        dragging = true;
        startX = e.touches[0].clientX; startY = e.touches[0].clientY;
        startPX = posX; startPY = posY;
        e.preventDefault();
    }, { passive: false });
    document.addEventListener('touchmove', e => {
        if (!dragging || e.touches.length !== 1) return;
        posX = startPX + (e.touches[0].clientX - startX);
        posY = startPY + (e.touches[0].clientY - startY);
        clamp();
        applyTransform();
        e.preventDefault();
    }, { passive: false });
    document.addEventListener('touchend', () => { dragging = false; });

    cancelBtn.addEventListener('click', () => {
        overlay.style.display = 'none';
    });

    confirmBtn.addEventListener('click', () => {
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = CROP_SIZE;
        const ctx = canvas.getContext('2d');

        ctx.beginPath();
        ctx.arc(CROP_SIZE/2, CROP_SIZE/2, CROP_SIZE/2, 0, Math.PI*2);
        ctx.clip();

        const tmp = new Image();
        tmp.onload = () => {
            ctx.drawImage(tmp, posX, posY, imgNatW * scale, imgNatH * scale);
            const croppedDataUrl = canvas.toDataURL('image/png');

            const avatarImg     = document.getElementById('avatarImg');
            const avatarInitial = document.getElementById('avatarInitial');
            if (avatarImg) {
                avatarImg.src = croppedDataUrl;
                avatarImg.style.display = 'block';
            }
            if (avatarInitial) avatarInitial.style.display = 'none';

            document.querySelectorAll('.sidebar-profile .profile-avatar').forEach(el => {
                el.style.background = 'none';
                el.style.padding    = '0';
                el.style.overflow   = 'hidden';
                el.innerHTML = `<img src="${croppedDataUrl}" style="width:100%;height:100%;object-fit:cover;border-radius:50%">`;
            });

            overlay.style.display = 'none';
            uploadMsg.textContent = '⏳ กำลังอัปโหลด...';
            overlay.style.display = 'flex';
            confirmBtn.disabled = true;
            cancelBtn.disabled  = true;

            fetch('uploadavatar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ image: croppedDataUrl })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    uploadMsg.style.color = '#10b981';
                    uploadMsg.textContent = '✓ อัปโหลดสำเร็จ!';
                    if (data.url) {
                        if (avatarImg) avatarImg.src = data.url;
                        document.querySelectorAll('.sidebar-profile .profile-avatar img').forEach(img => img.src = data.url);
                    }
                    setTimeout(() => { overlay.style.display = 'none'; }, 1000);
                } else {
                    uploadMsg.style.color = '#ef4444';
                    uploadMsg.textContent = '✗ ' + (data.message || 'อัปโหลดล้มเหลว');
                }
            })
            .catch(() => {
                uploadMsg.style.color = '#ef4444';
                uploadMsg.textContent = '✗ เกิดข้อผิดพลาด กรุณาลองใหม่';
            })
            .finally(() => {
                confirmBtn.disabled = false;
                cancelBtn.disabled  = false;
            });
        };
        tmp.src = srcDataUrl;
    });
})();