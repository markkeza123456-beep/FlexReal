/* ===== teacherdash.js ===== */

document.addEventListener('DOMContentLoaded', () => {

    // ── Sidebar nav – page view switching ─────────────
    const navItems = document.querySelectorAll('.nav-item[data-view]');
    function switchView(viewName) {
        document.querySelectorAll('.page-view').forEach(v => v.style.display = 'none');
        const target = document.getElementById('view-' + viewName);
        if (target) target.style.display = 'flex';

        document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
        document.querySelector(`.nav-item[data-view="${viewName}"]`)?.classList.add('active');
    }
    switchView('dashboard');
    navItems.forEach(item => item.addEventListener('click', e => {
        e.preventDefault(); switchView(item.dataset.view);
    }));

    // ── Tabs ในหน้ารายละเอียด ───────────────────────────────────────────
    function switchLessonTab(tab) {
        document.querySelectorAll('.lesson-tab-btn').forEach(btn => {
            const isActive = btn.dataset.tab === tab;
            btn.classList.toggle('active', isActive);
            if (isActive) {
                btn.style.background = 'var(--orange-dim)'; btn.style.color = 'var(--orange)'; btn.style.fontWeight = '600';
            } else {
                btn.style.background = 'none'; btn.style.color = 'var(--text-dim)'; btn.style.fontWeight = '500';
            }
        });
        document.querySelectorAll('.lesson-tab-content').forEach(el => {
            el.style.display = el.id === `lessonTab-${tab}` ? 'block' : 'none';
        });
    }
    document.querySelectorAll('.lesson-tab-btn').forEach(btn => btn.addEventListener('click', () => switchLessonTab(btn.dataset.tab)));


    // ── Student search inside detail ───────────────────
    const detailStudentSearch = document.getElementById('detailStudentSearch');
    if (detailStudentSearch) {
        detailStudentSearch.addEventListener('input', () => {
            const q = detailStudentSearch.value.toLowerCase();
            document.querySelectorAll('.detail-student-row').forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(q) ? '' : 'none';
            });
        });
    }

    // ════════════ 1. ระบบจัดการ "บทเรียนย่อย" ════════════ //
    const modalOverlay = document.getElementById('modalOverlay');
    document.getElementById('openModalBtn')?.addEventListener('click', () => modalOverlay.classList.add('open'));
    document.getElementById('closeModalBtn')?.addEventListener('click', () => modalOverlay.classList.remove('open'));
    document.getElementById('closeModalBtn2')?.addEventListener('click', () => modalOverlay.classList.remove('open'));
    
    document.getElementById('saveLessonBtn')?.addEventListener('click', (e) => {
        const btn = e.target;
        const lessonName = document.getElementById('lessonNameInput').value.trim();
        const subjectId = document.getElementById('lessonSubjectId').value;

        if (!lessonName) { document.getElementById('lessonNameInput').style.borderColor = '#ef4444'; return; }
        btn.textContent = '⏳...'; btn.disabled = true;

        const fd = new FormData(); fd.append('action', 'add_lesson'); fd.append('lesson_name', lessonName); fd.append('subject_id', subjectId);
        fetch('teacher_api.php', { method: 'POST', body: fd }).then(r=>r.json()).then(d => {
            if(d.success) {
                location.reload(); 
            } else { 
                alert(d.message); 
                if(d.message.includes('ล็อกอิน')) {
                    window.location.href = 'login.php';
                } else {
                    btn.disabled = false;
                    btn.textContent = '💾 บันทึกบทเรียน';
                }
            }
        }).catch(() => { alert('เชื่อมต่อฐานข้อมูลล้มเหลว'); btn.disabled = false; btn.textContent = '💾 บันทึกบทเรียน'; });
    });

    const editLessonModal = document.getElementById('editLessonModal');
    document.querySelectorAll('.btn-edit-lsn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('editLsnIdInput').value = btn.dataset.id;
            document.getElementById('editLsnNameInput').value = btn.dataset.name;
            editLessonModal.classList.add('open');
        });
    });

    document.getElementById('saveEditLsnBtn')?.addEventListener('click', (e) => {
        const btn = e.target; const id = document.getElementById('editLsnIdInput').value; const name = document.getElementById('editLsnNameInput').value.trim();
        btn.textContent = '⏳...'; btn.disabled = true;
        const fd = new FormData(); fd.append('action', 'edit_lesson'); fd.append('lesson_id', id); fd.append('lesson_name', name);
        fetch('teacher_api.php', { method: 'POST', body: fd }).then(r=>r.json()).then(d => {
            if(d.success) {
                location.reload(); 
            } else { 
                alert(d.message); 
                if(d.message.includes('ล็อกอิน')) window.location.href = 'login.php';
                else { btn.disabled = false; btn.textContent = '💾 บันทึกการแก้ไข'; }
            }
        });
    });

    document.querySelectorAll('.btn-del-lsn').forEach(btn => {
        btn.addEventListener('click', () => {
            if(confirm('ต้องการลบบทเรียนนี้ใช่หรือไม่? (ข้อสอบในบทนี้จะถูกลบไปด้วย)')) {
                const fd = new FormData(); fd.append('action', 'delete_lesson'); fd.append('lesson_id', btn.dataset.id);
                fetch('teacher_api.php', { method: 'POST', body: fd }).then(r=>r.json()).then(d => {
                    if(d.message && d.message.includes('ล็อกอิน')) { alert(d.message); window.location.href = 'login.php'; }
                    else location.reload();
                });
            }
        });
    });


    // ════════════ 2. ระบบจัดการ "แบบทดสอบ (Quiz) ใหม่" ════════════ //

    const quizTypeAdd = document.getElementById('quizTypeAdd');
    const grpChoiceAdd = document.getElementById('quizChoiceGroupAdd');
    const grpTFAdd = document.getElementById('quizTFGroupAdd');
    const grpEssayAdd = document.getElementById('quizEssayGroupAdd');

    quizTypeAdd?.addEventListener('change', () => {
        grpChoiceAdd.style.display = 'none'; grpTFAdd.style.display = 'none'; grpEssayAdd.style.display = 'none';
        if(quizTypeAdd.value === 'choice') grpChoiceAdd.style.display = 'block';
        else if(quizTypeAdd.value === 'truefalse') grpTFAdd.style.display = 'block';
        else if(quizTypeAdd.value === 'essay') grpEssayAdd.style.display = 'block';
    });

    const quizModalOverlay = document.getElementById('quizModalOverlay');
    document.querySelectorAll('.btn-open-add-quiz').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('quizTargetLessonId').value = btn.dataset.id;
            document.getElementById('quizTargetLessonName').textContent = btn.dataset.name;
            quizModalOverlay.classList.add('open');
        });
    });

    document.getElementById('saveQuizBtn')?.addEventListener('click', (e) => {
        const btn = e.target;
        const lessonId = document.getElementById('quizTargetLessonId').value;
        const type = document.getElementById('quizTypeAdd').value;
        const question = document.getElementById('quizQuestionAdd').value.trim();

        if (!question) { document.getElementById('quizQuestionAdd').style.borderColor = '#ef4444'; return; }

        let chA = '', chB = '', chC = '', chD = '', ans = '';
        if (type === 'choice') {
            chA = document.getElementById('chA_Add').value.trim(); chB = document.getElementById('chB_Add').value.trim();
            chC = document.getElementById('chC_Add').value.trim(); chD = document.getElementById('chD_Add').value.trim();
            ans = document.querySelector('input[name="correctAdd"]:checked')?.value || 'A';
        } else if (type === 'truefalse') {
            ans = document.querySelector('input[name="tfAdd"]:checked')?.value || 'A';
        } else if (type === 'essay') {
            ans = '-';
        }

        btn.textContent = '⏳...'; btn.disabled = true;
        const fd = new FormData();
        fd.append('action', 'add_quiz'); fd.append('lesson_id', lessonId); fd.append('type', type);
        fd.append('question', question); fd.append('choice_a', chA); fd.append('choice_b', chB); 
        fd.append('choice_c', chC); fd.append('choice_d', chD); fd.append('answer', ans);

        fetch('teacher_api.php', { method: 'POST', body: fd }).then(r=>r.json()).then(d => {
            if(d.success) {
                location.reload(); 
            } else { 
                alert(d.message); 
                if(d.message.includes('ล็อกอิน')) window.location.href = 'login.php';
                else { btn.disabled = false; btn.textContent = '➕ เพิ่มคำถาม'; }
            }
        });
    });

    const editQuizModal = document.getElementById('editQuizModal');
    const grpChoiceEdit = document.getElementById('quizChoiceGroupEdit');
    const grpTFEdit = document.getElementById('quizTFGroupEdit');

    document.querySelectorAll('.btn-edit-quiz').forEach(btn => {
        btn.addEventListener('click', () => {
            const type = btn.dataset.type;
            document.getElementById('editQuizIdInput').value = btn.dataset.id;
            document.getElementById('editQuizTypeHidden').value = type;
            document.getElementById('editQuizQuestion').value = btn.dataset.question;

            grpChoiceEdit.style.display = 'none'; grpTFEdit.style.display = 'none';
            const ans = btn.dataset.answer;

            if (type === 'choice') {
                grpChoiceEdit.style.display = 'block';
                document.getElementById('chA_Edit').value = btn.dataset.ca;
                document.getElementById('chB_Edit').value = btn.dataset.cb;
                document.getElementById('chC_Edit').value = btn.dataset.cc;
                document.getElementById('chD_Edit').value = btn.dataset.cd;
                const r = document.querySelector(`input[name="correctEdit"][value="${ans}"]`);
                if(r) r.checked = true;
            } else if (type === 'truefalse') {
                grpTFEdit.style.display = 'block';
                const r = document.querySelector(`input[name="tfEdit"][value="${ans}"]`);
                if(r) r.checked = true;
            } else if (type === 'essay') {
                // ข้อเขียน
            }
            editQuizModal.classList.add('open');
        });
    });

    document.getElementById('saveEditQuizBtn')?.addEventListener('click', (e) => {
        const btn = e.target;
        const type = document.getElementById('editQuizTypeHidden').value;
        let chA = '', chB = '', chC = '', chD = '', ans = '';

        if (type === 'choice') {
            chA = document.getElementById('chA_Edit').value.trim(); chB = document.getElementById('chB_Edit').value.trim();
            chC = document.getElementById('chC_Edit').value.trim(); chD = document.getElementById('chD_Edit').value.trim();
            ans = document.querySelector('input[name="correctEdit"]:checked')?.value || 'A';
        } else if (type === 'truefalse') {
            ans = document.querySelector('input[name="tfEdit"]:checked')?.value || 'A';
        } else if (type === 'essay') {
            ans = '-';
        }

        const fd = new FormData();
        fd.append('action', 'edit_quiz'); fd.append('quiz_id', document.getElementById('editQuizIdInput').value);
        fd.append('type', type); fd.append('question', document.getElementById('editQuizQuestion').value.trim());
        fd.append('choice_a', chA); fd.append('choice_b', chB); fd.append('choice_c', chC); fd.append('choice_d', chD); fd.append('answer', ans);

        btn.textContent = '⏳...'; btn.disabled = true;
        fetch('teacher_api.php', { method: 'POST', body: fd }).then(r=>r.json()).then(d => {
            if(d.success) {
                location.reload(); 
            } else { 
                alert(d.message); 
                if(d.message.includes('ล็อกอิน')) window.location.href = 'login.php';
                else { btn.disabled = false; btn.textContent = '💾 บันทึกการแก้ไข'; }
            }
        });
    });

    document.querySelectorAll('.btn-del-quiz').forEach(btn => {
        btn.addEventListener('click', () => {
            if(confirm('แน่ใจหรือไม่ว่าต้องการลบคำถามนี้ทิ้ง?')) {
                const fd = new FormData(); fd.append('action', 'delete_quiz'); fd.append('quiz_id', btn.dataset.id);
                fetch('teacher_api.php', { method: 'POST', body: fd }).then(r=>r.json()).then(d => {
                    if(d.message && d.message.includes('ล็อกอิน')) { alert(d.message); window.location.href = 'login.php'; }
                    else location.reload();
                });
            }
        });
    });
});

