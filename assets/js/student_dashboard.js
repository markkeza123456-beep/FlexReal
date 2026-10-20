let dashboardState = {
    student: null,
    courses: []
};

let graduationCsrfToken = '';
let courseCertificateCsrfToken = '';

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
    const compulsory = type === 'required' || type === 'compulsory';
    const label = compulsory ? 'วิชาบังคับ' : 'วิชาเลือก';
    return '<span class="subject-type-badge ' + (compulsory ? 'required' : 'elective') + '">' + label + '</span>';
}

function renderLessonBreakdown(course) {
    const lessons = Array.isArray(course.lessons) ? course.lessons : [];
    if (!lessons.length) return '<p class="empty-state">รายวิชานี้ยังไม่มีบทเรียน</p>';
    const rows = lessons.map((lesson) => {
        const progress = Math.max(0, Math.min(100, Number(lesson.progress) || 0));
        const isTransferred = Boolean(lesson.is_transferred);
        const description = isTransferred
            ? 'ผ่านจากการเทียบโอนหน่วยกิต'
            : lesson.has_attempt ? 'ทำแบบทดสอบแล้ว' : progress > 0 ? 'กำลังเรียน' : 'ยังไม่เริ่มเรียน';
        const statusClassName = lesson.quiz_status === 'pending_review'
            ? 'pending'
            : (isTransferred || lesson.quiz_status === 'passed') ? 'passed' : 'failed';
        const statusLabelText = statusClassName === 'pending' ? 'รอตรวจ'
            : statusClassName === 'passed' ? 'ผ่าน' : 'ยังไม่ผ่าน';
        return `<div class="lesson-breakdown-row"><div class="lesson-number">${Number(lesson.position)}</div><div class="lesson-detail"><div class="lesson-detail-heading"><strong>${escapeHtml(lesson.title)}</strong><span class="lesson-score-status ${statusClassName}">${statusLabelText}</span></div><span class="lesson-short-status">${description}</span><div class="progress-bar-bg" role="progressbar" aria-label="ความคืบหน้า ${escapeHtml(lesson.title)}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${progress}"><div class="progress-bar-fill" style="width:${progress}%"></div></div></div></div>`;
    }).join('');
    return `<details class="lesson-breakdown"><summary>ดูรายการบทเรียนย่อย (${lessons.length})</summary><div class="lesson-breakdown-list">${rows}</div></details>`;
}

function renderTable(courses) {
    const tbody = document.getElementById('courseTableBody');
    if (!courses.length) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:#888;">ยังไม่มีรายวิชาที่กำหนดให้เรียน</td></tr>';
        return;
    }

    tbody.innerHTML = courses.map((item) => {
        const progress = Math.max(0, Math.min(100, Number(item.progress) || 0));
        const progressCell = item.is_transferred
            ? '<span class="course-transfer-status">ผ่านการเทียบโอน</span>'
            : `<span>${progress}%</span>`;
        const resultCell = item.is_transferred
            ? '<span class="course-transfer-status">TR</span>'
            : item.is_passed ? '<span class="course-passed-status">ผ่าน</span>' : '<span>กำลังศึกษา</span>';
        return `<tr><td style="color:#888">${escapeHtml(item.id)}</td><td>${escapeHtml(item.name)} ${subjectTypeBadge(item.subject_type)}</td><td>${progressCell}</td><td>${resultCell}</td></tr>`;
    }).join('');
}

