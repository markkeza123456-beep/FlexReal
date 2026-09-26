


const PARENT_KEY = 'parentProfile_v1';


const mockParent = {
  name: 'ผู้ปกครอง',
  email: '',
  phone: '',
  relation: 'ผู้ปกครอง',
  avatarInitials: 'ผป',
  photoDataUrl: null
};


function loadParent() {
  try {
    const s = localStorage.getItem(PARENT_KEY);
    return s ? Object.assign({}, mockParent, JSON.parse(s)) : { ...mockParent };
  } catch { return { ...mockParent }; }
}

function saveParent(profile) {
  try { localStorage.setItem(PARENT_KEY, JSON.stringify(profile)); }
  catch (e) { console.warn('localStorage error', e); }
}

function currentParentProfile() {
  const profile = loadParent();
  if (_parentData) {
    profile.name = _parentData.parents_name || profile.name;
    profile.email = _parentData.email || '';
    profile.phone = _parentData.phone || '';
  }
  return profile;
}


function applyParentProfile(p) {
  const fn = id => document.getElementById(id);


  if (fn('sidebarName')) fn('sidebarName').textContent = p.name;
  if (fn('sidebarRole')) fn('sidebarRole').textContent = p.relation || 'ผู้ปกครอง';
  renderSidebarAvatar(p);


  if (fn('displayName'))    fn('displayName').textContent  = p.name;
  if (fn('profileRole'))    fn('profileRole').textContent  = p.relation || 'ผู้ปกครอง';
  if (fn('profileName'))    fn('profileName').value        = p.name;
  if (fn('profileEmail'))   fn('profileEmail').value       = p.email || '';
  if (fn('profilePhone'))   fn('profilePhone').value       = p.phone || '';


  const avatarImg     = fn('avatarImg');
  const avatarInitial = fn('avatarInitial');
  if (p.photoDataUrl) {
    if (avatarImg) { avatarImg.src = p.photoDataUrl; avatarImg.style.display = 'block'; }
    if (avatarInitial) avatarInitial.style.display = 'none';
  } else {
    if (avatarImg) avatarImg.style.display = 'none';
    if (avatarInitial) {
      avatarInitial.style.display = '';
      avatarInitial.textContent = p.avatarInitials || p.name.slice(0, 2);
    }
  }
}

function renderSidebarAvatar(p) {
  const el = document.getElementById('sidebarAvatar');
  if (!el) return;
  if (p.photoDataUrl) {
    el.style.cssText += ';background:none;padding:0;overflow:hidden;';
    el.innerHTML = `<img src="${p.photoDataUrl}" style="width:100%;height:100%;object-fit:cover;border-radius:50%">`;
  } else {
    el.innerHTML = '';
    el.style.background = '';
    el.textContent = p.avatarInitials || p.name.slice(0, 2);
  }
}


function previewAvatar(input) {
  if (!input.files || !input.files[0]) return;
  const file = input.files[0];
  if (!file.type.startsWith('image/')) return;
  const reader = new FileReader();
  reader.onload = (e) => {
    const image = new Image();
    image.onload = () => {
      _crop.img = image;
      _cropInit();
      const overlay = document.getElementById('avatarCropOverlay');
      if (overlay) overlay.style.display = 'flex';
    };
    image.src = e.target.result;
  };
  reader.readAsDataURL(file);
  input.value = '';
}


