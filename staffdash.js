/* ============================================
   NEXORA — staff_dashboard.js 
   (เวอร์ชันรวมระบบจัดการบทเรียน)
   ============================================ */

(() => {
  let curricula = [];
  let subjects = [];
  let members = []; 
  let lessons = []; 
  const initialPage = new URLSearchParams(window.location.search).get('page') || 'dashboard';
  let currentPage = initialPage;
  let currentSubjectId = null;

  const pageNames = {
    'dashboard':       'แดชบอร์ด',
    'curriculum-list': 'รายการหลักสูตร',
    'curriculum-add':  'เพิ่ม/แก้ไขหลักสูตร',
    'subject-list':    'รายการรายวิชา',
    'subject-add':     'เพิ่ม/แก้ไขรายวิชา',
    'subject-detail':  'รายละเอียดรายวิชา (บทเรียน)',
    'lesson-add':      'เพิ่ม/แก้ไขบทเรียน',
    'member-list':     'จัดการผู้ใช้งาน',
    'member-edit':     'แก้ไขข้อมูลสมาชิก',
  };

  // ── UI Controls ─────────────────────────
  document.getElementById('menuBtn')?.addEventListener('click', () => document.getElementById('sidebar').classList.toggle('open'));
  document.getElementById('sidebarClose')?.addEventListener('click', () => document.getElementById('sidebar').classList.remove('open'));
  
  const modalOverlay = document.getElementById('modalOverlay');
  const modalConfirm = document.getElementById('modalConfirm');
  modalOverlay.addEventListener('click', e => { if (e.target === modalOverlay) closeModal(); });
  document.getElementById('modalCancel').addEventListener('click', closeModal);

  function openModal(title, body, onConfirm) {
    document.getElementById('modalTitle').textContent = title;
    document.getElementById('modalBody').textContent  = body;
    modalOverlay.classList.add('show');
    modalConfirm.onclick = () => { onConfirm(); closeModal(); };
  }
  function closeModal() { modalOverlay.classList.remove('show'); }

  function showToast(msg, type = 'info') {
    const toast = document.getElementById('toast');
    toast.textContent = msg;
    toast.className = 'toast show ' + type;
    setTimeout(() => toast.classList.remove('show'), 3000);
  }

  window.goTo = function(page) {
    if (!document.getElementById('page-' + page)) {
      page = 'dashboard';
    }
    currentPage = page;
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    document.getElementById('page-' + page)?.classList.add('active');

    document.querySelectorAll('.nav-item').forEach(n => {
      n.classList.toggle('active', n.dataset.page === page);
    });
    document.getElementById('topbarTitle').textContent = pageNames[page] || '';

    if (page === 'dashboard') updateDashStats();
    if (page === 'curriculum-list') renderCurricula();
    if (page === 'subject-list') renderSubjects();
    if (page === 'member-list') renderMembers();

    document.getElementById('sidebar').classList.remove('open');
    window.scrollTo(0, 0);
  }

  document.querySelectorAll('.nav-item').forEach(item => {
    item.addEventListener('click', e => { e.preventDefault(); goTo(item.dataset.page); });
  });

  document.addEventListener('click', e => {
    const gt = e.target.closest('[data-goto]');
    if (gt) goTo(gt.dataset.goto);
  });

  async function loadData() {
    try {
      const res = await fetch('api_staff.php?action=getAllData');
      const data = await res.json();
      if (data.status === 'success') {
        curricula = data.curricula || [];
        subjects = data.subjects || [];
        members = data.members || [];
        updateDashStats();
        if(currentPage === 'curriculum-list') renderCurricula();
        if(currentPage === 'subject-list') renderSubjects();
        if(currentPage === 'member-list') renderMembers();
      }
    } catch(err) { showToast('✕ ไม่สามารถเชื่อมต่อฐานข้อมูลได้', 'error'); }
  }

  function updateDashStats() {
    document.getElementById('stat-curriculum').textContent = curricula.length;
    document.getElementById('stat-subject').textContent    = subjects.length;
    document.getElementById('stat-student').textContent    = members.length;
  }

  // ── ส่วนจัดการสมาชิก (Members) ──
  function renderMembers() {
    const roleName = { student: 'นักเรียน', teacher: 'อาจารย์', parent: 'ผู้ปกครอง', staff: 'เจ้าหน้าที่' };
    document.getElementById('memberBody').innerHTML = members.length ? members.map(m => `
      <tr>
        <td>${m.firstname} ${m.lastname}</td>
        <td style="color:var(--text-secondary)">${m.email}</td>
        <td><span class="badge ${m.role === 'staff' ? 'required' : 'draft'}">${roleName[m.role]}</span></td>
        <td>${m.status === 'active' ? '<span class="badge active">ปกติ</span>' : '<span class="badge inactive">ระงับบัญชี</span>'}</td>
        <td>
          <div class="action-btns">
            <button type="button" class="btn-icon edit" onclick="editMember(${m.id})">✎</button>
            <button type="button" class="btn-icon del" onclick="deleteMember(${m.id})">✖</button>
          </div>
        </td>
      </tr>
    `).join('') : `<tr><td colspan="5" style="text-align:center; padding:30px;">ไม่พบข้อมูล</td></tr>`;
  }
  
  window.editMember = (id) => {
    const m = members.find(x => x.id == id);
    if(m) {
      document.getElementById('mf-id').value = m.id;
      document.getElementById('mf-firstname').value = m.firstname;
      document.getElementById('mf-lastname').value = m.lastname;
      document.getElementById('mf-email').value = m.email;
      document.getElementById('mf-role').value = m.role;
      document.getElementById('mf-status').value = m.status;
      goTo('member-edit');
    }
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
      const res = await fetch('api_staff.php', { method: 'POST', body: formData });
      const result = await res.json();
      if(result.status === 'success') { showToast('✓ บันทึกสำเร็จ', 'success'); await loadData(); goTo('member-list'); }
    } catch(err) { showToast('✕ ขัดข้อง', 'error'); }
  });

  window.deleteMember = (id) => {
    openModal('ลบสมาชิก', 'ยืนยันหรือไม่?', async () => {
      const formData = new FormData(); formData.append('action', 'deleteMember'); formData.append('id', id);
      await fetch('api_staff.php', { method: 'POST', body: formData });
      showToast('✓ ลบสำเร็จ', 'success'); await loadData();
    });
  };

  // ── ส่วนจัดการหลักสูตร (Curriculum) ──
  function renderCurricula() {
    document.getElementById('curriculumBody').innerHTML = curricula.length ? curricula.map(c => `
      <tr>
        <td class="mono">${c.code}</td><td>${c.name}</td><td>${c.level.toUpperCase()}</td>
        <td><span class="badge ${c.status === 'active'?'active':'draft'}">${c.status}</span></td>
        <td>
          <div class="action-btns">
            <button class="btn-icon edit" onclick="editCurriculum(${c.id})">✎</button>
            <button class="btn-icon del" onclick="deleteCurriculum(${c.id})">✖</button>
          </div>
        </td>
      </tr>
    `).join('') : `<tr><td colspan="5" style="text-align:center;">ไม่มีข้อมูล</td></tr>`;
  }
  document.querySelector('[data-goto="curriculum-add"]')?.addEventListener('click', () => {
    document.getElementById('curriculumFormTitle').textContent = 'เพิ่มหลักสูตรใหม่'; document.getElementById('curriculumForm').reset(); document.getElementById('cf-id').value = '';
  });
  window.editCurriculum = (id) => {
    const c = curricula.find(x => x.id == id);
    if(c) {
      document.getElementById('curriculumFormTitle').textContent = 'แก้ไขหลักสูตร'; document.getElementById('cf-id').value = c.id;
      document.getElementById('cf-code').value = c.code; document.getElementById('cf-name').value = c.name;
      document.getElementById('cf-level').value = c.level; document.getElementById('cf-year').value = c.year || '';
      document.getElementById('cf-desc').value = c.description || ''; document.getElementById('cf-status').value = c.status;
      goTo('curriculum-add');
    }
  };
  document.getElementById('curriculumForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(); fd.append('action', 'saveCurriculum');
    fd.append('id', document.getElementById('cf-id').value); fd.append('code', document.getElementById('cf-code').value);
    fd.append('name', document.getElementById('cf-name').value); fd.append('level', document.getElementById('cf-level').value);
    fd.append('year', document.getElementById('cf-year').value);
    fd.append('description', document.getElementById('cf-desc').value);
    fd.append('status', document.getElementById('cf-status').value);
    await fetch('api_staff.php', { method: 'POST', body: fd }); showToast('✓ บันทึกสำเร็จ', 'success'); await loadData(); goTo('curriculum-list');
  });
  window.deleteCurriculum = (id) => {
    openModal('ลบ', 'ยืนยัน?', async () => { const fd = new FormData(); fd.append('action', 'deleteCurriculum'); fd.append('id', id); await fetch('api_staff.php', { method: 'POST', body: fd }); showToast('✓ สำเร็จ', 'success'); await loadData(); });
  };

  // ── ส่วนจัดการรายวิชา (Subjects) ──
  function renderSubjects() {
    document.getElementById('subjectBody').innerHTML = subjects.length ? subjects.map(s => `
      <tr>
        <td class="mono">${s.code}</td><td>${s.name}</td><td>${s.credit}</td>
        <td><span class="badge ${s.type === 'required' ? 'required' : 'elective'}">${s.type === 'required' ? 'บังคับ' : 'เลือก'}</span></td>
        <td>
          <div class="action-btns">
            <button type="button" class="btn-icon" style="color:var(--blue);" onclick="openSubjectEditor(${s.id}, 'lessons')" title="จัดการบทเรียน">📚</button>
            <button type="button" class="btn-icon edit" onclick="openSubjectEditor(${s.id}, 'subject')" title="แก้ไขรายวิชา">✎</button>
            <button type="button" class="btn-icon del" onclick="deleteSubject(${s.id})">✖</button>
          </div>
        </td>
      </tr>
    `).join('') : `<tr><td colspan="5" style="text-align:center;">ไม่มีข้อมูลรายวิชา</td></tr>`;
  }
  document.querySelector('[data-goto="subject-add"]')?.addEventListener('click', () => { document.getElementById('subjectFormTitle').textContent = 'เพิ่มวิชา'; document.getElementById('subjectForm').reset(); document.getElementById('sf-id').value = ''; });
  window.openSubjectEditor = (id, section = 'subject') => {
    window.location.href = `staff_subject_editor.php?subject_id=${encodeURIComponent(id)}&section=${encodeURIComponent(section)}`;
  };
  window.editSubject = (id) => window.openSubjectEditor(id, 'subject');
  document.getElementById('subjectForm')?.addEventListener('submit', async (e) => {
    e.preventDefault(); const fd = new FormData(); fd.append('action', 'saveSubject');
    ['sf-id','sf-code','sf-name','sf-credit','sf-type'].forEach(id => fd.append(id.replace('sf-',''), document.getElementById(id).value));
    await fetch('api_staff.php', { method: 'POST', body: fd }); showToast('✓ บันทึกวิชาสำเร็จ', 'success'); await loadData(); goTo('subject-list');
  });
  window.deleteSubject = (id) => {
    openModal('ลบ', 'ยืนยัน?', async () => { const fd = new FormData(); fd.append('action', 'deleteSubject'); fd.append('id', id); await fetch('api_staff.php', { method: 'POST', body: fd }); showToast('✓ สำเร็จ', 'success'); await loadData(); });
  };

  // ==========================================
  // ── ส่วนจัดการบทเรียน (Lessons) NEW! ──
  // ==========================================
  window.manageLessons = async (subId, subName) => {
    currentSubjectId = subId;
    document.getElementById('detailSubjectTitle').textContent = `วิชา: ${subName}`;
    await loadLessons();
    goTo('subject-detail');
  };

  async function loadLessons() {
    try {
      const res = await fetch(`api_staff.php?action=getLessons&subject_id=${currentSubjectId}`);
      const data = await res.json();
      if(data.status === 'success') { lessons = data.lessons || []; renderLessons(); }
    } catch(err) { console.error(err); }
  }

  function renderLessons() {
    document.getElementById('lessonBody').innerHTML = lessons.length ? lessons.map(l => `
      <tr>
        <td>${l.image_path ? `<img src="${l.image_path}" style="height:40px; border-radius:4px; object-fit:cover;">` : '<div style="height:40px;width:60px;background:#222;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:10px;">ไม่มีรูป</div>'}</td>
        <td>${l.title}</td>
        <td>${l.video_url ? `<a href="${l.video_url}" target="_blank" style="color:var(--blue);text-decoration:none;">🔗 ลิงก์วิดีโอ</a>` : '-'}</td>
        <td>
          <div class="action-btns">
            <button type="button" class="btn-icon edit" onclick="editLesson(${l.id})">✎</button>
            <button type="button" class="btn-icon del" onclick="deleteLesson(${l.id})">✖</button>
          </div>
        </td>
      </tr>
    `).join('') : `<tr><td colspan="4" style="text-align:center; padding:30px;">ยังไม่มีบทเรียนในวิชานี้</td></tr>`;
  }

  window.openAddLesson = () => {
    document.getElementById('lessonFormTitle').textContent = 'เพิ่มบทเรียนใหม่';
    document.getElementById('lessonForm').reset();
    document.getElementById('lf-id').value = '';
    goTo('lesson-add');
  };

  window.editLesson = (id) => {
    const l = lessons.find(x => x.id == id);
    if(l) {
      document.getElementById('lessonFormTitle').textContent = 'แก้ไขบทเรียน';
      document.getElementById('lf-id').value = l.id;
      document.getElementById('lf-title').value = l.title;
      document.getElementById('lf-content').value = l.content;
      document.getElementById('lf-video').value = l.video_url || '';
      goTo('lesson-add');
    }
  };

  document.getElementById('lessonForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData();
    formData.append('action', 'saveLesson');
    formData.append('id', document.getElementById('lf-id').value);
    formData.append('subject_id', currentSubjectId);
    formData.append('title', document.getElementById('lf-title').value);
    formData.append('content', document.getElementById('lf-content').value);
    formData.append('video_url', document.getElementById('lf-video').value);
    
    // ดึงไฟล์รูปภาพเพื่อแนบส่งไปด้วย
    const fileInput = document.getElementById('lf-image');
    if(fileInput.files.length > 0) { formData.append('image', fileInput.files[0]); }

    try {
      const res = await fetch('api_staff.php', { method: 'POST', body: formData });
      const result = await res.json();
      if(result.status === 'success') {
        showToast('✓ บันทึกบทเรียนเรียบร้อย', 'success');
        await loadLessons();
        goTo('subject-detail');
      } else { showToast('✕ ' + result.message, 'error'); }
    } catch(err) { showToast('✕ ระบบขัดข้อง', 'error'); }
  });

  window.deleteLesson = (id) => {
    openModal('ลบบทเรียน', 'ยืนยันการลบบทเรียนนี้ใช่หรือไม่?', async () => {
      const formData = new FormData(); formData.append('action', 'deleteLesson'); formData.append('id', id);
      await fetch('api_staff.php', { method: 'POST', body: formData });
      showToast('✓ ลบบทเรียนสำเร็จ', 'success');
      await loadLessons();
    });
  };

  goTo(initialPage); loadData();
})();
