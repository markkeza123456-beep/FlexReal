let dashboardState = {
    student: null,
    courses: []
};

const navBtns = {
    dashboard: document.getElementById('btn-dashboard'),
    lessons: document.getElementById('btn-lessons'),
    reports: document.getElementById('btn-reports'),
    settings: document.getElementById('btn-settings')
};

const pages = {
    dashboard: document.getElementById('dashboard-page'),
    lessons: document.getElementById('lesson-page'),
    reports: document.getElementById('reports-page'),
    settings: document.getElementById('settings-page')
};

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);
}

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

    if (pageKey === 'lessons') renderLessons(dashboardState.courses);
    if (pageKey === 'reports') renderReports(dashboardState.courses, dashboardState.stats || {});
}

function statusClass(score) {
    if (score >= 85) return 'excellent';
    if (score >= 70) return 'good';
    if (score >= 50) return 'average';
    return 'needs-help';
}

function statusLabel(score) {
    if (score === null || score === undefined) return 'ยังไม่มีคะแนน';
    const map = {
        excellent: 'ดีเยี่ยม',
        good: 'ดี',
        average: 'ปานกลาง',
        'needs-help': 'ต้องดูแล'
    };
    return map[statusClass(score)] || 'เริ่มต้น';
}

function subjectTypeBadge(subjectType) {
    const type = String(subjectType || 'elective').toLowerCase();
    const label = type === 'required' ? 'วิชาบังคับ' : 'วิชาเลือก';
    return '<span class="subject-type-badge ' + (type === 'required' ? 'required' : 'elective') + '">' + label + '</span>';
}

function renderLessonBreakdown(course) {
    const lessons = Array.isArray(course.lessons) ? course.lessons : [];
    if (!lessons.length) return '<p class="empty-state">รายวิชานี้ยังไม่มีบทเรียน</p>';
    const rows = lessons.map((lesson) => {
        const progress = Math.max(0, Math.min(100, Number(lesson.progress) || 0));
        const fmt = (value) => Number(value || 0).toLocaleString('th-TH', { maximumFractionDigits: 2 });
        const quizScore = lesson.has_attempt ? `${fmt(lesson.score_earned)}/${fmt(lesson.score_total)} คะแนน` : 'ยังไม่มีผลสอบ';
        const parts = [];
        if (Number(lesson.objective_score_total) > 0) parts.push(`ปรนัย ${fmt(lesson.objective_score_earned)}/${fmt(lesson.objective_score_total)}`);
        if (Number(lesson.essay_question_count) > 0) {
            parts.push(`ข้อเขียน ${fmt(lesson.essay_score_earned)}/${fmt(lesson.essay_score_total)}`);
        }
        if (parts.length === 0) parts.push('ยังไม่มีรายละเอียดคะแนน');
        const status = lesson.quiz_status === 'pending_review'
            ? `<span class="lesson-score-status pending">รอตรวจข้อเขียน ${Number(lesson.essay_pending_count)} ข้อ</span>`
            : lesson.quiz_status === 'passed' ? '<span class="lesson-score-status passed">ผ่าน</span>'
                : lesson.quiz_status === 'failed' ? '<span class="lesson-score-status failed">ยังไม่ผ่าน</span>' : '';
        const attemptLabel = lesson.has_attempt ? `ครั้งที่ ${Number(lesson.latest_attempt_number)} · ทำแล้ว ${Number(lesson.attempt_count)} ครั้ง` : 'ยังไม่เคยทำแบบทดสอบ';
        return `<div class="lesson-breakdown-row"><div class="lesson-number">${Number(lesson.position)}</div><div class="lesson-detail"><strong>${escapeHtml(lesson.title)}</strong><span>${attemptLabel}</span><span>คะแนนรวม: ${quizScore}</span><span class="lesson-score-parts">${parts.join(' · ')}</span>${status}<span>ความคืบหน้า ${progress}%</span><div class="progress-bar-bg"><div class="progress-bar-fill" style="width:${progress}%"></div></div></div></div>`;
    }).join('');
    return `<details class="lesson-breakdown"><summary>ดูรายการบทเรียนย่อย (${lessons.length})</summary><div class="lesson-breakdown-list">${rows}</div></details>`;
}

