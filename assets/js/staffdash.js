

(() => {
  let curricula = [];
  let subjects = [];
  let curriculumSubjects = [];
  let curriculumIssues = [];
  let members = [];
  let teachers = [];
  let lessons = [];
  let memberSearchTerm = '';
  const initialPage = new URLSearchParams(window.location.search).get('page') || 'dashboard';
  let currentPage = initialPage;
  let currentSubjectId = null;
  let graduationRequests = [];
  let graduationRequestFilter = 'pending';

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
    'staff-add': 'เพิ่มเจ้าหน้าที่ / ผู้บริหาร',
    'graduation-requests': 'อนุมัติจบการศึกษา',
    'profile': 'โปรไฟล์ของฉัน',
  };
  let graduationReviewCsrf = '';

  const sidebar = document.getElementById('sidebar');
  const topbarTitle = document.getElementById('topbarTitle');
  const modalOverlay = document.getElementById('modalOverlay');
  const modalConfirm = document.getElementById('modalConfirm');
  const toast = document.getElementById('toast');
  const staffCreateForm = document.getElementById('staffCreateForm');
  const staffUserIdInput = document.getElementById('stf-user-id');
  const subjectTeacherSelect = document.getElementById('sf-teacher');
  const selectedSubjectTeachers = document.getElementById('sf-selected-teachers');
  let selectedSubjectTeacherIds = [];

  document.getElementById('memberSearch')?.addEventListener('input', event => {
    memberSearchTerm = event.target.value.trim().toLocaleLowerCase();
    if (currentPage === 'member-list') {
      renderMembers();
    }
  });
  document.getElementById('memberStatusFilter')?.addEventListener('change', () => {
    if (currentPage === 'member-list') renderMembers();
  });

  document.getElementById('menuBtn')?.addEventListener('click', (event) => {
    sidebar?.classList.toggle('open');
    const isOpen = sidebar?.classList.contains('open') || false;
    event.currentTarget.setAttribute('aria-expanded', String(isOpen));
    event.currentTarget.setAttribute('aria-label', isOpen ? 'ปิดเมนู' : 'เปิดเมนู');
  });
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
    setTimeout(() => toast.classList.remove('show'), type === 'error' ? 10000 : 3000);
  }

  async function fetchJson(url, options = {}) {
    const response = await fetch(url, options);
    const result = await response.json();
    if (!response.ok) {
      const error = new Error(result.message || 'Request failed');
      error.payload = result;
      throw error;
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

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]);
  }

  function normalizeMember(member) {
    const nameParts = splitMemberName(member.name);

    return {
      ...member,
      name: member.name || '-',
      firstname: member.firstname ?? nameParts.firstname,
      lastname: member.lastname ?? nameParts.lastname,
      email: member.email && member.email !== '-' ? member.email : '',
      phone: member.phone && member.phone !== '-' ? String(member.phone) : '',
      role: String(member.role || ''),
      roleValue: String(member.role || '').toLowerCase(),
      status: String(member.status || member.status_account || 'active').toLowerCase(),
    };
  }

  function formatPlainNumber(value) {
    const numeric = Number(value);
    if (!Number.isFinite(numeric)) {
      return '0';
    }
    return String(Math.trunc(numeric));
  }

  function formatRegistrationDate(value) {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '-';
    return new Intl.DateTimeFormat('th-TH', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      hourCycle: 'h23',
      timeZone: 'Asia/Bangkok',
    }).format(date) + ' น.';
  }

  window.goTo = function goTo(page) {
    if (!document.getElementById(`page-${page}`)) {
      page = 'dashboard';
    }

    if (page === 'profile' && currentPage !== 'profile') window._staffProfileReturnPage = currentPage;
    currentPage = page;
    document.querySelector('.main-wrap')?.classList.toggle('curriculum-add-active', page === 'curriculum-add');
    if (page === 'profile') document.querySelector('main.content')?.scrollTo({ top: 0, behavior: 'auto' });
    document.querySelectorAll('.page').forEach(section => section.classList.remove('active'));
    document.getElementById(`page-${page}`)?.classList.add('active');

    document.querySelectorAll('.nav-item').forEach(item => {
      item.classList.toggle('active', item.dataset.page === page);
    });
    document.querySelector('.sidebar-footer.staff-profile-trigger')?.classList.toggle('active', page === 'profile');

    if (topbarTitle) {
      topbarTitle.textContent = pageNames[page] || '';
      topbarTitle.hidden = page === 'profile' || page === 'dashboard' || page === 'curriculum-add' || page === 'curriculum-list' || page === 'subject-list' || page === 'subject-add' || page === 'member-list' || page === 'staff-add' || page === 'graduation-requests';
    }
    const pageUrl = new URL(window.location.href);
    pageUrl.searchParams.set('page', page);
    window.history.replaceState({ page }, '', pageUrl);

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
      populateTeacherSelect(selectedSubjectTeacherIds);
    }
    if (page === 'graduation-requests') {
      loadGraduationRequests();
    }

    sidebar?.classList.remove('open');
    window.scrollTo(0, 0);
  };

  document.querySelectorAll('.nav-item').forEach(item => {
    if (!item.dataset.page) return;
    item.addEventListener('click', e => {
      e.preventDefault();
      goTo(item.dataset.page);
    });
  });

  document.getElementById('graduationRequestsList')?.addEventListener('click', async event => {
    const button = event.target.closest('[data-approve-graduation], [data-retry-graduation], [data-regenerate-graduation]');
    if (!button || button.disabled) return;
    const isRetry = button.hasAttribute('data-retry-graduation');
    const isRegenerate = button.hasAttribute('data-regenerate-graduation');
    const requestId = isRetry ? button.dataset.retryGraduation : isRegenerate ? button.dataset.regenerateGraduation : button.dataset.approveGraduation;
    const confirmMessage = isRetry
      ? 'ยืนยันลองสร้างและอัปโหลด PDF สำหรับคำขอที่อนุมัติแล้วอีกครั้ง?'
      : isRegenerate
        ? 'ยืนยันสร้างใบสรุปผลการเรียน PDF ฉบับใหม่แทนไฟล์เดิม?'
        : 'ยืนยันอนุมัติจบการศึกษาและออกใบสรุปผลการเรียน PDF?';
    if (!window.confirm(confirmMessage)) return;
    button.disabled = true;
    const formData = new FormData();
    formData.append('action', isRetry ? 'retry_pdf' : isRegenerate ? 'regenerate_pdf' : 'approve');
    formData.append('request_id', requestId);
    formData.append('csrf_token', graduationReviewCsrf);
    try {
      const result = await fetchJson('staff_graduation_api.php', { method: 'POST', credentials: 'same-origin', body: formData });
      showToast(result.message || 'อนุมัติเรียบร้อย', 'success');
      if (!isRetry && !isRegenerate) {
        graduationRequestFilter = 'approved';
        document.querySelectorAll('[data-graduation-filter]').forEach(tab => tab.classList.toggle('active', tab.dataset.graduationFilter === 'approved'));
      }
      await loadGraduationRequests();
    } catch (error) {
      if (error.payload?.approved) {
        await loadGraduationRequests();
      } else {
        button.disabled = false;
      }
      showToast(error.message || 'อนุมัติไม่สำเร็จ', 'error');
    }
  });

  document.querySelectorAll('[data-graduation-filter]').forEach(button => {
    button.addEventListener('click', () => {
      graduationRequestFilter = button.dataset.graduationFilter || 'pending';
      document.querySelectorAll('[data-graduation-filter]').forEach(tab => tab.classList.toggle('active', tab === button));
      renderGraduationRequests();
    });
  });
  document.getElementById('graduationRequestSearch')?.addEventListener('input', renderGraduationRequests);

  async function loadGraduationRequests() {
    const list = document.getElementById('graduationRequestsList');
    const message = document.getElementById('graduationRequestsMessage');
    if (!list || !message) return;
    message.hidden = false;
    message.textContent = 'กำลังโหลดคำขอ...';
    try {
      const result = await fetchJson('staff_graduation_api.php', { credentials: 'same-origin' });
      graduationReviewCsrf = String(result.csrf_token || '');
      graduationRequests = Array.isArray(result.requests) ? result.requests : [];
      renderGraduationRequests();
    } catch (error) {
      message.hidden = false;
      message.textContent = error.message || 'ไม่สามารถโหลดคำขอได้';
      list.innerHTML = '';
    }
  }

  function renderGraduationRequests() {
    const list = document.getElementById('graduationRequestsList');
    const message = document.getElementById('graduationRequestsMessage');
    if (!list || !message) return;
    const pendingCount = graduationRequests.filter(request => request.status === 'pending').length;
    const approvedCount = graduationRequests.filter(request => request.status === 'approved').length;
    const pendingCountElement = document.getElementById('graduationPendingCount');
    const approvedCountElement = document.getElementById('graduationApprovedCount');
    if (pendingCountElement) pendingCountElement.textContent = String(pendingCount);
    if (approvedCountElement) approvedCountElement.textContent = String(approvedCount);
    const query = (document.getElementById('graduationRequestSearch')?.value || '').trim().toLocaleLowerCase();
    const requests = graduationRequests.filter(request => {
      if (!query && request.status !== graduationRequestFilter) return false;
      if (!query) return true;
      return `${request.student_name || ''} ${request.student_id || ''}`.toLocaleLowerCase().includes(query);
    });
    message.hidden = requests.length > 0;
    message.textContent = requests.length ? '' : graduationRequests.length
      ? (query ? 'ไม่พบคำขอที่ตรงกับคำค้นหา' : graduationRequestFilter === 'pending' ? 'ไม่มีคำขอที่รออนุมัติ' : 'ยังไม่มีคำขอที่อนุมัติแล้ว')
      : 'ไม่มีคำขอจบการศึกษา';
    list.innerHTML = requests.map(request => {
        const courses = Array.isArray(request.courses) ? request.courses : [];
        const rows = courses.map(course => `<tr>
          <td class="mono">${escapeHtml(course.code || '-')}</td>
          <td>${escapeHtml(course.name || '-')}</td>
          <td>${escapeHtml(course.credits ?? 0)}</td>
          <td>${Number(course.transferred_lessons || 0) > 0 ? `<span class="graduation-grade-tr">TR · เทียบโอน (${escapeHtml(course.transfer_summary)})</span>` : escapeHtml(course.grade_label || '-')}</td>
        </tr>`).join('');
        const eligible = Boolean(request.eligible);
        const approved = request.status === 'approved';
        const hasPdf = Boolean(request.document_bucket && request.document_object_path);
        const retryPdf = approved && !hasPdf;
        const regeneratePdf = approved && hasPdf;
        const actionAttribute = regeneratePdf
          ? `data-regenerate-graduation="${escapeHtml(request.request_id)}"`
          : retryPdf
            ? `data-retry-graduation="${escapeHtml(request.request_id)}"`
            : `data-approve-graduation="${escapeHtml(request.request_id)}"`;
        const approvalLabel = approved
          ? `<span class="graduation-status-badge is-approved">อนุมัติแล้ว${hasPdf ? '' : ' · PDF ยังไม่พร้อม'}</span>`
          : '<span class="graduation-status-badge is-pending">รออนุมัติ</span>';
        const actionLabel = regeneratePdf
          ? 'สร้าง PDF ฉบับใหม่'
          : retryPdf ? 'ลองออก PDF อีกครั้ง' : 'อนุมัติและออก PDF';
        return `<article class="form-card graduation-request-card">
          <div class="graduation-request-heading"><div><h2>${escapeHtml(request.student_name || 'นักเรียน')}</h2><p>รหัส ${escapeHtml(request.student_id)} · ส่งเมื่อ ${escapeHtml(formatRegistrationDate(request.submitted_at))} · ${approvalLabel}</p></div><strong>${Number(request.total_credits || 0).toLocaleString('th-TH')} หน่วยกิต</strong></div>
          <div class="table-wrap"><table class="data-table"><thead><tr><th>รหัสวิชา</th><th>รายวิชา</th><th>หน่วยกิต</th><th>ผลการเรียน</th></tr></thead><tbody>${rows || '<tr><td colspan="4">ไม่พบรายวิชา</td></tr>'}</tbody></table></div>
          <div class="graduation-request-actions">${regeneratePdf ? '<span>ไฟล์ปัจจุบันสร้างไว้แล้ว สามารถสร้างใหม่ตามแบบล่าสุดได้</span>' : retryPdf ? '<span class="graduation-ineligible">อนุมัติแล้ว แต่ไฟล์ PDF ยังไม่พร้อม</span>' : eligible ? '<span>ตรวจสอบครบทุกวิชาแล้ว</span>' : '<span class="graduation-ineligible">ยังมีวิชาที่ไม่ผ่าน จึงอนุมัติไม่ได้</span>'}<button type="button" class="btn-primary" ${actionAttribute} ${eligible || approved ? '' : 'disabled'}>${actionLabel}</button></div>
        </article>`;
    }).join('');
  }

  window.addEventListener('popstate', () => {
    goTo(new URLSearchParams(window.location.search).get('page') || 'dashboard');
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
      curriculumSubjects = data.curriculum_subjects || [];
      curriculumIssues = data.curriculum_issues || [];
      members = (data.members || []).map(normalizeMember);
      teachers = data.teachers || [];
      if (currentPage === 'subject-add') {
        populateTeacherSelect(selectedSubjectTeacherIds);
      }

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
      renderStaffList();
    } catch (error) {
      showToast(error.message || 'ไม่สามารถโหลดข้อมูลได้', 'error');
    }
  }

  function updateDashStats() {
    const curriculumStat = document.getElementById('stat-curriculum');
    const subjectStat = document.getElementById('stat-subject');
    const memberStat = document.getElementById('stat-student');

    if (curriculumStat) curriculumStat.textContent = formatPlainNumber(curricula.length);
    if (subjectStat) subjectStat.textContent = formatPlainNumber(subjects.length);
    if (memberStat) memberStat.textContent = formatPlainNumber(members.length);

    renderDashboardTables();
  }

  function renderDashboardTables() {

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


    const dashSubjectBody = document.getElementById('dashSubjectBody');
    if (dashSubjectBody) {
      const sorted = [...subjects].reverse();
      dashSubjectBody.innerHTML = sorted.length
        ? sorted.map(s => `
          <tr>
            <td class="mono" style="color:var(--orange);">${s.code || s.id}</td>
            <td>${s.name}</td>
            <td>${formatPlainNumber(s.credit)}</td>
          </tr>
        `).join('')
        : '<tr><td colspan="3" style="text-align:center;padding:20px;color:var(--text-muted);">ยังไม่มีข้อมูล</td></tr>';
    }
  }


  function populateTeacherSelect(selectedTeacherIds = []) {
    if (!subjectTeacherSelect) {
      return;
    }

    selectedSubjectTeacherIds = [...new Set(
      (Array.isArray(selectedTeacherIds) ? selectedTeacherIds : String(selectedTeacherIds || '').split(','))
        .map(id => String(id).trim())
        .filter(Boolean)
    )];
    subjectTeacherSelect.innerHTML = `
      <option value="">${teachers.length ? '-- เลือกอาจารย์ผู้สอน --' : 'ยังไม่มีรายชื่ออาจารย์ในระบบ'}</option>
      ${teachers.map(t => `<option value="${escapeHtml(t.id)}">${escapeHtml(t.name)}</option>`).join('')}
    `;
    renderSelectedSubjectTeachers();
  }

  function renderSelectedSubjectTeachers() {
    if (!selectedSubjectTeachers) return;
    selectedSubjectTeachers.innerHTML = selectedSubjectTeacherIds.map(id => {
      const teacher = teachers.find(item => String(item.id) === String(id));
      if (!teacher) return '';
      return `<span style="display:inline-flex;align-items:center;gap:7px;padding:6px 10px;border:1px solid var(--border);border-radius:999px;background:#fff7f2;color:var(--text);">${teacher.name}<button type="button" data-remove-teacher="${teacher.id}" aria-label="ลบ ${teacher.name}" style="border:0;background:transparent;color:var(--orange);font-size:18px;line-height:1;cursor:pointer;">×</button></span>`;
    }).join('') || '<span style="color:var(--text-secondary);font-size:13px;">ยังไม่ได้เลือกอาจารย์ผู้สอน</span>';
  }

  function addSelectedSubjectTeacher() {
    const teacherId = subjectTeacherSelect?.value || '';
    if (!teacherId || selectedSubjectTeacherIds.includes(teacherId)) return false;
    selectedSubjectTeacherIds.push(teacherId);
    subjectTeacherSelect.value = '';
    renderSelectedSubjectTeachers();
    return true;
  }


  subjectTeacherSelect?.addEventListener('change', addSelectedSubjectTeacher);

  selectedSubjectTeachers?.addEventListener('click', event => {
    const button = event.target.closest('[data-remove-teacher]');
    if (!button) return;
    selectedSubjectTeacherIds = selectedSubjectTeacherIds.filter(id => String(id) !== String(button.dataset.removeTeacher));
    renderSelectedSubjectTeachers();
  });

  function renderMembers() {
    const memberBody = document.getElementById('memberBody');
    if (!memberBody) return;

    const roleName = {
      student: 'นักเรียน',
      teacher: 'อาจารย์',
      parent: 'ผู้ปกครอง',
      staff: 'เจ้าหน้าที่',
      director: 'ผู้อำนวยการ',
    };

    const statusFilter = document.getElementById('memberStatusFilter')?.value || 'active';
    const filteredMembers = members.filter(member => {
      if (statusFilter !== 'all' && member.status !== statusFilter) return false;
      if (!memberSearchTerm) return true;
      const searchable = `${member.name} ${member.email} ${member.phone}`.toLocaleLowerCase();
      return searchable.includes(memberSearchTerm);
    });

    memberBody.innerHTML = filteredMembers.length
      ? filteredMembers.map(member => `
        <tr>
          <td>${member.name}</td>
          <td style="color:var(--text-secondary)">${member.email || '-'}</td>
          <td style="color:var(--text-secondary)">${member.phone || '-'}</td>
          <td><span class="badge ${member.role === 'Staff' ? 'required' : 'draft'}">${roleName[member.role] || member.role}</span></td>
          <td><span class="badge ${member.status === 'active' ? 'active' : 'draft'}">${member.status === 'inactive' ? 'ระงับบัญชี' : 'ปกติ'}</span></td>
          <td>${formatRegistrationDate(member.registered_at)}</td>
          <td>${formatRegistrationDate(member.updated_at)}</td>
          <td>${escapeHtml(member.created_by_name || '-')}</td>
          <td>${escapeHtml(member.updated_by_name || member.created_by_name || '-')}</td>
          <td>
            <div class="action-btns">
              <button type="button" class="btn-icon edit" onclick="editMember('${member.id}')">แก้ไข</button>
              <button type="button" class="btn-icon del" onclick="deleteMember('${escapeHtml(member.id)}')">ลบถาวร</button>
            </div>
          </td>
        </tr>
      `).join('')
      : '<tr><td colspan="10" style="text-align:center; padding:30px;">ไม่พบข้อมูลที่ตรงกับการค้นหา</td></tr>';
  }

  function renderStaffList() {
    const body = document.getElementById('staffListBody');
    if (!body) return;
    const staffMembers = members.filter(member => ['staff', 'director'].includes(member.roleValue) && member.status === 'active');
    body.innerHTML = staffMembers.length
      ? staffMembers.map(member => `
        <tr>
          <td>${escapeHtml(member.name)}</td>
          <td>${escapeHtml(member.id)}</td>
          <td><span class="badge ${member.status === 'active' ? 'active' : 'draft'}">${member.roleValue === 'director' ? 'ผู้อำนวยการ' : 'เจ้าหน้าที่'} · ${member.status === 'inactive' ? 'ระงับบัญชี' : 'ปกติ'}</span></td>
          <td>${formatRegistrationDate(member.registered_at)}</td>
          <td>${formatRegistrationDate(member.updated_at)}</td>
          <td>${escapeHtml(member.created_by_name || '-')}</td>
          <td>${escapeHtml(member.updated_by_name || member.created_by_name || '-')}</td>
          <td><button type="button" class="btn-icon edit" onclick="editMember('${escapeHtml(member.id)}')">แก้ไข</button></td>
        </tr>
      `).join('')
      : '<tr><td colspan="8" style="text-align:center;padding:24px;">ยังไม่มีบัญชีเจ้าหน้าที่</td></tr>';
  }

  window.editMember = id => {
    const member = members.find(item => String(item.id) === String(id));
    if (!member) {
      return;
    }

    document.getElementById('mf-id').value = member.id;
    document.getElementById('mf-user-id').value = member.id;
    document.getElementById('mf-firstname').value = member.firstname || '';
    document.getElementById('mf-lastname').value = member.lastname || '';
    document.getElementById('mf-email').value = member.email;
    document.getElementById('mf-phone').value = member.phone;
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
    formData.append('phone', document.getElementById('mf-phone').value);
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
    openModal('ลบบัญชีถาวร', 'บัญชีนี้และข้อมูลที่เกี่ยวข้องจะถูกลบออกจากระบบอย่างถาวร ยืนยันหรือไม่?', async () => {
      const formData = new FormData();
      formData.append('action', 'deleteMember');
      formData.append('id', id);

      try {
        await fetchJson('api_staff.php', { method: 'POST', body: formData });
        showToast('ลบบัญชีเรียบร้อย', 'success');
        await loadData();
      } catch (error) {
        showToast(error.message || 'ไม่สามารถลบบัญชีได้', 'error');
      }
    });
  };

  window.permanentlyDeleteMember = id => {
    openModal('ลบบัญชีถาวร', 'บัญชีและข้อมูลทั้งหมดที่อ้างถึงจะถูกลบถาวร ยืนยันหรือไม่?', async () => {
      const formData = new FormData();
      formData.append('action', 'permanentlyDeleteMember');
      formData.append('id', id);
      try {
        await fetchJson('api_staff.php', { method: 'POST', body: formData });
        showToast('ลบบัญชีผู้ใช้งานถาวรเรียบร้อย', 'success');
        await loadData();
      } catch (error) {
        showToast(error.message || 'ไม่สามารถลบบัญชีได้', 'error');
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
          <td>${escapeHtml(curriculum.created_by_name || '-')}</td>
          <td>${escapeHtml(curriculum.updated_by_name || curriculum.created_by_name || '-')}</td>
          <td>${formatRegistrationDate(curriculum.created_at)}</td>
          <td>${formatRegistrationDate(curriculum.updated_at)}</td>
          <td>
            <div class="action-btns">
              <button type="button" class="btn-icon" style="color:var(--blue); border-color:var(--border);" onclick="manageCurriculumSubjects('${curriculum.id}', '${curriculum.name}')" title="จัดการวิชาเข้าหลักสูตร">จัดการ</button>
              <button type="button" class="btn-icon edit" onclick="editCurriculum('${curriculum.id}')">แก้ไข</button>
              <button type="button" class="btn-icon del" onclick="deleteCurriculum('${curriculum.id}')">ลบ</button>
            </div>
          </td>
        </tr>
      `).join('')
      : '<tr><td colspan="9" style="text-align:center;">ไม่มีข้อมูลหลักสูตร</td></tr>';

    const issuesNotice = document.getElementById('curriculumIssuesNotice');
    if (issuesNotice) {
      issuesNotice.hidden = curriculumIssues.length === 0;
      issuesNotice.innerHTML = curriculumIssues.length
        ? `<strong>ต้องตรวจสอบรายวิชาที่ไม่อยู่ในหลักสูตร</strong><ul style="margin:8px 0 0;padding-left:22px;">${curriculumIssues.map(issue => `<li>${escapeHtml(issue.curriculum_code || issue.curriculum_id)} · ${escapeHtml(issue.subject_code || issue.course_id)} ${escapeHtml(issue.subject_name)} (นักเรียน ${Number(issue.affected_students)} คน) <button type="button" class="btn-icon" onclick="manageCurriculumSubjects('${escapeHtml(issue.curriculum_id)}','${escapeHtml(issue.curriculum_name)}')">กำหนดประเภทและลำดับ</button></li>`).join('')}</ul>`
        : '';
    }
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

  function getEffectiveSubjectType(subject) {
    const selectedCurriculumId = document.getElementById('subjectCurriculumFilter')?.value || '';
    const relatedTypes = curriculumSubjects
      .filter(item => String(item.course_id) === String(subject.id)
        && (!selectedCurriculumId || String(item.curriculum_id) === String(selectedCurriculumId)))
      .map(item => String(item.requirement_type).toLowerCase() === 'required' ? 'compulsory' : 'elective');
    const uniqueTypes = [...new Set(relatedTypes)];
    if (uniqueTypes.length === 1) return uniqueTypes[0];
    if (uniqueTypes.length > 1) return 'varies';

    const baseType = String(subject.type ?? subject.category ?? subject.subject_type ?? '').trim().toLowerCase();
    return ['required', 'compulsory', 'mandatory'].includes(baseType) ? 'compulsory' : 'elective';
  }

  function renderSubjects() {
    const subjectBody = document.getElementById('subjectBody');
    if (!subjectBody) {
      return;
    }
    const curriculumFilter = document.getElementById('subjectCurriculumFilter');
    if (curriculumFilter && curriculumFilter.options.length !== curricula.length + 1) {
      const selectedValue = curriculumFilter.value;
      curriculumFilter.innerHTML = '<option value="">รายวิชาทั้งหมด</option>' + curricula.map(item => `<option value="${escapeHtml(item.id)}">${escapeHtml(item.code)} · ${escapeHtml(item.name)}</option>`).join('');
      curriculumFilter.value = selectedValue;
    }
    const selectedCurriculumId = curriculumFilter?.value || '';
    const curriculumTypeBySubject = new Map(curriculumSubjects
      .filter(item => String(item.curriculum_id) === String(selectedCurriculumId))
      .map(item => [String(item.course_id), String(item.requirement_type).toLowerCase() === 'required' ? 'compulsory' : 'elective']));
    const visibleSubjects = selectedCurriculumId
      ? subjects.filter(subject => curriculumTypeBySubject.has(String(subject.id)))
      : subjects;

    subjectBody.innerHTML = visibleSubjects.length
      ? visibleSubjects.map(subject => {
        const type = getEffectiveSubjectType(subject);
        const isCompulsory = type === 'compulsory';
        const typeLabel = type === 'varies' ? 'เลือกหลักสูตร' : isCompulsory ? 'วิชาบังคับ' : 'วิชาเลือก';
        return `
        <tr>
          <td class="mono">${escapeHtml(subject.code)}</td>
          <td>${escapeHtml(subject.name)}</td>
          <td><span class="badge ${type === 'varies' ? '' : isCompulsory ? 'compulsory' : 'elective'}">${typeLabel}</span></td>
          <td style="color:var(--orange); font-weight:500;">${escapeHtml(subject.teacher_name || 'ยังไม่มีผู้ดูแล')}</td>
          <td>${escapeHtml(subject.created_by_name || '-')}</td>
          <td>${escapeHtml(subject.updated_by_name || subject.created_by_name || '-')}</td>
          <td>${formatRegistrationDate(subject.created_at)}</td>
          <td>${formatRegistrationDate(subject.updated_at)}</td>
          <td>
            <div class="action-btns">
              <button type="button" class="btn-icon" style="color:var(--blue); border-color:var(--border);" onclick="manageLessons('${subject.id}')" title="จัดการบทเรียน">บทเรียน</button>
              <button type="button" class="btn-icon edit" onclick="editSubject('${subject.id}')" title="แก้ไขรายวิชา">แก้ไข</button>
              <button type="button" class="btn-icon del" onclick="deleteSubject('${subject.id}')">ลบ</button>
            </div>
          </td>
        </tr>
      `;
      }).join('')
      : `<tr><td colspan="9" style="text-align:center;">${selectedCurriculumId ? 'หลักสูตรนี้ยังไม่มีรายวิชาที่กำหนดไว้' : 'ไม่มีข้อมูลรายวิชา'}</td></tr>`;
  }

  document.getElementById('subjectCurriculumFilter')?.addEventListener('change', renderSubjects);

  document.querySelector('[data-goto="subject-add"]')?.addEventListener('click', () => {
    document.getElementById('subjectFormTitle').textContent = 'เพิ่มรายวิชาใหม่';
    document.getElementById('subjectForm').reset();
    document.getElementById('sf-id').value = '';
    document.getElementById('sf-description').value = '';
    document.getElementById('sf-image').value = '';
    document.getElementById('sf-image-name').textContent = 'ยังไม่ได้เลือกไฟล์';
    document.getElementById('sf-image-preview-wrap').hidden = true;
    populateTeacherSelect('');
  });

  window.editSubject = id => {
    const subject = subjects.find(item => String(item.id) === String(id));
    if (!subject) {
      showToast('ไม่พบข้อมูลรายวิชา', 'error');
      return;
    }

    document.getElementById('subjectFormTitle').textContent = `แก้ไขรายวิชา: ${subject.name || subject.code || ''}`;
    document.getElementById('sf-id').value = subject.id || '';
    document.getElementById('sf-code').value = subject.code || '';
    document.getElementById('sf-name').value = subject.name || '';
    document.getElementById('sf-credit').value = subject.credit || 0;
    const effectiveType = getEffectiveSubjectType(subject);
    const baseType = String(subject.type ?? subject.category ?? subject.subject_type ?? '').trim().toLowerCase();
    const formType = effectiveType === 'varies'
      ? (['required', 'compulsory', 'mandatory'].includes(baseType) ? 'compulsory' : 'elective')
      : effectiveType;
    document.getElementById('sf-type').value = formType === 'compulsory' ? 'required' : 'elective';
    document.getElementById('sf-description').value = subject.description || '';
    document.getElementById('sf-image').value = '';
    document.getElementById('sf-image-name').textContent = subject.image_path ? 'ใช้รูปปกเดิม' : 'ยังไม่ได้เลือกไฟล์';
    const preview = document.getElementById('sf-image-preview');
    const previewWrap = document.getElementById('sf-image-preview-wrap');
    if (subject.image_path) {
      preview.src = subject.image_path;
      previewWrap.hidden = false;
    } else {
      previewWrap.hidden = true;
    }

    populateTeacherSelect(subject.teacher_ids || '');
    goTo('subject-add');
  };

  document.getElementById('sf-image')?.addEventListener('change', event => {
    const file = event.target.files?.[0];
    const preview = document.getElementById('sf-image-preview');
    const previewWrap = document.getElementById('sf-image-preview-wrap');
    if (!file) return;
    document.getElementById('sf-image-name').textContent = file.name;
    preview.src = URL.createObjectURL(file);
    previewWrap.hidden = false;
  });

  document.getElementById('subjectForm')?.addEventListener('submit', async e => {
    e.preventDefault();

    const formData = new FormData();
    formData.append('action', 'saveSubject');
    formData.append('id', document.getElementById('sf-id').value);
    formData.append('code', document.getElementById('sf-code').value);
    formData.append('name', document.getElementById('sf-name').value);
    formData.append('credit', document.getElementById('sf-credit').value);
    formData.append('type', document.getElementById('sf-type').value);
    formData.append('description', document.getElementById('sf-description').value);
    const courseImage = document.getElementById('sf-image').files?.[0];
    if (courseImage) formData.append('course_image', courseImage);

    addSelectedSubjectTeacher();
    formData.append('teacher_ids', JSON.stringify(selectedSubjectTeacherIds));

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
          <td>${formatRegistrationDate(lesson.created_at)}</td>
          <td>${formatRegistrationDate(lesson.updated_at)}</td>
          <td>
            <div class="action-btns">
              <button type="button" class="btn-icon edit" onclick="editLesson('${lesson.id}')">แก้ไข</button>
              <button type="button" class="btn-icon del" onclick="deleteLesson('${lesson.id}')">ลบ</button>
            </div>
          </td>
        </tr>
      `).join('')
      : '<tr><td colspan="6" style="text-align:center; padding:30px;">ยังไม่มีบทเรียนในวิชานี้</td></tr>';
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
    staffUserIdInput.value = staffUserIdInput.value.replace(/\D+/g, '');
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
    formData.append('email', document.getElementById('stf-email').value);
    formData.append('phone', document.getElementById('stf-phone').value);
    formData.append('role', document.getElementById('stf-role').value);

    try {
      const result = await fetchJson('api_staff.php', { method: 'POST', body: formData });
      if (result.status === 'success') {
        showToast('สร้างบัญชีเรียบร้อย', 'success');
        resetStaffForm();
      }
    } catch (error) {
      showToast(error.message || 'ไม่สามารถเพิ่มเจ้าหน้าที่ได้', 'error');
    }
  });


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
            const isChecked = Array.isArray(data.selected) && data.selected.some(id => String(id) === String(sub.id)) ? 'checked' : '';
            const mappedType = data.selected_types?.[String(sub.id)] || '';
            const mappedOrder = data.selected_orders?.[String(sub.id)] ?? '';
            return `
              <div class="curriculum-subject-row" style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; padding:10px; border-radius:10px; transition:background 0.2s;" onmouseover="this.style.background='rgba(255,107,26,0.05)'" onmouseout="this.style.background='transparent'">
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;flex:1;min-width:240px"><input type="checkbox" name="curriculum_subjects[]" value="${escapeHtml(sub.id)}" ${isChecked} style="width:18px; height:18px; accent-color:var(--orange); cursor:pointer;"><span><strong style="color:var(--orange);">${escapeHtml(sub.code || sub.id)}</strong> - ${escapeHtml(sub.name)}</span></label>
                <select name="curriculum_subject_type[${escapeHtml(sub.id)}]" aria-label="ประเภทวิชาในหลักสูตรสำหรับ ${escapeHtml(sub.name)}" style="min-width:145px;padding:7px 10px;border:1px solid var(--border);border-radius:8px;background:var(--surface);color:var(--text);">
                  <option value="" ${mappedType === '' ? 'selected' : ''}>เลือกประเภทวิชา</option>
                  <option value="compulsory" ${mappedType === 'compulsory' ? 'selected' : ''}>วิชาบังคับ</option>
                  <option value="elective" ${mappedType === 'elective' ? 'selected' : ''}>วิชาเลือก</option>
                </select>
                <label style="display:flex;align-items:center;gap:6px;white-space:nowrap">ลำดับ
                  <input type="number" name="curriculum_subject_order[${escapeHtml(sub.id)}]" min="1" step="1" value="${escapeHtml(mappedOrder)}" aria-label="ลำดับหลักสูตรสำหรับ ${escapeHtml(sub.name)}" style="width:82px;padding:7px;border:1px solid var(--border);border-radius:8px;background:var(--surface);color:var(--text);">
                </label>
              </div>
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
    const selectedSubjects = Array.from(checkboxes).map(cb => ({
      id: cb.value,
      type: document.querySelector(`[name="curriculum_subject_type[${CSS.escape(cb.value)}]"]`)?.value || '',
      order: document.querySelector(`[name="curriculum_subject_order[${CSS.escape(cb.value)}]"]`)?.value || ''
    }));
    if (selectedSubjects.some(subject => !['compulsory', 'elective'].includes(subject.type))) {
      showToast('กรุณาเลือกประเภทวิชาของรายวิชาที่อยู่ในหลักสูตรให้ครบ', 'error');
      return;
    }
    const orders = selectedSubjects.map(subject => Number(subject.order));
    if (orders.some(order => !Number.isInteger(order) || order < 1) || new Set(orders).size !== orders.length) {
      showToast('กรุณากำหนดลำดับหลักสูตรเป็นจำนวนเต็มบวกที่ไม่ซ้ำกัน', 'error');
      return;
    }

    const formData = new FormData();
    formData.append('action', 'saveCurriculumSubjects');
    formData.append('curriculum_id', curriculumId);
    formData.append('subjects', JSON.stringify(selectedSubjects));

    try {
      await fetchJson('api_staff.php', { method: 'POST', body: formData });
      showToast('บันทึกรายวิชาเข้าหลักสูตรเรียบร้อย!', 'success');
      await loadData();
      goTo('curriculum-list');
    } catch (error) {
      showToast(error.message || 'ไม่สามารถบันทึกข้อมูลได้', 'error');
    }
  });

  goTo(initialPage);
  loadData();
})();


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
  const profileImg = document.getElementById('staffProfileAvatarImg');
  const profileInitial = document.getElementById('staffProfileAvatarInitial');
  if (profileImg) { profileImg.src = src; profileImg.style.display = 'block'; }
  if (profileInitial) profileInitial.style.display = 'none';
}

function _staffCropInjectModal() {
  if (document.getElementById('staffCropOverlay')) return;
  document.body.insertAdjacentHTML('beforeend', `
  <div id="staffCropOverlay" style="position:fixed;inset:0;background:rgba(0,0,0,0.6);
       display:flex;align-items:center;justify-content:center;z-index:9999;padding:1rem;box-sizing:border-box">
    <div style="background:#1a1a1a;border:1px solid rgba(255,255,255,0.1);border-radius:16px;
         padding:1.25rem;width:340px;max-width:100%;color:#fff;font-family:'IBM Plex Sans Thai',sans-serif">
      <p style="margin:0 0 1rem;font-size:15px;font-weight:500">️ ครอปรูปโปรไฟล์</p>
      <div id="staffCropStage" style="position:relative;width:100%;height:280px;
           background:#0d0d0d;border-radius:10px;overflow:hidden;cursor:grab;user-select:none;touch-action:none">
        <canvas id="staffCropCanvas" style="display:block;width:100%;height:100%"></canvas>
        <div id="staffCropCircle" style="position:absolute;border:2px dashed rgba(255,255,255,0.85);
             border-radius:50%;box-shadow:0 0 0 9999px rgba(0,0,0,0.45);pointer-events:none"></div>
      </div>
      <div style="display:flex;align-items:center;gap:10px;margin-top:10px">
        <span style="font-size:12px;color:#aaa;white-space:nowrap"> ซูม</span>
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
            cursor:pointer;font-family:'IBM Plex Sans Thai',sans-serif;font-weight:500"> ใช้รูปนี้</button>
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
    .then(d => {
      if (!d.success) throw new Error(d.message || 'อัปโหลดรูปไม่สำเร็จ');
      _staffApplyAvatarUI(d.url);
    })
    .catch(err => {
      console.warn('Avatar upload error:', err);
      alert(err.message || 'บันทึกรูปโปรไฟล์ไม่สำเร็จ');
    });
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

function openStaffProfile() {
  if (typeof window.goTo === 'function') window.goTo('profile');
}

function closeStaffProfile() {
  if (typeof window.goTo === 'function') window.goTo(window._staffProfileReturnPage || 'dashboard');
}

function toggleStaffPwd(id, button) {
  const input = document.getElementById(id);
  if (!input || !button) return;
  const show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  const label = show ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน';
  button.setAttribute('aria-label', label);
  button.title = label;
  button.innerHTML = staffPasswordEyeIcon(show);
}

function staffPasswordEyeIcon(showing) {
  return showing
    ? '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18M10.6 10.6a2 2 0 002.8 2.8M9.9 5.2A10.8 10.8 0 0112 5c6.4 0 10 7 10 7a14.7 14.7 0 01-3 3.8M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7c1.4 0 2.7-.3 3.8-.8"/></svg>'
    : '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';
}

function checkStaffPwdStrength(value) {
  const wrap = document.getElementById('staffPwdStrengthWrap');
  const bar = document.getElementById('staffPwdStrengthBar');
  const label = document.getElementById('staffPwdStrengthLabel');
  if (!wrap || !bar || !label) return;
  if (!value) { wrap.style.display = 'none'; checkStaffPwdMatch(); return; }
  wrap.style.display = 'block';
  let score = 0;
  if (value.length >= 8) score++;
  if (value.length >= 12) score++;
  if (/[A-Z]/.test(value)) score++;
  if (/[0-9]/.test(value)) score++;
  if (/[^A-Za-z0-9]/.test(value)) score++;
  const levels = [
    { pct: '20%', color: '#ef4444', text: 'อ่อนมาก' },
    { pct: '40%', color: '#f97316', text: 'อ่อน' },
    { pct: '60%', color: '#eab308', text: 'ปานกลาง' },
    { pct: '80%', color: '#3b82f6', text: 'ดี' },
    { pct: '100%', color: '#10b981', text: 'แข็งแกร่งมาก' }
  ];
  const level = levels[Math.min(Math.max(score - 1, 0), 4)];
  bar.style.width = level.pct;
  bar.style.background = level.color;
  label.style.color = level.color;
  label.textContent = `ความแข็งแกร่ง: ${level.text}`;
  checkStaffPwdMatch();
}

function checkStaffPwdMatch() {
  const confirm = document.getElementById('staffPwdConfirm')?.value || '';
  const message = document.getElementById('staffPwdMatchMsg');
  const newPassword = document.getElementById('staffPwdNew')?.value || '';
  if (!message) return;
  if (!confirm) { message.textContent = ''; return; }
  message.textContent = newPassword === confirm ? 'รหัสผ่านตรงกัน' : 'รหัสผ่านไม่ตรงกัน';
  message.style.color = newPassword === confirm ? '#10b981' : '#ef4444';
}

document.getElementById('staffProfileForm')?.addEventListener('submit', async event => {
  event.preventDefault();
  const form = event.currentTarget;
  const button = document.getElementById('saveStaffProfileBtn');
  const message = document.getElementById('staffProfileMessage');
  const body = new FormData(form);
  button.disabled = true;
  message.textContent = 'กำลังบันทึก...';
  message.style.color = 'var(--text-muted)';
  try {
    const response = await fetch('update_staff_profile.php', { method: 'POST', body, credentials: 'same-origin' });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.message || 'บันทึกข้อมูลไม่สำเร็จ');
    const name = document.querySelector('.user-name');
    if (name) name.textContent = result.name;
    const profileName = document.querySelector('.staff-profile-identity h2');
    if (profileName) profileName.textContent = result.name;
    const profileIdentifier = document.getElementById('staffProfileIdentifier');
    if (profileIdentifier && result.user_id) profileIdentifier.textContent = `เจ้าหน้าที่ · รหัส ${result.user_id}`;
    message.textContent = result.message || 'บันทึกข้อมูลโปรไฟล์แล้ว';
    message.style.color = 'var(--green, #16a34a)';
  } catch (error) {
    message.textContent = error.message || 'บันทึกข้อมูลไม่สำเร็จ กรุณาลองอีกครั้ง';
    message.style.color = 'var(--red)';
  } finally {
    button.disabled = false;
  }
});

