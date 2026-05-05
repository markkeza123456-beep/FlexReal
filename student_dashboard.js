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
        pages[key].style.display = key === pageKey ? 'block' : 'none';
        if (navBtns[key]) {
            navBtns[key].classList.toggle('active', key === pageKey);
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
        needs-help: 'ต้องดูแล'
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

    const roleTexts = document.querySelectorAll('.role, .avatar-role');
    roleTexts.forEach((el) => {
        el.textContent = `นักเรียน - ${className}`;
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
    setText('statAvgProgress', `${avgProgress}%`);
    setText('statAvgScore', `${avgScore}%`);

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

function previewAvatar(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    if (!file.type.startsWith('image/')) return;
    const reader = new FileReader();
    reader.onload = (e) => {
        const img = document.getElementById('avatarImg');
        const initial = document.getElementById('avatarInitial');
        img.src = e.target.result;
        img.style.display = 'block';
        initial.style.display = 'none';
        const sideAvatar = document.getElementById('sidebarAvatar');
        if (sideAvatar) {
            sideAvatar.style.background = 'none';
            sideAvatar.style.padding = '0';
            sideAvatar.style.overflow = 'hidden';
            sideAvatar.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;border-radius:50%">`;
        }
    };
    reader.readAsDataURL(file);
}

function togglePwd(id, btn) {
    const inp = document.getElementById(id);
    if (!inp) return;
    const show = inp.type === 'password';
    inp.type = show ? 'text' : 'password';
    btn.textContent = show ? '🙈' : '👁';
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
        { pct: '100%', color: '#10b981', text: 'แข็งแรงมาก' }
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
    body.append('name', name);
    body.append('pwd_current', current);
    body.append('pwd_new', newPwd);

    fetch('update_profile.php', { method: 'POST', body })
        .then((r) => r.json())
        .then((data) => {
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
