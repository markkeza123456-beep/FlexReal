/* ===== teacherdash.js ===== */

document.addEventListener('DOMContentLoaded', () => {

    // โ”€โ”€ Sidebar nav โ€“ page view switching โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€
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

    const teacherSubjectSelect = document.getElementById('teacherSubjectSelect');
    teacherSubjectSelect?.addEventListener('change', () => {
        const subjectId = teacherSubjectSelect.value;
        const url = new URL(window.location.href);
        if (subjectId) url.searchParams.set('subject_id', subjectId);
        else url.searchParams.delete('subject_id');
        window.location.href = url.pathname.split('/').pop() + url.search;
    });
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


    // โ”€โ”€ Student search inside detail โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€โ”€
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

    // โ•โ•โ•โ•โ•โ•โ•โ•โ•โ•โ•โ• 1. เธฃเธฐเธเธเธเธฑเธ”เธเธฒเธฃ "เธเธ—เน€เธฃเธตเธขเธเธขเนเธญเธข" โ•โ•โ•โ•โ•โ•โ•โ•โ•โ•โ•โ• //
    const modalOverlay = document.getElementById('modalOverlay');
    document.getElementById('openModalBtn')?.addEventListener('click', () => modalOverlay.classList.add('open'));
    document.getElementById('closeModalBtn')?.addEventListener('click', () => modalOverlay.classList.remove('open'));
    document.getElementById('closeModalBtn2')?.addEventListener('click', () => modalOverlay.classList.remove('open'));
    
    document.getElementById('saveLessonBtn')?.addEventListener('click', (e) => {
        const btn = e.target;
        const lessonName = document.getElementById('lessonNameInput').value.trim();
        const subjectId = document.getElementById('lessonSubjectId').value;

        if (!lessonName) { document.getElementById('lessonNameInput').style.borderColor = '#ef4444'; return; }
        btn.textContent = 'โณ...'; btn.disabled = true;

        const fd = new FormData(); fd.append('action', 'add_lesson'); fd.append('lesson_name', lessonName); fd.append('subject_id', subjectId);
        fetch('teacher_api.php', { method: 'POST', body: fd }).then(r=>r.json()).then(d => {
            if(d.success) {
                location.reload(); 
            } else { 
                alert(d.message); 
                if(d.message.includes('เธฅเนเธญเธเธญเธดเธ')) {
                    window.location.href = 'login.php';
                } else {
                    btn.disabled = false;
                    btn.textContent = '๐’พ เธเธฑเธเธ—เธถเธเธเธ—เน€เธฃเธตเธขเธ';
                }
            }
        }).catch(() => { alert('เน€เธเธทเนเธญเธกเธ•เนเธญเธเธฒเธเธเนเธญเธกเธนเธฅเธฅเนเธกเน€เธซเธฅเธง'); btn.disabled = false; btn.textContent = '๐’พ เธเธฑเธเธ—เธถเธเธเธ—เน€เธฃเธตเธขเธ'; });
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
        btn.textContent = 'โณ...'; btn.disabled = true;
        const fd = new FormData(); fd.append('action', 'edit_lesson'); fd.append('lesson_id', id); fd.append('lesson_name', name);
        fetch('teacher_api.php', { method: 'POST', body: fd }).then(r=>r.json()).then(d => {
            if(d.success) {
                location.reload(); 
            } else { 
                alert(d.message); 
                if(d.message.includes('เธฅเนเธญเธเธญเธดเธ')) window.location.href = 'login.php';
                else { btn.disabled = false; btn.textContent = '๐’พ เธเธฑเธเธ—เธถเธเธเธฒเธฃเนเธเนเนเธ'; }
            }
        });
    });

    document.querySelectorAll('.btn-del-lsn').forEach(btn => {
        btn.addEventListener('click', () => {
            if(confirm('เธ•เนเธญเธเธเธฒเธฃเธฅเธเธเธ—เน€เธฃเธตเธขเธเธเธตเนเนเธเนเธซเธฃเธทเธญเนเธกเน? (เธเนเธญเธชเธญเธเนเธเธเธ—เธเธตเนเธเธฐเธ–เธนเธเธฅเธเนเธเธ”เนเธงเธข)')) {
                const fd = new FormData(); fd.append('action', 'delete_lesson'); fd.append('lesson_id', btn.dataset.id);
                fetch('teacher_api.php', { method: 'POST', body: fd }).then(r=>r.json()).then(d => {
                    if(d.message && d.message.includes('เธฅเนเธญเธเธญเธดเธ')) { alert(d.message); window.location.href = 'login.php'; }
                    else location.reload();
                });
            }
        });
    });


    // โ•โ•โ•โ•โ•โ•โ•โ•โ•โ•โ•โ• 2. เธฃเธฐเธเธเธเธฑเธ”เธเธฒเธฃ "เนเธเธเธ—เธ”เธชเธญเธ (Quiz) เนเธซเธกเน" โ•โ•โ•โ•โ•โ•โ•โ•โ•โ•โ•โ• //

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

        btn.textContent = 'โณ...'; btn.disabled = true;
        const fd = new FormData();
        fd.append('action', 'add_quiz'); fd.append('lesson_id', lessonId); fd.append('type', type);
        fd.append('question', question); fd.append('choice_a', chA); fd.append('choice_b', chB); 
        fd.append('choice_c', chC); fd.append('choice_d', chD); fd.append('answer', ans);

        fetch('teacher_api.php', { method: 'POST', body: fd }).then(r=>r.json()).then(d => {
            if(d.success) {
                location.reload(); 
            } else { 
                alert(d.message); 
                if(d.message.includes('เธฅเนเธญเธเธญเธดเธ')) window.location.href = 'login.php';
                else { btn.disabled = false; btn.textContent = 'โ• เน€เธเธดเนเธกเธเธณเธ–เธฒเธก'; }
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
                // เธเนเธญเน€เธเธตเธขเธ
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

        btn.textContent = 'โณ...'; btn.disabled = true;
        fetch('teacher_api.php', { method: 'POST', body: fd }).then(r=>r.json()).then(d => {
            if(d.success) {
                location.reload(); 
            } else { 
                alert(d.message); 
                if(d.message.includes('เธฅเนเธญเธเธญเธดเธ')) window.location.href = 'login.php';
                else { btn.disabled = false; btn.textContent = '๐’พ เธเธฑเธเธ—เธถเธเธเธฒเธฃเนเธเนเนเธ'; }
            }
        });
    });

    document.querySelectorAll('.btn-del-quiz').forEach(btn => {
        btn.addEventListener('click', () => {
            if(confirm('เนเธเนเนเธเธซเธฃเธทเธญเนเธกเนเธงเนเธฒเธ•เนเธญเธเธเธฒเธฃเธฅเธเธเธณเธ–เธฒเธกเธเธตเนเธ—เธดเนเธ?')) {
                const fd = new FormData(); fd.append('action', 'delete_quiz'); fd.append('quiz_id', btn.dataset.id);
                fetch('teacher_api.php', { method: 'POST', body: fd }).then(r=>r.json()).then(d => {
                    if(d.message && d.message.includes('เธฅเนเธญเธเธญเธดเธ')) { alert(d.message); window.location.href = 'login.php'; }
                    else location.reload();
                });
            }
        });
    });
});

// เธเธฑเธเธเนเธเธฑเธเนเธเธงเนเธเนเธญเธกเธนเธฅเธเธงเธฒเธกเธเธทเธเธซเธเนเธฒเธเธฑเธเน€เธฃเธตเธขเธเน€เธงเธฅเธฒเธเธฅเธดเธเธ—เธตเนเธเธทเนเธญ
window.showStudentProgress = function(element) {
    const name = element.getAttribute('data-name');
    const completed = element.getAttribute('data-completed');
    const total = element.getAttribute('data-total');
    const jsonStr = element.getAttribute('data-json');
    
    document.getElementById('spm-student-name').innerText = name;
    document.getElementById('spm-completed-count').innerText = completed;
    document.getElementById('spm-total-count').innerText = total;
    
    let lessons = [];
    try { lessons = JSON.parse(jsonStr); } catch(e) {}
    
    const listContainer = document.getElementById('spm-lesson-list');
    if (lessons && lessons.length > 0) {
        listContainer.innerHTML = lessons.map(l => `
            <div style="padding: 10px 12px; border-bottom: 1px solid var(--border); color: #10b981; font-size: 13.5px; display:flex; align-items:center; gap:10px;">
                <span style="background:rgba(16,185,129,0.15); padding:4px 6px; border-radius:4px; font-size:11px;">โ…</span> 
                <span>${l}</span>
            </div>
        `).join('');
    } else {
        listContainer.innerHTML = `<div style="text-align:center; color:var(--text-muted); font-size:13px; padding:20px;">เธขเธฑเธเนเธกเนเนเธ”เนเน€เธฃเธดเนเธกเน€เธฃเธตเธขเธเธเธ—เน€เธฃเธตเธขเธเนเธ”เน€เธฅเธข</div>`;
    }
    
    document.getElementById('studentProgressModal').classList.add('open');
}