function renderTable(courses) {
    const tbody = document.getElementById('courseTableBody');
    if (!courses.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="4" style="text-align:center;color:#888;">ยังไม่มีบทเรียนที่ลงทะเบียน</td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = courses.map((item) => `
        <tr>
            <td style="color: #888;">${item.id}</td>
            <td>${escapeHtml(item.name)} ${subjectTypeBadge(item.subject_type)}</td>
            <td>ทำแล้ว ${item.attempted_lessons}/${item.lesson_count} บท</td>
            <td>${item.score_total > 0 ? `${item.score_earned}/${item.score_total} คะแนน` : 'ยังไม่มีคะแนน'}</td>
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
            <div class="lesson-card-head"><h3>${escapeHtml(course.name)}</h3>${subjectTypeBadge(course.subject_type)}</div>
            <p class="course-description">${escapeHtml(course.description || 'ไม่มีคำอธิบายรายวิชา')}</p>
            <div class="course-progress-label"><span>ความคืบหน้าแบบทดสอบ</span><strong>${course.progress}%</strong></div>
            <div class="progress-bar-bg">
                <div class="progress-bar-fill" style="width: ${Math.max(0, Math.min(100, Number(course.progress) || 0))}%"></div>
            </div>
            <p style="font-size: 0.8rem; color: #888;">แบบทดสอบในวิชานี้: ${course.attempted_lessons}/${course.lesson_count} บท</p>
            <p style="font-size: 0.8rem; color: #888;">คะแนนสะสม: ${course.score_total > 0 ? `${course.score_earned}/${course.score_total} คะแนน` : 'ยังไม่มีคะแนน'}</p>
            ${renderLessonBreakdown(course)}
            <a class="btn-submit" style="display:inline-block;text-decoration:none;margin-top:10px;" href="index.html?subject_id=${encodeURIComponent(course.subject_id)}">ดูรายวิชาและเข้าเรียน</a>
        </div>
    `).join('');
}

function flattenReportLessons(courses) {
    return courses.flatMap((course) => (Array.isArray(course.lessons) ? course.lessons : []).map((lesson) => ({ ...lesson, course_id: course.subject_id, course_name: course.name, subject_type: course.subject_type })));
}

function formatReportDate(value) {
    if (!value) return 'ยังไม่มีกิจกรรม';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '—' : date.toLocaleString('th-TH', { dateStyle: 'short', timeStyle: 'short' });
}

function reportQuizStatus(lesson) {
    if (!lesson.has_attempt) return '<span class="report-status none">ยังไม่ทำ</span>';
    if (lesson.quiz_status === 'pending_review') return '<span class="report-status pending">รอตรวจข้อเขียน</span>';
    if (lesson.quiz_status === 'passed') return '<span class="report-status passed">ผ่าน</span>';
    if (lesson.quiz_status === 'failed') return '<span class="report-status failed">ยังไม่ผ่าน</span>';
    return '<span class="report-status">ส่งแล้ว</span>';
}

function renderReports(courses) {
    const summary = document.getElementById('reportSummary');
    const courseBody = document.getElementById('courseReportBody');
    const lessonBody = document.getElementById('lessonReportBody');
    const filter = document.getElementById('reportCourseFilter');
    if (!summary || !courseBody || !lessonBody || !filter) return;

    const previous = filter.value || 'all';
    filter.replaceChildren(new Option('ทุกรายวิชา', 'all'));
    courses.forEach((course) => filter.add(new Option(course.name, course.subject_id)));
    filter.value = courses.some((course) => String(course.subject_id) === previous) ? previous : 'all';

    const student = dashboardState.student || {};
    const studentInfo = [student.name, student.class_name && student.class_name !== '-' ? student.class_name : ''].filter(Boolean).join(' · ');
    const studentLabel = document.getElementById('reportStudent');
    if (studentLabel) studentLabel.textContent = studentInfo ? `นักเรียน: ${studentInfo}` : 'รายงานตามข้อมูลการลงทะเบียน';

    const selectedId = filter.value;
    const visibleCourses = selectedId === 'all' ? courses : courses.filter((course) => String(course.subject_id) === selectedId);
    const rows = flattenReportLessons(visibleCourses);
    const attempted = rows.filter((lesson) => lesson.has_attempt).length;
    const lessonTotal = rows.length;
    const earned = rows.reduce((sum, lesson) => sum + (Number(lesson.score_earned) || 0), 0);
    const possible = rows.reduce((sum, lesson) => sum + (Number(lesson.score_total) || 0), 0);
    const pendingEssays = rows.reduce((sum, lesson) => sum + (Number(lesson.essay_pending_count) || 0), 0);
    const progress = lessonTotal ? Math.round(attempted / lessonTotal * 100) : 0;
    const fmt = (value) => Number(value || 0).toLocaleString('th-TH', { maximumFractionDigits: 2 });

    summary.innerHTML = `
        <div class="stat-card orange"><strong class="report-stat-heading">วิชาที่ลงทะเบียน</strong><p class="value">${visibleCourses.length}</p><span class="sub-value">เฉพาะวิชาของนักเรียน</span></div>
        <div class="stat-card blue"><strong class="report-stat-heading">แบบทดสอบที่ทำแล้ว</strong><p class="value">${attempted}/${lessonTotal}</p><span class="sub-value">นับตามบทเรียนในวิชาที่เลือก</span></div>
        <div class="stat-card green"><strong class="report-stat-heading">ความคืบหน้า</strong><p class="value">${progress}%</p><span class="sub-value">สัดส่วนบทที่ส่งแบบทดสอบ</span></div>
        <div class="stat-card purple"><strong class="report-stat-heading">คะแนนรวม</strong><p class="value">${fmt(earned)}/${fmt(possible)}</p><span class="sub-value">รวมคะแนนข้อเขียนที่ตรวจแล้ว</span></div>
        <div class="stat-card red"><strong class="report-stat-heading">ข้อเขียนรอตรวจ</strong><p class="value">${pendingEssays}</p><span class="sub-value">ข้อที่ยังรอครูตรวจ</span></div>`;

    if (!visibleCourses.length) {
        courseBody.innerHTML = '<tr><td colspan="8" class="report-empty">ยังไม่มีรายวิชาที่ลงทะเบียน</td></tr>';
    } else {
        courseBody.innerHTML = visibleCourses.map((course) => {
            const courseLessons = Array.isArray(course.lessons) ? course.lessons : [];
            const attempts = courseLessons.reduce((sum, lesson) => sum + (Number(lesson.attempt_count) || 0), 0);
            const pending = courseLessons.reduce((sum, lesson) => sum + (Number(lesson.essay_pending_count) || 0), 0);
            const score = Number(course.score_total) > 0 ? `${fmt(course.score_earned)}/${fmt(course.score_total)} (${fmt(course.score)}%)` : 'ยังไม่มีคะแนน';
            return `<tr><td><strong>${escapeHtml(course.name)}</strong><small>${escapeHtml(course.description || 'ไม่มีคำอธิบาย')}</small></td><td>${subjectTypeBadge(course.subject_type)}</td><td>${Number(course.attempted_lessons)}/${Number(course.lesson_count)} บท</td><td><div class="report-progress"><span>${fmt(course.progress)}%</span><div class="progress-bar-bg"><div class="progress-bar-fill" style="width:${Math.max(0, Math.min(100, Number(course.progress) || 0))}%"></div></div></div></td><td>${score}</td><td>${attempts} ครั้ง</td><td>${pending ? `<span class="report-status pending">${pending} ข้อ</span>` : 'ไม่มี'}</td><td>${formatReportDate(course.last_activity_at)}</td></tr>`;
        }).join('');
    }

    const countLabel = document.getElementById('reportLessonCount');
    if (countLabel) countLabel.textContent = `${rows.length} บทเรียน`;
    if (!rows.length) {
        lessonBody.innerHTML = '<tr><td colspan="8" class="report-empty">ยังไม่มีข้อมูลบทเรียน</td></tr>';
        return;
    }
    lessonBody.innerHTML = rows.map((lesson) => {
        const quiz = lesson.has_attempt ? `${fmt(lesson.score_earned)}/${fmt(lesson.score_total)}` : '—';
        const objective = lesson.objective_score_total > 0 ? `${fmt(lesson.objective_score_earned)}/${fmt(lesson.objective_score_total)}` : '—';
        const essay = lesson.essay_question_count > 0 ? `${fmt(lesson.essay_score_earned)}/${fmt(lesson.essay_score_total)}` : 'ไม่มี';
        return `<tr><td><strong>${escapeHtml(lesson.course_name)}</strong><small>บทที่ ${Number(lesson.position)} · ${escapeHtml(lesson.title)}</small></td><td>${reportQuizStatus(lesson)}</td><td>${quiz}</td><td>${objective}</td><td>${essay}</td><td>${Number(lesson.attempt_count)} ครั้ง</td><td>${formatReportDate(lesson.last_activity_at)}</td><td>${escapeHtml(lesson.essay_feedback || '—')}</td></tr>`;
    }).join('');
}

function _applyAvatarUI(src) {
    const avatarImg     = document.getElementById('avatarImg');
    const avatarInitial = document.getElementById('avatarInitial');
    if (avatarImg) { avatarImg.src = src; avatarImg.style.display = 'block'; }
    if (avatarInitial) { avatarInitial.style.display = 'none'; }

    const sidebarAvatar = document.getElementById('sidebarAvatar');
    if (sidebarAvatar) {
        sidebarAvatar.style.cssText += ';background:none;padding:0;overflow:hidden';
        const img = document.createElement('img');
        img.src = src;
        img.alt = '';
        img.style.cssText = 'width:100%;height:100%;object-fit:cover;border-radius:50%';
        sidebarAvatar.replaceChildren(img);
    }
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

    setText('sidebarName', name);
    setText('dashboardOwnerName', name);
    setText('displayName', name);
    setValue('profileName', name);
    setValue('profileEmail', student.email || '');
    setValue('profilePhone', student.phone || '');
    setValue('profileClass', className);
    setText('sidebarRole', 'นักเรียน - ' + className);
    setText('profileRole', 'นักเรียน · ' + className);

    const roleTexts = document.querySelectorAll('.role, .avatar-role');
    roleTexts.forEach((el) => {
        if (!el.id) el.textContent = 'นักเรียน - ' + className;
    });


    if (student.avatar_url) {
        _applyAvatarUI(student.avatar_url);
    } else {
        setText('sidebarAvatar', firstChar);
        setText('avatarInitial', firstChar);
        const avatarImg = document.getElementById('avatarImg');
        if (avatarImg) avatarImg.style.display = 'none';
        const avatarInitial = document.getElementById('avatarInitial');
        if (avatarInitial) avatarInitial.style.display = '';
    }
}

function updateStats(stats, courses) {
    const courseCount = Number(stats?.course_count || 0);
    const attemptedLessons = Number(stats?.attempted_lessons || 0);
    const totalLessons = Number(stats?.total_lessons || 0);
    const scoreEarned = Number(stats?.score_earned || 0);
    const scoreTotal = Number(stats?.score_total || 0);

    const setText = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    };
    setText('statCourseCount', String(courseCount));
    setText('statAvgProgress', `${attemptedLessons}/${totalLessons}`);
    setText('statAvgScore', `${scoreEarned}/${scoreTotal}`);

    renderTable(courses);
}

