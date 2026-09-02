(() => {
  const MAX_LESSONS_PER_SUBJECT = 3;
  const params = new URLSearchParams(window.location.search);
  const subjectId = (params.get('subject_id') || '').trim();
  const initialSection = params.get('section') || 'subject';

  let subject = null;
  let lessons = [];

  const heroTitle = document.getElementById('heroTitle');
  const heroSubtitle = document.getElementById('heroSubtitle');
  const heroMeta = document.getElementById('heroMeta');
  const editorContent = document.getElementById('editorContent');
  const errorPanel = document.getElementById('errorPanel');
  const errorMessage = document.getElementById('errorMessage');
  const lessonSection = document.getElementById('lessonSection');
  const lessonEditor = document.getElementById('lessonEditor');
  const lessonTableBody = document.getElementById('lessonTableBody');
  const lessonFormTitle = document.getElementById('lessonFormTitle');
  const documentPreview = document.getElementById('documentPreview');
  const documentPreviewLink = document.getElementById('documentPreviewLink');

  function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = `toast show ${type}`;
    window.clearTimeout(showToast.timer);
    showToast.timer = window.setTimeout(() => {
      toast.classList.remove('show');
    }, 3000);
  }

  function subjectTypeLabel(type) {
    return type === 'elective' ? 'เลือก' : 'บังคับ';
  }

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => {
      const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
      };
      return map[char];
    });
  }

  function setErrorState(message) {
    editorContent.hidden = true;
    lessonSection.hidden = true;
    lessonEditor.hidden = true;
    errorPanel.hidden = false;
    heroTitle.textContent = 'ไม่สามารถเปิดหน้าจัดการรายวิชาได้';
    heroSubtitle.textContent = 'กรุณาตรวจสอบข้อมูลวิชาที่เลือกแล้วลองใหม่อีกครั้ง';
    errorMessage.textContent = message;
  }

  function setResourcePreview(previewNode, linkNode, path, label) {
    if (!previewNode || !linkNode) return;

    if (path) {
      linkNode.href = path;
      linkNode.textContent = label || path.split('/').pop();
      previewNode.hidden = false;
      return;
    }

    linkNode.removeAttribute('href');
    linkNode.textContent = '';
    previewNode.hidden = true;
  }

  function resetLessonForm() {
    document.getElementById('lessonForm').reset();
    document.getElementById('lessonId').value = '';
    lessonFormTitle.textContent = 'เพิ่มบทเรียนใหม่';
    setResourcePreview(documentPreview, documentPreviewLink, '', '');
  }

  function renderHero() {
    heroTitle.textContent = `${subject.name}`;
    heroSubtitle.textContent = `จัดการข้อมูลรายวิชาและบทเรียนทั้งหมดของ ${subject.name} ได้จากหน้านี้`;
    heroMeta.innerHTML = `
      <span class="meta-pill">รหัสวิชา ${escapeHtml(subject.code)}</span>
      <span class="meta-pill">ประเภท ${escapeHtml(subjectTypeLabel(subject.type))}</span>
      <span class="meta-pill">บทเรียน ${lessons.length}/${MAX_LESSONS_PER_SUBJECT} รายการ</span>
    `;

    document.getElementById('manageVideosLink').href = `staff_video_editor.php?subject_id=${encodeURIComponent(subject.id)}`;
    const addLessonBtn = document.getElementById('addLessonBtn');
    if (addLessonBtn) {
      const reachedLimit = lessons.length >= MAX_LESSONS_PER_SUBJECT;
      addLessonBtn.disabled = reachedLimit;
      addLessonBtn.title = reachedLimit ? 'รายวิชานี้มีบทเรียนครบ 3 บทแล้ว' : 'เพิ่มบทเรียนใหม่';
    }
  }

  function renderSubjectForm() {
    document.getElementById('subjectId').value = subject.id;
    document.getElementById('subjectCode').value = subject.code || '';
    document.getElementById('subjectName').value = subject.name || '';
    document.getElementById('subjectCredit').value = subject.credit || 0;
    document.getElementById('subjectType').value = subject.type || 'required';
  }

  function renderLessons() {
    if (!lessons.length) {
      lessonTableBody.innerHTML = `
        <tr>
          <td colspan="3" class="empty-cell">ยังไม่มีบทเรียนในรายวิชานี้</td>
        </tr>
      `;
      return;
    }

    lessonTableBody.innerHTML = lessons.map((lesson) => `
      <tr>
        <td>
          <div class="lesson-title-cell">
            <strong>${escapeHtml(lesson.title)}</strong>
          </div>
        </td>
        <td>
          ${lesson.document_path
            ? `<a class="resource-link document" href="${escapeHtml(lesson.document_path)}" target="_blank" rel="noopener noreferrer">${escapeHtml(lesson.document_name || 'เปิดเอกสาร')}</a>`
            : '<span class="resource-empty">ยังไม่มีเอกสาร</span>'}
        </td>
        <td>
          <div class="action-btns">
            <button type="button" class="btn-icon" data-edit-lesson="${lesson.id}" title="แก้ไขบทเรียน">✎</button>
            <button type="button" class="btn-icon danger" data-delete-lesson="${lesson.id}" title="ลบบทเรียน">✖</button>
          </div>
        </td>
      </tr>
    `).join('');
  }

  function highlightSection(target) {
    [editorContent.firstElementChild, lessonSection, lessonEditor].forEach((node) => {
      node?.classList.remove('is-highlighted');
    });

    const sectionNode = target === 'lessons' ? lessonSection : editorContent.firstElementChild;
    sectionNode?.classList.add('is-highlighted');
  }

  function applyInitialSection() {
    highlightSection(initialSection);

    if (initialSection === 'lessons') {
      lessonSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  async function loadEditorData() {
    if (!subjectId) {
      setErrorState('ไม่พบรหัสรายวิชาที่ต้องการแก้ไข');
      return;
    }

    try {
      const response = await fetch(`api_staff.php?action=getSubjectEditorData&subject_id=${encodeURIComponent(subjectId)}`, {
        credentials: 'same-origin',
      });
      const data = await response.json();

      if (data.status !== 'success') {
        const message = response.status === 403
          ? 'กรุณาเข้าสู่ระบบเจ้าหน้าที่ก่อนจัดการรายวิชา'
          : (data.message || 'ไม่สามารถโหลดข้อมูลรายวิชาได้');
        setErrorState(message);
        return;
      }

      subject = data.subject;
      lessons = Array.isArray(data.lessons) ? data.lessons.slice(0, MAX_LESSONS_PER_SUBJECT) : [];

      editorContent.hidden = false;
      lessonSection.hidden = false;
      lessonEditor.hidden = false;
      errorPanel.hidden = true;
      renderHero();
      renderSubjectForm();
      renderLessons();
      resetLessonForm();
      applyInitialSection();
    } catch (error) {
      setErrorState(error.message || 'เกิดข้อผิดพลาดระหว่างเชื่อมต่อข้อมูล');
    }
  }

  function findLesson(lessonId) {
    const targetId = String(lessonId ?? '').trim();
    return lessons.find((lesson) => String(lesson.id ?? '').trim() === targetId) || null;
  }

  function editLesson(lessonId) {
    const lesson = findLesson(lessonId);
    if (!lesson) {
      showToast('ไม่พบบทเรียนที่ต้องการแก้ไข', 'error');
      return;
    }

    document.getElementById('lessonId').value = lesson.id;
    document.getElementById('lessonTitle').value = lesson.title || '';
    lessonFormTitle.textContent = `แก้ไขบทเรียน: ${lesson.title}`;
    setResourcePreview(
      documentPreview,
      documentPreviewLink,
      lesson.document_path || '',
      lesson.document_name || 'ไฟล์เอกสารปัจจุบัน'
    );
    lessonEditor.classList.add('is-highlighted');
    lessonEditor.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  async function deleteLesson(lessonId) {
    const lesson = findLesson(lessonId);
    if (!lesson) {
      showToast('ไม่พบบทเรียนที่ต้องการลบ', 'error');
      return;
    }

    if (!window.confirm(`ยืนยันการลบบทเรียน "${lesson.title}" ?`)) {
      return;
    }

    const formData = new FormData();
    formData.append('action', 'deleteLesson');
    formData.append('id', lessonId);
    formData.append('subject_id', subjectId);

    try {
      const response = await fetch('api_staff.php', {
        method: 'POST',
        body: formData,
      });
      const result = await response.json();

      if (result.status !== 'success') {
        showToast(result.message || 'ลบบทเรียนไม่สำเร็จ', 'error');
        return;
      }

      showToast('ลบบทเรียนเรียบร้อยแล้ว');
      await loadEditorData();
      lessonSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch (error) {
      showToast('เกิดข้อผิดพลาดระหว่างลบบทเรียน', 'error');
    }
  }

  document.getElementById('subjectForm').addEventListener('submit', async (event) => {
    event.preventDefault();

    const formData = new FormData();
    formData.append('action', 'saveSubject');
    formData.append('id', subjectId);
    formData.append('code', document.getElementById('subjectCode').value.trim());
    formData.append('name', document.getElementById('subjectName').value.trim());
    formData.append('credit', document.getElementById('subjectCredit').value);
    formData.append('type', document.getElementById('subjectType').value);

    try {
      const response = await fetch('api_staff.php', {
        method: 'POST',
        body: formData,
      });
      const result = await response.json();

      if (result.status !== 'success') {
        showToast(result.message || 'บันทึกรายวิชาไม่สำเร็จ', 'error');
        return;
      }

      showToast('บันทึกรายวิชาเรียบร้อยแล้ว');
      await loadEditorData();
      highlightSection('subject');
    } catch (error) {
      showToast('เกิดข้อผิดพลาดระหว่างบันทึกรายวิชา', 'error');
    }
  });

  document.getElementById('lessonForm').addEventListener('submit', async (event) => {
    event.preventDefault();

<<<<<<< Updated upstream
    const lessonId = document.getElementById('lessonId').value.trim();
    if (!lessonId && lessons.length >= MAX_LESSONS_PER_SUBJECT) {
      showToast('รายวิชานี้มีบทเรียนครบ 3 บทแล้ว', 'error');
      return;
    }
=======
    const formData = new FormData();
    formData.append('action', 'saveLesson');
    formData.append('id', document.getElementById('lessonId').value);
    formData.append('subject_id', subjectId);
    formData.append('title', document.getElementById('lessonTitle').value.trim());
>>>>>>> Stashed changes

    const formData = new FormData();
    formData.append('action', 'saveLesson');
    formData.append('id', lessonId);
    formData.append('subject_id', subjectId);
    formData.append('title', document.getElementById('lessonTitle').value.trim());
    const documentInput = document.getElementById('lessonDocument');
    if (documentInput.files[0]) {
      formData.append('document', documentInput.files[0]);
    }


    try {
      const response = await fetch('api_staff.php', {
        method: 'POST',
        body: formData,
      });
      const result = await response.json();

      if (result.status !== 'success') {
        showToast(result.message || 'บันทึกบทเรียนไม่สำเร็จ', 'error');
        return;
      }

      showToast('บันทึกบทเรียนเรียบร้อยแล้ว');
      await loadEditorData();
      lessonSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch (error) {
      showToast('เกิดข้อผิดพลาดระหว่างบันทึกบทเรียน', 'error');
    }
  });

  document.getElementById('addLessonBtn').addEventListener('click', () => {
    if (lessons.length >= MAX_LESSONS_PER_SUBJECT) {
      showToast('รายวิชานี้มีบทเรียนครบ 3 บทแล้ว', 'error');
      return;
    }
    resetLessonForm();
    lessonEditor.classList.add('is-highlighted');
    lessonEditor.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  document.getElementById('cancelLessonBtn').addEventListener('click', () => {
    resetLessonForm();
    lessonEditor.classList.remove('is-highlighted');
  });

  lessonTableBody.addEventListener('click', (event) => {
    const editButton = event.target.closest('[data-edit-lesson]');
    if (editButton) {
      editLesson(editButton.dataset.editLesson);
      return;
    }

    const deleteButton = event.target.closest('[data-delete-lesson]');
    if (deleteButton) {
      deleteLesson(deleteButton.dataset.deleteLesson);
    }
  });

  loadEditorData();
})();
