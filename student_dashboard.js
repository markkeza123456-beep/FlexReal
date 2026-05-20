let dashboardState = {
    student: null,
    courses: [],
    assignments: []
};

const navBtns = {
    dashboard: document.getElementById('btn-dashboard'),
    lessons: document.getElementById('btn-lessons'),
    assignments: document.getElementById('btn-assignments'),
    settings: document.getElementById('btn-settings')
};

const pages = {
    dashboard: document.getElementById('dashboard-page'),
    lessons: document.getElementById('lesson-page'),
    assignments: document.getElementById('assignment-page'),
    settings: document.getElementById('settings-page')
};

function showPage(pageKey) {
    Object.keys(pages).forEach((key) => {
        const isActive = key === pageKey;
        if (isActive) {
            pages[key].style.display = key === 'settings' ? 'flex' : 'block';
        } else {
            pages[key].style.display = 'none';
        }
        if (navBtns[key]) {
            navBtns[key].classList.toggle('active', isActive);
        }
    });

    if (pageKey === 'lessons') {
        renderLessons(dashboardState.courses);
    } else if (pageKey === 'assignments') {
        renderAssignments(dashboardState.assignments);
    }
}

function statusClass(score) {
    if (score >= 85) return 'excellent';
    if (score >= 70) return 'good';
    if (score >= 50) return 'average';
    return 'needs-help';
}

function statusLabel(score) {
    const map = {
        excellent: 'ดีเยี่ยม',
        good: 'ดี',
        average: 'ปานกลาง',
        'needs-help': 'ต้องดูแล'
    };
    return map[statusClass(score)] || 'เริ่มต้น';
}