function fmtDashboardNumber(value) {
    return Number(value || 0).toLocaleString('th-TH', { maximumFractionDigits: 2 });
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
            ${renderCourseCertificateAction(course)}
        </div>
    `).join('');
}

function renderCourseCertificateAction(course) {
    const certificate = course.certificate;
    if (certificate?.certificate_id) {
        const id = encodeURIComponent(certificate.certificate_id);
        return `<div class="course-certificate-actions"><span>ได้รับใบประกาศแล้ว</span><a href="download_course_certificate.php?certificate_id=${id}" target="_blank" rel="noopener">ดูใบประกาศ</a><a href="download_course_certificate.php?certificate_id=${id}&download=1">ดาวน์โหลด</a></div>`;
    }
    if (course.certificate_eligible) {
        return `<div class="course-certificate-actions"><span>ผ่านเงื่อนไขรับใบประกาศ</span><button type="button" class="btn-submit" data-issue-course-certificate="${encodeURIComponent(course.subject_id)}">รับใบประกาศ</button></div>`;
    }
    return '';
}

async function loadStudentCourseCertificates() {
    const response = await fetch('course_certificate_api.php', { credentials: 'same-origin', cache: 'no-store' });
    if (!response.ok) throw new Error('โหลดสิทธิ์รับใบประกาศไม่สำเร็จ');
    const result = await response.json();
    if (result.status !== 'success') throw new Error(result.message || 'โหลดสิทธิ์รับใบประกาศไม่สำเร็จ');
    courseCertificateCsrfToken = String(result.csrf_token || '');
    const byCourse = new Map((Array.isArray(result.courses) ? result.courses : []).map((item) => [String(item.course_id), item]));
    dashboardState.courses = dashboardState.courses.map((course) => {
        const certificateCourse = byCourse.get(String(course.subject_id));
        return certificateCourse ? {
            ...course,
            certificate_eligible: certificateCourse.is_eligible === true,
            certificate: certificateCourse.certificate || null
        } : course;
    });
    renderLessons(dashboardState.courses);
}

async function issueStudentCourseCertificate(courseId, button) {
    if (!courseCertificateCsrfToken || !button || button.disabled) return;
    button.disabled = true;
    const originalText = button.textContent;
    button.textContent = 'กำลังออกใบประกาศ...';
    try {
        const response = await fetch('course_certificate_api.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': courseCertificateCsrfToken },
            body: JSON.stringify({ course_id: Number(courseId) })
        });
        const result = await response.json();
        if (!response.ok || result.status !== 'success') throw new Error(result.message || 'ออกใบประกาศไม่สำเร็จ');
        await loadStudentCourseCertificates();
        window.alert(result.message || 'ออกใบประกาศเรียบร้อยแล้ว');
    } catch (error) {
        window.alert(error.message || 'ออกใบประกาศไม่สำเร็จ กรุณาลองอีกครั้ง');
        button.disabled = false;
        button.textContent = originalText;
    }
}

function flattenReportLessons(courses) {
    return courses.flatMap((course) => (Array.isArray(course.lessons) ? course.lessons : []).map((lesson) => ({
        ...lesson,
        course_id: course.subject_id,
        course_code: course.code,
        course_name: course.name,
        subject_type: course.subject_type,
        is_transferred: Boolean(course.is_transferred || lesson.is_transferred),
    })));
}

function formatReportDate(value) {
    if (!value) return 'ยังไม่มีกิจกรรม';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '—' : date.toLocaleString('th-TH', { dateStyle: 'short', timeStyle: 'short' });
}

function reportQuizStatus(lesson) {
    if (lesson.is_transferred) return '<span class="report-status passed">เทียบโอน</span>';
    if (lesson.quiz_status === 'pending_review') return '<span class="report-status pending">รอตรวจข้อเขียน</span>';
    return lesson.is_passed
        ? '<span class="report-status passed">ผ่าน</span>'
        : '<span class="report-status failed">ไม่ผ่าน</span>';
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
    const quizAttempts = rows.reduce((sum, lesson) => sum + (Number(lesson.attempt_count) || 0), 0);
    const progress = lessonTotal ? Math.round(attempted / lessonTotal * 100) : 0;
    const fmt = (value) => Number(value || 0).toLocaleString('th-TH', { maximumFractionDigits: 2 });

    summary.innerHTML = `
        <div class="stat-card orange"><strong class="report-stat-heading">วิชาที่ลงทะเบียน</strong><p class="value">${visibleCourses.length}</p><span class="sub-value">เฉพาะวิชาของนักเรียน</span></div>
        <div class="stat-card blue"><strong class="report-stat-heading">บทเรียนที่มีผล</strong><p class="value">${attempted}/${lessonTotal}</p><span class="sub-value">รวมผลสอบและผลเทียบโอน</span></div>
        <div class="stat-card blue"><strong class="report-stat-heading">ครั้งที่ส่งข้อสอบ</strong><p class="value">${quizAttempts}</p><span class="sub-value">รวมทุกครั้งในรายวิชาที่เลือก</span></div>
        <div class="stat-card green"><strong class="report-stat-heading">ความคืบหน้า</strong><p class="value">${progress}%</p><span class="sub-value">สัดส่วนบทที่ส่งแบบทดสอบ</span></div>
        <div class="stat-card purple"><strong class="report-stat-heading">คะแนนรวม</strong><p class="value">${fmt(earned)}/${fmt(possible)}</p><span class="sub-value">รวมคะแนนข้อเขียนที่ตรวจแล้ว</span></div>
        <div class="stat-card red"><strong class="report-stat-heading">ข้อเขียนรอตรวจ</strong><p class="value">${pendingEssays}</p><span class="sub-value">ข้อที่ยังรอครูตรวจ</span></div>`;

    if (!visibleCourses.length) {
        courseBody.innerHTML = '<tr><td colspan="6" class="report-empty">ยังไม่มีรายวิชาที่ลงทะเบียน</td></tr>';
    } else {
        courseBody.innerHTML = visibleCourses.map((course) => {
            const courseLessons = Array.isArray(course.lessons) ? course.lessons : [];
            const pending = courseLessons.reduce((sum, lesson) => sum + (Number(lesson.essay_pending_count) || 0), 0);
            const description = String(course.description || '').trim();
            const hasDescription = description && !['ไม่มีคำอธิบาย', 'ไม่มีคำอธิบายรายวิชา'].includes(description);
            const score = course.is_transferred
                ? 'TR (ผ่านการเทียบโอน)'
                : Number(course.score_total) > 0 ? `${fmt(course.score_earned)}/${fmt(course.score_total)}` : '—';
            const courseName = `<strong>${escapeHtml(course.name)}</strong>${hasDescription ? `<small>${escapeHtml(description)}</small>` : ''}`;
            return `<tr><td>${courseName}</td><td>${subjectTypeBadge(course.subject_type)}</td><td>${course.is_transferred ? 'ไม่ต้องสอบ' : `${Number(course.attempted_lessons)}/${Number(course.lesson_count)} บท`}</td><td>${fmt(course.progress)}%</td><td>${score}</td><td>${pending ? `<span class="report-status pending">${pending} ข้อ</span>` : '—'}</td></tr>`;
        }).join('');
    }

    const countLabel = document.getElementById('reportLessonCount');
    if (countLabel) countLabel.textContent = `${rows.length} บทเรียน`;
    const lessonRowsByCourse = new Map();
    rows.forEach((lesson) => {
        const courseKey = String(lesson.course_id);
        if (!lessonRowsByCourse.has(courseKey)) lessonRowsByCourse.set(courseKey, []);
        lessonRowsByCourse.get(courseKey).push(lesson);
    });
    lessonBody.innerHTML = visibleCourses.map((course) => {
        const courseLessons = lessonRowsByCourse.get(String(course.subject_id)) || [];
        if (!courseLessons.length) {
            if (!course.is_transferred) return '';
            const courseName = escapeHtml(course.name);
            const courseCode = escapeHtml(course.code || course.subject_id);
            return `<tr><td class="report-course-group"><strong>${courseName}</strong><small>รหัสวิชา ${courseCode}</small></td><td class="report-lesson-cell"><strong class="report-lesson-title">เทียบโอนทั้งรายวิชา</strong></td><td colspan="3" class="report-transfer-cell"><span class="report-transfer-badge">TR (ผ่านการเทียบโอน)</span></td><td class="report-center">${reportQuizStatus({ is_transferred: true })}</td></tr>`;
        }
        return courseLessons.map((lesson, lessonIndex) => {
            const courseCell = lessonIndex === 0
                ? `<td class="report-course-group" rowspan="${courseLessons.length}"><strong>${escapeHtml(lesson.course_name)}</strong><small>รหัสวิชา ${escapeHtml(lesson.course_code || lesson.course_id)}</small></td>`
                : '';
            const lessonTitle = String(lesson.title || '').trim();
            const lessonCell = `<td class="report-lesson-cell"><strong class="report-lesson-title">บทที่ ${Number(lesson.position)}</strong>${lessonTitle ? `<small class="report-lesson-subtitle">${escapeHtml(lessonTitle)}</small>` : ''}</td>`;
            if (lesson.is_transferred) {
                return `<tr>${courseCell}${lessonCell}<td colspan="3" class="report-transfer-cell"><span class="report-transfer-badge">TR (ผ่านการเทียบโอน)</span></td><td class="report-center">${reportQuizStatus(lesson)}</td></tr>`;
            }
            const objective = Number(lesson.objective_score_total) > 0
                ? `${fmt(lesson.objective_score_earned)}/${fmt(lesson.objective_score_total)}`
                : '—';
            const essay = Number(lesson.essay_pending_count) > 0
                ? '<span class="report-status pending">รอตรวจ</span>'
                : Number(lesson.essay_score_total) > 0
                    ? `${fmt(lesson.essay_score_earned)}/${fmt(lesson.essay_score_total)}`
                    : '—';
            const total = lesson.has_attempt && Number(lesson.score_total) > 0
                ? `${fmt(lesson.score_earned)}/${fmt(lesson.score_total)}`
                : '—';
            return `<tr>${courseCell}${lessonCell}<td class="report-center">${objective}</td><td class="report-center">${essay}</td><td class="report-center">${total}</td><td class="report-center">${reportQuizStatus(lesson)}</td></tr>`;
        }).join('');
    }).join('') || '<tr><td colspan="6" class="report-empty">ยังไม่มีข้อมูลบทเรียน</td></tr>';
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
    const quizAttempts = Number(stats?.quiz_attempts || 0);
    const totalLessons = Number(stats?.total_lessons || 0);
    const scoreEarned = Number(stats?.score_earned || 0);
    const scoreTotal = Number(stats?.score_total || 0);

    const setText = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    };
    setText('statCourseCount', String(courseCount));
    setText('statAvgProgress', String(quizAttempts));
    setText('statQuizAttemptCoverage', `ครอบคลุม ${attemptedLessons}/${totalLessons} บทเรียน`);
    setText('statAvgScore', `${scoreEarned}/${scoreTotal}`);

    renderTable(courses);
}

function renderGraduationTracker(graduation) {
    const completed = Number(graduation?.completed_courses || 0);
    const total = Number(graduation?.total_courses || 0);
    const percent = Math.max(0, Math.min(100, Number(graduation?.progress_percent || 0)));
    const pending = graduation?.pending_request;
    const approvedRequest = graduation?.approved_request;
    const message = document.getElementById('graduationTrackerMessage');
    const countLabel = document.getElementById('graduationCoursesLabel');
    const fill = document.getElementById('graduationProgressFill');
    const track = document.querySelector('.graduation-progress-track');
    const percentLabel = document.getElementById('graduationProgressPercent');
    const button = document.getElementById('graduationRequestBtn');
    const downloadLink = document.getElementById('graduationDownloadLink');
    if (!message || !countLabel || !fill || !track || !percentLabel || !button) return;

    const requiredDone = Number(graduation?.completed_compulsory_courses || 0);
    const requiredTotal = Number(graduation?.compulsory_courses || 0);
    const electiveDone = Number(graduation?.completed_elective_courses || 0);
    const electiveTotal = Number(graduation?.elective_courses || 0);
    countLabel.textContent = `วิชาบังคับ ${requiredDone}/${requiredTotal} · วิชาเลือก ${electiveDone}/${electiveTotal}`;
    fill.style.width = `${percent}%`;
    track.setAttribute('aria-valuenow', String(percent));
    percentLabel.textContent = `${percent}%`;
    button.hidden = true;
    button.disabled = false;
    button.classList.remove('is-pending');
    if (downloadLink) {
        const approvedDocument = graduation?.approved_document;
        downloadLink.hidden = !approvedDocument?.request_id;
        if (approvedDocument?.request_id) {
            downloadLink.href = `download_graduation_document.php?request_id=${encodeURIComponent(approvedDocument.request_id)}`;
        }
    }

    if (approvedRequest?.request_id) {
        message.textContent = 'คำขอจบการศึกษาได้รับการอนุมัติแล้ว สามารถดาวน์โหลดใบสรุปผลการเรียน PDF ได้';
        button.hidden = true;
    } else if (pending?.request_id) {
        message.textContent = 'ส่งคำขอจบการศึกษาแล้ว กำลังรอเจ้าหน้าที่พิจารณา';
        button.textContent = 'รอการอนุมัติจบการศึกษา';
        button.classList.add('is-pending');
        button.hidden = false;
        button.disabled = true;
        if (downloadLink) downloadLink.hidden = true;
    } else if (!graduation?.has_courses) {
        message.textContent = 'ยังไม่พบรายวิชาที่กำหนดให้เรียน กรุณาติดต่อเจ้าหน้าที่';
    } else if (graduation.eligible) {
        message.textContent = 'ผ่านวิชาบังคับครบทุกวิชาแล้ว สามารถยื่นขอจบการศึกษาได้';
        button.textContent = 'ยื่นขอจบการศึกษา';
        button.classList.remove('is-pending');
        button.hidden = false;
    } else {
        message.textContent = 'ต้องเรียนหรือเทียบโอนวิชาบังคับให้ครบทุกวิชาก่อน จึงจะสามารถกดส่งคำขอจบได้';
        button.textContent = 'ยื่นขอจบการศึกษา';
        button.classList.remove('is-pending');
        button.hidden = false;
        button.disabled = true;
    }
}

async function loadGraduationTracker() {
    const response = await fetch('graduation_tracker_api.php', { credentials: 'same-origin' });
    if (response.status === 401) {
        window.location.assign('login.php');
        return;
    }
    const result = await response.json();
    if (result.status !== 'success') throw new Error(result.message || 'โหลดความคืบหน้าการจบไม่สำเร็จ');
    graduationCsrfToken = String(result.graduation?.csrf_token || '');
    renderGraduationTracker(result.graduation || {});
}

async function submitGraduationRequest() {
    const button = document.getElementById('graduationRequestBtn');
    const message = document.getElementById('graduationTrackerMessage');
    if (!button || button.disabled || !graduationCsrfToken) return;

    button.disabled = true;
    message.textContent = 'กำลังส่งคำขอ...';
    try {
        const response = await fetch('graduation_tracker_api.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-Token': graduationCsrfToken }
        });
        const result = await response.json();
        if (result.status !== 'success') throw new Error(result.message || 'ส่งคำขอไม่สำเร็จ');
        await loadGraduationTracker();
    } catch (error) {
        message.textContent = error.message || 'ส่งคำขอไม่สำเร็จ กรุณาลองอีกครั้ง';
        button.disabled = false;
    }
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
    loadStudentCourseCertificates().catch((error) => console.error('Student course certificate load failed:', error));
    loadGraduationTracker().catch((error) => {
        const message = document.getElementById('graduationTrackerMessage');
        if (message) message.textContent = error.message || 'โหลดความคืบหน้าการจบไม่สำเร็จ';
    });
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
document.getElementById('graduationRequestBtn')?.addEventListener('click', submitGraduationRequest);
document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-issue-course-certificate]');
    if (button) issueStudentCourseCertificate(button.dataset.issueCourseCertificate, button);
});


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
    .then(async response => {
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'บันทึกรูปโปรไฟล์ไม่สำเร็จ');
        return data;
    })
    .then(data => {
        if (dashboardState.student) dashboardState.student.avatar_url = data.url;
        _applyAvatarUI(data.url);
    })
    .catch(err => {
        console.error('Avatar upload error:', err);
        if (dashboardState.student) updateProfile(dashboardState.student);
        alert(err.message || 'บันทึกรูปโปรไฟล์ไม่สำเร็จ');
    });
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
    const label = show ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน';
    btn.setAttribute('aria-label', label);
    btn.title = label;
    btn.innerHTML = show
        ? '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18M10.6 10.6a2 2 0 002.8 2.8M9.9 5.2A10.8 10.8 0 0112 5c6.4 0 10 7 10 7a14.7 14.7 0 01-3 3.8M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7c1.4 0 2.7-.3 3.8-.8"/></svg>'
        : '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';
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
