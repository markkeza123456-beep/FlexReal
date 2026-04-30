(() => {
  let curricula = [];
  let subjects = [];
  let members = [];
  let lessons = [];
  const initialPage = new URLSearchParams(window.location.search).get('page') || 'dashboard';
  let currentPage = initialPage;
  let currentSubjectId = null;

  const pageNames = {
    'dashboard': 'แดชบอร์ด',
    'curriculum-list': 'รายการหลักสูตร',
    'curriculum-add': 'เพิ่ม/แก้ไขหลักสูตร',
    'subject-list': 'รายการรายวิชา',
    'subject-add': 'เพิ่ม/แก้ไขรายวิชา',
    'subject-detail': 'จัดการบทเรียน',
    'lesson-add': 'เพิ่ม/แก้ไขบทเรียน',
    'member-list': 'จัดการผู้ใช้งาน',
    'member-edit': 'แก้ไขข้อมูลสมาชิก',
    'staff-add': 'เพิ่มเจ้าหน้าที่',
  };

  const sidebar = document.getElementById('sidebar');
  const topbarTitle = document.getElementById('topbarTitle');
  const modalOverlay = document.getElementById('modalOverlay');
  const modalConfirm = document.getElementById('modalConfirm');
  const toast = document.getElementById('toast');
  const staffCreateForm = document.getElementById('staffCreateForm');
  const staffUserIdInput = document.getElementById('stf-user-id');

  document.getElementById('menuBtn')?.addEventListener('click', () => sidebar?.classList.toggle('open'));
  document.getElementById('sidebarClose')?.addEventListener('click', () => sidebar?.classList.remove('open'));
  modalOverlay?.addEventListener('click', e => {
    if (e.target === modalOverlay) {
      closeModal();
    }
  });
  document.getElementById('modalCancel')?.addEventListener('click', closeModal);

  function openModal(title, body, onConfirm) {
    document.getElementById('modalTitle').textContent = title;
    document.getElementById('modalBody').textContent = body;
    modalOverlay?.classList.add('show');
    if (modalConfirm) {
      modalConfirm.onclick = () => {
        onConfirm();
        closeModal();
      };
    }
  }

  function closeModal() {
    modalOverlay?.classList.remove('show');
  }

  function showToast(message, type = 'info') {
    if (!toast) {
      return;
    }
    toast.textContent = message;
    toast.className = `toast show ${type}`;
    setTimeout(() => toast.classList.remove('show'), 3000);
  }

  async function fetchJson(url, options = {}) {
    const response = await fetch(url, options);
    const result = await response.json();
    if (!response.ok) {
      throw new Error(result.message || 'Request failed');
    }
    return result;
  }

  window.goTo = function goTo(page) {
    if (!document.getElementById(`page-${page}`)) {
      page = 'dashboard';
    }

    currentPage = page;
    document.querySelectorAll('.page').forEach(section => section.classList.remove('active'));
    document.getElementById(`page-${page}`)?.classList.add('active');

    document.querySelectorAll('.nav-item').forEach(item => {
      item.classList.toggle('active', item.dataset.page === page);
    });

    if (topbarTitle) {
      topbarTitle.textContent = pageNames[page] || '';
    }

    if (page === 'dashboard') {
      updateDashStats();
    }
    if (page === 'curriculum-list') {
      renderCurricula();
    }
    if (page === 'subject-list') {
      renderSubjects();
    }
    if (page === 'member-list') {
      renderMembers();
    }

    sidebar?.classList.remove('open');
    window.scrollTo(0, 0);
  };

  document.querySelectorAll('.nav-item').forEach(item => {
    item.addEventListener('click', e => {
      e.preventDefault();
      goTo(item.dataset.page);
    });
  });

  document.addEventListener('click', e => {
    const gotoTarget = e.target.closest('[data-goto]');
    if (gotoTarget) {
      goTo(gotoTarget.dataset.goto);
    }
  });

  async function loadData() {
    try {
      const data = await fetchJson('api_staff.php?action=getAllData');
      curricula = data.curricula || [];
      subjects = data.subjects || [];
      members = data.members || [];
      updateDashStats();

      if (currentPage === 'curriculum-list') {
        renderCurricula();
      }
      if (currentPage === 'subject-list') {
        renderSubjects();
      }
      if (currentPage === 'member-list') {
        renderMembers();
      }
    } catch (error) {
      showToast(error.message || 'ไม่สามารถโหลดข้อมูลได้', 'error');
    }
  }

  function updateDashStats() {
    const curriculumStat = document.getElementById('stat-curriculum');
    const subjectStat = document.getElementById('stat-subject');
    const memberStat = document.getElementById('stat-student');

    if (curriculumStat) curriculumStat.textContent = String(curricula.length);
    if (subjectStat) subjectStat.textContent = String(subjects.length);
    if (memberStat) memberStat.textContent = String(members.length);
  }

  function renderMembers() {
    const memberBody = document.getElementById('memberBody');
    if (!memberBody) {
      return;
    }

    const roleName = {
      student: 'นักเรียน',
      teacher: 'อาจารย์',
      parent: 'ผู้ปกครอง',
      staff: 'เจ้าหน้าที่',
    };

    memberBody.innerHTML = members.length
      ? members.map(member => `
        <tr>
          <td>${member.firstname} ${member.lastname}</td>
          <td style="color:var(--text-secondary)">${member.email}</td>
          <td><span class="badge ${member.role === 'staff' ? 'required' : 'draft'}">${roleName[member.role] || member.role}</span></td>
          <td>${member.status === 'active' ? '<span class="badge active">ปกติ</span>' : '<span class="badge inactive">ระงับบัญชี</span>'}</td>
          <td>
            <div class="action-btns">
              <button type="button" class="btn-icon edit" onclick="editMember(${member.id})">✎</button>
              <button type="button" class="btn-icon del" onclick="deleteMember(${member.id})">✕</button>
            </div>
          </td>
        </tr>
      `).join('')
      : '<tr><td colspan="5" style="text-align:center; padding:30px;">ไม่พบข้อมูล</td></tr>';
  }

  window.editMember = id => {
    const member = members.find(item => Number(item.id) === Number(id));
    if (!member) {
      return;
    }

    document.getElementById('mf-id').value = member.id;
    document.getElementById('mf-firstname').value = member.firstname;
    document.getElementById('mf-lastname').value = member.lastname;
    document.getElementById('mf-email').value = member.email;
    document.getElementById('mf-role').value = member.role;
    document.getElementById('mf-status').value = member.status;
    goTo('member-edit');
  };

  document.getElementById('memberForm')?.addEventListener('submit', async e => {
    e.preventDefault();

    const formData = new FormData();
    formData.append('action', 'saveMember');
    formData.append('id', document.getElementById('mf-id').value);
    formData.append('firstname', document.getElementById('mf-firstname').value);
    formData.append('lastname', document.getElementById('mf-lastname').value);
    formData.append('email', document.getElementById('mf-email').value);
    formData.append('role', document.getElementById('mf-role').value);
    formData.append('status', document.getElementById('mf-status').value);

    try {
      const result = await fetchJson('api_staff.php', { method: 'POST', body: formData });
      if (result.status === 'success') {
        showToast('บันทึกข้อมูลสมาชิกเรียบร้อย', 'success');
        await loadData();
        goTo('member-list');
      }
    } catch (error) {
      showToast(error.message || 'ไม่สามารถบันทึกข้อมูลสมาชิกได้', 'error');
    }
  });

  window.deleteMember = id => {
    openModal('ลบสมาชิก', 'ยืนยันการลบสมาชิกนี้หรือไม่?', async () => {
      const formData = new FormData();
      formData.append('action', 'deleteMember');
      formData.append('id', id);

      try {
        await fetchJson('api_staff.php', { method: 'POST', body: formData });
        showToast('ลบสมาชิกเรียบร้อย', 'success');
        await loadData();
      } catch (error) {
        showToast(error.message || 'ไม่สามารถลบสมาชิกได้', 'error');
      }
    });
  };

  function renderCurricula() {
    const curriculumBody = document.getElementById('curriculumBody');
    if (!curriculumBody) {
      return;
    }

    curriculumBody.innerHTML = curricula.length
      ? curricula.map(curriculum => `
        <tr>
          <td class="mono">${curriculum.code}</td>
          <td>${curriculum.name}</td>
          <td>${String(curriculum.level || '').toUpperCase()}</td>
          <td><span class="badge ${curriculum.status === 'active' ? 'active' : 'draft'}">${curriculum.status}</span></td>
          <td>
            <div class="action-btns">
              <button type="button" class="btn-icon edit" onclick="editCurriculum(${curriculum.id})">✎</button>
              <button type="button" class="btn-icon del" onclick="deleteCurriculum(${curriculum.id})">✕</button>
            </div>
          </td>
        </tr>
      `).join('')
      : '<tr><td colspan="5" style="text-align:center;">ไม่มีข้อมูลหลักสูตร</td></tr>';
  }

  document.querySelector('[data-goto="curriculum-add"]')?.addEventListener('click', () => {
    document.getElementById('curriculumFormTitle').textContent = 'เพิ่มหลักสูตรใหม่';
    document.getElementById('curriculumForm').reset();
    document.getElementById('cf-id').value = '';
  });

  window.editCurriculum = id => {
    const curriculum = curricula.find(item => Number(item.id) === Number(id));
    if (!curriculum) {
      return;
    }

    document.getElementById('curriculumFormTitle').textContent = 'แก้ไขหลักสูตร';
    document.getElementById('cf-id').value = curriculum.id;
    document.getElementById('cf-code').value = curriculum.code;
    document.getElementById('cf-name').value = curriculum.name;
    document.getElementById('cf-level').value = curriculum.level;
    document.getElementById('cf-year').value = curriculum.year || '';
    document.getElementById('cf-desc').value = curriculum.description || '';
    document.getElementById('cf-status').value = curriculum.status;
    goTo('curriculum-add');
  };

  document.getElementById('curriculumForm')?.addEventListener('submit', async e => {
    e.preventDefault();

    const formData = new FormData();
    formData.append('action', 'saveCurriculum');
    formData.append('id', document.getElementById('cf-id').value);
    formData.append('code', document.getElementById('cf-code').value);
    formData.append('name', document.getElementById('cf-name').value);
    formData.append('level', document.getElementById('cf-level').value);
    formData.append('year', document.getElementById('cf-year').value);
    formData.append('description', document.getElementById('cf-desc').value);
    formData.append('status', document.getElementById('cf-status').value);

    try {
      await fetchJson('api_staff.php', { method: 'POST', body: formData });
      showToast('บันทึกหลักสูตรเรียบร้อย', 'success');
      await loadData();
      goTo('curriculum-list');
    } catch (error) {
      showToast(error.message || 'ไม่สามารถบันทึกหลักสูตรได้', 'error');
    }
  });

  window.deleteCurriculum = id => {
    openModal('ลบหลักสูตร', 'ยืนยันการลบหลักสูตรนี้หรือไม่?', async () => {
      const formData = new FormData();
      formData.append('action', 'deleteCurriculum');
      formData.append('id', id);

      try {
        await fetchJson('api_staff.php', { method: 'POST', body: formData });
        showToast('ลบหลักสูตรเรียบร้อย', 'success');
        await loadData();
      } catch (error) {
        showToast(error.message || 'ไม่สามารถลบหลักสูตรได้', 'error');
      }
    });
  };

  function renderSubjects() {
    const subjectBody = document.getElementById('subjectBody');
    if (!subjectBody) {
      return;
    }

    subjectBody.innerHTML = subjects.length
      ? subjects.map(subject => `
        <tr>
          <td class="mono">${subject.code}</td>
          <td>${subject.name}</td>
          <td>${subject.credit}</td>
          <td><span class="badge ${subject.type === 'required' ? 'required' : 'elective'}">${subject.type === 'required' ? 'บังคับ' : 'เลือก'}</span></td>
          <td>
            <div class="action-btns">
              <button type="button" class="btn-icon" style="color:var(--blue);" onclick="openSubjectEditor(${subject.id}, 'lessons')" title="จัดการบทเรียน">📚</button>
              <button type="button" class="btn-icon edit" onclick="openSubjectEditor(${subject.id}, 'subject')" title="แก้ไขรายวิชา">✎</button>
              <button type="button" class="btn-icon del" onclick="deleteSubject(${subject.id})">✕</button>
            </div>
          </td>
        </tr>
      `).join('')
      : '<tr><td colspan="5" style="text-align:center;">ไม่มีข้อมูลรายวิชา</td></tr>';
  }

  document.querySelector('[data-goto="subject-add"]')?.addEventListener('click', () => {
    document.getElementById('subjectFormTitle').textContent = 'เพิ่มรายวิชาใหม่';
    document.getElementById('subjectForm').reset();
    document.getElementById('sf-id').value = '';
  });

  window.openSubjectEditor = (id, section = 'subject') => {
    window.location.href = `staff_subject_editor.php?subject_id=${encodeURIComponent(id)}&section=${encodeURIComponent(section)}`;
  };

  window.editSubject = id => window.openSubjectEditor(id, 'subject');

  document.getElementById('subjectForm')?.addEventListener('submit', async e => {
    e.preventDefault();

    const formData = new FormData();
    formData.append('action', 'saveSubject');
    formData.append('id', document.getElementById('sf-id').value);
    formData.append('code', document.getElementById('sf-code').value);
    formData.append('name', document.getElementById('sf-name').value);
    formData.append('credit', document.getElementById('sf-credit').value);
    formData.append('type', document.getElementById('sf-type').value);

    try {
      await fetchJson('api_staff.php', { method: 'POST', body: formData });
      showToast('บันทึกรายวิชาเรียบร้อย', 'success');
      await loadData();
      goTo('subject-list');
    } catch (error) {
      showToast(error.message || 'ไม่สามารถบันทึกรายวิชาได้', 'error');
    }
  });

  window.deleteSubject = id => {
    openModal('ลบรายวิชา', 'ยืนยันการลบรายวิชานี้หรือไม่?', async () => {
      const formData = new FormData();
      formData.append('action', 'deleteSubject');
      formData.append('id', id);

      try {
        await fetchJson('api_staff.php', { method: 'POST', body: formData });
        showToast('ลบรายวิชาเรียบร้อย', 'success');
        await loadData();
      } catch (error) {
        showToast(error.message || 'ไม่สามารถลบรายวิชาได้', 'error');
      }
    });
  };

  window.manageLessons = async (subjectId, subjectName) => {
    currentSubjectId = subjectId;
    document.getElementById('detailSubjectTitle').textContent = `วิชา: ${subjectName}`;
    await loadLessons();
    goTo('subject-detail');
  };

  async function loadLessons() {
    if (!currentSubjectId) {
      return;
    }

    try {
      const data = await fetchJson(`api_staff.php?action=getLessons&subject_id=${encodeURIComponent(currentSubjectId)}`);
      lessons = data.lessons || [];
      renderLessons();
    } catch (error) {
      showToast(error.message || 'ไม่สามารถโหลดบทเรียนได้', 'error');
    }
  }

  function renderLessons() {
    const lessonBody = document.getElementById('lessonBody');
    if (!lessonBody) {
      return;
    }

    lessonBody.innerHTML = lessons.length
      ? lessons.map(lesson => `
        <tr>
          <td>${lesson.image_path ? `<img src="${lesson.image_path}" style="height:40px; border-radius:4px; object-fit:cover;">` : '<div style="height:40px;width:60px;background:#222;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:10px;">ไม่มีรูป</div>'}</td>
          <td>${lesson.title}</td>
          <td>${lesson.video_url ? `<a href="${lesson.video_url}" target="_blank" style="color:var(--blue);text-decoration:none;">ลิงก์วิดีโอ</a>` : '-'}</td>
          <td>
            <div class="action-btns">
              <button type="button" class="btn-icon edit" onclick="editLesson(${lesson.id})">✎</button>
              <button type="button" class="btn-icon del" onclick="deleteLesson(${lesson.id})">✕</button>
            </div>
          </td>
        </tr>
      `).join('')
      : '<tr><td colspan="4" style="text-align:center; padding:30px;">ยังไม่มีบทเรียนในวิชานี้</td></tr>';
  }

  window.openAddLesson = () => {
    document.getElementById('lessonFormTitle').textContent = 'เพิ่มบทเรียนใหม่';
    document.getElementById('lessonForm').reset();
    document.getElementById('lf-id').value = '';
    goTo('lesson-add');
  };

  window.editLesson = id => {
    const lesson = lessons.find(item => Number(item.id) === Number(id));
    if (!lesson) {
      return;
    }

    document.getElementById('lessonFormTitle').textContent = 'แก้ไขบทเรียน';
    document.getElementById('lf-id').value = lesson.id;
    document.getElementById('lf-title').value = lesson.title;
    document.getElementById('lf-content').value = lesson.content;
    document.getElementById('lf-video').value = lesson.video_url || '';
    goTo('lesson-add');
  };

  document.getElementById('lessonForm')?.addEventListener('submit', async e => {
    e.preventDefault();

    const formData = new FormData();
    formData.append('action', 'saveLesson');
    formData.append('id', document.getElementById('lf-id').value);
    formData.append('subject_id', currentSubjectId);
    formData.append('title', document.getElementById('lf-title').value);
    formData.append('content', document.getElementById('lf-content').value);
    formData.append('video_url', document.getElementById('lf-video').value);

    const fileInput = document.getElementById('lf-image');
    if (fileInput?.files?.length) {
      formData.append('image', fileInput.files[0]);
    }

    try {
      const result = await fetchJson('api_staff.php', { method: 'POST', body: formData });
      if (result.status === 'success') {
        showToast('บันทึกบทเรียนเรียบร้อย', 'success');
        await loadLessons();
        goTo('subject-detail');
      }
    } catch (error) {
      showToast(error.message || 'ไม่สามารถบันทึกบทเรียนได้', 'error');
    }
  });

  window.deleteLesson = id => {
    openModal('ลบบทเรียน', 'ยืนยันการลบบทเรียนนี้หรือไม่?', async () => {
      const formData = new FormData();
      formData.append('action', 'deleteLesson');
      formData.append('id', id);

      try {
        await fetchJson('api_staff.php', { method: 'POST', body: formData });
        showToast('ลบบทเรียนเรียบร้อย', 'success');
        await loadLessons();
      } catch (error) {
        showToast(error.message || 'ไม่สามารถลบบทเรียนได้', 'error');
      }
    });
  };

  function resetStaffForm() {
    staffCreateForm?.reset();
  }

  staffUserIdInput?.addEventListener('input', () => {
    staffUserIdInput.value = staffUserIdInput.value.replace(/\D+/g, '').slice(0, 13);
  });

  document.getElementById('staffResetBtn')?.addEventListener('click', resetStaffForm);

  staffCreateForm?.addEventListener('submit', async e => {
    e.preventDefault();

    const userId = (staffUserIdInput?.value || '').replace(/\D+/g, '');
    const password = document.getElementById('stf-password').value;
    const passwordConfirm = document.getElementById('stf-password-confirm').value;
    const firstname = document.getElementById('stf-firstname').value.trim();
    const lastname = document.getElementById('stf-lastname').value.trim();

    if (userId.length !== 13) {
      showToast('กรุณากรอกเลขบัตรประชาชน 13 หลัก', 'error');
      staffUserIdInput?.focus();
      return;
    }

    if (password.length < 6) {
      showToast('รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร', 'error');
      document.getElementById('stf-password').focus();
      return;
    }

    if (password !== passwordConfirm) {
      showToast('รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน', 'error');
      document.getElementById('stf-password-confirm').focus();
      return;
    }

    if (!firstname || !lastname) {
      showToast('กรุณากรอกชื่อและนามสกุลให้ครบ', 'error');
      return;
    }

    const formData = new FormData();
    formData.append('action', 'createStaff');
    formData.append('user_id', userId);
    formData.append('password', password);
    formData.append('firstname', firstname);
    formData.append('lastname', lastname);

    try {
      const result = await fetchJson('api_staff.php', { method: 'POST', body: formData });
      if (result.status === 'success') {
        showToast('บันทึกเจ้าหน้าที่เรียบร้อย', 'success');
        resetStaffForm();
      }
    } catch (error) {
      showToast(error.message || 'ไม่สามารถเพิ่มเจ้าหน้าที่ได้', 'error');
    }
  });

  goTo(initialPage);
  loadData();
})();
