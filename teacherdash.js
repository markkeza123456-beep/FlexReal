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


    // ════════════ 1. ระบบจัดการ "บทเรียนย่อย" ════════════ //
    const modalOverlay = document.getElementById('modalOverlay');
    document.getElementById('openModalBtn')?.addEventListener('click', () => modalOverlay.classList.add('open'));
    
    document.getElementById('saveLessonBtn')?.addEventListener('click', (e) => {
        const btn = e.target;
        const lessonName = document.getElementById('lessonNameInput').value.trim();
        const subjectId = document.getElementById('lessonSubjectId').value;

        if (!lessonName) { document.getElementById('lessonNameInput').style.borderColor = '#ef4444'; return; }
        btn.textContent = '⏳...'; btn.disabled = true;

        const fd = new FormData(); fd.append('action', 'add_lesson'); fd.append('lesson_name', lessonName); fd.append('subject_id', subjectId);
        fetch('teacher_api.php', { method: 'POST', body: fd }).then(r=>r.json()).then(d => {
            if(d.success) location.reload(); else { alert(d.message); btn.disabled=false; }
        });
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
            if(d.success) location.reload(); else { alert(d.message); btn.disabled=false; }
        });
    });

    document.querySelectorAll('.btn-del-lsn').forEach(btn => {
        btn.addEventListener('click', () => {
            if(confirm('ต้องการลบบทเรียนนี้ใช่หรือไม่? (ข้อสอบในบทนี้จะถูกลบไปด้วย)')) {
                const fd = new FormData(); fd.append('action', 'delete_lesson'); fd.append('lesson_id', btn.dataset.id);
                fetch('teacher_api.php', { method: 'POST', body: fd }).then(() => location.reload());
            }
        });
    });


    // ════════════ 2. ระบบจัดการ "แบบทดสอบ (Quiz) ใหม่" ════════════ //

    // ควบคุม UI การเลือกประเภทข้อสอบใน Modal เพิ่ม
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

    // เปิด Modal เพิ่มข้อสอบ
    const quizModalOverlay = document.getElementById('quizModalOverlay');
    document.querySelectorAll('.btn-open-add-quiz').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('quizTargetLessonId').value = btn.dataset.id;
            document.getElementById('quizTargetLessonName').textContent = btn.dataset.name;
            quizModalOverlay.classList.add('open');
        });
    });

    // บันทึก เพิ่มข้อสอบ
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
            if(d.success) location.reload(); else { alert(d.message); btn.disabled=false; }
        });
    });

    // เปิด Modal แก้ไขข้อสอบ
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
                // ข้อเขียน ไม่ต้องกรอกตัวเลือก ปล่อยว่าง
            }
            editQuizModal.classList.add('open');
        });
    });

    // บันทึก แก้ไขข้อสอบ
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
            if(d.success) location.reload(); else { alert(d.message); btn.disabled=false; }
        });
    });

    // ลบข้อสอบ
    document.querySelectorAll('.btn-del-quiz').forEach(btn => {
        btn.addEventListener('click', () => {
            if(confirm('แน่ใจหรือไม่ว่าต้องการลบคำถามนี้ทิ้ง?')) {
                const fd = new FormData(); fd.append('action', 'delete_quiz'); fd.append('quiz_id', btn.dataset.id);
                fetch('teacher_api.php', { method: 'POST', body: fd }).then(() => location.reload());
            }
        });
    });
});