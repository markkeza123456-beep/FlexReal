/* ===== teacherdash.js ===== */

document.addEventListener('DOMContentLoaded', () => {
    const MAX_LESSONS_PER_SUBJECT = 3;

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
    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
    }

    async function loadEssaySubmissions() {
        const subjectId = teacherSubjectSelect?.value || '';
        const pendingList = document.getElementById('essayPendingList');
        const reviewedList = document.getElementById('essayReviewedList');
        if (!pendingList || !reviewedList || !subjectId) return;
        pendingList.innerHTML = '<div style="padding:24px;color:var(--text-muted)">กำลังโหลดคำตอบข้อเขียน...</div>';
        reviewedList.innerHTML = '';
        const form = new FormData(); form.append('action', 'get_essay_submissions'); form.append('subject_id', subjectId);
        try {
            const data = await (await fetch('teacher_api.php', { method: 'POST', body: form })).json();
            if (!data.success) throw new Error(data.message || 'โหลดข้อมูลไม่สำเร็จ');
            const rows = data.submissions || [];
            const renderRows = (items, isReviewed) => items.length ? items.map(row => {
                const reviewed = row.review_status !== 'pending';
                const status = row.review_status === 'pass' ? 'ผ่าน' : row.review_status === 'fail' ? 'ไม่ผ่าน' : 'รอตรวจ';
                return `<article style="padding:18px;border:1px solid var(--border);border-radius:12px;background:var(--bg3)">
                    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap"><strong>${escapeHtml(row.student_name)}</strong><span class="badge badge-${reviewed ? (row.review_status === 'pass' ? 'active' : 'needs-help') : 'draft'}">${status}</span></div>
                    <div style="font-size:12px;color:var(--text-muted);margin:5px 0 12px">${escapeHtml(row.lessons_name)} · ส่งเมื่อ ${escapeHtml(row.submitted_at)}</div>
                    <p style="font-weight:600;margin-bottom:7px">คำถาม: ${escapeHtml(row.questions_text)}</p><div style="white-space:pre-wrap;padding:12px;border-radius:8px;background:#fff;border:1px solid var(--border)">${escapeHtml(row.answer_text)}</div>
                    ${isReviewed ? `<p style="margin-top:12px;font-size:13px">ผลตรวจ: <b>${status}</b>${row.teacher_comment ? ` · ${escapeHtml(row.teacher_comment)}` : ''}</p>` : `<div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap"><input class="form-input essay-comment" data-id="${row.submission_id}" placeholder="ความเห็นถึงนักเรียน (ไม่บังคับ)" style="flex:1;min-width:220px"><button class="btn-save essay-review" data-id="${row.submission_id}" data-decision="pass">ผ่าน</button><button class="btn-cancel essay-review" data-id="${row.submission_id}" data-decision="fail">ไม่ผ่าน</button></div>`}
                </article>`;
            }).join('') : `<div style="padding:30px;text-align:center;color:var(--text-muted)">ยังไม่มีข้อเขียน${isReviewed ? 'ที่ตรวจแล้ว' : 'รอตรวจ'}</div>`;
            const pending = rows.filter(row => row.review_status === 'pending');
            const reviewed = rows.filter(row => row.review_status !== 'pending');
            pendingList.innerHTML = renderRows(pending, false);
            reviewedList.innerHTML = renderRows(reviewed, true);
            document.getElementById('essayPendingCount').textContent = pending.length;
            document.getElementById('essayReviewedCount').textContent = reviewed.length;
        } catch (error) { pendingList.innerHTML = `<div style="padding:24px;color:#dc2626">${escapeHtml(error.message || 'โหลดข้อมูลไม่สำเร็จ')}</div>`; }
    }

    document.querySelectorAll('.lesson-tab-btn').forEach(btn => btn.addEventListener('click', () => {
        switchLessonTab(btn.dataset.tab);
        if (btn.dataset.tab === 'essay') loadEssaySubmissions();
    }));

    document.querySelectorAll('.essay-filter-btn').forEach(button => button.addEventListener('click', () => {
        const showReviewed = button.dataset.essayFilter === 'reviewed';
        document.querySelectorAll('.essay-filter-btn').forEach(item => item.classList.toggle('active', item === button));
        document.getElementById('essayPendingList').style.display = showReviewed ? 'none' : 'flex';
        document.getElementById('essayReviewedList').style.display = showReviewed ? 'flex' : 'none';
    }));

    document.getElementById('lessonTab-essay')?.addEventListener('click', async (event) => {
        const button = event.target.closest('.essay-review'); if (!button) return;
        const comment = document.querySelector(`.essay-comment[data-id="${button.dataset.id}"]`)?.value || '';
        button.disabled = true;
        const form = new FormData(); form.append('action', 'review_essay_submission'); form.append('submission_id', button.dataset.id); form.append('decision', button.dataset.decision); form.append('comment', comment);
        try { const data = await (await fetch('teacher_api.php', { method: 'POST', body: form })).json(); if (!data.success) throw new Error(data.message); await loadEssaySubmissions(); } catch (error) { alert(error.message || 'บันทึกผลการตรวจไม่สำเร็จ'); button.disabled = false; }
    });


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

    // Search students on the dashboard as the teacher types.
    const studentSearch = document.getElementById('studentSearch');
    const studentSearchEmpty = document.getElementById('studentSearchEmpty');
    studentSearch?.addEventListener('input', () => {
        const query = studentSearch.value.trim().toLocaleLowerCase('th-TH');
        let matched = 0;
        document.querySelectorAll('.dashboard-student-row').forEach(row => {
            const name = row.querySelector('.student-progress-link')?.textContent?.trim().toLocaleLowerCase('th-TH') || '';
            const visible = !query || name.includes(query);
            row.style.display = visible ? '' : 'none';
            if (visible) matched += 1;
        });
        if (studentSearchEmpty) studentSearchEmpty.style.display = matched ? 'none' : '';
    });

    // โ•โ•โ•โ•โ•โ•โ•โ•โ•โ•โ•โ• 1. เธฃเธฐเธเธเธเธฑเธ”เธเธฒเธฃ "เธเธ—เน€เธฃเธตเธขเธเธขเนเธญเธข" โ•โ•โ•โ•โ•โ•โ•โ•โ•โ•โ•โ• //
    const modalOverlay = document.getElementById('modalOverlay');
    const contentManagerModal = document.getElementById('contentManagerModal');
    let managedLessonId = '';

    async function openContentManager(lessonId = '', lessonName = '') {
        if (!contentManagerModal) return;
        managedLessonId = String(lessonId || '');
        const lessonNameInput = document.getElementById('inlineLessonName');
        const heading = document.getElementById('inlineLessonHeading');
        const count = document.getElementById('inlineLessonCount');
        const documentHint = document.getElementById('inlineLessonDocumentHint');
        const documentInput = document.getElementById('inlineLessonDocument');
        const saveButton = document.getElementById('combinedContentSaveBtn');
        document.getElementById('inlineLessonForm')?.reset();
        resetInlineVideoForm();
        documentInput.required = false;
        lessonNameInput.value = lessonName;
        if (!count.dataset.defaultCount) count.dataset.defaultCount = count.textContent;
        heading.textContent = managedLessonId ? 'แก้ไขบทเรียนย่อย' : 'เพิ่มบทเรียนย่อย';
        count.textContent = managedLessonId ? 'กำลังแก้ไขบทเรียน' : count.dataset.defaultCount;
        documentHint.textContent = managedLessonId ? 'ไม่เลือกไฟล์ใหม่ ระบบจะเก็บเอกสารเดิมไว้' : 'เลือกไฟล์ใหม่เมื่อต้องการเพิ่มเอกสาร';
        saveButton.disabled = false;
        saveButton.textContent = managedLessonId ? ' บันทึกการแก้ไข' : ' บันทึกบทเรียนและวิดีโอ';
        contentManagerModal.classList.add('open');
        if (managedLessonId) {
            try {
                const form = new FormData(); form.append('action', 'get_lesson_content'); form.append('lesson_id', managedLessonId);
                const data = await teacherRequest(form);
                lessonNameInput.value = data.lesson?.title || lessonName;
                const currentDocument = (data.documents || [])[0];
                const currentDocumentIsPdf = currentDocument && /\.pdf$/i.test(currentDocument.url || '');
                documentInput.required = Boolean(currentDocument && !currentDocumentIsPdf);
                documentHint.textContent = documentInput.required
                    ? 'ไฟล์เดิมไม่ใช่ PDF กรุณาเลือกไฟล์ PDF ใหม่'
                    : 'ไม่เลือกไฟล์ใหม่ ระบบจะเก็บเอกสาร PDF เดิมไว้';
            } catch (error) { alert(error.message || 'โหลดข้อมูลบทเรียนไม่สำเร็จ'); }
        }
        await loadInlineVideos();
    }

    document.getElementById('openContentManagerBtn')?.addEventListener('click', () => openContentManager());
    document.getElementById('closeContentManagerBtn')?.addEventListener('click', () => contentManagerModal?.classList.remove('open'));
    contentManagerModal?.addEventListener('click', (event) => { if (event.target === contentManagerModal) contentManagerModal.classList.remove('open'); });
    document.getElementById('openModalBtn')?.addEventListener('click', () => {
        const currentLessonCount = document.querySelectorAll('.btn-edit-lsn').length;
        if (currentLessonCount >= MAX_LESSONS_PER_SUBJECT) {
            alert('รายวิชานี้มีบทเรียนครบ 3 บทแล้ว');
            return;
        }
        modalOverlay.classList.add('open');
    });
    document.getElementById('closeModalBtn')?.addEventListener('click', () => modalOverlay.classList.remove('open'));
    document.getElementById('closeModalBtn2')?.addEventListener('click', () => modalOverlay.classList.remove('open'));
    
    document.getElementById('saveLessonBtn')?.addEventListener('click', (e) => {
        const btn = e.target;
        const lessonName = document.getElementById('lessonNameInput').value.trim();
        const subjectId = document.getElementById('lessonSubjectId').value;
        const lessonDocument = document.getElementById('lessonDocument')?.files?.[0] || null;
        const currentLessonCount = document.querySelectorAll('.btn-edit-lsn').length;

        if (!lessonName) { document.getElementById('lessonNameInput').style.borderColor = '#ef4444'; return; }
        if (currentLessonCount >= MAX_LESSONS_PER_SUBJECT) {
            alert('รายวิชานี้มีบทเรียนครบ 3 บทแล้ว');
            return;
        }
        btn.textContent = 'กำลังบันทึก...'; btn.disabled = true;

        const fd = new FormData();
        fd.append('action', 'add_lesson');
        fd.append('lesson_name', lessonName);
        fd.append('subject_id', subjectId);
        if (lessonDocument) fd.append('lesson_document', lessonDocument);
        fetch('teacher_api.php', { method: 'POST', body: fd }).then(r=>r.json()).then(d => {
            if(d.success) {
                location.reload(); 
            } else { 
                console.error('saveLesson failed:', d);
                alert('บันทึกบทเรียนไม่สำเร็จ กรุณาตรวจสอบข้อมูลแล้วลองใหม่อีกครั้ง'); 
                if (String(d.message || '').includes('ล็อกอิน')) {
                    window.location.href = 'login.php';
                } else {
                    btn.disabled = false;
                    btn.textContent = 'บันทึกบทเรียน';
                }
            }
        }).catch((error) => { console.error('saveLesson request failed:', error); alert('เชื่อมต่อฐานข้อมูลไม่สำเร็จ กรุณาลองใหม่อีกครั้ง'); btn.disabled = false; btn.textContent = 'บันทึกบทเรียน'; });
    });

    document.querySelectorAll('.btn-edit-lsn').forEach(btn => {
        btn.addEventListener('click', () => openContentManager(btn.dataset.id, btn.dataset.name));
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

        btn.textContent = '⏳ กำลังบันทึก...'; btn.disabled = true;
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

        btn.textContent = '⏳ กำลังบันทึก...'; btn.disabled = true;
        fetch('teacher_api.php', { method: 'POST', body: fd }).then(r=>r.json()).then(d => {
            if(d.success) {
                location.reload(); 
            } else { 
                alert(d.message); 
                if(d.message.includes('เธฅเนเธญเธเธญเธดเธ')) window.location.href = 'login.php';
                else { btn.disabled = false; btn.textContent = ' บันทึกการแก้ไข'; }
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

    // Inline lesson + video editor: both forms live on the course page.
    const inlineLessonForm = document.getElementById('inlineLessonForm');
    const inlineVideoForm = document.getElementById('inlineVideoForm');
    const inlineVideoList = document.getElementById('inlineVideoList');
    let inlineVideos = [];

    async function teacherRequest(formData) {
        const response = await fetch('teacher_api.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (!data.success) throw new Error(data.message || 'บันทึกข้อมูลไม่สำเร็จ');
        return data;
    }

    function resetInlineVideoForm() {
        if (!inlineVideoForm) return;
        inlineVideoForm.reset();
        document.getElementById('inlineVideoId').value = '';
        const input = document.getElementById('inlineVideoFile');
        input.required = true;
        document.getElementById('inlineVideoFileRequired').textContent = '*';
        document.getElementById('inlineVideoFileHint').textContent = 'เลือกไฟล์วิดีโอเพื่อบันทึก';
    }

    function editInlineVideo(video) {
        if (!inlineVideoForm || !video) return;
        document.getElementById('inlineVideoId').value = String(video.id || '');
        document.getElementById('inlineVideoTitle').value = video.title || '';
        const input = document.getElementById('inlineVideoFile');
        input.value = '';
        input.required = false;
        document.getElementById('inlineVideoFileRequired').textContent = '';
        document.getElementById('inlineVideoFileHint').textContent = 'เว้นว่างเพื่อใช้ไฟล์วิดีโอเดิม หรือเลือกไฟล์ใหม่เพื่อเปลี่ยน';
        document.getElementById('inlineVideoTitle').focus();
    }

    function renderInlineVideos(data) {
        if (!inlineVideoList) return;
        inlineVideos = data.videos || [];
        const videosForLesson = managedLessonId ? inlineVideos.filter(video => String(video.lessons_id) === managedLessonId) : [];
        inlineVideoList.innerHTML = videosForLesson.length ? videosForLesson.map((video) => `
            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 0;border-bottom:1px solid var(--border)">
                <div style="min-width:0"><strong style="font-size:13px;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"> ${escapeHtml(video.title)}</strong><span style="font-size:12px;color:var(--text-muted)">${escapeHtml(video.lesson_title || '-')}</span></div>
                <div style="display:flex;gap:5px;flex-shrink:0"><button type="button" class="action-icon-btn" data-inline-video-edit="${escapeHtml(video.id)}" title="แก้ไขวิดีโอ">แก้ไข</button><button type="button" class="action-icon-btn" data-inline-video-delete="${escapeHtml(video.id)}" title="ลบวิดีโอ" style="color:#ef4444">ลบ</button></div>
            </div>`).join('') : `<div style="padding:12px 0;color:var(--text-muted);font-size:13px">${managedLessonId ? 'ยังไม่มีวิดีโอในบทเรียนนี้' : 'บันทึกบทเรียนก่อน แล้วจึงเพิ่มวิดีโอได้'}</div>`;
    }

    async function loadInlineVideos() {
        if (!inlineVideoForm || !teacherSubjectSelect?.value) return;
        const form = new FormData();
        form.append('action', 'get_videos'); form.append('subject_id', teacherSubjectSelect.value);
        try { renderInlineVideos(await teacherRequest(form)); }
        catch (error) { inlineVideoList.innerHTML = `<div style="color:#ef4444;font-size:13px">${escapeHtml(error.message)}</div>`; }
    }

    // The single button either creates a lesson with a video or updates the selected lesson.
    document.getElementById('combinedContentSaveBtn')?.addEventListener('click', async () => {
        const saveButton = document.getElementById('combinedContentSaveBtn');
        const lessonName = document.getElementById('inlineLessonName');
        const videoTitle = document.getElementById('inlineVideoTitle');
        const videoFile = document.getElementById('inlineVideoFile');
        const documentInput = document.getElementById('inlineLessonDocument');
        const documentFile = documentInput.files[0];
        const videoId = document.getElementById('inlineVideoId').value;
        const editingVideo = Boolean(videoId);
        const hasVideoInput = Boolean(videoTitle.value.trim() || videoFile.files[0]);
        const completeNewVideo = Boolean(videoTitle.value.trim() && videoFile.files[0]);
        if (!lessonName.value.trim()) { lessonName.focus(); lessonName.reportValidity(); return; }
        if (!managedLessonId && !completeNewVideo) { videoTitle.focus(); alert('กรุณากรอกชื่อและเลือกไฟล์วิดีโอ'); return; }
        if (editingVideo && !videoTitle.value.trim()) { videoTitle.focus(); videoTitle.reportValidity(); return; }
        const incompleteNewVideo = managedLessonId && !editingVideo && hasVideoInput && !completeNewVideo;
        const subjectId = teacherSubjectSelect?.value || '';
        saveButton.disabled = true; saveButton.textContent = 'กำลังบันทึก...';
        try {
            const lessonForm = new FormData();
            lessonForm.append('action', managedLessonId ? 'edit_lesson' : 'add_lesson');
            if (managedLessonId) lessonForm.append('lesson_id', managedLessonId); else lessonForm.append('subject_id', subjectId);
            lessonForm.append('lesson_name', lessonName.value.trim());
            if (managedLessonId && documentInput.required && !documentFile) {
                documentInput.focus();
                alert('กรุณาเลือกไฟล์ PDF ใหม่แทนเอกสารเดิม');
                saveButton.disabled = false;
                saveButton.textContent = ' บันทึกการแก้ไข';
                return;
            }
            if (documentFile) lessonForm.append('lesson_document', documentFile);
            const lessonResult = await teacherRequest(lessonForm);
            const lessonId = managedLessonId || lessonResult.lesson_id;
            if (incompleteNewVideo) {
                alert('บันทึกบทเรียนและ PDF แล้ว แต่ยังไม่ได้บันทึกวิดีโอ เพราะต้องกรอกชื่อและเลือกไฟล์ให้ครบ');
                window.location.reload();
                return;
            }
            if (editingVideo || completeNewVideo) {
                const videoForm = new FormData();
                videoForm.append('action', 'save_video'); videoForm.append('subject_id', subjectId);
                videoForm.append('video_id', videoId); videoForm.append('title', videoTitle.value.trim());
                videoForm.append('lesson_id', lessonId);
                if (videoFile.files[0]) videoForm.append('video_file', videoFile.files[0]);
                await teacherRequest(videoForm);
            }
            window.location.reload();
        } catch (error) { alert(error.message || 'บันทึกเนื้อหาไม่สำเร็จ'); saveButton.disabled = false; saveButton.textContent = ' บันทึกบทเรียนและวิดีโอ'; }
    });

    document.getElementById('inlineVideoClearBtn')?.addEventListener('click', resetInlineVideoForm);
    inlineVideoList?.addEventListener('click', async (event) => {
        const editButton = event.target.closest('[data-inline-video-edit]');
        if (editButton) {
            const video = inlineVideos.find(item => String(item.id) === String(editButton.dataset.inlineVideoEdit));
            editInlineVideo(video);
            return;
        }
        const deleteButton = event.target.closest('[data-inline-video-delete]');
        if (!deleteButton || !window.confirm('ต้องการลบวิดีโอนี้ใช่หรือไม่?')) return;
        const form = new FormData();
        form.append('action', 'delete_video'); form.append('subject_id', teacherSubjectSelect?.value || '');
        form.append('video_id', deleteButton.dataset.inlineVideoDelete);
        try { await teacherRequest(form); await loadInlineVideos(); }
        catch (error) { alert(error.message); }
    });
    loadInlineVideos();
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
                <span style="background:rgba(16,185,129,0.15); padding:4px 6px; border-radius:4px; font-size:11px;"></span>
                <span>${l}</span>
            </div>
        `).join('');
    } else {
        listContainer.innerHTML = `<div style="text-align:center; color:var(--text-muted); font-size:13px; padding:20px;">เธขเธฑเธเนเธกเนเนเธ”เนเน€เธฃเธดเนเธกเน€เธฃเธตเธขเธเธเธ—เน€เธฃเธตเธขเธเนเธ”เน€เธฅเธข</div>`;
    }
    
    document.getElementById('studentProgressModal').classList.add('open');
}