async function saveProfile() {
  const btn = document.getElementById('saveProfileBtn');
  const feedback = document.getElementById('profileFeedback');

  const name  = (document.getElementById('profileName')?.value  || '').trim();
  const email = (document.getElementById('profileEmail')?.value || '').trim();
  const phone = (document.getElementById('profilePhone')?.value || '').trim();
  const current  = document.getElementById('pwdCurrent')?.value  || '';
  const pwdNew   = document.getElementById('pwdNew')?.value      || '';
  const pwdConfirm = document.getElementById('pwdConfirm')?.value || '';

  function showFeedback(type, msg) {
    feedback.style.display = 'block';
    feedback.textContent = msg;
    feedback.style.background = type === 'success' ? 'rgba(16,185,129,.15)' : 'rgba(239,68,68,.15)';
    feedback.style.color      = type === 'success' ? '#10b981' : '#ef4444';
    feedback.style.border     = `1px solid ${type === 'success' ? '#10b98133' : '#ef444433'}`;
    clearTimeout(feedback._t);
    feedback._t = setTimeout(() => { feedback.style.display = 'none'; }, 4000);
  }

  if (!name) { showFeedback('error', 'กรุณากรอกชื่อ-นามสกุล'); return; }
  if (pwdNew && pwdNew !== pwdConfirm) { showFeedback('error', 'รหัสผ่านใหม่ไม่ตรงกัน'); return; }
  if (pwdNew && pwdNew.length < 6)    { showFeedback('error', 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร'); return; }
  if (pwdNew && !current)             { showFeedback('error', 'กรุณาใส่รหัสผ่านปัจจุบันก่อน'); return; }

  if (btn) { btn.disabled = true; btn.innerHTML = '<i class="ti ti-loader"></i> กำลังบันทึก...'; }
  try {
    const body = new FormData();
    body.append('name', name);
    body.append('email', email);
    body.append('phone', phone);
    body.append('pwd_current', current);
    body.append('pwd_new', pwdNew);
    const response = await fetch('update_parent_profile.php', { method: 'POST', body, credentials: 'same-origin' });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.message || 'บันทึกข้อมูลไม่สำเร็จ');

    const profile = currentParentProfile();
    profile.name = name;
    profile.email = email;
    profile.phone = phone;
    profile.avatarInitials = name.slice(0, 2);
    saveParent(profile);
    _parentData.parents_name = name;
    _parentData.email = email;
    _parentData.phone = phone;
    applyParentProfile(profile);
    ['pwdCurrent','pwdNew','pwdConfirm'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
    const sw = document.getElementById('pwdStrengthWrap');
    if (sw) sw.style.display = 'none';
    const mm = document.getElementById('pwdMatchMsg');
    if (mm) mm.textContent = '';
    showFeedback('success', result.message || 'บันทึกข้อมูลสำเร็จ');
  } catch (error) {
    showFeedback('error', error.message || 'บันทึกข้อมูลไม่สำเร็จ กรุณาลองอีกครั้ง');
  } finally {
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="ti ti-device-floppy"></i> บันทึกข้อมูล'; }
  }
}


function togglePwd(id, btn) {
  const inp = document.getElementById(id);
  if (!inp) return;
  const show = inp.type === 'password';
  inp.type = show ? 'text' : 'password';
  const label = show ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน';
  btn.setAttribute('aria-label', label);
  btn.title = label;
  const icon = btn.querySelector('i');
  if (icon) icon.className = show ? 'ti ti-eye-off' : 'ti ti-eye';
}

function checkPwdStrength(val) {
  const wrap  = document.getElementById('pwdStrengthWrap');
  const bar   = document.getElementById('pwdStrengthBar');
  const label = document.getElementById('pwdStrengthLabel');
  if (!wrap) return;
  if (!val) { wrap.style.display = 'none'; return; }
  wrap.style.display = 'block';
  let score = 0;
  if (val.length >= 8) score++;
  if (val.length >= 12) score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;
  const levels = [
    { pct: '20%', color: '#ef4444', text: 'อ่อนมาก' },
    { pct: '40%', color: '#f97316', text: 'อ่อน' },
    { pct: '60%', color: '#eab308', text: 'ปานกลาง' },
    { pct: '80%', color: '#3b82f6', text: 'ดี' },
    { pct: '100%', color: '#10b981', text: 'แข็งแกร่งมาก' }
  ];
  const lv = levels[Math.min(score - 1, 4)] || levels[0];
  bar.style.width = lv.pct;
  bar.style.background = lv.color;
  label.style.color = lv.color;
  label.textContent = `ความแข็งแกร่ง: ${lv.text}`;
  checkPwdMatch();
}

function checkPwdMatch() {
  const n   = document.getElementById('pwdNew')?.value     || '';
  const c   = document.getElementById('pwdConfirm')?.value || '';
  const msg = document.getElementById('pwdMatchMsg');
  if (!msg || !c) return;
  if (n === c) { msg.style.color = '#10b981'; msg.textContent = ' รหัสผ่านตรงกัน'; }
  else         { msg.style.color = '#ef4444'; msg.textContent = ' รหัสผ่านไม่ตรงกัน'; }
}


const _crop = {
  img: null, imgX: 0, imgY: 0, imgW: 0, imgH: 0, zoom: 1,
  dragging: false, lastX: 0, lastY: 0,
  canvas: null, ctx: null, stage: null, circle: null,
  stageW: 0, stageH: 0, circleSize: 0,
};

function _cropInjectModal() {
  if (document.getElementById('avatarCropOverlay')) return;
  const html = `
  <div id="avatarCropOverlay" style="
      position:fixed;inset:0;background:rgba(0,0,0,0.6);
      display:flex;align-items:center;justify-content:center;
      z-index:9999;padding:1rem;box-sizing:border-box">
    <div style="
        background:#1a1a2e;border:1px solid rgba(255,255,255,0.1);
        border-radius:16px;padding:1.25rem;width:340px;max-width:100%;
        color:#fff;font-family:'Kanit',sans-serif">
      <p style="margin:0 0 1rem;font-size:15px;font-weight:500">️ ครอปรูปโปรไฟล์</p>
      <div id="cropStage" style="
          position:relative;width:100%;height:280px;
          background:#0d0d1a;border-radius:10px;overflow:hidden;
          cursor:grab;user-select:none;touch-action:none">
        <canvas id="cropCanvas" style="display:block;width:100%;height:100%"></canvas>
        <div id="cropCircle" style="
            position:absolute;border:2px dashed rgba(255,255,255,0.85);
            border-radius:50%;box-shadow:0 0 0 9999px rgba(0,0,0,0.45);
            pointer-events:none"></div>
      </div>
      <div style="display:flex;align-items:center;gap:10px;margin-top:10px">
        <span style="font-size:12px;color:#aaa;white-space:nowrap"> ซูม</span>
        <input type="range" id="cropZoomSlider" min="100" max="300" value="100" step="1"
               oninput="_cropSetZoom(this.value)"
               style="flex:1;accent-color:#f97316">
        <span id="cropZoomVal" style="font-size:12px;color:#aaa;min-width:36px">100%</span>
      </div>
      <p style="font-size:11px;color:#666;text-align:center;margin:6px 0 1rem">
        ลากรูปเพื่อจัดตำแหน่ง · เลื่อนซูมเพื่อขยาย
      </p>
      <div style="display:flex;gap:8px">
        <button onclick="_cropClose()" style="
            flex:1;padding:9px 0;font-size:13px;border-radius:8px;
            border:1px solid rgba(255,255,255,0.15);background:transparent;
            color:#fff;cursor:pointer;font-family:'Kanit',sans-serif">
          ยกเลิก
        </button>
        <button onclick="_cropConfirm()" style="
            flex:1;padding:9px 0;font-size:13px;border-radius:8px;
            border:1px solid rgba(249,115,22,0.4);
            background:rgba(249,115,22,0.15);color:#f97316;
            cursor:pointer;font-family:'Kanit',sans-serif;font-weight:500">
           ใช้รูปนี้
        </button>
      </div>
    </div>
  </div>`;
  document.body.insertAdjacentHTML('beforeend', html);

  const stage = document.getElementById('cropStage');
  stage.addEventListener('mousedown',  _cropStartDrag);
  stage.addEventListener('mousemove',  _cropOnDrag);
  stage.addEventListener('mouseup',    _cropEndDrag);
  stage.addEventListener('mouseleave', _cropEndDrag);
  stage.addEventListener('touchstart', _cropStartDrag, { passive: false });
  stage.addEventListener('touchmove',  _cropOnDrag,    { passive: false });
  stage.addEventListener('touchend',   _cropEndDrag);
}

function _cropGetXY(e) {
  if (e.touches && e.touches.length) return { x: e.touches[0].clientX, y: e.touches[0].clientY };
  return { x: e.clientX, y: e.clientY };
}
function _cropStartDrag(e) { _crop.dragging = true; const p = _cropGetXY(e); _crop.lastX = p.x; _crop.lastY = p.y; e.preventDefault(); }
function _cropOnDrag(e) {
  if (!_crop.dragging) return;
  const p = _cropGetXY(e);
  _crop.imgX += p.x - _crop.lastX; _crop.imgY += p.y - _crop.lastY;
  _crop.lastX = p.x; _crop.lastY = p.y;
  _cropDraw(); e.preventDefault();
}
function _cropEndDrag() { _crop.dragging = false; }

function _cropSetZoom(v) {
  _crop.zoom = v / 100;
  document.getElementById('cropZoomVal').textContent = v + '%';
  _cropDraw();
}

function _cropDraw() {
  const { ctx, img, imgX, imgY, imgW, imgH, zoom, stageW, stageH } = _crop;
  if (!ctx || !img) return;
  ctx.clearRect(0, 0, stageW, stageH);
  const drawW = imgW * zoom, drawH = imgH * zoom;
  const drawX = imgX - (zoom - 1) * imgW / 2;
  const drawY = imgY - (zoom - 1) * imgH / 2;
  ctx.drawImage(img, drawX, drawY, drawW, drawH);
}

function _cropInit() {
  _cropInjectModal();
  const stage = document.getElementById('cropStage');
  const canvas = document.getElementById('cropCanvas');
  const circle = document.getElementById('cropCircle');
  _crop.canvas = canvas; _crop.ctx = canvas.getContext('2d');
  _crop.stage = stage; _crop.circle = circle;
  _crop.stageW = stage.offsetWidth; _crop.stageH = stage.offsetHeight;
  _crop.circleSize = Math.min(_crop.stageW, _crop.stageH) * 0.72;
  canvas.width = _crop.stageW; canvas.height = _crop.stageH;
  const cs = _crop.circleSize;
  circle.style.width  = cs + 'px'; circle.style.height = cs + 'px';
  circle.style.left   = ((_crop.stageW - cs) / 2) + 'px';
  circle.style.top    = ((_crop.stageH - cs) / 2) + 'px';
  _crop.zoom = 1;
  const slider = document.getElementById('cropZoomSlider');
  if (slider) slider.value = 100;
  const zoomVal = document.getElementById('cropZoomVal');
  if (zoomVal) zoomVal.textContent = '100%';
  const scale = Math.max(cs / _crop.img.width, cs / _crop.img.height);
  _crop.imgW = _crop.img.width * scale; _crop.imgH = _crop.img.height * scale;
  _crop.imgX = (_crop.stageW - _crop.imgW) / 2; _crop.imgY = (_crop.stageH - _crop.imgH) / 2;
  _cropDraw();
}

function _cropClose() {
  const overlay = document.getElementById('avatarCropOverlay');
  if (overlay) overlay.style.display = 'none';
}

function _cropConfirm() {
  const { img, stageW, stageH, circleSize, imgX, imgY, imgW, imgH, zoom } = _crop;
  if (!img) return;
  const offscreen = document.createElement('canvas');
  const size = 300; offscreen.width = offscreen.height = size;
  const oc = offscreen.getContext('2d');
  const drawW = imgW * zoom, drawH = imgH * zoom;
  const drawX = imgX - (zoom - 1) * imgW / 2;
  const drawY = imgY - (zoom - 1) * imgH / 2;
  const cx = stageW / 2, cy = stageH / 2, r = circleSize / 2;
  const scaleX = img.naturalWidth / drawW, scaleY = img.naturalHeight / drawH;
  const srcX = (cx - r - drawX) * scaleX, srcY = (cy - r - drawY) * scaleY;
  const srcW = circleSize * scaleX, srcH = circleSize * scaleY;
  oc.save();
  oc.beginPath(); oc.arc(size / 2, size / 2, size / 2, 0, Math.PI * 2); oc.clip();
  oc.drawImage(img, srcX, srcY, srcW, srcH, 0, 0, size, size);
  oc.restore();
  const dataURL = offscreen.toDataURL('image/png');


  const avatarImg     = document.getElementById('avatarImg');
  const avatarInitial = document.getElementById('avatarInitial');
  if (avatarImg) { avatarImg.src = dataURL; avatarImg.style.display = 'block'; }
  if (avatarInitial) avatarInitial.style.display = 'none';
  renderSidebarAvatar({ photoDataUrl: dataURL });


  const p = loadParent();
  p.photoDataUrl = dataURL;
  saveParent(p);
  _cropClose();
}


const PAGE_IDS = ['overview', 'grades', 'messages', 'settings'];

function showPage(name, menuEl) {
  PAGE_IDS.forEach(id => {
    const el = document.getElementById('page-' + id);
    if (el) el.classList.toggle('active', id === name);
  });


  document.querySelectorAll('.menu-item').forEach(m => m.classList.remove('active'));
  const btnSettings = document.getElementById('btn-settings');
  if (btnSettings) btnSettings.classList.remove('active');

  if (name === 'settings') {
    if (btnSettings) btnSettings.classList.add('active');
    applyParentProfile(currentParentProfile());
  } else if (menuEl) {
    menuEl.classList.add('active');
  }
}


const msgs = [
  { sender: 'อ.สมชาย วิชาการ', subject: 'วิทยาศาสตร์ ม.4/2', time: 'เมื่อวาน 14:30', body: 'กานต์ทำได้ดีมากในการทดสอบกลางภาค ขอแนะนำให้ฝึกเรื่องสมการเพิ่มเติมก่อนปลายภาคครับ เพื่อให้ผลสอบปลายภาคออกมาดียิ่งขึ้น' },
  { sender: 'อ.วราภรณ์ ภาษาไทย', subject: 'ภาษาไทย ม.4/2', time: '23 พ.ค. 10:15', body: 'เรื่องการส่งงานเขียนเรียงความ กรุณาแจ้งให้กานต์ส่งงานภายในศุกร์นี้ด้วยนะคะ มิฉะนั้นจะมีผลต่อคะแนนเก็บ' },
  { sender: 'อ.ประเสริฐ คณิตศาสตร์', subject: 'คณิตศาสตร์ ม.4/2', time: '20 พ.ค. 09:00', body: 'แจ้งผลสอบกลางภาค: กานต์ได้ 88 คะแนน อยู่ในเกณฑ์ดีมาก ขอให้รักษาระดับนี้ต่อไปครับ' },
];

function openMsg(i) {
  const m = msgs[i];
  if (!m) return;
  document.getElementById('detailSender').textContent  = m.sender;
  document.getElementById('detailSubject').textContent = m.subject;
  document.getElementById('detailTime').textContent    = m.time;
  document.getElementById('detailBody').textContent    = m.body;
  document.getElementById('msgOverlay').classList.add('open');
}

function closeMsg(e) {
  if (e.target === document.getElementById('msgOverlay')) {
    document.getElementById('msgOverlay').classList.remove('open');
  }
}


let _parentData  = null;
let _children    = [];
let _activeChild = 0;
let _dashboardRefreshing = false;
let _overviewSubjects = [];

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);
}

