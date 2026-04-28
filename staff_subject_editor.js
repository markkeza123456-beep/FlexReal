(() => {
  const params = new URLSearchParams(window.location.search);
  const subjectId = Number(params.get('subject_id') || 0);
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
  const imagePreview = document.getElementById('imagePreview');
  const previewImage = document.getElementById('previewImage');
  const previewLabel = document.getElementById('previewLabel');

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

  function setCurrentImage(path) {
    if (path) {
      previewImage.src = path;
      previewLabel.textContent = 'รูปปัจจุบัน';
      imagePreview.hidden = false;
      return;
    }

    previewImage.removeAttribute('src');
    imagePreview.hidden = true;
  }

  function resetLessonForm() {
    document.getElementById('lessonForm').reset();
    document.getElementById('lessonId').value = '';
    lessonFormTitle.textContent = 'เพิ่มบทเรียนใหม่';
    setCurrentImage('');
  }

  function renderHero() {
    heroTitle.textContent = `${subject.name}`;
    heroSubtitle.textContent = `จัดการข้อมูลรายวิชาและบทเรียนทั้งหมดของ ${subject.name} ได้จากหน้านี้`;
    heroMeta.innerHTML = `
      <span class="meta-pill">รหัสวิชา ${escapeHtml(subject.code)}</span>
      <span class="meta-pill">ประเภท ${escapeHtml(subjectTypeLabel(subject.type))}</span>
      <span class="meta-pill">บทเรียน ${lessons.length} รายการ</span>
    `;

    document.getElementById('statSubjectName').textContent = subject.name;
    document.getElementById('statLessonCount').textContent = `${lessons.length} รายการ`;
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
          <td colspan="4" class="empty-cell">ยังไม่มีบทเรียนในรายวิชานี้</td>
        </tr>
      `;
      return;
    }

    lessonTableBody.innerHTML = lessons.map((lesson) => `
      <tr>
        <td>
          ${lesson.image_path
            ? `<img src="${escapeHtml(lesson.image_path)}" alt="${escapeHtml(lesson.title)}" class="thumb" />`
            : '<div class="thumb-empty">ไม่มีรูป</div>'}
        </td>
        <td>${escapeHtml(lesson.title)}</td>
        <td>
          ${lesson.video_url
            ? `<a class="video-link" href="${escapeHtml(lesson.video_url)}" target="_blank" rel="noopener noreferrer">เปิดลิงก์วิดีโอ</a>`
            : '-'}
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
    if (subjectId <= 0) {
      setErrorState('ไม่พบรหัสรายวิชาที่ต้องการแก้ไข');
      return;
    }

    try {
      const response = await fetch(`api_staff.php?action=getSubjectEditorData&subject_id=${encodeURIComponent(subjectId)}`);
      const data = await response.json();

      if (data.status !== 'success') {
        setErrorState(data.message || 'ไม่สามารถโหลดข้อมูลรายวิชาได้');
        return;
      }

      subject = data.subject;
      lessons = Array.isArray(data.lessons) ? data.lessons : [];

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
      setErrorState('เกิดข้อผิดพลาดระหว่างเชื่อมต่อข้อมูล');
    }
  }

  function findLesson(lessonId) {
    return lessons.find((lesson) => Number(lesson.id) === Number(lessonId)) || null;
  }

  function editLesson(lessonId) {
    const lesson = findLesson(lessonId);
    if (!lesson) {
      showToast('ไม่พบบทเรียนที่ต้องการแก้ไข', 'error');
      return;
    }

    document.getElementById('lessonId').value = lesson.id;
    document.getElementById('lessonTitle').value = lesson.title || '';
    document.getElementById('lessonContent').value = lesson.content || '';
    document.getElementById('lessonVideo').value = lesson.video_url || '';
    lessonFormTitle.textContent = `แก้ไขบทเรียน: ${lesson.title}`;
    setCurrentImage(lesson.image_path || '');
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

    const formData = new FormData();
    formData.append('action', 'saveLesson');
    formData.append('id', document.getElementById('lessonId').value);
    formData.append('subject_id', subjectId);
    formData.append('title', document.getElementById('lessonTitle').value.trim());
    formData.append('content', document.getElementById('lessonContent').value.trim());
    formData.append('video_url', document.getElementById('lessonVideo').value.trim());

    const imageInput = document.getElementById('lessonImage');
    if (imageInput.files[0]) {
      formData.append('image', imageInput.files[0]);
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
