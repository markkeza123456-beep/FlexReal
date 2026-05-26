/* ============================================
   NEXORA STAFF PANEL — staffdash.js (ฉบับสมบูรณ์)
   ============================================ */

(() => {
  let curricula = [];
  let subjects = [];
  let members = [];
  let teachers = []; // สำหรับเก็บรายชื่ออาจารย์จากฐานข้อมูล
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
  const subjectTeacherSelect = document.getElementById('sf-teacher'); // ตัวเลือกอาจารย์ผู้ดูแลวิชา

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

  function splitMemberName(fullName) {
    const cleaned = String(fullName || '').trim();
    if (!cleaned || cleaned === '-') {
      return { firstname: '', lastname: '' };
    }

    const parts = cleaned.split(/\s+/).filter(Boolean);
    if (parts.length === 1) {
      return { firstname: parts[0], lastname: '' };
    }

    return {
      firstname: parts[0],
      lastname: parts.slice(1).join(' '),
    };
  }

  function normalizeMember(member) {
    const nameParts = splitMemberName(member.name);

    return {
      ...member,
      name: member.name || '-',
      firstname: member.firstname ?? nameParts.firstname,
      lastname: member.lastname ?? nameParts.lastname,
      email: member.email && member.email !== '-' ? member.email : '',
      role: String(member.role || ''),
      roleValue: String(member.role || '').toLowerCase(),
      status: String(member.status || member.status_account || 'active').toLowerCase(),
    };
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
    if (page === 'subject-add') {
      populateTeacherSelect(); // อัปเดตรายชื่อครูใน Select ทุกครั้งที่เปิดหน้าเพิ่ม/แก้ไขวิชา
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
      members = (data.members || []).map(normalizeMember);
      teachers = data.teachers || []; // รับข้อมูลรายชื่อครูจากฐานข้อมูลหลังบ้าน
      
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

    renderDashboardTables();
  }

  function renderDashboardTables() {
    // หลักสูตรล่าสุด (5 รายการ)
    const dashCurriculumBody = document.getElementById('dashCurriculumBody');
    if (dashCurriculumBody) {
      const latest = curricula.slice(-5).reverse();
      dashCurriculumBody.innerHTML = latest.length
        ? latest.map(c => `
          <tr>
            <td class="mono" style="color:var(--orange);">${c.code || c.id}</td>
            <td>${c.name}</td>
            <td>${c.level || 'ม.ปลาย'}</td>
          </tr>
        `).join('')
        : '<tr><td colspan="3" style="text-align:center;padding:20px;color:var(--text-muted);">ยังไม่มีข้อมูล</td></tr>';
    }

    // รายวิชาล่าสุด (5 รายการ)
    const dashSubjectBody = document.getElementById('dashSubjectBody');
    if (dashSubjectBody) {
      const latest = subjects.slice(-5).reverse();
      dashSubjectBody.innerHTML = latest.length
        ? latest.map(s => `
          <tr>
            <td class="mono" style="color:var(--orange);">${s.code || s.id}</td>
            <td>${s.name}</td>
            <td>${s.credit}</td>
          </tr>
        `).join('')
        : '<tr><td colspan="3" style="text-align:center;padding:20px;color:var(--text-muted);">ยังไม่มีข้อมูล</td></tr>';
    }
  }

  // 💥 ฟังก์ชันโหลดอาจารย์ใส่ใน Select dropdown หน้าจัดการรายวิชา
  function populateTeacherSelect(selectedTeacherId = '') {
    if (!subjectTeacherSelect) {
      return;
    }

    subjectTeacherSelect.innerHTML = `
      <option value="">-- เลือกอาจารย์ผู้ดูแลรายวิชา --</option>
      ${teachers.map(t => `<option value="${t.id}">${t.name}</option>`).join('')}
    `;
    
    // ตั้งค่าอาจารย์คนเดิมในกรณีที่เป็นการแก้ไขข้อมูล
    subjectTeacherSelect.value = selectedTeacherId || '';
  }

  function renderMembers() {
    const memberBody = document.getElementById('memberBody');
    if (!memberBody) return;

    const roleName = {
      'Student': 'นักเรียน',
      'Teacher': 'อาจารย์',
      'Parent': 'ผู้ปกครอง',
      'Staff': 'เจ้าหน้าที่',
    };

    memberBody.innerHTML = members.length
      ? members.map(member => `
        <tr>
          <td>${member.name}</td>
          <td style="color:var(--text-secondary)">${member.email || '-'}</td>
          <td><span class="badge ${member.role === 'Staff' ? 'required' : 'draft'}">${roleName[member.role] || member.role}</span></td>
          <td><span class="badge ${member.status === 'active' ? 'active' : 'draft'}">${member.status === 'inactive' ? 'ระงับบัญชี' : 'ปกติ'}</span></td>
          <td>
            <div class="action-btns">
              <button type="button" class="btn-icon edit" onclick="editMember('${member.id}')">✎</button>
              <button type="button" class="btn-icon del" onclick="deleteMember('${member.id}')">✕</button>
            </div>
          </td>
        </tr>
      `).join('')
      : '<tr><td colspan="5" style="text-align:center; padding:30px;">ไม่พบข้อมูล</td></tr>';
  }

  window.editMember = id => {
    const member = members.find(item => String(item.id) === String(id));
    if (!member) {
      return;
    }

    document.getElementById('mf-id').value = member.id;
    document.getElementById('mf-firstname').value = member.firstname || '';
    document.getElementById('mf-lastname').value = member.lastname || '';
    document.getElementById('mf-email').value = member.email;
    document.getElementById('mf-role').value = member.roleValue || 'student';
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
          <td class="mono">${curriculum.code || curriculum.id}</td>
          <td>${curriculum.name}</td>
          <td>${curriculum.level || 'ม.ปลาย'}</td>
          <td><span class="badge ${curriculum.status === 'active' ? 'active' : 'draft'}">${curriculum.status || 'active'}</span></td>
          <td>
            <div class="action-btns">
              <button type="button" class="btn-icon" style="color:var(--blue); border-color:var(--border);" onclick="manageCurriculumSubjects('${curriculum.id}', '${curriculum.name}')" title="จัดการวิชาเข้าหลักสูตร">📚</button>
              <button type="button" class="btn-icon edit" onclick="editCurriculum('${curriculum.id}')">✎</button>
              <button type="button" class="btn-icon del" onclick="deleteCurriculum('${curriculum.id}')">✕</button>
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
    const curriculum = curricula.find(item => String(item.id) === String(id));
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

    // 💥 อัปเดตตารางแสดงวิชาให้โชว์ "อาจารย์ผู้ดูแลรายวิชา" จากหลังบ้านจริง
    subjectBody.innerHTML = subjects.length
      ? subjects.map(subject => `
        <tr>
          <td class="mono">${subject.code}</td>
          <td>${subject.name}</td>
          <td>${subject.credit}</td>
          <td><span class="badge ${subject.type === 'required' ? 'required' : 'elective'}">${subject.type === 'required' ? 'บังคับ' : 'วิชาเลือก'}</span></td>
          <td style="color:var(--orange); font-weight:500;">${subject.teacher_name || 'ยังไม่มีผู้ดูแล'}</td>
          <td>
            <div class="action-btns">
              <button type="button" class="btn-icon" style="color:var(--blue); border-color:var(--border);" onclick="manageLessons('${subject.id}')" title="จัดการบทเรียน">📚</button>
              <button type="button" class="btn-icon edit" onclick="editSubject('${subject.id}')" title="แก้ไขรายวิชา">✎</button>
              <button type="button" class="btn-icon del" onclick="deleteSubject('${subject.id}')">✕</button>
            </div>
          </td>
        </tr>
      `).join('')
      : '<tr><td colspan="6" style="text-align:center;">ไม่มีข้อมูลรายวิชา</td></tr>';
  }

  document.querySelector('[data-goto="subject-add"]')?.addEventListener('click', () => {
    document.getElementById('subjectFormTitle').textContent = 'เพิ่มรายวิชาใหม่';
    document.getElementById('subjectForm').reset();
    document.getElementById('sf-id').value = '';
    populateTeacherSelect(''); // เคลียร์ค่า Dropdown อาจารย์ให้เป็นค่าเริ่มต้น
  });

  window.editSubject = id => {
    const subject = subjects.find(item => String(item.id) === String(id));
    if (!subject) {
      showToast('ไม่พบข้อมูลรายวิชา', 'error');
      return;
    }

    document.getElementById('subjectFormTitle').textContent = 'แก้ไขรายวิชา';
    document.getElementById('sf-id').value = subject.id || '';
    document.getElementById('sf-code').value = subject.code || '';
    document.getElementById('sf-name').value = subject.name || '';
    document.getElementById('sf-credit').value = subject.credit || 0;
    document.getElementById('sf-type').value = subject.type || 'required';
    
    // ส่ง id ของอาจารย์คนเดิมไปค้างไว้ในฟังก์ชันเลือก Dropdown
    populateTeacherSelect(subject.teachers_id || '');
    goTo('subject-add');
  };

  document.getElementById('subjectForm')?.addEventListener('submit', async e => {
    e.preventDefault();

    const formData = new FormData();
    formData.append('action', 'saveSubject');
    formData.append('id', document.getElementById('sf-id').value);
    formData.append('code', document.getElementById('sf-code').value);
    formData.append('name', document.getElementById('sf-name').value);
    formData.append('credit', document.getElementById('sf-credit').value);
    formData.append('type', document.getElementById('sf-type').value);
    formData.append('teacher_id', subjectTeacherSelect?.value || ''); // 💥 บันทึกรหัสอาจารย์ผู้ดูแลวิชาเข้าฐานข้อมูล

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

  window.manageLessons = (subjectId) => {
    window.location.href = `staff_subject_editor.php?subject_id=${encodeURIComponent(subjectId)}`;
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
              <button type="button" class="btn-icon edit" onclick="editLesson('${lesson.id}')">✎</button>
              <button type="button" class="btn-icon del" onclick="deleteLesson('${lesson.id}')">✕</button>
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
    const lesson = lessons.find(item => String(item.id) === String(id));
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
    openModal('ลบบบทเรียน', 'ยืนยันการลบบทเรียนนี้หรือไม่?', async () => {
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

  // --- ระบบจัดการวิชาเข้าหลักสูตร ---
  window.manageCurriculumSubjects = async (id, name) => {
    document.getElementById('csTitle').textContent = `จัดการวิชา: ${name}`;
    document.getElementById('cs-curriculum-id').value = id;
    
    try {
      const data = await fetchJson(`api_staff.php?action=getCurriculumSubjects&curriculum_id=${encodeURIComponent(id)}`);
      const listContainer = document.getElementById('cs-subject-list');
      
      if (!data.subjects || data.subjects.length === 0) {
         listContainer.innerHTML = '<p style="color:var(--text-secondary); text-align:center; padding: 20px;">ยังไม่มีรายวิชาในระบบ กรุณาไปเพิ่มรายวิชาก่อนครับ</p>';
      } else {
         listContainer.innerHTML = data.subjects.map(sub => {
            const isChecked = Array.isArray(data.selected) && data.selected.includes(sub.id) ? 'checked' : '';
            return `
              <label class="curriculum-subject-row" style="display:flex; align-items:center; gap:10px; cursor:pointer; padding:10px; border-radius:10px; transition:background 0.2s;" onmouseover="this.style.background='rgba(255,107,26,0.05)'" onmouseout="this.style.background='transparent'">
                <input type="checkbox" name="curriculum_subjects[]" value="${sub.id}" ${isChecked} style="width:18px; height:18px; accent-color:var(--orange); cursor:pointer;">
                <span><strong style="color:var(--orange);">${sub.code || sub.id}</strong> - ${sub.name}</span>
              </label>
            `;
         }).join('');
      }
      goTo('curriculum-subjects');
    } catch (error) {
      showToast(error.message || 'ไม่สามารถโหลดข้อมูลรายวิชาได้', 'error');
    }
  };

  document.getElementById('csForm')?.addEventListener('submit', async e => {
    e.preventDefault();
    
    const curriculumId = document.getElementById('cs-curriculum-id').value;
    const checkboxes = document.querySelectorAll('input[name="curriculum_subjects[]"]:checked');
    const selectedIds = Array.from(checkboxes).map(cb => cb.value);
    
    const formData = new FormData();
    formData.append('action', 'saveCurriculumSubjects');
    formData.append('curriculum_id', curriculumId);
    formData.append('subjects', JSON.stringify(selectedIds)); // ส่งเป็น JSON array
    
    try {
      await fetchJson('api_staff.php', { method: 'POST', body: formData });
      showToast('บันทึกรายวิชาเข้าหลักสูตรเรียบร้อย!', 'success');
      goTo('curriculum-list');
    } catch (error) {
      showToast(error.message || 'ไม่สามารถบันทึกข้อมูลได้', 'error');
    }
  });

  goTo(initialPage);
  loadData();
})();