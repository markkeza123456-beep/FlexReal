/* ============================================
   NEXORA — staff_dashboard.js 
   (เวอร์ชันเชื่อมต่อ Database 100%)
   ============================================ */

(() => {
  // ── State ──────────────────────────────────
  let curricula = [];
  let subjects = [];
  let members = []; 

  let currentPage = 'dashboard';
  let curPage = 1;
  let subPage = 1;
  const PAGE_SIZE = 5;

  const pageNames = {
    'dashboard':       'แดชบอร์ด',
    'curriculum-list': 'รายการหลักสูตร',
    'curriculum-add':  'เพิ่ม/แก้ไขหลักสูตร',
    'subject-list':    'รายการรายวิชา',
    'subject-add':     'เพิ่ม/แก้ไขรายวิชา',
    'member-list':     'จัดการผู้ใช้งาน',
    'member-edit':     'แก้ไขข้อมูลสมาชิก',
  };

  // ── Selectors ─────────────────────────────
  const sidebar      = document.getElementById('sidebar');
  const menuBtn      = document.getElementById('menuBtn');
  const sidebarClose = document.getElementById('sidebarClose');
  const topbarTitle  = document.getElementById('topbarTitle');
  const toast        = document.getElementById('toast');
  const modalOverlay = document.getElementById('modalOverlay');
  const modalTitle   = document.getElementById('modalTitle');
  const modalBody    = document.getElementById('modalBody');
  const modalConfirm = document.getElementById('modalConfirm');
  const modalCancel  = document.getElementById('modalCancel');

  // ── UI Controls ─────────────────────────
  menuBtn.addEventListener('click', () => sidebar.classList.toggle('open'));
  sidebarClose.addEventListener('click', () => sidebar.classList.remove('open'));
  modalOverlay.addEventListener('click', e => { if (e.target === modalOverlay) closeModal(); });

  function openModal(title, body, onConfirm) {
    modalTitle.textContent = title;
    modalBody.textContent  = body;
    modalOverlay.classList.add('show');
    modalConfirm.onclick = () => { onConfirm(); closeModal(); };
  }
  function closeModal() { modalOverlay.classList.remove('show'); }
  modalCancel.addEventListener('click', closeModal);

  function showToast(msg, type = 'info') {
    toast.textContent = msg;
    toast.className = 'toast show ' + type;
    setTimeout(() => toast.classList.remove('show'), 3000);
  }

  function goTo(page) {
    currentPage = page;
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    const el = document.getElementById('page-' + page);
    if (el) el.classList.add('active');

    document.querySelectorAll('.nav-item').forEach(n => {
      n.classList.toggle('active', n.dataset.page === page);
    });
    topbarTitle.textContent = pageNames[page] || '';

    if (page === 'dashboard') updateDashStats();
    if (page === 'curriculum-list') renderCurricula();
    if (page === 'subject-list') renderSubjects();
    if (page === 'member-list') renderMembers();

    sidebar.classList.remove('open');
    window.scrollTo(0, 0);
  }

  document.querySelectorAll('.nav-item').forEach(item => {
    item.addEventListener('click', e => { e.preventDefault(); goTo(item.dataset.page); });
  });

  document.addEventListener('click', e => {
    const gt = e.target.closest('[data-goto]');
    if (gt) goTo(gt.dataset.goto);
  });

  // ==========================================
  // ── Database API Connection ──
  // ==========================================

  // โหลดข้อมูลทั้งหมดจากฐานข้อมูล (Dashboard, Curricula, Subjects, Members)
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
      } else {
        showToast('✕ โหลดข้อมูลไม่สำเร็จ', 'error');
      }
    } catch(err) { 
      console.error("Fetch Error: ", err);
      showToast('✕ ไม่สามารถเชื่อมต่อฐานข้อมูลได้', 'error');
    }
  }

  function updateDashStats() {
    document.getElementById('stat-curriculum').textContent = curricula.length;
    document.getElementById('stat-subject').textContent    = subjects.length;
    document.getElementById('stat-student').textContent    = members.length;
  }

  // ── จัดการสมาชิก (Members) ──
  function renderMembers() {
    const q = (document.getElementById('searchMember')?.value || '').toLowerCase();
    const roleFilter = document.getElementById('filterMemberRole')?.value || '';
    
    const filtered = members.filter(m => 
      (m.firstname.toLowerCase().includes(q) || m.email.toLowerCase().includes(q)) &&
      (roleFilter === '' || m.role === roleFilter)
    );

    const roleName = { student: 'นักเรียน', teacher: 'อาจารย์', parent: 'ผู้ปกครอง', staff: 'เจ้าหน้าที่' };
    
    document.getElementById('memberBody').innerHTML = filtered.length ? filtered.map(m => `
      <tr>
        <td>${m.firstname} ${m.lastname}</td>
        <td style="color:var(--text-secondary)">${m.email}</td>
        <td><span class="badge ${m.role === 'staff' ? 'required' : 'draft'}">${roleName[m.role]}</span></td>
        <td>${m.status === 'active' ? '<span class="badge active">ปกติ</span>' : '<span class="badge inactive">ระงับบัญชี</span>'}</td>
        <td>
          <div class="action-btns">
            <button type="button" class="btn-icon edit" onclick="editMember(${m.id})">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button type="button" class="btn-icon del" onclick="deleteMember(${m.id})">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
            </button>
          </div>
        </td>
      </tr>
    `).join('') : `<tr><td colspan="5" style="text-align:center; padding:30px; color:var(--text-muted)">ไม่พบข้อมูลผู้ใช้งาน</td></tr>`;
  }

  document.getElementById('searchMember')?.addEventListener('input', renderMembers);
  document.getElementById('filterMemberRole')?.addEventListener('change', renderMembers);

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
      if(result.status === 'success') {
        showToast('✓ บันทึกข้อมูลสมาชิกสำเร็จ', 'success');
        await loadData();
        goTo('member-list');
      } else {
        showToast('✕ ' + result.message, 'error');
      }
    } catch(err) { showToast('✕ ระบบขัดข้อง', 'error'); }
  });

  window.deleteMember = (id) => {
    openModal('ยืนยันการลบสมาชิก', 'คุณแน่ใจหรือไม่ที่จะลบบัญชีนี้? การกระทำนี้ไม่สามารถกู้คืนได้', async () => {
      const formData = new FormData();
      formData.append('action', 'deleteMember');
      formData.append('id', id);
      try {
        const res = await fetch('api_staff.php', { method: 'POST', body: formData });
        const result = await res.json();
        if(result.status === 'success') {
          showToast('✓ ลบสมาชิกออกจากระบบสำเร็จ', 'success');
          await loadData();
        }
      } catch(err) { showToast('✕ เกิดข้อผิดพลาด', 'error'); }
    });
  };

  // ── จัดการหลักสูตร & รายวิชา (เรนเดอร์เบื้องต้น) ──
  function renderCurricula() {
    document.getElementById('curriculumBody').innerHTML = curricula.map(c => `
      <tr>
        <td class="mono">${c.code}</td>
        <td>${c.name}</td>
        <td>${c.level}</td>
        <td><span class="badge ${c.status === 'active'?'active':'draft'}">${c.status}</span></td>
        <td><button class="btn-icon edit" onclick="goTo('curriculum-add')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button></td>
      </tr>
    `).join('');
  }

  function renderSubjects() {
    document.getElementById('subjectBody').innerHTML = subjects.map(s => `
      <tr>
        <td class="mono">${s.code}</td>
        <td>${s.name}</td>
        <td>${s.credit}</td>
        <td><span class="badge required">${s.type}</span></td>
        <td><button class="btn-icon edit" onclick="goTo('subject-add')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button></td>
      </tr>
    `).join('');
  }

  // ── Init ───────────────────────────────────
  goTo('dashboard');
  loadData();

})();