function formatActivity(value) {
  if (!value) return 'ยังไม่มีประวัติการเรียน';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? 'ยังไม่มีประวัติการเรียน' : 'ทำกิจกรรมล่าสุด ' + new Intl.DateTimeFormat('th-TH', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
}

function updateDashboardSyncStatus(message) {
  ['overviewLastUpdated', 'gradesLastUpdated'].forEach(id => {
    const status = document.getElementById(id);
    if (status) status.textContent = message;
  });
}


async function loadDashboardData() {
  if (_dashboardRefreshing) return;
  _dashboardRefreshing = true;
  const selectedStudentId = _children[_activeChild]?.student_id;
  try {
    const res    = await fetch('parent_dashboard_api.php', { credentials: 'same-origin', cache: 'no-store' });
    const result = await res.json();

    if (result.status !== 'success') {
      console.warn('API error:', result.message);
      updateDashboardSyncStatus('อัปเดตข้อมูลไม่สำเร็จ');
      return;
    }

    const updatedAt = new Intl.DateTimeFormat('th-TH', { hour: '2-digit', minute: '2-digit', second: '2-digit' }).format(new Date());
    updateDashboardSyncStatus(`ข้อมูลจากฐานข้อมูล · อัปเดตล่าสุด ${updatedAt}`);

    const settingsOpen = document.getElementById('page-settings')?.classList.contains('active');
    _parentData = result.parent;
    _children   = result.children || [];


    if (_parentData) {
      const p = loadParent();
      p.name  = _parentData.parents_name || 'ผู้ปกครอง';
      p.email = _parentData.email || '';
      p.phone = _parentData.phone || '';
      p.avatarInitials = p.name.slice(0, 2);
      saveParent(p);
      if (!settingsOpen) applyParentProfile(p);
    }


    renderChildTabs();


    if (_children.length > 0) {
      const selectedIndex = _children.findIndex(child => String(child.student_id) === String(selectedStudentId));
      switchChild(selectedIndex >= 0 ? selectedIndex : 0, null);
    } else {
      renderLearningProgress([]);
      renderCourseChart([]);
      renderGradeTable([], {});
      const subtitle = document.getElementById('overviewSubtitle');
      if (subtitle) subtitle.textContent = 'ยังไม่มีนักเรียนที่เชื่อมโยงกับบัญชีนี้';
    }

  } catch (err) {
    console.warn('loadDashboardData error:', err);
    updateDashboardSyncStatus('เชื่อมต่อฐานข้อมูลไม่สำเร็จ');
  } finally {
    _dashboardRefreshing = false;
  }
}


const TAB_COLORS = [
  { bg: 'rgba(255,122,0,0.2)',  text: 'var(--accent)' },
  { bg: 'rgba(59,130,246,0.2)', text: 'var(--blue)' },
  { bg: 'rgba(34,197,94,0.2)',  text: 'var(--green)' },
  { bg: 'rgba(168,85,247,0.2)', text: 'var(--purple)' },
];


const TAB_CONTAINERS = ['childTabsOverview', 'childTabsGrades'];

function renderChildTabs() {
  TAB_CONTAINERS.forEach(containerId => {
    const container = document.getElementById(containerId);
    if (!container) return;

    if (_children.length === 0) {
      container.innerHTML = '<div style="font-size:.82rem;color:var(--text-muted);padding:6px 0;">ยังไม่มีบุตรหลานที่ผูกกับบัญชีนี้</div>';
      return;
    }


    if (_children.length === 1) {
      container.innerHTML = '';
      return;
    }

    container.innerHTML = _children.map((child, i) => {
      const color   = TAB_COLORS[i % TAB_COLORS.length];
      const initial = child.initial || child.student_name.charAt(0);
      const level   = child.student_level
        ? '<span style="font-size:0.72rem;opacity:.7;margin-left:4px;">' + escapeHtml(child.student_level) + '</span>'
        : '';
      const avInner = child.avatar_url
        ? '<img src="' + escapeHtml(child.avatar_url) + '" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">'
        : escapeHtml(initial);
      return '<div class="child-tab' + (i === _activeChild ? ' active' : '') + '"'
        + ' onclick="switchChild(' + i + ', this)"'
        + ' data-child-idx="' + i + '"'
        + ' title="' + escapeHtml(child.student_name) + '">'
        + '<div class="child-av" style="background:' + color.bg + ';color:' + color.text + ';overflow:hidden;">' + avInner + '</div>'
        + escapeHtml(child.student_name) + level
        + '</div>';
    }).join('');
  });
}



function switchChild(idx, el) {
  _activeChild = idx;


  TAB_CONTAINERS.forEach(containerId => {
    const container = document.getElementById(containerId);
    if (!container) return;
    container.querySelectorAll('.child-tab').forEach(tab => {
      const tabIdx = parseInt(tab.dataset.childIdx ?? '-1', 10);
      tab.classList.toggle('active', tabIdx === idx);
    });
  });

  const child = _children[idx];
  if (!child) return;


  const h1 = document.querySelector('#page-overview .page-header h1');
  if (h1 && _parentData) {
    h1.textContent = 'สวัสดี ผู้ปกครองของ' + child.student_name + ' ';
  }

  const subtitle = document.getElementById('overviewSubtitle');
  if (subtitle) subtitle.textContent = [child.student_level, formatActivity(child.stats?.last_activity_at)].filter(Boolean).join(' · ');

  renderChildStats(child);
}


function renderChildStats(child) {
  const stats = child.stats || {};

  const h1 = document.querySelector('#page-overview .page-header h1');
  if (h1 && _parentData) h1.textContent = 'สวัสดี ผู้ปกครองของ' + child.student_name + ' ';


  _overviewSubjects = Array.isArray(child.subjects) ? child.subjects : [];
  const courseCount = document.getElementById('overviewCourseCount');
  const quizCount = document.getElementById('overviewQuizCount');
  const scoreTotal = document.getElementById('overviewScoreTotal');
  if (courseCount) courseCount.textContent = stats.course_count ?? _overviewSubjects.length;
  if (quizCount) quizCount.textContent = `${stats.attempted_lessons ?? 0}/${stats.total_lessons ?? 0}`;
  if (scoreTotal) scoreTotal.textContent = `${formatDashboardNumber(stats.score_earned ?? sumSubjectValue(_overviewSubjects, 'score_earned'))}/${formatDashboardNumber(stats.score_possible ?? sumSubjectValue(_overviewSubjects, 'score_possible'))}`;
  renderOverviewCourseTable();
  renderGradeTable(child.subjects || [], stats);
  renderCourseChart(child.subjects || []);
  renderLearningProgress(child.subjects || []);
}

function formatDashboardNumber(value) {
  const number = Number(value) || 0;
  return Number.isInteger(number) ? String(number) : number.toFixed(1);
}

function sumSubjectValue(subjects, key) {
  return subjects.reduce((total, subject) => total + (Number(subject[key]) || 0), 0);
}

function renderOverviewCourseTable() {
  const tbody = document.getElementById('overviewCourseBody');
  if (!tbody) return;
  const query = (document.getElementById('overviewCourseSearch')?.value || '').trim().toLocaleLowerCase('th');
  const subjects = _overviewSubjects.filter(subject => String(subject.subject_name || '').toLocaleLowerCase('th').includes(query));
  if (!subjects.length) {
    tbody.innerHTML = `<tr><td colspan="4" class="overview-course-empty">${_overviewSubjects.length ? 'ไม่พบรายวิชาที่ค้นหา' : 'ยังไม่มีรายวิชาที่ลงทะเบียน'}</td></tr>`;
    return;
  }
  tbody.innerHTML = subjects.map(subject => {
    const originalIndex = _overviewSubjects.indexOf(subject);
    const attempted = Number(subject.attempted_lessons) || 0;
    const total = Number(subject.lesson_count) || 0;
    const score = attempted
      ? `${formatDashboardNumber(subject.score_earned)}/${formatDashboardNumber(subject.score_possible)} คะแนน`
      : 'ยังไม่มีคะแนน';
    const subjectType = subject.subject_type === 'required' ? 'วิชาบังคับ' : 'วิชาเลือก';
    return `<tr>
      <td>${String(originalIndex + 1).padStart(2, '0')}</td>
      <td><span class="overview-course-name">${escapeHtml(subject.subject_name)}</span><span class="overview-course-tag">${subjectType}</span></td>
      <td>ทำแล้ว ${attempted}/${total} บท</td>
      <td>${score}</td>
    </tr>`;
  }).join('');
}

function renderCourseChart(subjects) {
  const chart = document.getElementById('courseChart');
  if (!chart) return;
  if (!subjects.length) {
    chart.innerHTML = '<div class="empty-state">ยังไม่มีข้อมูลรายวิชาสำหรับแสดงกราฟ</div>';
    return;
  }

  chart.innerHTML = subjects.map(subject => {
    const progress = Math.max(0, Math.min(100, Number(subject.progress) || 0));
    const attempted = Number(subject.attempted_lessons) || 0;
    const total = Number(subject.lesson_count) || 0;
    return `<div class="course-chart-row">
      <div class="course-chart-meta"><strong class="course-chart-name" title="${escapeHtml(subject.subject_name)}">${escapeHtml(subject.subject_name)}</strong><span>${attempted}/${total} บทเรียน</span></div>
      <div class="course-chart-progress" role="img" aria-label="${escapeHtml(subject.subject_name)} ทำแบบทดสอบแล้ว ${attempted} จาก ${total} บทเรียน คิดเป็น ${progress} เปอร์เซ็นต์"><div class="course-chart-track"><span class="course-chart-fill" style="width:${progress}%"></span></div><strong>${progress}%</strong></div>
    </div>`;
  }).join('');
}

function renderGradeTable(subjects, stats) {
  const tbody = document.getElementById('gradeBody');
  if (!tbody) return;

  if (!subjects.length) {
    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--text-muted);">ยังไม่มีข้อมูลคะแนน</td></tr>';
    document.getElementById('topScore').textContent = '—';
    document.getElementById('topSubject').textContent = 'ยังไม่มีคะแนน';
    document.getElementById('gradeLessonsDone').textContent = '0';
    document.getElementById('gradeLessonsTotal').textContent = 'จาก 0 บท';
    return;
  }

  tbody.innerHTML = subjects.map(s => {
    const attempted = Number(s.attempted_lessons) || 0;
    const lessonCount = Number(s.lesson_count) || 0;
    const courseStatus = subjectResultStatus(s);
    const scoreLabel = attempted
      ? `${Number(s.score_earned) || 0}/${Number(s.score_possible) || 0} คะแนน`
      : '—';
    const statusScore = attempted
      ? `คะแนน ${Number(s.score_earned) || 0}/${Number(s.score_possible) || 0}`
      : 'ยังไม่มีคะแนน';
    return `<tr>
      <td>${escapeHtml(s.subject_name)}</td>
      <td>${scoreLabel}</td>
      <td>${attempted}/${lessonCount} บท</td>
      <td><span class="grade-pill ${courseStatus.className}">${courseStatus.label}</span><div class="grade-status-score">${statusScore}</div></td>
    </tr>`;
  }).join('');


  const topScoreEl = document.getElementById('topScore');
  if (topScoreEl) topScoreEl.textContent = stats.top_score ?? '—';

  const topSubEl = document.getElementById('topSubject');
  if (topSubEl) topSubEl.textContent = stats.top_subject ? 'วิชา' + stats.top_subject : 'ยังไม่มีคะแนน';
  const doneEl = document.getElementById('gradeLessonsDone');
  if (doneEl) doneEl.textContent = stats.attempted_lessons ?? 0;
  const totalEl = document.getElementById('gradeLessonsTotal');
  if (totalEl) totalEl.textContent = `จาก ${stats.total_lessons ?? 0} บท`;
}

