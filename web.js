// --- ตัวแปรหลักของระบบ ---
let currentSubjectId = '';
let currentCourseName = '';
let enrolledCourses = {};
let courseIdByName = {};
let currentLessonsData = [];
let currentProgressSummary = null;
let currentPassedLessons = new Set();
let currentUser = { logged_in: false, role: '' };
const LESSON_VIDEO_FILES = [
    'videos/lesson1.mp4.mp4',
    'videos/lesson-thai.mp4',
    'videos/lesson-english.mp4',
    'videos/lesson-math.mp4',
    'videos/lesson-social.mp4'
];
const VIDEO_CACHE_BUST = Date.now();

function pick(obj, ...keys) {
    for (const key of keys) {
        if (obj && obj[key] !== undefined && obj[key] !== null) return obj[key];
    }
    return '';
}

function escapeHtml(text) {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function normalizeSubjectType(value) {
    return String(value || '').trim().toLowerCase() === 'required' ? 'required' : 'elective';
}

function isTruthy(value) {
    const normalized = String(value ?? '').trim().toLowerCase();
    return normalized === '1' || normalized === 'true' || normalized === 't' || normalized === 'yes';
}

function isStudentLoggedIn() {
    return Boolean(currentUser?.logged_in) && String(currentUser?.role || '').toLowerCase() === 'student';
}

function getDashboardUrlForRole(role) {
    const map = {
        student: 'student_dashboard.php',
        teacher: 'teacherdash.php',
        staff: 'staffdash.php',
        parent: 'parent_dashboard.php'
    };
    return map[String(role || '').toLowerCase()] || 'web.html';
}

function formatDisplayName(fullName, maxLength = 18) {
    const name = String(fullName || '').trim();
    if (!name) return 'ผู้ใช้งาน';
    if (name.length <= maxLength) return name;
    return `${name.slice(0, maxLength)}...`;
}

// 💥 แปลงข้อมูลบทเรียนจากฐานข้อมูล
function buildLessonsFromDB(lessons) {
    const normalized = Array.isArray(lessons) ? lessons : [];
    const firstLessonTitle = normalized.length > 0
        ? (pick(normalized[0], 'lessons_name', 'Lessons_Name') || 'บทเรียนที่ 1')
        : 'บทเรียนที่ 1';
    const output = normalized.map((lsn, index) => {
        const lsnId = pick(lsn, 'lessons_id', 'Lessons_ID') || `tmp_${index}`;
        const lsnName = pick(lsn, 'lessons_name', 'Lessons_Name') || `บทเรียนที่ ${index + 1}`;
        return {
            index: index + 1,
            id: lsnId,
            title: lsnName,
            expanded: false
        };
    });

    for (let i = output.length + 1; i <= 5; i += 1) {
        output.push({
            index: i,
            id: `auto_${i}`,
            title: firstLessonTitle,
            expanded: false
        });
    }

    return output;
}

function isLessonPassed(lessonIndex) { return currentPassedLessons.has(Number(lessonIndex)); }

function getLessonProgressMap() {
    const map = new Map();
    const lessons = Array.isArray(currentProgressSummary?.lessons) ? currentProgressSummary.lessons : [];
    lessons.forEach((row) => {
        const idx = Number(row.lesson_index || 0);
        if (idx > 0) map.set(idx, row);
    });
    return map;
}

function canAccessLesson(lessonIndex) {
    if (Number(lessonIndex) <= 1) return true;
    return isLessonPassed(Number(lessonIndex) - 1);
}

function hasReadLessonDocument(lessonIndex) {
    const progressMap = getLessonProgressMap();
    const row = progressMap.get(Number(lessonIndex)) || {};
    return Number(row.opened_count || 0) > 0;
}

function hasCompletedLessonVideo(lessonIndex) {
    const progressMap = getLessonProgressMap();
    const row = progressMap.get(Number(lessonIndex)) || {};
    return Number(row.video_open_count || 0) > 0;
}

function isVideoUnlocked(lessonIndex) {
    return canAccessLesson(lessonIndex) && hasReadLessonDocument(lessonIndex);
}

function isQuizUnlocked(lessonIndex) {
    if (!canAccessLesson(lessonIndex)) return false;
    return hasReadLessonDocument(lessonIndex) && hasCompletedLessonVideo(lessonIndex);
}

function getLessonLockMessage(lessonIndex) {
    if (!canAccessLesson(lessonIndex)) return `ปลดล็อกเมื่อผ่านแบบทดสอบบทที่ ${Number(lessonIndex) - 1}`;
    if (!hasReadLessonDocument(lessonIndex)) return 'ต้องอ่านบทเรียนให้จบก่อน';
    if (!hasCompletedLessonVideo(lessonIndex)) return 'ต้องชมวิดีโอให้จบก่อนเริ่ม Quiz';
    return '';
}

function getLessonStatusInfo(lessonIndex) {
    const progressMap = getLessonProgressMap();
    const row = progressMap.get(Number(lessonIndex)) || {};
    const score = Number(row.best_quiz_score || 0);
    const total = Number(row.quiz_total_score || 0);
    const hasAttempt = total > 0;
    const passed = isLessonPassed(lessonIndex);

    if (passed) return { label: 'ผ่าน', color: '#1e8449', bg: '#eafaf1', scoreText: hasAttempt ? `${score}/${total}` : '-' };
    if (hasAttempt) return { label: 'ไม่ผ่าน', color: '#c0392b', bg: '#fdecea', scoreText: `${score}/${total}` };
    return { label: 'ยังไม่ทำ', color: '#7f8c8d', bg: '#f4f6f7', scoreText: '-' };
}

// 💥 สร้างกล่องบทเรียนบนหน้าเว็บ
function renderLessonAccordion(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;

    // ถ้าอาจารย์ยังไม่เพิ่มบทเรียน ให้โชว์หน้าต่างชัดๆ แทนกล่องขาวโล่งๆ
    if (!currentLessonsData || currentLessonsData.length === 0) {
        container.innerHTML = `
            <div style="text-align:center; padding:40px 20px; color:#888; background:#fafafa; border:2px dashed #ddd; border-radius:12px; margin-top: 15px;">
                <div style="font-size:32px; margin-bottom:10px;">📁</div>
                <h3 style="color:#555; margin-bottom:5px;">วิชานี้ยังไม่มีบทเรียน</h3>
                <p style="font-size:14px;">โปรดรออาจารย์ผู้สอนเพิ่มเนื้อหาเข้าสู่ระบบ</p>
            </div>
        `;
        container.style.display = 'block'; // บังคับโชว์
        return;
    }

    const html = currentLessonsData.map((lesson) => {
        const status = getLessonStatusInfo(lesson.index);
        return `
        <div class="lesson-accordion ${lesson.expanded ? 'is-expanded' : 'is-collapsed'}">
            <button type="button" class="lesson-header" onclick="toggleLesson(${lesson.index})" aria-expanded="${lesson.expanded ? 'true' : 'false'}">
                <span>บทที่ ${lesson.index}: ${escapeHtml(lesson.title)}</span>
                <span class="lesson-chevron">${lesson.expanded ? '▴' : '▾'}</span>
            </button>
            <div class="lesson-body">
                <div class="curriculum-list">
                    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:0 0 10px;">
                        <span style="padding:4px 10px;border-radius:999px;font-size:12px;font-weight:600;color:${status.color};background:${status.bg};">สถานะ: ${status.label}</span>
                        <span style="font-size:12px;color:#2c3e50;">คะแนน: ${status.scoreText}</span>
                    </div>
                    ${!canAccessLesson(lesson.index) ? `<p style="margin:0 0 8px;color:#d35400;font-size:13px;">${getLessonLockMessage(lesson.index)}</p>` : ''}
                    <div class="curriculum-item">
                        <div class="curr-left">
                            <span class="curr-icon">📄</span>
                            <div class="curr-text"><b>เอกสารประกอบบทเรียน</b><p>เปิดอ่านเอกสาร</p></div>
                        </div>
                        <button class="btn-orange" onclick="downloadCourseLesson(${lesson.index})" ${canAccessLesson(lesson.index) ? '' : 'disabled'}>เปิดอ่าน</button>
                    </div>
                    <div class="curriculum-item">
                        <div class="curr-left">
                            <span class="curr-icon">🎬</span>
                            <div class="curr-text"><b>วิดีโอสรุปบทเรียน</b><p>รับชมวิดีโอ</p></div>
                        </div>
                        <button class="btn-orange" onclick="openCourseVideo(${lesson.index})" ${isVideoUnlocked(lesson.index) ? '' : 'disabled'}>ชมวิดีโอ</button>
                    </div>
                    <div class="curriculum-item">
                        <div class="curr-left">
                            <span class="curr-icon">📝</span>
                            <div class="curr-text"><b>แบบทดสอบประจำบท</b><p>ทดสอบความเข้าใจ</p></div>
                        </div>
                        <button class="btn-outline-orange" onclick="startQuiz(${lesson.index})" ${isQuizUnlocked(lesson.index) ? '' : 'disabled'}>เริ่มทำ Quiz</button>
                    </div>
                    ${isQuizUnlocked(lesson.index) ? '' : `<p style="margin:8px 0 0;color:#7f8c8d;font-size:12px;">${getLessonLockMessage(lesson.index)}</p>`}
                </div>
            </div>
        </div>
    `;
    }).join('');

    container.innerHTML = html;
    container.style.display = 'grid'; // บังคับโชว์
}

function renderAllLessonAccordions() {
    renderLessonAccordion('course-curriculum-lesson-list');
    renderLessonAccordion('learning-lesson-text-list');
}

function setText(id, value) {
    const element = document.getElementById(id);
    if (element) element.textContent = value;
}

function updateInstructorInfo(course) {
    const teacherName = String(pick(course, 'teachers_name', 'Teachers_Name')).trim() || 'ยังไม่กำหนดอาจารย์ผู้สอน';
    const teacherRole = teacherName === 'ยังไม่ระบุอาจารย์ผู้สอน' ? 'รอการกำหนดบทบาท' : 'อาจารย์ประจำวิชา';
    setText('detail-instructor-name', teacherName);
    setText('detail-instructor-role', teacherRole);
    setText('learning-instructor-name', teacherName);
    setText('learning-instructor-role', teacherRole);
}

function updateCourseMeta(course, lessons) {
    const lessonCount = currentLessonsData.length || 0;
    setText('detail-lesson-count', `${lessonCount} บทเรียน`);
    setText('detail-quiz-count', `${lessonCount} ชุด`);
    setText('learning-lesson-count', `${lessonCount} บทเรียน`);
    setText('learning-quiz-count', `${lessonCount} ชุด`);
}

function applyCourseProgressSummary(summary) {
    currentProgressSummary = summary || null;
    const progressText = `${Number(summary?.progress_percent || 0).toFixed(1)}%`;
    const scoreText = `${Number(summary?.best_score_percent || 0).toFixed(1)}%`;
    setText('detail-progress', progressText);
    setText('detail-score', scoreText);
    setText('learning-progress', progressText);
    setText('learning-score', scoreText);
    renderAllLessonAccordions();
}

async function fetchQuizProgress(subjectId) {
    if (!subjectId || !enrolledCourses[subjectId]) {
        currentPassedLessons = new Set();
        renderAllLessonAccordions(); return;
    }
    try {
        const response = await fetch(`api_quiz_progress.php?subject_id=${encodeURIComponent(subjectId)}`, { credentials: 'same-origin' });
        const result = await response.json();
        if (result.status === 'success' && Array.isArray(result.passed_lessons)) {
            currentPassedLessons = new Set(result.passed_lessons.map((v) => Number(v)));
        } else { currentPassedLessons = new Set(); }
    } catch (error) { currentPassedLessons = new Set(); }
    renderAllLessonAccordions();
}

async function fetchCourseProgress(subjectId) {
    if (!subjectId || !enrolledCourses[subjectId]) { applyCourseProgressSummary(null); return; }
    try {
        const response = await fetch(`student_learning_api.php?action=summary&subject_id=${encodeURIComponent(subjectId)}`, { credentials: 'same-origin' });
        const result = await response.json();
        if (result.status === 'success') applyCourseProgressSummary(result.summary || null); 
    } catch (error) { applyCourseProgressSummary(null); }
}

async function recordLearningEvent(activityType, lessonIndex = 1) {
    if (!currentSubjectId || !enrolledCourses[currentSubjectId]) return;
    try {
        const lesson = currentLessonsData.find((item) => item.index === Number(lessonIndex));
        const formData = new FormData();
        formData.append('action', 'record'); formData.append('subject_id', currentSubjectId);
        formData.append('lesson_index', String(lessonIndex || 1));
        formData.append('lesson_title', lesson ? lesson.title : `${currentCourseName} บทที่ ${lessonIndex || 1}`);
        formData.append('activity_type', activityType);

        const response = await fetch('student_learning_api.php', { method: 'POST', body: formData, credentials: 'same-origin' });
        const result = await response.json();
        if (result.status === 'success') applyCourseProgressSummary(result.summary || null);
    } catch (error) {}
}

function toggleLesson(lessonIndex) {
    currentLessonsData = currentLessonsData.map((lesson) => {
        if (lesson.index === lessonIndex) return { ...lesson, expanded: !lesson.expanded };
        return lesson;
    });
    renderAllLessonAccordions();
}

function showPage(pageId) {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    const targetPage = document.getElementById(pageId);
    if (targetPage) targetPage.classList.add('active');
    window.scrollTo(0, 0);
}

function getCourseReturnUrl(courseName) {
    const url = new URL('web.html', window.location.href);
    url.searchParams.set('subject_id', currentSubjectId);
    return url.pathname.split('/').pop() + url.search;
}

function createCourseCardMarkup(course) {
    const subjectId = pick(course, 'subjects_id', 'Subjects_ID');
    const subjectName = pick(course, 'subjects_name', 'Subjects_Name') || '-';
    const subjectDesc = pick(course, 'subjects_description', 'Subjects_Description') || 'คลิกเพื่อดูรายละเอียด';
    const subjectType = normalizeSubjectType(pick(course, 'subject_type', 'type'));
    const isEnrolled = isTruthy(pick(course, 'is_enrolled', 'Is_Enrolled'));
    const tagText = subjectType === 'required' ? 'บังคับ' : 'วิชาเลือก';
    const icon = subjectType === 'required' ? '✓' : '＋';
    const statusText = isEnrolled ? 'ลงทะเบียนแล้ว' : 'ยังไม่ลงทะเบียน';

    return `
        <div class="card card-no-image" onclick="showCourse('${escapeHtml(subjectId)}')">
            <div class="card-content">
                <div class="card-icon">${icon}</div>
                <span class="card-tag ${subjectType}">${tagText}</span>
                <h3>${escapeHtml(subjectName)}</h3>
                <p>${escapeHtml(subjectDesc)}</p>
                <div class="card-meta">รหัสวิชา: ${escapeHtml(subjectId || '-')} · ${statusText}</div>
            </div>
        </div>
    `;
}

function renderCourseCollection(container, courses, emptyMessage) {
    if (!container) return;
    if (!Array.isArray(courses) || courses.length === 0) {
        container.innerHTML = `<div class="empty-course-state">${escapeHtml(emptyMessage)}</div>`;
        return;
    }

    container.innerHTML = courses.map((course) => createCourseCardMarkup(course)).join('');
}

function toggleCourseLayout(useStudentSections) {
    const publicSection = document.getElementById('public-course-section');
    const studentSections = document.getElementById('student-course-sections');
    if (publicSection) publicSection.style.display = useStudentSections ? 'none' : 'block';
    if (studentSections) studentSections.style.display = useStudentSections ? 'block' : 'none';
}

function renderCourseSections(courses) {
    const publicGrid = document.getElementById('course-grid');
    const requiredGrid = document.getElementById('required-course-grid');
    const electiveGrid = document.getElementById('elective-course-grid');

    if (isStudentLoggedIn()) {
        toggleCourseLayout(true);
        const studyCourses = courses.filter((course) => {
            const isCurriculumRequired = pick(course, 'is_curriculum_required', 'Is_Curriculum_Required');
            const isEnrolled = pick(course, 'is_enrolled', 'Is_Enrolled');
            return isTruthy(isCurriculumRequired) || isTruthy(isEnrolled);
        });
        const electiveCourses = courses.filter((course) => !studyCourses.includes(course));

        renderCourseCollection(requiredGrid, studyCourses, 'ยังไม่มีวิชาที่ต้องเรียนหรือวิชาที่ลงทะเบียน');
        renderCourseCollection(electiveGrid, electiveCourses, 'ยังไม่มีวิชาเลือกให้ลงทะเบียน');
        return;
    }

    toggleCourseLayout(false);
    renderCourseCollection(publicGrid, courses, 'ยังไม่มีรายวิชาในระบบ');
}

// 💥 โหลดวิชาทั้งหมด
async function loadAllCourses() {
    try {
        const response = await fetch('api_courses.php?action=get_all');
        const result = await response.json();
        const dropdown = document.getElementById('course-dropdown');
        if (!dropdown) return;
        
        if (result.status === 'success') {
            dropdown.innerHTML = '';
            courseIdByName = {};
            const courses = Array.isArray(result.data) ? result.data : [];
            
            courses.forEach(course => {
                const subjectId = pick(course, 'subjects_id', 'Subjects_ID');
                const subjectName = pick(course, 'subjects_name', 'Subjects_Name');
                if (subjectName) courseIdByName[subjectName] = subjectId;
                if (subjectId) {
                    const a = document.createElement('a');
                    a.href = '#'; a.textContent = subjectName || subjectId;
                    a.addEventListener('click', (e) => { e.preventDefault(); showCourse(subjectId); });
                    dropdown.appendChild(a);
                }
            });

            renderCourseSections(courses);
            if (dropdown.children.length === 0) dropdown.innerHTML = '<a href="#" onclick="return false;">ยังไม่มีรายวิชา</a>';
        } else {
            renderCourseSections([]);
            const grid = document.getElementById('course-grid');
            if (grid) grid.innerHTML = `<p style="text-align:center; color:red;">เกิดข้อผิดพลาด: ${result.message}</p>`;
        }
    } catch (error) {
        console.error("Error loading courses:", error);
        renderCourseSections([]);
        const grid = document.getElementById('course-grid');
        if (grid) grid.innerHTML = '<p style="text-align:center;">ไม่สามารถเชื่อมต่อฐานข้อมูลได้</p>';
    }
}

// 💥 โหลดข้อมูลตอนกดเข้าวิชา
async function showCourse(subjectId) {
    if (courseIdByName[subjectId]) subjectId = courseIdByName[subjectId];
    currentSubjectId = subjectId;
    
    try {
        const response = await fetch(`api_courses.php?action=get_detail&id=${subjectId}`);
        const result = await response.json();
        
        if (result.status === 'success') {
            const course = result.course;
            const lessons = result.lessons;
            currentCourseName = pick(course, 'subjects_name', 'Subjects_Name'); 
            
            document.getElementById('detail-title').innerText = currentCourseName;
            document.getElementById('detail-desc-top').innerText = pick(course, 'subjects_description', 'Subjects_Description') || 'รายละเอียดเบื้องต้น';
            document.getElementById('detail-desc').innerText = pick(course, 'subjects_description', 'Subjects_Description') || 'คำอธิบายรายวิชา...';
            document.getElementById('detail-duration').innerText = course.duration || 'ไม่ระบุเวลา';
            updateInstructorInfo(course);
            
            // ดึงบทเรียนมาจาก DB ทันที
            currentLessonsData = buildLessonsFromDB(Array.isArray(lessons) ? lessons : []);
            updateCourseMeta(course, lessons);
            
            applyCourseProgressSummary(null);
            currentPassedLessons = new Set();
            renderAllLessonAccordions();

            updateEnrollButton(false);
            setCurriculumAccess(false);
            checkCourseEnrollment(subjectId); 

            openTab({ currentTarget: document.querySelector('.tab-btn') }, 'overview');
            showPage('course-detail');
            
            const url = new URL(window.location.href);
            url.searchParams.set('subject_id', subjectId);
            window.history.replaceState({}, '', url);
        } else {
            alert('ไม่พบข้อมูลรายวิชา: ' + result.message);
        }
    } catch (error) {
        console.error("Error fetching course detail:", error);
        alert('เชื่อมต่อฐานข้อมูลล้มเหลว');
    }
}

function goToCourseLearning() {
    if (!enrolledCourses[currentSubjectId]) { alert('กรุณาลงรายวิชาก่อนเข้าเนื้อหาบทเรียน'); return; }
    document.getElementById('learning-title').innerText = document.getElementById('detail-title').innerText;
    document.getElementById('learning-desc-top').innerText = document.getElementById('detail-desc-top').innerText;
    document.getElementById('learning-desc').innerText = document.getElementById('detail-desc').innerText;
    document.getElementById('learning-duration').innerText = document.getElementById('detail-duration').innerText;
    renderAllLessonAccordions();
    showPage('course-learning');
    openLearningTab({ currentTarget: document.querySelector("#course-learning .tab-btn[onclick*='learning-curriculum']") }, 'learning-curriculum');
    recordLearningEvent('course_enter', 1);
}

async function checkCourseEnrollment(subjectId) {
    try {
        const response = await fetch(`course_enrollment_api.php?subject_id=${encodeURIComponent(subjectId)}`, { credentials: 'same-origin' });
        const result = await response.json();
        if (result.status === 'success') {
            enrolledCourses[subjectId] = Boolean(result.enrolled);
            updateEnrollButton(Boolean(result.enrolled));
            setCurriculumAccess(Boolean(result.enrolled));
            if (result.enrolled) { fetchCourseProgress(subjectId); fetchQuizProgress(subjectId); }
        } else if (result.status === 'unauthorized') {
            enrolledCourses[subjectId] = false; updateEnrollButton(false); setCurriculumAccess(false);
            applyCourseProgressSummary(null); currentPassedLessons = new Set(); renderAllLessonAccordions();
        }
    } catch (error) {
        enrolledCourses[subjectId] = false; updateEnrollButton(false); setCurriculumAccess(false);
        applyCourseProgressSummary(null); currentPassedLessons = new Set(); renderAllLessonAccordions();
    }
}

function updateEnrollButton(isEnrolled) {
    const button = document.getElementById('enroll-course-btn');
    if (!button) return;
    if(isEnrolled) { button.innerText = 'เข้าเรียน'; button.classList.add('is-enrolled'); } 
    else { button.innerText = 'ลงทะเบียน'; button.classList.remove('is-enrolled'); }
}

async function enrollCourseAndOpenLearning() {
    if (!currentSubjectId) return;
    if (enrolledCourses[currentSubjectId]) { goToCourseLearning(); return; }
    try {
        const formData = new FormData(); formData.append('subject_id', currentSubjectId);
        const response = await fetch('course_enrollment_api.php', { method: 'POST', body: formData, credentials: 'same-origin' });
        const result = await response.json();

        if (result.status === 'unauthorized') {
            alert(result.message || 'กรุณาเข้าสู่ระบบนักเรียนก่อนลงรายวิชา');
            const loginUrl = new URL(result.login_url || 'login.php', window.location.href);
            loginUrl.searchParams.set('return', getCourseReturnUrl(currentCourseName));
            window.location.href = loginUrl.href;
            return;
        }
        if (result.status !== 'success') { alert(result.message || 'ไม่สามารถลงรายวิชาได้'); return; }

        enrolledCourses[currentSubjectId] = true; updateEnrollButton(true); setCurriculumAccess(true);
        await loadAllCourses();
        fetchCourseProgress(currentSubjectId); fetchQuizProgress(currentSubjectId); goToCourseLearning();
    } catch (error) { alert('เชื่อมต่อระบบลงรายวิชาไม่ได้ กรุณาลองใหม่อีกครั้ง'); }
}

function filterCourses() {
    let input = document.getElementById('searchInput').value.toLowerCase();
    let cards = document.getElementsByClassName('card');
    for (let i = 0; i < cards.length; i++) {
        let title = cards[i].querySelector('h3').innerText.toLowerCase();
        cards[i].style.display = title.includes(input) ? "" : "none";
    }
}

function openTab(evt, tabName) {
    document.querySelectorAll(".tab-content").forEach(t => t.style.display = "none");
    document.querySelectorAll(".tab-btn").forEach(b => b.classList.remove("active"));
    document.getElementById(tabName).style.display = "block";
    if (evt && evt.currentTarget) evt.currentTarget.classList.add("active");
}

function openLearningTab(evt, tabName) {
    document.querySelectorAll("#course-learning .learning-tab-content").forEach(t => t.style.display = "none");
    document.querySelectorAll("#course-learning .tab-btn").forEach(b => b.classList.remove("active"));
    document.getElementById(tabName).style.display = "block";
    if (evt && evt.currentTarget) evt.currentTarget.classList.add("active");
}

// 💥 ควบคุมการโชว์/ซ่อน รายชื่อบทเรียน
function setCurriculumAccess(isEnrolled) {
    const lockedMsg = document.getElementById('curriculum-locked-msg');
    const lessonList = document.getElementById('course-curriculum-lesson-list');
    if (!lockedMsg || !lessonList) return;
    
    if (isEnrolled) {
        lockedMsg.style.display = 'none';
        lessonList.style.display = 'block'; // บังคับให้บล็อกเปิดเสมอถ้า Enroll แล้ว
    } else {
        lockedMsg.style.display = 'block';
        lessonList.style.display = 'none';
    }
}

function downloadCourseLesson(lessonIndex) {
    if (!enrolledCourses[currentSubjectId]) { alert('กรุณาลงรายวิชาก่อนอ่านเอกสาร'); return; }
    if (!canAccessLesson(lessonIndex || 1)) { alert(`กรุณาผ่านแบบทดสอบบทที่ ${Number(lessonIndex || 1) - 1} ก่อน`); return; }
    recordLearningEvent('lesson_open', lessonIndex || 1);
    const params = new URLSearchParams();
    params.set('subject_id', currentSubjectId); params.set('lesson', String(lessonIndex || 1));
    if (currentCourseName) params.set('course_name', currentCourseName);
    window.open(`download_course_lesson.php?${params.toString()}`, '_blank');
}

function getLessonVideoPath(lessonIndex) {
    const safeIndex = Math.max(1, Math.min(currentLessonsData.length || 5, Number(lessonIndex) || 1));
    const candidates = [
        `videos/${encodeURIComponent(currentSubjectId)}-lesson-${safeIndex}.mp4`,
        LESSON_VIDEO_FILES[safeIndex - 1],
        LESSON_VIDEO_FILES[0]
    ];
    const selected = candidates.find(Boolean) || '';
    if (!selected) return '';
    return `${selected}?v=${VIDEO_CACHE_BUST}`;
}

function renderVideoModalBody(lessonIndex) {
    const body = document.getElementById('modal-body');
    if (!body) return;
    const safeIndex = Math.max(1, Math.min(currentLessonsData.length || 5, Number(lessonIndex) || 1));
    const selectedLesson = currentLessonsData.find((lesson) => lesson.index === safeIndex);
    const selectedTitle = selectedLesson ? selectedLesson.title : `Lesson ${safeIndex}`;
    const videoPath = getLessonVideoPath(safeIndex);
    const selectorOptions = currentLessonsData.map((lesson) => `
        <option value="${lesson.index}" ${lesson.index === safeIndex ? 'selected' : ''}>
            บทที่ ${lesson.index}: ${escapeHtml(lesson.title)}
        </option>
    `).join('');

    body.innerHTML = `
        <h3 style="margin-bottom:10px; color:#E67E22;">🎥 วิดีโอบทเรียน</h3>
        <p style="margin-bottom:12px; color:#636e72;">กำลังดู: บทที่ ${safeIndex} - ${escapeHtml(selectedTitle)}</p>
        <label for="video-lesson-select" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3436;">เปลี่ยนบทเรียน</label>
        <select id="video-lesson-select" onchange="changeModalLessonVideo(this.value)" style="width:100%; padding:10px 12px; border:1px solid #ddd; border-radius:8px; margin-bottom:14px;">
            ${selectorOptions}
        </select>
        <video width="100%" controls autoplay style="border-radius:10px; background:#000;">
            <source src="${videoPath}" type="video/mp4">
            เบราว์เซอร์ไม่รองรับวิดีโอ
        </video>
    `;

    const videoElement = body.querySelector('video');
    if (videoElement) {
        videoElement.addEventListener('ended', () => {
            recordLearningEvent('video_open', safeIndex);
            fetchCourseProgress(currentSubjectId);
        }, { once: true });
    }
}

window.changeModalLessonVideo = function(lessonIndex) {
    const targetIndex = Number(lessonIndex) || 1;
    if (!isVideoUnlocked(targetIndex)) {
        alert(getLessonLockMessage(targetIndex) || 'ต้องอ่านบทเรียนก่อนดูวิดีโอ');
        return;
    }
    renderVideoModalBody(targetIndex);
}

function openCourseVideo(lessonIndex) {
    if (!enrolledCourses[currentSubjectId]) { alert('กรุณาลงรายวิชาก่อนชมวิดีโอ'); return; }
    if (!canAccessLesson(lessonIndex || 1)) { alert(`กรุณาผ่านแบบทดสอบบทที่ ${Number(lessonIndex || 1) - 1} ก่อน`); return; }
    if (!hasReadLessonDocument(lessonIndex || 1)) { alert('กรุณาอ่านบทเรียนให้จบก่อนดูวิดีโอ'); return; }
    const modal = document.getElementById('modal-overlay');
    renderVideoModalBody(lessonIndex || 1);
    modal.style.display = 'flex';
}

function startQuiz(lessonIndex) {
    if (!enrolledCourses[currentSubjectId]) { alert('กรุณาลงรายวิชาก่อนทำแบบทดสอบ'); return; }
    if (!isQuizUnlocked(lessonIndex || 1)) { alert(getLessonLockMessage(lessonIndex || 1) || 'บทเรียนยังไม่ปลดล็อก'); return; }
    
    const url = new URL('test.html', window.location.href);
    if (currentCourseName) url.searchParams.set('course', currentCourseName);
    if (currentSubjectId) url.searchParams.set('subject_id', currentSubjectId);
    if (lessonIndex) url.searchParams.set('lesson', String(lessonIndex));
    
    recordLearningEvent('lesson_open', lessonIndex || 1);
    window.location.href = url.pathname.split('/').pop() + url.search;
}

function closeModal() {
    const modalBody = document.getElementById('modal-body');
    if(modalBody) modalBody.innerHTML = '';
    document.getElementById('modal-overlay').style.display = 'none';
}

function showGuide(type) {
    const title = document.getElementById('guide-title');
    const content = document.getElementById('guide-content');
    const guides = {
        'register': ['ขั้นตอนการสมัคร', '1. กด Login <br> 2. กรอก Email <br> 3. เริ่มเรียน'],
        'certificate': ['การรับใบประกาศ', 'เรียนจบ 100% ดาวน์โหลดได้ทันที']
    };
    title.innerText = guides[type][0];
    content.innerHTML = `<div class="info-card">${guides[type][1]}</div>`;
    showPage('page-guide');
}

function showContact(type) {
    const title = document.getElementById('contact-title');
    const content = document.getElementById('contact-content');
    if(type === 'channel') {
        title.innerText = 'ช่องทางการติดต่อ';
        content.innerHTML = `<div class="info-card">Email: Flexible@hub.com <br> Line: @FlexibleHub</div>`;
    } else {
        title.innerText = 'สถานที่ตั้ง';
        content.innerHTML = `<div class="info-card">อาคาร Flexible Hub ชั้น 10 กรุงเทพฯ</div>`;
    }
    showPage('page-contact');
}

document.addEventListener('DOMContentLoaded', async () => {
    try {
        const sessionResponse = await fetch('student_session.php', { credentials: 'same-origin' });
        const user = await sessionResponse.json();
        const loginBtn = document.getElementById('loginBtn');
        const userProfile = document.getElementById('userProfile');
        const userName = document.getElementById('userName');
        const userAvatar = document.getElementById('userAvatar');
        const userProfileBtn = document.getElementById('userProfileBtn');
        const userMenu = document.getElementById('userMenu');

        currentUser = user && user.logged_in ? user : { logged_in: false, role: '' };

        if (currentUser.logged_in) {
            if (loginBtn) loginBtn.style.display = 'none';
            if (userProfile) userProfile.style.display = 'inline-flex';
            if (userName) {
                const fullName = currentUser.name || 'ผู้ใช้งาน';
                userName.textContent = formatDisplayName(fullName);
                userName.title = fullName;
            }
            if (userAvatar) userAvatar.textContent = currentUser.avatar_text || 'U';
            if (userMenu) {
                const profileLink = userMenu.querySelector('a');
                if (profileLink) profileLink.href = currentUser.dashboard_url || getDashboardUrlForRole(currentUser.role);
            }
            if (userProfileBtn && userMenu) {
                userProfileBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    userMenu.classList.toggle('show');
                });
                document.addEventListener('click', () => userMenu.classList.remove('show'));
            }
        } else {
            if (loginBtn) loginBtn.style.display = 'inline-block';
            if (userProfile) userProfile.style.display = 'none';
        }
    } catch (error) {
        currentUser = { logged_in: false, role: '' };
    }

    await loadAllCourses();

    const params = new URLSearchParams(window.location.search);
    const subjectId = params.get('subject_id');
    const courseName = params.get('course');
    if (subjectId) { setTimeout(() => { showCourse(subjectId); }, 300); } 
    else if (courseName) { setTimeout(() => { showCourse(courseName); }, 400); }
});
