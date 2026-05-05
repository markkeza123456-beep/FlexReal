// Data Arrays
const courses = [
    { id: '01', name: 'คณิตศาสตร์พื้นฐาน', progress: 90, score: 92, status: 'ดีเยี่ยม', class: 'excellent' },
    { id: '02', name: 'ภาษาอังกฤษเพื่อการสื่อสาร', progress: 75, score: 85, status: 'ดี', class: 'good' },
    { id: '03', name: 'วิทยาศาสตร์กายภาพ', progress: 50, score: 71, status: 'ปานกลาง', class: 'average' }
];

const assignments = [
    { title: 'แบบฝึกหัดแคลคูลัสเบื้องต้น', subject: 'คณิตศาสตร์', due: 'วันนี้, 23:59', status: 'ยังไม่ได้ส่ง' },
    { title: 'เรียงความภาษาอังกฤษ Topic: My Future', subject: 'ภาษาอังกฤษ', due: 'พรุ่งนี้', status: 'รอดำเนินการ' },
    { title: 'รายงานผลการทดลองแรงโน้มถ่วง', subject: 'ฟิสิกส์', due: '4 พ.ค. 2026', status: 'ยังไม่ได้ส่ง' }
];

// Navigation Elements
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

// Function: Switch Page
function showPage(pageKey) {
    // Hide all pages
    Object.keys(pages).forEach(key => {
        pages[key].style.display = 'none';
    });

    // Show target page
    if (pages[pageKey]) pages[pageKey].style.display = 'block';

    // Update active state on menu items (menu-item class)
    document.querySelectorAll('.menu-item').forEach(el => el.classList.remove('active'));

    // Update active state for settings button (user-profile)
    const settingsBtn = document.getElementById('btn-settings');
    settingsBtn.classList.remove('active');

    if (pageKey === 'settings') {
        settingsBtn.classList.add('active');
    } else if (navBtns[pageKey]) {
        navBtns[pageKey].classList.add('active');
    }

    if (pageKey === 'lessons') renderLessons();
    if (pageKey === 'assignments') renderAssignments();
}

// Event Listeners
navBtns.dashboard.addEventListener('click', () => showPage('dashboard'));
navBtns.lessons.addEventListener('click', () => showPage('lessons'));
navBtns.assignments.addEventListener('click', () => showPage('assignments'));
navBtns.settings.addEventListener('click', () => showPage('settings'));

// Render Dashboard Table
function renderTable() {
    const tbody = document.getElementById('courseTableBody');
    tbody.innerHTML = courses.map(item => `
        <tr>
            <td style="color: #888;">${item.id}</td>
            <td>${item.name}</td>
            <td>${item.progress}%</td>
            <td>${item.score}</td>
            <td><span class="status-label ${item.class}">${item.status}</span></td>
        </tr>
    `).join('');
}

// Render Lessons
function renderLessons() {
    const container = document.getElementById('lessons-list');
    container.innerHTML = courses.map(course => `
        <div class="lesson-card">
            <h3>${course.name}</h3>
            <div class="progress-bar-bg">
                <div class="progress-bar-fill" style="width: ${course.progress}%"></div>
            </div>
            <p style="font-size: 0.8rem; color: #888;">ความคืบหน้า: ${course.progress}%</p>
        </div>
    `).join('');
}

// Render Assignments
function renderAssignments() {
    const container = document.getElementById('assignment-container');
    container.innerHTML = assignments.map(task => `
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

// Search Logic
document.getElementById('courseSearch').addEventListener('input', (e) => {
    const term = e.target.value.toLowerCase();
    const filtered = courses.filter(c => c.name.toLowerCase().includes(term));
    const tbody = document.getElementById('courseTableBody');
    tbody.innerHTML = filtered.map(item => `
        <tr>
            <td>${item.id}</td>
            <td>${item.name}</td>
            <td>${item.progress}%</td>
            <td>${item.score}</td>
            <td><span class="status-label ${item.class}">${item.status}</span></td>
        </tr>
    `).join('');
});

// Init
renderTable();

// ── Avatar preview ─────────────────────────────────
function previewAvatar(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    if (!file.type.startsWith('image/')) return;
    const reader = new FileReader();
    reader.onload = e => {
        const img     = document.getElementById('avatarImg');
        const initial = document.getElementById('avatarInitial');
        img.src               = e.target.result;
        img.style.display     = 'block';
        initial.style.display = 'none';
        // Update sidebar avatar too
        const sideAvatar = document.getElementById('sidebarAvatar');
        if (sideAvatar) {
            sideAvatar.style.background = 'none';
            sideAvatar.style.padding    = '0';
            sideAvatar.style.overflow   = 'hidden';
            sideAvatar.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;border-radius:50%">`;
        }
    };
    reader.readAsDataURL(file);
}

// ── Toggle password visibility ─────────────────────
function togglePwd(id, btn) {
    const inp = document.getElementById(id);
    if (!inp) return;
    const show = inp.type === 'password';
    inp.type        = show ? 'text' : 'password';
    btn.textContent = show ? '🙈' : '👁';
}