async function loadDashboardData() {
    const response = await fetch('student_dashboard_api.php', {
        credentials: 'same-origin'
    });
    if (response.status === 401) { window.location.assign('login.php'); return; }
    const result = await response.json();
    if (result.status !== 'success') {
        throw new Error(result.message || 'load failed');
    }

    dashboardState = {
        student: result.student || null,
        courses: Array.isArray(result.courses) ? result.courses : [],
        stats: result.stats || {}
    };

    updateProfile(dashboardState.student);
    updateStats(result.stats || {}, dashboardState.courses);
    renderLessons(dashboardState.courses);
    renderReports(dashboardState.courses, dashboardState.stats);
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
loadDashboardData().catch((error) => {
    const message = document.getElementById('dashboardLoadError');
    if (message) {
        message.textContent = 'โหลดข้อมูลไม่สำเร็จ กรุณาตรวจสอบการเชื่อมต่อแล้วรีเฟรชหน้า';
        message.hidden = false;
    }
    renderTable([]);
    renderLessons([]);
    renderReports([], {});
    console.error('Student dashboard load failed:', error);
});

document.getElementById('reportCourseFilter')?.addEventListener('change', () => renderReports(dashboardState.courses, dashboardState.stats || {}));
document.getElementById('reportPrintBtn')?.addEventListener('click', () => window.print());


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

    const drawW = imgW * zoom;
    const drawH = imgH * zoom;
    const drawX = imgX - (zoom - 1) * imgW / 2;
    const drawY = imgY - (zoom - 1) * imgH / 2;

    const cx = stageW / 2;
    const cy = stageH / 2;
    const r  = circleSize / 2;

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


    _applyAvatarUI(dataURL);
    _cropClose();


    _uploadAvatarToServer(dataURL);
}


