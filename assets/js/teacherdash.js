

document.addEventListener('DOMContentLoaded', () => {
    const MAX_LESSONS_PER_SUBJECT = 3;


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
                const reviewed = row.review_status === 'graded';
                const status = !reviewed ? 'รอตรวจ' : row.result_status === 'passed' ? 'ผ่าน' : 'ไม่ผ่าน';
                const maxScore = Math.round(Number(row.max_score) || 0);
                const score = Math.round(Number(reviewed ? row.score : row.max_score) || 0);
                const reviewDate = row.reviewed_at ? new Date(row.reviewed_at).toLocaleString('th-TH') : '';
                return `<article style="padding:18px;border:1px solid var(--border);border-radius:12px;background:var(--bg3)">
                    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap"><strong>${escapeHtml(row.student_name)}</strong><span class="badge badge-${reviewed ? (row.result_status === 'passed' ? 'active' : 'needs-help') : 'draft'}">${reviewed ? `ตรวจแล้ว · ${status}` : status}</span></div>
                    <div style="font-size:12px;color:var(--text-muted);margin:5px 0 12px">${escapeHtml(row.student_id)} · ${escapeHtml(row.course_name)} · ${escapeHtml(row.lessons_name)} · ข้อ ${escapeHtml(row.question_id)} · ส่งเมื่อ ${escapeHtml(row.submitted_at)}</div>
                    <p style="font-weight:600;margin:12px 0 7px">คำถาม: ${escapeHtml(row.questions_text)}</p><div style="white-space:pre-wrap;overflow-wrap:anywhere;padding:12px;border-radius:8px;background:#fff;border:1px solid var(--border)"><strong>คำตอบของนักเรียน</strong><br>${escapeHtml(row.answer_text || 'ไม่มีข้อความคำตอบ')}</div>
                    <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap;align-items:center"><label>คะแนน <input class="form-input essay-score" data-id="${row.submission_id}" type="number" min="0" max="${maxScore}" step="1" value="${score}" style="width:110px"></label><span>/ ${maxScore}</span><label>ผลสอบ <select class="form-input essay-result-status" data-id="${row.submission_id}"><option value="passed" ${!reviewed || row.result_status === 'passed' ? 'selected' : ''}>ผ่าน</option><option value="failed" ${reviewed && row.result_status === 'failed' ? 'selected' : ''}>ไม่ผ่าน</option></select></label><input class="form-input essay-comment" data-id="${row.submission_id}" value="${escapeHtml(row.teacher_comment || '')}" placeholder="ความเห็นหรือข้อเสนอแนะ" style="flex:1;min-width:220px"><button class="btn-save essay-review" data-id="${row.submission_id}">${reviewed ? 'บันทึกการแก้ไข' : 'บันทึกผลตรวจ'}</button></div>
                    ${reviewed ? `<p style="margin-top:8px;font-size:12px;color:var(--text-muted)">ผู้ตรวจ: ${escapeHtml(row.reviewer_name || row.reviewer_id || '—')} · ตรวจเสร็จ ${escapeHtml(reviewDate)} · ผลสอบ ${status}</p>` : ''}
                </article>`;
            }).join('') : `<div style="padding:30px;text-align:center;color:var(--text-muted)">ยังไม่มีข้อเขียน${isReviewed ? 'ที่ตรวจแล้ว' : 'รอตรวจ'}</div>`;
            const pending = rows.filter(row => row.review_status === 'pending');
            const reviewed = rows.filter(row => row.review_status === 'graded');
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
        showEssayFilter(button.dataset.essayFilter);
    }));

    function showEssayFilter(filter) {
        const showReviewed = filter === 'reviewed';
        document.querySelectorAll('.essay-filter-btn').forEach(item => item.classList.toggle('active', item.dataset.essayFilter === filter));
        document.getElementById('essayPendingList').style.display = showReviewed ? 'none' : 'flex';
        document.getElementById('essayReviewedList').style.display = showReviewed ? 'flex' : 'none';
    }

    document.getElementById('lessonTab-essay')?.addEventListener('click', async (event) => {
        const button = event.target.closest('.essay-review'); if (!button) return;
        const card = button.closest('article');
        const score = card.querySelector(`.essay-score[data-id="${button.dataset.id}"]`)?.value ?? '';
        if (score.trim() === '' || !Number.isInteger(Number(score))) { alert('กรุณากรอกคะแนนเป็นจำนวนเต็ม'); return; }
        const resultStatus = card.querySelector(`.essay-result-status[data-id="${button.dataset.id}"]`)?.value ?? '';
        const comment = card.querySelector(`.essay-comment[data-id="${button.dataset.id}"]`)?.value || '';
        card.querySelectorAll('button, input').forEach(control => { control.disabled = true; });
        const form = new FormData(); form.append('action', 'review_essay_submission'); form.append('submission_id', button.dataset.id); form.append('score', score); form.append('result_status', resultStatus); form.append('comment', comment);
        try { const response = await fetch('teacher_api.php', { method: 'POST', body: form }); const data = await response.json(); if (!response.ok || !data.success) throw new Error(data.message || 'บันทึกผลตรวจไม่สำเร็จ'); alert(data.message || 'บันทึกผลตรวจสำเร็จ'); await loadEssaySubmissions(); showEssayFilter('reviewed'); } catch (error) { alert(error.message || 'บันทึกผลการตรวจไม่สำเร็จ'); card.querySelectorAll('button, input').forEach(control => { control.disabled = false; }); }
    });



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
        const saveButton = document.getElementById('saveInlineLessonBtn');
        document.getElementById('inlineLessonForm')?.reset();
        resetInlineVideoForm();
        documentInput.required = false;
        documentHint.style.display = 'none';
        lessonNameInput.value = lessonName;
        if (!count.dataset.defaultCount) count.dataset.defaultCount = count.textContent;
        heading.textContent = managedLessonId ? 'แก้ไขบทเรียนย่อย' : 'เพิ่มบทเรียนย่อย';
        count.textContent = managedLessonId ? 'กำลังแก้ไขบทเรียน' : count.dataset.defaultCount;
        documentHint.textContent = managedLessonId ? 'ไม่เลือกไฟล์ใหม่ ระบบจะเก็บเอกสารเดิมไว้' : 'เลือกไฟล์ใหม่เมื่อต้องการเพิ่มเอกสาร';
        saveButton.disabled = false;
        saveButton.textContent = managedLessonId ? 'บันทึกการแก้ไข' : 'บันทึกบทเรียน';
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
                documentHint.style.display = documentInput.required ? 'block' : 'none';
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

    document.getElementById('saveLessonBtn')?.addEventListener('click', async (e) => {
        const btn = e.currentTarget;
        if (btn.dataset.saving === '1') return;
        const lessonName = document.getElementById('lessonNameInput').value.trim();
        const subjectId = document.getElementById('lessonSubjectId').value;
        const lessonDocument = document.getElementById('lessonDocument')?.files?.[0] || null;
        const lessonVideo = document.getElementById('lessonVideo')?.files?.[0] || null;
        const currentLessonCount = document.querySelectorAll('.btn-edit-lsn').length;

        if (!lessonName) { document.getElementById('lessonNameInput').style.borderColor = '#ef4444'; document.getElementById('lessonNameInput').focus(); return; }
        if (!subjectId) { alert('ไม่พบรายวิชาที่เลือก กรุณาเลือกหรือเปิดรายวิชาใหม่'); return; }
        if (lessonDocument && !lessonDocument.name.toLowerCase().endsWith('.pdf')) { alert('เอกสารประกอบต้องเป็นไฟล์ PDF'); return; }
        if (lessonVideo && !lessonVideo.name.toLowerCase().endsWith('.mp4')) { alert('วิดีโอบทเรียนต้องเป็นไฟล์ MP4'); return; }
        if (currentLessonCount >= MAX_LESSONS_PER_SUBJECT) {
            alert('รายวิชานี้มีบทเรียนครบ 3 บทแล้ว');
            return;
        }
        btn.dataset.saving = '1'; btn.textContent = 'กำลังบันทึก...'; btn.disabled = true;
        try {
            const fd = new FormData();
            fd.append('action', 'add_lesson');
            fd.append('lesson_name', lessonName);
            fd.append('subject_id', subjectId);
            if (lessonDocument) fd.append('lesson_document', lessonDocument);
            if (lessonVideo) fd.append('lesson_video', lessonVideo);
            const response = await fetch('teacher_api.php', { method: 'POST', body: fd });
            let result;
            try { result = await response.json(); }
            catch { throw new Error(`เซิร์ฟเวอร์ตอบกลับไม่ใช่ JSON (HTTP ${response.status}) ตรวจสอบ PHP error log และการตั้งค่า post_max_size`); }
            if (!response.ok || !result.success) throw new Error(result.message || `บันทึกไม่สำเร็จ (HTTP ${response.status})`);
            alert(result.message || 'บันทึกบทเรียนเรียบร้อยแล้ว');
            window.location.reload();
        } catch (error) {
            console.error('saveLesson failed:', error);
            alert(error.message || 'บันทึกบทเรียนไม่สำเร็จ');
            if (String(error.message || '').includes('ล็อกอิน')) window.location.href = 'login.php';
        } finally {
            btn.dataset.saving = '0'; btn.disabled = false; btn.textContent = 'บันทึกบทเรียน';
        }
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
        const maxScore = document.getElementById('quizMaxScoreAdd').value;

        if (!question) { document.getElementById('quizQuestionAdd').style.borderColor = '#ef4444'; return; }
        if (!Number.isInteger(Number(maxScore)) || Number(maxScore) < 1 || Number(maxScore) > 10000) { alert('กรุณากำหนดคะแนนเต็มเป็นจำนวนเต็มระหว่าง 1 ถึง 10,000'); return; }

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

        const originalButtonText = btn.textContent;
        btn.textContent = '⏳ กำลังบันทึก...'; btn.disabled = true;
        const fd = new FormData();
        fd.append('action', 'add_quiz'); fd.append('lesson_id', lessonId); fd.append('type', type);
        fd.append('question', question); fd.append('choice_a', chA); fd.append('choice_b', chB);
        fd.append('choice_c', chC); fd.append('choice_d', chD); fd.append('answer', ans); fd.append('max_score', maxScore);

        const controller = new AbortController();
        const requestTimeout = window.setTimeout(() => controller.abort(), 30000);
        fetch('teacher_api.php', { method: 'POST', body: fd, signal: controller.signal })
            .then(async response => {
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message || `บันทึกไม่สำเร็จ (HTTP ${response.status})`);
                location.reload();
            })
            .catch(error => {
                console.error('add_quiz failed:', error);
                if (error.name === 'AbortError') alert('ระบบใช้เวลาบันทึกนานเกินไป กรุณาลองอีกครั้ง');
                else alert(error.message || 'บันทึกข้อสอบไม่สำเร็จ กรุณาลองอีกครั้ง');
                if (String(error.message || '').includes('กรุณาล็อกอิน')) window.location.href = 'login.php';
                else { btn.disabled = false; btn.textContent = originalButtonText; }
            })
            .finally(() => window.clearTimeout(requestTimeout));
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
            document.getElementById('editQuizMaxScore').value = btn.dataset.maxScore || '1';

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

            }
            editQuizModal.classList.add('open');
        });
    });

    document.getElementById('saveEditQuizBtn')?.addEventListener('click', (e) => {
        const btn = e.target;
        const type = document.getElementById('editQuizTypeHidden').value;
        const maxScore = document.getElementById('editQuizMaxScore').value;
        let chA = '', chB = '', chC = '', chD = '', ans = '';

        if (!Number.isInteger(Number(maxScore)) || Number(maxScore) < 1 || Number(maxScore) > 10000) { alert('กรุณากำหนดคะแนนเต็มเป็นจำนวนเต็มระหว่าง 1 ถึง 10,000'); return; }

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
        fd.append('choice_a', chA); fd.append('choice_b', chB); fd.append('choice_c', chC); fd.append('choice_d', chD); fd.append('answer', ans); fd.append('max_score', maxScore);

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


    const inlineLessonForm = document.getElementById('inlineLessonForm');
    const inlineVideoForm = document.getElementById('inlineVideoForm');
    const inlineVideoList = document.getElementById('inlineVideoList');
    let inlineVideos = [];

    async function teacherRequest(formData) {
        const response = await fetch('teacher_api.php', { method: 'POST', body: formData });
        let data;
        try { data = await response.json(); }
        catch { throw new Error(`เซิร์ฟเวอร์ตอบกลับไม่ใช่ JSON (HTTP ${response.status}) ตรวจสอบ PHP error log และ post_max_size`); }
        if (!response.ok || !data.success) throw new Error(data.message || `บันทึกข้อมูลไม่สำเร็จ (HTTP ${response.status})`);
        return data;
    }

    function resetInlineVideoForm() {
        if (!inlineVideoForm) return;
        inlineVideoForm.reset();
        document.getElementById('inlineVideoId').value = '';
        const input = document.getElementById('inlineVideoFile');
        input.required = true;
        document.getElementById('inlineVideoFileRequired').textContent = '*';
        document.getElementById('inlineVideoFileHint').textContent = 'เลือกไฟล์ MP4 เพื่อเพิ่มวิดีโอใหม่';
    }

    function formatTeacherEditDate(value) {
        const timestamp = Number(value);
        if (!Number.isFinite(timestamp) || timestamp <= 0) return 'ยังไม่มีข้อมูลวันที่';
        const date = new Date(timestamp * 1000);
        if (Number.isNaN(date.getTime())) return 'ยังไม่มีข้อมูลวันที่';
        return new Intl.DateTimeFormat('th-TH', { timeZone: 'Asia/Bangkok', day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(date);
    }

    function editInlineVideo(video) {
        if (!video) return;
        document.getElementById('inlineVideoId').value = String(video.id || '');
        document.getElementById('inlineVideoTitle').value = video.title || 'วิดีโอบทเรียน';
        const fileInput = document.getElementById('inlineVideoFile');
        fileInput.required = false;
        document.getElementById('inlineVideoFileRequired').textContent = '';
        document.getElementById('inlineVideoFileHint').textContent = 'เลือกไฟล์ใหม่เมื่อต้องการแทนที่วิดีโอเดิม';
    }

    function renderInlineVideos(data) {
        if (!inlineVideoList) return;
        inlineVideos = data.videos || [];
        const videosForLesson = managedLessonId ? inlineVideos.filter(video => String(video.lessons_id) === managedLessonId) : [];
        inlineVideoList.innerHTML = videosForLesson.length ? videosForLesson.map((video) => `
            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 0;border-bottom:1px solid var(--border)">
                <div style="min-width:0"><strong style="font-size:13px;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${escapeHtml(video.title)}</strong><span style="font-size:11px;color:var(--text-muted)">แก้ไขล่าสุด ${formatTeacherEditDate(video.last_modified_epoch)} · โดย ${escapeHtml(video.updated_by_name || 'ยังไม่มีข้อมูล')}</span></div>
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

    async function checkLessonStorageConfig() {
        const button = document.getElementById('checkLessonStorageBtn');
        const output = document.getElementById('lessonStorageStatus');
        if (!button || !output) return;
        button.disabled = true;
        output.textContent = 'กำลังตรวจสอบค่าฝั่งเซิร์ฟเวอร์...';
        try {
            const form = new FormData();
            form.append('action', 'check_storage_config');
            const { status } = await teacherRequest(form);
            const authLabel = status.storage_api_accepted_key === true
                ? 'Storage API ยอมรับคีย์'
                : status.storage_api_accepted_key === false
                    ? `Storage API ไม่ยอมรับคีย์ (HTTP ${status.storage_http_status || 'เชื่อมต่อไม่ได้'})`
                    : 'ยังตรวจสอบ Storage API ไม่ได้';
            output.textContent = `URL ${status.url_configured ? 'พบ' : 'ไม่พบ'} · Service Role Key ${status.service_role_key_configured ? 'พบ' : 'ไม่พบ'} · Bucket ${status.bucket_exists ? 'พบ' : 'ไม่พบ'}${status.bucket_public ? ' (public)' : ''} · ${authLabel} · ${status.message}`;
            output.style.color = status.url_configured && status.service_role_key_configured && status.bucket_exists && status.storage_api_accepted_key
                ? 'var(--text-muted)' : '#b91c1c';
        } catch (error) {
            output.textContent = error.message || 'ตรวจสอบการตั้งค่าไม่สำเร็จ';
            output.style.color = '#b91c1c';
        } finally {
            button.disabled = false;
        }
    }

    async function saveInlineLesson() {
        const saveButton = document.getElementById('saveInlineLessonBtn');
        const lessonName = document.getElementById('inlineLessonName');
        const documentInput = document.getElementById('inlineLessonDocument');
        const documentFile = documentInput.files[0];
        if (!lessonName.value.trim()) { lessonName.focus(); lessonName.reportValidity(); return; }
        saveButton.disabled = true; saveButton.textContent = 'กำลังบันทึก...';
        try {
            const lessonForm = new FormData();
            lessonForm.append('action', managedLessonId ? 'edit_lesson' : 'add_lesson');
            if (managedLessonId) lessonForm.append('lesson_id', managedLessonId);
            else lessonForm.append('subject_id', teacherSubjectSelect?.value || '');
            lessonForm.append('lesson_name', lessonName.value.trim());
            if (managedLessonId && documentInput.required && !documentFile) {
                documentInput.focus();
                alert('กรุณาเลือกไฟล์ PDF ใหม่แทนเอกสารเดิม');
                saveButton.disabled = false;
                saveButton.textContent = ' บันทึกการแก้ไข';
                return;
            }
            if (documentFile) lessonForm.append('lesson_document', documentFile);
            await teacherRequest(lessonForm);
            window.location.reload();
        } catch (error) { alert(error.message || 'บันทึกบทเรียนไม่สำเร็จ'); saveButton.disabled = false; saveButton.textContent = 'บันทึกบทเรียน'; }
    }

    async function saveInlineVideo() {
        const saveButton = document.getElementById('saveInlineVideoBtn');
        const videoTitle = document.getElementById('inlineVideoTitle');
        const videoFile = document.getElementById('inlineVideoFile');
        const videoId = document.getElementById('inlineVideoId').value;
        const editingVideo = Boolean(videoId);
        if (!managedLessonId) { alert('กรุณาบันทึกบทเรียนก่อนเพิ่มวิดีโอ'); return; }
        if (!editingVideo && !videoFile.files[0]) { videoFile.focus(); alert('กรุณาเลือกไฟล์วิดีโอ'); return; }
        saveButton.disabled = true; saveButton.textContent = 'กำลังบันทึก...';
        try {
            const videoForm = new FormData();
            videoForm.append('action', 'save_video');
            videoForm.append('subject_id', teacherSubjectSelect?.value || '');
            videoForm.append('video_id', videoId);
            videoForm.append('title', videoTitle.value.trim() || 'วิดีโอบทเรียน');
            videoForm.append('lesson_id', managedLessonId);
            if (videoFile.files[0]) videoForm.append('video_file', videoFile.files[0]);
            const result = await teacherRequest(videoForm);
            const videoModifiedAt = document.getElementById(`lessonVideoModified-${managedLessonId}`);
            const videoEditorName = result.video.updated_by_name || 'ยังไม่มีข้อมูล';
            if (videoModifiedAt) videoModifiedAt.textContent = `วิดีโอแก้ไขล่าสุด ${formatTeacherEditDate(result.video.last_modified_epoch)} · โดย ${videoEditorName}`;
            const savedVideo = {
                id: String(result.video.id),
                title: videoTitle.value.trim() || 'วิดีโอบทเรียน',
                lessons_id: managedLessonId,
                last_modified_epoch: result.video.last_modified_epoch,
                updated_by_name: videoEditorName,
            };
            const existingIndex = inlineVideos.findIndex(video => String(video.id) === savedVideo.id);
            if (existingIndex >= 0) inlineVideos[existingIndex] = { ...inlineVideos[existingIndex], ...savedVideo };
            else inlineVideos.unshift(savedVideo);
            document.getElementById('inlineVideoId').value = savedVideo.id;
            renderInlineVideos({ videos: inlineVideos });
            document.getElementById('inlineVideoFileHint').textContent = result.message || 'อัปโหลดวิดีโอและบันทึกสำเร็จ';
            saveButton.disabled = false;
            saveButton.textContent = 'บันทึกวิดีโอ';
        } catch (error) { alert(error.message || 'บันทึกวิดีโอไม่สำเร็จ'); saveButton.disabled = false; saveButton.textContent = 'บันทึกวิดีโอ'; }
    }

    document.getElementById('saveInlineLessonBtn')?.addEventListener('click', saveInlineLesson);
    document.getElementById('saveInlineVideoBtn')?.addEventListener('click', saveInlineVideo);
    document.getElementById('checkLessonStorageBtn')?.addEventListener('click', checkLessonStorageConfig);

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