// ฟังก์ชันโชว์ข้อมูลนักเรียนเมื่อคลิกที่ชื่อ
window.showStudentProgress = function(element) {
    const name      = element.getAttribute('data-name');
    const cls       = element.getAttribute('data-class') || '-';
    const score     = parseFloat(element.getAttribute('data-score') || 0);
    const status    = element.getAttribute('data-status') || '';
    const pct       = parseInt(element.getAttribute('data-pct') || 0);
    const completed = parseInt(element.getAttribute('data-completed') || 0);
    const total     = parseInt(element.getAttribute('data-total') || 0);
    const jsonStr   = element.getAttribute('data-json');
    const quizJsonStr = element.getAttribute('data-quiz-json');

    document.getElementById('spm-avatar').textContent = name ? name.charAt(0).toUpperCase() : '?';
    document.getElementById('spm-student-name').textContent = name;
    document.getElementById('spm-class-badge').textContent = cls !== '-' ? 'ระดับชั้น ' + cls : 'ไม่ระบุระดับชั้น';
    document.getElementById('spm-score').textContent = score.toFixed(1);
    document.getElementById('spm-progress-pct').textContent = pct + '%';
    document.getElementById('spm-progress-bar').style.width = pct + '%';
    document.getElementById('spm-lesson-count').textContent = completed + ' / ' + total + ' บทเรียน';

    const statusMap = {
        'excellent':  { label: 'ดีเยี่ยม', bg: 'rgba(16,185,129,.2)',  color: '#10b981' },
        'good':       { label: 'ดี',        bg: 'rgba(96,165,250,.2)',  color: '#60a5fa' },
        'average':    { label: 'ปานกลาง',  bg: 'rgba(251,191,36,.2)',  color: '#fbbf24' },
        'needs-help': { label: 'ต้องดูแล', bg: 'rgba(239,68,68,.2)',   color: '#ef4444' },
    };
    const s = statusMap[status] || { label: '-', bg: 'rgba(255,255,255,.08)', color: '#aaa' };
    const badge = document.getElementById('spm-status-badge');
    badge.textContent = s.label; badge.style.background = s.bg; badge.style.color = s.color;

    // ── Tab: บทเรียนที่เรียนแล้ว ──
    let lessons = [];
    try { lessons = JSON.parse(jsonStr); } catch(e) {}
    const listContainer = document.getElementById('spm-lesson-list');
    if (lessons && lessons.length > 0) {
        listContainer.innerHTML = lessons.map(function(l) {
            return '<div style="display:flex;align-items:center;gap:10px;padding:9px 12px;background:rgba(16,185,129,.07);border:1px solid rgba(16,185,129,.15);border-radius:8px;font-size:13px;color:#d1fae5;"><span>✅</span><span>' + l + '</span></div>';
        }).join('');
    } else {
        listContainer.innerHTML = '<div style="text-align:center;padding:32px 16px;color:var(--text-dim,#aaa);font-size:13px;">📚 ยังไม่ได้เริ่มเรียนบทเรียนใดเลย</div>';
    }

    // ── Tab: ผลการสอบ ──
    let quizData = [];
    try { quizData = JSON.parse(quizJsonStr); } catch(e) {}
    const quizContainer = document.getElementById('spm-quiz-list');

    function getScoreColor(sc) {
        if (sc >= 85) return { color: '#10b981', bg: 'rgba(16,185,129,.12)', label: 'ดีเยี่ยม' };
        if (sc >= 70) return { color: '#60a5fa', bg: 'rgba(96,165,250,.12)', label: 'ดี' };
        if (sc >= 50) return { color: '#fbbf24', bg: 'rgba(251,191,36,.12)', label: 'ปานกลาง' };
        return { color: '#ef4444', bg: 'rgba(239,68,68,.12)', label: 'ต้องปรับปรุง' };
    }

    if (quizData && quizData.length > 0) {
        quizContainer.innerHTML = quizData.map(function(q) {
            const sc = q.score;
            const c = getScoreColor(sc);
            const barW = Math.min(100, Math.max(0, sc)) + '%';
            return `<div style="background:${c.bg};border:1px solid ${c.color}33;border-radius:10px;padding:12px 14px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                    <span style="font-size:13px;color:#fff;font-weight:500;">${q.lesson}</span>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span style="font-size:11px;color:var(--text-dim,#aaa);">ทำ ${q.attempts} ครั้ง</span>
                        <span style="font-size:15px;font-weight:700;color:${c.color};">${sc.toFixed(1)}</span>
                    </div>
                </div>
                <div style="height:5px;background:rgba(255,255,255,0.08);border-radius:99px;overflow:hidden;">
                    <div style="height:100%;width:${barW};background:${c.color};border-radius:99px;transition:width 0.5s ease;"></div>
                </div>
                <div style="margin-top:4px;font-size:10.5px;color:${c.color};">${c.label}</div>
            </div>`;
        }).join('');
    } else {
        quizContainer.innerHTML = '<div style="text-align:center;padding:32px 16px;color:var(--text-dim,#aaa);font-size:13px;">🧪 ยังไม่มีผลการสอบ</div>';
    }

    // Reset to lessons tab
    if (typeof spmSwitchTab === 'function') spmSwitchTab('lessons');

    const modal = document.getElementById('studentProgressModal');
    modal.style.display = 'flex';
    document.getElementById('spmCloseBtn').onclick = function() { modal.style.display = 'none'; };
    modal.addEventListener('click', function(e) { if (e.target === modal) modal.style.display = 'none'; }, { once: true });
};