function _uploadAvatarToServer(dataURL) {
    fetch('uploadavatar_student.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ image: dataURL })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {

            if (dashboardState.student) {
                dashboardState.student.avatar_url = data.url;
            }
        } else {
            console.warn('อัปโหลดรูปไม่สำเร็จ:', data.message);
        }
    })
    .catch(err => console.warn('Avatar upload error:', err));
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

function togglePwd(id, btn) {
    const inp = document.getElementById(id);
    if (!inp) return;
    const show = inp.type === 'password';
    inp.type = show ? 'text' : 'password';
    btn.textContent = show ? 'ซ่อน' : 'แสดง';
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
    body.append('pwd_new',     newPwd);

    fetch('update_student_profile.php', { method: 'POST', body })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showFeedback('success', data.message);
                if (dashboardState.student) {
                    dashboardState.student.name = name;
                    dashboardState.student.email = document.getElementById('profileEmail')?.value.trim() ?? '';
                    dashboardState.student.phone = document.getElementById('profilePhone')?.value.trim() ?? '';
                    updateProfile(dashboardState.student);
                }
                ['pwdCurrent', 'pwdNew', 'pwdConfirm'].forEach((id) => { const field = document.getElementById(id); if (field) field.value = ''; });
                const strength = document.getElementById('pwdStrengthWrap');
                if (strength) strength.style.display = 'none';
                const match = document.getElementById('pwdMatchMsg');
                if (match) match.textContent = '';
            } else {
                showFeedback('error', data.message);
            }
        })
        .catch(() => {
            showFeedback('error', 'บันทึกข้อมูลไม่สำเร็จ');
        })
        .finally(() => {
            btn.disabled = false;
            btn.textContent = ' บันทึกข้อมูล';
        });
}