function renderTable(courses) {
    const tbody = document.getElementById('courseTableBody');
    if (!courses.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" style="text-align:center;color:#888;">ยังไม่มีบทเรียนที่ลงทะเบียน</td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = courses.map((item) => `
        <tr>
            <td style="color: #888;">${item.id}</td>
            <td>${item.name}</td>
            <td>${Number(item.progress).toFixed(1)}%</td>
            <td>${Number(item.score).toFixed(1)}%</td>
            <td><span class="status-label ${item.class}">${item.status}</span></td>
        </tr>
    `).join('');
}

function renderLessons(courses) {
    const container = document.getElementById('lessons-list');
    if (!courses.length) {
        container.innerHTML = '<div class="lesson-card"><h3>ยังไม่มีบทเรียนที่ลงทะเบียน</h3><p>เข้าไปที่หน้าเว็บไซต์หลักเพื่อเลือกบทเรียนที่สนใจ</p></div>';
        return;
    }

    container.innerHTML = courses.map((course) => `
        <div class="lesson-card">
            <h3>${course.name}</h3>
            <div class="progress-bar-bg">
                <div class="progress-bar-fill" style="width: ${course.progress}%"></div>
            </div>
            <p style="font-size: 0.8rem; color: #888;">ความคืบหน้า: ${Number(course.progress).toFixed(1)}%</p>
            <p style="font-size: 0.8rem; color: #888;">คะแนนสูงสุด: ${Number(course.score).toFixed(1)}%</p>
            <a class="btn-submit" style="display:inline-block;text-decoration:none;margin-top:10px;" href="web.html?subject_id=${encodeURIComponent(course.subject_id)}">เข้าเรียนต่อ</a>
        </div>
    `).join('');
}

function renderAssignments(assignments) {
    const container = document.getElementById('assignment-container');
    if (!assignments.length) {
        container.innerHTML = `
            <div class="assignment-item">
                <div class="assign-info">
                    <h3>ยังไม่มีงานที่มอบหมายจากระบบ</h3>
                    <p>เมื่อมีงานหรือแบบฝึกหัดใหม่ รายการจะแสดงที่หน้านี้</p>
                </div>
            </div>
        `;
        return;
    }

    container.innerHTML = assignments.map((task) => `
        <div class="assignment-item">
            <div class="assign-info">
                <h3>${task.title}</h3>
                <p>วิชา: ${task.subject} | สถานะ: ${task.status}</p>
            </div>
            <div style="text-align: right;">
                <p class="due-date">กำหนดส่ง: ${task.due}</p>
                <button class="btn-submit" style="margin-top: 8px;">ส่งงาน</button>
            </div>
        </div>
    `).join('');
}

function updateProfile(student) {
    if (!student) return;
    const name = student.name || 'นักเรียน';
    const firstChar = name.trim().charAt(0) || 'S';
    const className = student.class_name || '-';

    const setValue = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.value = value;
    };
    const setText = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    };

    setText('dashboardWelcome', `ยินดีต้อนรับกลับมา, ${name} 👋`);
    setText('sidebarName', name);
    setText('displayName', name);
    setText('sidebarAvatar', firstChar);
    setText('avatarInitial', firstChar);
    setValue('profileName', name);
    setValue('profileEmail', student.email || '');
    setValue('profilePhone', student.phone || '');

    setValue('profileClass', className);
    setText('sidebarRole', 'นักเรียน - ' + className);
    setText('profileRole', 'นักเรียน · ' + className);

    const roleTexts = document.querySelectorAll('.role, .avatar-role');
    roleTexts.forEach((el) => {
        if (!el.id) {
            el.textContent = 'นักเรียน - ' + className;
        }
    });
}

function updateStats(stats, courses) {
    const courseCount = Number(stats?.course_count || 0);
    const avgProgress = Number(stats?.avg_progress || 0).toFixed(1);
    const avgScore = Number(stats?.avg_score || 0).toFixed(1);

    const setText = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    };
    setText('statCourseCount', String(courseCount));
    setText('statAvgProgress', avgProgress + '%');
    setText('statAvgScore', avgScore + '%');

    let stateText = 'เริ่มต้น';
    if (courseCount > 0 && Number(avgProgress) >= 80) {
        stateText = 'ก้าวหน้า';
    } else if (courseCount > 0 && Number(avgProgress) > 0) {
        stateText = 'กำลังเรียน';
    }
    setText('statLearningState', stateText);

    renderTable(courses);
}

async function loadDashboardData() {
    const response = await fetch('student_dashboard_api.php', {
        credentials: 'same-origin'
    });
    const result = await response.json();
    if (result.status !== 'success') {
        throw new Error(result.message || 'load failed');
    }

    dashboardState = {
        student: result.student || null,
        courses: Array.isArray(result.courses) ? result.courses : [],
        assignments: Array.isArray(result.assignments) ? result.assignments : []
    };

    updateProfile(dashboardState.student);
    updateStats(result.stats || {}, dashboardState.courses);
    renderLessons(dashboardState.courses);
    renderAssignments(dashboardState.assignments);
}

Object.entries(navBtns).forEach(([key, button]) => {
    if (!button) return;
    button.addEventListener('click', () => showPage(key));
});

document.getElementById('courseSearch')?.addEventListener('input', (e) => {
    const term = e.target.value.toLowerCase();
    const filtered = dashboardState.courses.filter((course) => course.name.toLowerCase().includes(term));
    renderTable(filtered);
});

showPage('dashboard');
loadDashboardData().catch(() => {
    renderTable([]);
    renderLessons([]);
    renderAssignments([]);
});

/* ─────────────────────────────────────────────────
   Avatar Crop System
   ───────────────────────────────────────────────── */
const _crop = {
    img: null,
    imgX: 0, imgY: 0,
    imgW: 0, imgH: 0,
    zoom: 1,
    dragging: false,
    lastX: 0, lastY: 0,
    canvas: null, ctx: null,
    stage: null, circle: null,
    stageW: 0, stageH: 0,
    circleSize: 0,
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
        <p style="margin:0 0 1rem;font-size:15px;font-weight:500">✂️ ครอปรูปโปรไฟล์</p>
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
          <span style="font-size:12px;color:#aaa;white-space:nowrap">🔍 ซูม</span>
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
            ✓ ใช้รูปนี้
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

function _cropStartDrag(e) {
    _crop.dragging = true;
    const p = _cropGetXY(e);
    _crop.lastX = p.x;
    _crop.lastY = p.y;
    e.preventDefault();
}

function _cropOnDrag(e) {
    if (!_crop.dragging) return;
    const p = _cropGetXY(e);
    _crop.imgX += p.x - _crop.lastX;
    _crop.imgY += p.y - _crop.lastY;
    _crop.lastX = p.x;
    _crop.lastY = p.y;
    _cropDraw();
    e.preventDefault();
}

function _cropEndDrag() {
    _crop.dragging = false;
}

function _cropSetZoom(v) {
    _crop.zoom = v / 100;
    document.getElementById('cropZoomVal').textContent = v + '%';
    _cropDraw();
}

function _cropDraw() {
    const { ctx, img, imgX, imgY, imgW, imgH, zoom, stageW, stageH } = _crop;
    if (!ctx || !img) return;
    ctx.clearRect(0, 0, stageW, stageH);
    // zoom จากจุดกึ่งกลาง stage
    const drawW = imgW * zoom;
    const drawH = imgH * zoom;
    const drawX = imgX - (zoom - 1) * imgW / 2;
    const drawY = imgY - (zoom - 1) * imgH / 2;
    ctx.drawImage(img, drawX, drawY, drawW, drawH);
}

function _cropInit() {
    _cropInjectModal();
    const stage = document.getElementById('cropStage');
    const canvas = document.getElementById('cropCanvas');
    const circle = document.getElementById('cropCircle');

    _crop.canvas = canvas;
    _crop.ctx = canvas.getContext('2d');
    _crop.stage = stage;
    _crop.circle = circle;
    _crop.stageW = stage.offsetWidth;
    _crop.stageH = stage.offsetHeight;
    _crop.circleSize = Math.min(_crop.stageW, _crop.stageH) * 0.72;

    canvas.width  = _crop.stageW;
    canvas.height = _crop.stageH;

    const cs = _crop.circleSize;
    circle.style.width  = cs + 'px';
    circle.style.height = cs + 'px';
    circle.style.left   = ((_crop.stageW - cs) / 2) + 'px';
    circle.style.top    = ((_crop.stageH - cs) / 2) + 'px';

    _crop.zoom = 1;
    const slider = document.getElementById('cropZoomSlider');
    if (slider) { slider.value = 100; }
    const zoomVal = document.getElementById('cropZoomVal');
    if (zoomVal) { zoomVal.textContent = '100%'; }

    const scale = Math.max(cs / _crop.img.width, cs / _crop.img.height);
    _crop.imgW = _crop.img.width  * scale;
    _crop.imgH = _crop.img.height * scale;
    _crop.imgX = (_crop.stageW - _crop.imgW) / 2;
    _crop.imgY = (_crop.stageH - _crop.imgH) / 2;

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
    const size = 300;
    offscreen.width = offscreen.height = size;
    const oc = offscreen.getContext('2d');

    // ใช้สูตรเดียวกับ _cropDraw
    const drawW = imgW * zoom;
    const drawH = imgH * zoom;
    const drawX = imgX - (zoom - 1) * imgW / 2;
    const drawY = imgY - (zoom - 1) * imgH / 2;

    // วงกลมอยู่กึ่งกลาง stage
    const cx = stageW / 2;
    const cy = stageH / 2;
    const r  = circleSize / 2;

    // แปลงพิกัดวงกลมกลับเป็น source pixel ใน img จริง
    const scaleX = img.naturalWidth  / drawW;
    const scaleY = img.naturalHeight / drawH;
    const srcX = (cx - r - drawX) * scaleX;
    const srcY = (cy - r - drawY) * scaleY;
    const srcW = circleSize * scaleX;
    const srcH = circleSize * scaleY;

    oc.save();
    oc.beginPath();
    oc.arc(size / 2, size / 2, size / 2, 0, Math.PI * 2);
    oc.clip();
    oc.drawImage(img, srcX, srcY, srcW, srcH, 0, 0, size, size);
    oc.restore();

    const dataURL = offscreen.toDataURL('image/png');
    _cropApplyAvatar(dataURL);
    _cropClose();
}

function _cropApplyAvatar(dataURL) {
    const avatarImg     = document.getElementById('avatarImg');
    const avatarInitial = document.getElementById('avatarInitial');
    if (avatarImg)     { avatarImg.src = dataURL; avatarImg.style.display = 'block'; }
    if (avatarInitial) { avatarInitial.style.display = 'none'; }

    const sidebarAvatar = document.getElementById('sidebarAvatar');
    if (sidebarAvatar) {
        sidebarAvatar.style.cssText += ';background:none;padding:0;overflow:hidden';
        sidebarAvatar.innerHTML = `<img src="${dataURL}" style="width:100%;height:100%;object-fit:cover;border-radius:50%">`;
    }
}

function previewAvatar(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    if (!file.type.startsWith('image/')) return;

    const reader = new FileReader();
    reader.onload = (e) => {
        _cropInjectModal();
        _crop.img = new Image();
        _crop.img.onload = () => {
            document.getElementById('avatarCropOverlay').style.display = 'flex';
            // รอ browser layout ก่อนอ่าน offsetWidth
            requestAnimationFrame(() => requestAnimationFrame(() => _cropInit()));
        };
        _crop.img.src = e.target.result;
    };
    reader.readAsDataURL(file);
    input.value = '';
}

function togglePwd(id, btn) {
    const inp = document.getElementById(id);
    if (!inp) return;
    const show = inp.type === 'password';
    inp.type = show ? 'text' : 'password';
    btn.textContent = show ? '🙈' : '👁 ';
}

function checkPwdStrength(val) {
    const wrap = document.getElementById('pwdStrengthWrap');
    const bar = document.getElementById('pwdStrengthBar');
    const label = document.getElementById('pwdStrengthLabel');
    if (!val) {
        wrap.style.display = 'none';
        return;
    }
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
    const newPwd = document.getElementById('pwdNew')?.value;
    const confirm = document.getElementById('pwdConfirm')?.value;
    const msg = document.getElementById('pwdMatchMsg');
    if (!msg || !confirm) return;
    if (newPwd === confirm) {
        msg.style.color = '#10b981';
        msg.textContent = 'รหัสผ่านตรงกัน';
    } else {
        msg.style.color = '#ef4444';
        msg.textContent = 'รหัสผ่านไม่ตรงกัน';
    }
}

function saveProfile() {
    const btn = document.getElementById('saveProfileBtn');
    const feedback = document.getElementById('profileFeedback');
    const name = document.getElementById('profileName')?.value.trim();
    const current = document.getElementById('pwdCurrent')?.value ?? '';
    const newPwd = document.getElementById('pwdNew')?.value ?? '';
    const confirm = document.getElementById('pwdConfirm')?.value ?? '';

    function showFeedback(type, msg) {
        feedback.style.display = 'block';
        feedback.textContent = msg;
        feedback.style.background = type === 'success' ? 'rgba(16,185,129,.15)' : 'rgba(239,68,68,.15)';
        feedback.style.color = type === 'success' ? '#10b981' : '#ef4444';
        feedback.style.border = `1px solid ${type === 'success' ? '#10b98133' : '#ef444433'}`;
        setTimeout(() => { feedback.style.display = 'none'; }, 4000);
    }

    if (newPwd && newPwd !== confirm) {
        showFeedback('error', 'รหัสผ่านใหม่ไม่ตรงกัน');
        return;
    }
    if (newPwd && newPwd.length < 6) {
        showFeedback('error', 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร');
        return;
    }
    if (newPwd && !current) {
        showFeedback('error', 'กรุณาใส่รหัสผ่านปัจจุบันก่อน');
        return;
    }

    btn.disabled = true;
    btn.textContent = 'กำลังบันทึก...';

    const body = new FormData();
    body.append('name',        name);
    body.append('email',       document.getElementById('profileEmail')?.value.trim() ?? '');
    body.append('phone',       document.getElementById('profilePhone')?.value.trim() ?? '');
    body.append('pwd_current', current);
    body.append('pwd_new', newPwd);

    fetch('update_student_profile.php', { method: 'POST', body })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showFeedback('success', data.message);
                if (dashboardState.student) {
                    dashboardState.student.name = name;
                    updateProfile(dashboardState.student);
                }
            } else {
                showFeedback('error', data.message);
            }
        })
        .catch(() => {
            showFeedback('error', 'บันทึกข้อมูลไม่สำเร็จ');
        })
        .finally(() => {
            btn.disabled = false;
            btn.textContent = '💾 บันทึกข้อมูล';
        });
}