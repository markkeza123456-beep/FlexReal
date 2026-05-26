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
    // หลักสูตรล่าสุด (ทั้งหมด เรียงจากใหม่ไปเก่า)
    const dashCurriculumBody = document.getElementById('dashCurriculumBody');
    if (dashCurriculumBody) {
      const sorted = [...curricula].reverse();
      dashCurriculumBody.innerHTML = sorted.length
        ? sorted.map(c => `
          <tr>
            <td class="mono" style="color:var(--orange);">${c.code || c.id}</td>
            <td>${c.name}</td>
            <td>${c.level || 'ม.ปลาย'}</td>
          </tr>
        `).join('')
        : '<tr><td colspan="3" style="text-align:center;padding:20px;color:var(--text-muted);">ยังไม่มีข้อมูล</td></tr>';
    }

    // รายวิชาล่าสุด (ทั้งหมด เรียงจากใหม่ไปเก่า)
    const dashSubjectBody = document.getElementById('dashSubjectBody');
    if (dashSubjectBody) {
      const sorted = [...subjects].reverse();
      dashSubjectBody.innerHTML = sorted.length
        ? sorted.map(s => `
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

/* ══════════════════════════════════════════════
   STAFF AVATAR — Crop & Upload
   ══════════════════════════════════════════════ */
const _staffCrop = {
  img: null, dragging: false,
  imgX: 0, imgY: 0, imgW: 0, imgH: 0, zoom: 1,
  lastX: 0, lastY: 0,
  canvas: null, ctx: null,
  stage: null, circle: null,
  stageW: 0, stageH: 0, circleSize: 0,
};

function _staffApplyAvatarUI(src) {
  const img  = document.getElementById('staffAvatarImg');
  const text = document.getElementById('staffAvatarInitial');
  if (img)  { img.src = src; img.style.display = 'block'; }
  if (text) { text.style.display = 'none'; }
}

function _staffCropInjectModal() {
  if (document.getElementById('staffCropOverlay')) return;
  document.body.insertAdjacentHTML('beforeend', `
  <div id="staffCropOverlay" style="position:fixed;inset:0;background:rgba(0,0,0,0.6);
       display:flex;align-items:center;justify-content:center;z-index:9999;padding:1rem;box-sizing:border-box">
    <div style="background:#1a1a1a;border:1px solid rgba(255,255,255,0.1);border-radius:16px;
         padding:1.25rem;width:340px;max-width:100%;color:#fff;font-family:'IBM Plex Sans Thai',sans-serif">
      <p style="margin:0 0 1rem;font-size:15px;font-weight:500">✂️ ครอปรูปโปรไฟล์</p>
      <div id="staffCropStage" style="position:relative;width:100%;height:280px;
           background:#0d0d0d;border-radius:10px;overflow:hidden;cursor:grab;user-select:none;touch-action:none">
        <canvas id="staffCropCanvas" style="display:block;width:100%;height:100%"></canvas>
        <div id="staffCropCircle" style="position:absolute;border:2px dashed rgba(255,255,255,0.85);
             border-radius:50%;box-shadow:0 0 0 9999px rgba(0,0,0,0.45);pointer-events:none"></div>
      </div>
      <div style="display:flex;align-items:center;gap:10px;margin-top:10px">
        <span style="font-size:12px;color:#aaa;white-space:nowrap">🔍 ซูม</span>
        <input type="range" id="staffCropZoom" min="100" max="300" value="100" step="1"
               oninput="_staffCropSetZoom(this.value)"
               style="flex:1;accent-color:#ff6b1a">
        <span id="staffCropZoomVal" style="font-size:12px;color:#aaa;min-width:36px">100%</span>
      </div>
      <p style="font-size:11px;color:#555;text-align:center;margin:6px 0 1rem">ลากรูปเพื่อจัดตำแหน่ง · เลื่อนซูมเพื่อขยาย</p>
      <div style="display:flex;gap:8px">
        <button onclick="_staffCropClose()" style="flex:1;padding:9px 0;font-size:13px;border-radius:8px;
            border:1px solid rgba(255,255,255,0.15);background:transparent;color:#fff;cursor:pointer;
            font-family:'IBM Plex Sans Thai',sans-serif">ยกเลิก</button>
        <button onclick="_staffCropConfirm()" style="flex:1;padding:9px 0;font-size:13px;border-radius:8px;
            border:1px solid rgba(255,107,26,0.4);background:rgba(255,107,26,0.15);color:#ff6b1a;
            cursor:pointer;font-family:'IBM Plex Sans Thai',sans-serif;font-weight:500">✓ ใช้รูปนี้</button>
      </div>
    </div>
  </div>`);
  const stage = document.getElementById('staffCropStage');
  stage.addEventListener('mousedown',  _staffCropStartDrag);
  stage.addEventListener('mousemove',  _staffCropOnDrag);
  stage.addEventListener('mouseup',    _staffCropEndDrag);
  stage.addEventListener('mouseleave', _staffCropEndDrag);
  stage.addEventListener('touchstart', _staffCropStartDrag, { passive: false });
  stage.addEventListener('touchmove',  _staffCropOnDrag,    { passive: false });
  stage.addEventListener('touchend',   _staffCropEndDrag);
}

function _staffCropGetXY(e) {
  if (e.touches && e.touches.length) return { x: e.touches[0].clientX, y: e.touches[0].clientY };
  return { x: e.clientX, y: e.clientY };
}
function _staffCropStartDrag(e) {
  _staffCrop.dragging = true;
  const p = _staffCropGetXY(e); _staffCrop.lastX = p.x; _staffCrop.lastY = p.y; e.preventDefault();
}
function _staffCropOnDrag(e) {
  if (!_staffCrop.dragging) return;
  const p = _staffCropGetXY(e);
  _staffCrop.imgX += p.x - _staffCrop.lastX;
  _staffCrop.imgY += p.y - _staffCrop.lastY;
  _staffCrop.lastX = p.x; _staffCrop.lastY = p.y;
  _staffCropDraw(); e.preventDefault();
}
function _staffCropEndDrag() { _staffCrop.dragging = false; }
function _staffCropSetZoom(v) {
  _staffCrop.zoom = v / 100;
  document.getElementById('staffCropZoomVal').textContent = v + '%';
  _staffCropDraw();
}
function _staffCropDraw() {
  const { ctx, img, imgX, imgY, imgW, imgH, zoom, stageW, stageH } = _staffCrop;
  if (!ctx || !img) return;
  ctx.clearRect(0, 0, stageW, stageH);
  const drawW = imgW * zoom, drawH = imgH * zoom;
  ctx.drawImage(img, imgX - (zoom-1)*imgW/2, imgY - (zoom-1)*imgH/2, drawW, drawH);
}
function _staffCropInit() {
  _staffCropInjectModal();
  const stage  = document.getElementById('staffCropStage');
  const canvas = document.getElementById('staffCropCanvas');
  const circle = document.getElementById('staffCropCircle');
  _staffCrop.canvas = canvas; _staffCrop.ctx = canvas.getContext('2d');
  _staffCrop.stage = stage; _staffCrop.circle = circle;
  _staffCrop.stageW = stage.offsetWidth; _staffCrop.stageH = stage.offsetHeight;
  _staffCrop.circleSize = Math.min(_staffCrop.stageW, _staffCrop.stageH) * 0.72;
  canvas.width = _staffCrop.stageW; canvas.height = _staffCrop.stageH;
  const cs = _staffCrop.circleSize;
  circle.style.width = cs + 'px'; circle.style.height = cs + 'px';
  circle.style.left = ((_staffCrop.stageW - cs) / 2) + 'px';
  circle.style.top  = ((_staffCrop.stageH - cs) / 2) + 'px';
  _staffCrop.zoom = 1;
  document.getElementById('staffCropZoom').value = 100;
  document.getElementById('staffCropZoomVal').textContent = '100%';
  const scale = Math.max(cs / _staffCrop.img.width, cs / _staffCrop.img.height);
  _staffCrop.imgW = _staffCrop.img.width  * scale;
  _staffCrop.imgH = _staffCrop.img.height * scale;
  _staffCrop.imgX = (_staffCrop.stageW - _staffCrop.imgW) / 2;
  _staffCrop.imgY = (_staffCrop.stageH - _staffCrop.imgH) / 2;
  _staffCropDraw();
}
function _staffCropClose() {
  const o = document.getElementById('staffCropOverlay');
  if (o) o.style.display = 'none';
}
function _staffCropConfirm() {
  const { img, stageW, stageH, circleSize, imgX, imgY, imgW, imgH, zoom } = _staffCrop;
  if (!img) return;
  const offscreen = document.createElement('canvas');
  offscreen.width = offscreen.height = 300;
  const oc = offscreen.getContext('2d');
  const drawW = imgW * zoom, drawH = imgH * zoom;
  const drawX = imgX - (zoom-1)*imgW/2, drawY = imgY - (zoom-1)*imgH/2;
  const cx = stageW/2, cy = stageH/2, r = circleSize/2;
  const scaleX = img.naturalWidth / drawW, scaleY = img.naturalHeight / drawH;
  const srcX = (cx - r - drawX) * scaleX, srcY = (cy - r - drawY) * scaleY;
  const srcW = circleSize * scaleX,        srcH = circleSize * scaleY;
  oc.save(); oc.beginPath(); oc.arc(150, 150, 150, 0, Math.PI*2); oc.clip();
  oc.drawImage(img, srcX, srcY, srcW, srcH, 0, 0, 300, 300); oc.restore();
  const dataURL = offscreen.toDataURL('image/png');
  _staffApplyAvatarUI(dataURL);
  _staffCropClose();
  fetch('uploadavatar_staff.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    body: JSON.stringify({ image: dataURL })
  }).then(r => r.json())
    .then(d => { if (!d.success) console.warn('อัปโหลดรูปไม่สำเร็จ:', d.message); })
    .catch(err => console.warn('Avatar upload error:', err));
}
function staffPreviewAvatar(input) {
  if (!input.files || !input.files[0]) return;
  const file = input.files[0];
  if (!file.type.startsWith('image/')) return;
  const reader = new FileReader();
  reader.onload = e => {
    const image = new Image();
    image.onload = () => {
      _staffCrop.img = image;
      _staffCropInit();
      document.getElementById('staffCropOverlay').style.display = 'flex';
    };
    image.src = e.target.result;
  };
  reader.readAsDataURL(file);
  input.value = '';
}