function subjectResultStatus(subject) {
  const attempted = Number(subject.attempted_lessons) || 0;
  const total = Number(subject.lesson_count) || 0;
  const passed = Number(subject.passed_lessons) || 0;
  const failed = Number(subject.failed_lessons) || 0;
  if (failed > 0) return { label: 'มีบทไม่ผ่าน', className: 'grade-d' };
  if (total > 0 && passed >= total) return { label: 'ผ่านทุกบท', className: 'grade-a' };
  if (attempted === 0) return { label: 'ยังไม่เริ่ม', className: 'grade-c' };
  return { label: 'กำลังเรียน', className: 'grade-b' };
}

function lessonResultStatus(lesson) {
  switch (lesson.result_status) {
    case 'passed': return { label: 'ผ่าน', className: 'grade-a' };
    case 'failed': return { label: 'ไม่ผ่าน', className: 'grade-d' };
    case 'pending_review': return { label: 'รอตรวจข้อเขียน', className: 'grade-pending' };
    case 'not_attempted': return { label: 'ยังไม่ทำ', className: 'grade-c' };
    default: return { label: 'รอผล', className: 'grade-c' };
  }
}

function renderLearningProgress(subjects) {
  const list = document.getElementById('progressList');
  if (!list) return;
  if (!subjects.length) {
    list.innerHTML = '<div class="empty-state">ยังไม่มีรายวิชาหรือข้อมูลการเรียนของบุตรหลาน</div>';
    return;
  }
  list.innerHTML = subjects.map(subject => {
    const progress = Math.max(0, Math.min(100, Number(subject.progress) || 0));
    const courseStatus = subjectResultStatus(subject);
    const lessons = Array.isArray(subject.lessons) ? subject.lessons : [];
    const lessonRows = lessons.length ? lessons.map((lesson, index) => {
      const status = lessonResultStatus(lesson);
      const essayCount = Number(lesson.essay_count) || 0;
      const pendingEssays = Number(lesson.pending_essays) || 0;
      const essayDetail = essayCount === 0 ? '' : pendingEssays > 0
        ? `<div class="course-lesson-essay pending">ข้อสอบเขียน: รอตรวจ ${pendingEssays} ข้อ</div>`
        : `<div class="course-lesson-essay">ข้อสอบเขียน: ตรวจแล้ว ${Number(lesson.essay_score) || 0}/${Number(lesson.essay_score_possible) || 0} คะแนน</div>`;
      const scoreDetail = lesson.has_attempt && Number(lesson.score_total) > 0
        ? `<span class="course-lesson-score">ทำได้ ${Number(lesson.score_earned) || 0}/${Number(lesson.score_total)} คะแนน</span>` : '';
      return `<div class="course-lesson-item">
        <div class="course-lesson-info"><strong>บทที่ ${Number(lesson.position) || index + 1}: ${escapeHtml(lesson.title)}</strong>${scoreDetail}</div>
        <span class="grade-pill ${status.className}">${status.label}</span>
        ${essayDetail}
      </div>`;
    }).join('') : '<div class="empty-state">ยังไม่มีบทเรียนในรายวิชานี้</div>';
    return `<article class="progress-course">
      <div class="progress-course-head"><div><h3>${escapeHtml(subject.subject_name)}</h3><p>${formatActivity(subject.last_activity_at)}</p></div><span class="grade-pill ${courseStatus.className}">${courseStatus.label}</span></div>
      <div class="progress-course-meta"><span>ทำแบบทดสอบ ${Number(subject.attempted_lessons) || 0} จาก ${Number(subject.lesson_count) || 0} บท</span><strong>${progress}%</strong></div>
      <div class="prog-bg"><div class="prog-fill" style="width:${progress}%"></div></div>
      <div class="course-lesson-results">${lessonRows}</div>
    </article>`;
  }).join('');
}

document.addEventListener('DOMContentLoaded', () => {
  applyParentProfile(loadParent());
  document.getElementById('overviewCourseSearch')?.addEventListener('input', renderOverviewCourseTable);
  loadDashboardData();
  window.setInterval(() => {
    if (document.visibilityState === 'visible') loadDashboardData();
  }, 10000);
  window.addEventListener('focus', () => loadDashboardData());
});