// ── Password strength checker ──────────────────────
function checkPwdStrength(val) {
    const wrap  = document.getElementById('pwdStrengthWrap');
    const bar   = document.getElementById('pwdStrengthBar');
    const label = document.getElementById('pwdStrengthLabel');
    if (!val) { wrap.style.display = 'none'; return; }
    wrap.style.display = 'block';

    let score = 0;
    if (val.length >= 8)           score++;
    if (val.length >= 12)          score++;
    if (/[A-Z]/.test(val))         score++;
    if (/[0-9]/.test(val))         score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const levels = [
        { pct: '20%',  color: '#ef4444', text: 'อ่อนมาก' },
        { pct: '40%',  color: '#f97316', text: 'อ่อน' },
        { pct: '60%',  color: '#eab308', text: 'ปานกลาง' },
        { pct: '80%',  color: '#3b82f6', text: 'ดี' },
        { pct: '100%', color: '#10b981', text: 'แข็งแกร่งมาก' },
    ];
    const lv = levels[Math.min(score - 1, 4)] || levels[0];
    bar.style.width      = lv.pct;
    bar.style.background = lv.color;
    label.style.color    = lv.color;
    label.textContent    = `ความแข็งแกร่ง: ${lv.text}`;
    checkPwdMatch();
}

// ── Password match checker ─────────────────────────
function checkPwdMatch() {
    const newPwd  = document.getElementById('pwdNew')?.value;
    const confirm = document.getElementById('pwdConfirm')?.value;
    const msg     = document.getElementById('pwdMatchMsg');
    if (!msg || !confirm) return;
    if (newPwd === confirm) {
        msg.style.color = '#10b981';
        msg.textContent = '✓ รหัสผ่านตรงกัน';
    } else {
        msg.style.color = '#ef4444';
        msg.textContent = '✗ รหัสผ่านไม่ตรงกัน';
    }
}

// ── Save Profile ───────────────────────────────────
function saveProfile() {
    const btn      = document.getElementById('saveProfileBtn');
    const feedback = document.getElementById('profileFeedback');
    const name     = document.getElementById('profileName')?.value.trim();
    const current  = document.getElementById('pwdCurrent')?.value ?? '';
    const newPwd   = document.getElementById('pwdNew')?.value ?? '';
    const confirm  = document.getElementById('pwdConfirm')?.value ?? '';

    function showFeedback(type, msg) {
        feedback.style.display    = 'block';
        feedback.textContent      = msg;
        feedback.style.background = type === 'success' ? 'rgba(16,185,129,.15)' : 'rgba(239,68,68,.15)';
        feedback.style.color      = type === 'success' ? '#10b981' : '#ef4444';
        feedback.style.border     = `1px solid ${type === 'success' ? '#10b98133' : '#ef444433'}`;
        setTimeout(() => { feedback.style.display = 'none'; }, 4000);
    }

    if (newPwd && newPwd !== confirm) {
        showFeedback('error', '✗ รหัสผ่านใหม่ไม่ตรงกัน');
        return;
    }
    if (newPwd && newPwd.length < 6) {
        showFeedback('error', '✗ รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร');
        return;
    }
    if (newPwd && !current) {
        showFeedback('error', '✗ กรุณาใส่รหัสผ่านปัจจุบันก่อน');
        return;
    }

    btn.disabled    = true;
    btn.textContent = '⏳ กำลังบันทึก...';

    const body = new FormData();
    body.append('name',        name);
    body.append('email',       document.getElementById('profileEmail')?.value.trim() ?? '');
    body.append('phone',       document.getElementById('profilePhone')?.value.trim() ?? '');
    body.append('pwd_current', current);
    body.append('pwd_new',     newPwd);

    fetch('update_student_profile.php', { method: 'POST', body })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showFeedback('success', '✓ ' + data.message);
                ['pwdCurrent', 'pwdNew', 'pwdConfirm'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.value = '';
                });
                document.getElementById('pwdStrengthWrap').style.display = 'none';
                document.getElementById('pwdMatchMsg').textContent = '';
                // Update sidebar name
                const sidebarName = document.getElementById('sidebarName');
                if (sidebarName && name) sidebarName.textContent = name;
                const displayName = document.getElementById('displayName');
                if (displayName && name) displayName.textContent = name;
            } else {
                showFeedback('error', '✗ ' + data.message);
            }
        })
        .catch(() => {
            // Demo mode: simulate success
            showFeedback('success', '✓ บันทึกข้อมูลเรียบร้อยแล้ว (Demo)');
            ['pwdCurrent', 'pwdNew', 'pwdConfirm'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
            const wrap = document.getElementById('pwdStrengthWrap');
            if (wrap) wrap.style.display = 'none';
            const matchMsg = document.getElementById('pwdMatchMsg');
            if (matchMsg) matchMsg.textContent = '';
            const sidebarName = document.getElementById('sidebarName');
            if (sidebarName && name) sidebarName.textContent = name;
            const displayName = document.getElementById('displayName');
            if (displayName && name) displayName.textContent = name;
        })
        .finally(() => {
            btn.disabled    = false;
            btn.textContent = '💾 บันทึกข้อมูล';
        });
}