document.getElementById('staffPasswordForm')?.addEventListener('submit', async event => {
  event.preventDefault();
  const form = event.currentTarget;
  const button = document.getElementById('saveStaffPasswordBtn');
  const message = document.getElementById('staffPasswordMessage');
  const pwdNew = document.getElementById('staffPwdNew')?.value || '';
  const pwdConfirm = document.getElementById('staffPwdConfirm')?.value || '';
  if (pwdNew.length < 6 || pwdNew !== pwdConfirm) {
    message.textContent = pwdNew.length < 6 ? 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร' : 'รหัสผ่านใหม่และการยืนยันไม่ตรงกัน';
    message.style.color = 'var(--red)';
    return;
  }
  button.disabled = true;
  message.textContent = 'กำลังเปลี่ยนรหัสผ่าน...';
  message.style.color = 'var(--text-muted)';
  try {
    const body = new FormData(document.getElementById('staffProfileForm'));
    body.append('pwd_current', document.getElementById('staffPwdCurrent').value);
    body.append('pwd_new', pwdNew);
    body.append('pwd_confirm', pwdConfirm);
    const response = await fetch('update_staff_profile.php', { method: 'POST', body, credentials: 'same-origin' });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.message || 'เปลี่ยนรหัสผ่านไม่สำเร็จ');
    form.reset();
    ['staffPwdCurrent', 'staffPwdNew', 'staffPwdConfirm'].forEach(id => { const input = document.getElementById(id); if (input) input.type = 'password'; });
    document.querySelectorAll('#staffPasswordForm .staff-password-toggle').forEach(toggle => {
      toggle.innerHTML = staffPasswordEyeIcon(false);
      toggle.setAttribute('aria-label', 'แสดงรหัสผ่าน');
      toggle.title = 'แสดงรหัสผ่าน';
    });
    const strength = document.getElementById('staffPwdStrengthWrap');
    if (strength) strength.style.display = 'none';
    const match = document.getElementById('staffPwdMatchMsg');
    if (match) match.textContent = '';
    message.textContent = result.message || 'เปลี่ยนรหัสผ่านสำเร็จ';
    message.style.color = 'var(--green, #16a34a)';
  } catch (error) {
    message.textContent = error.message || 'เปลี่ยนรหัสผ่านไม่สำเร็จ กรุณาลองอีกครั้ง';
    message.style.color = 'var(--red)';
  } finally {
    button.disabled = false;
  }
});
