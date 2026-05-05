// --- ตัวแปรหลักของระบบ ---
let currentSubjectId = '';
let currentCourseName = '';
let enrolledCourses = {};
let courseIdByName = {};
let currentLessonsData = [];
let currentProgressSummary = null;
const LESSON_COUNT = 5;
const LESSON_VIDEO_FILES = [
    'videos/lesson1.mp4.mp4',
    'videos/lesson-thai.mp4',
    'videos/lesson-english.mp4',
    'videos/lesson-math.mp4',
    'videos/lesson-science.mp4'
];

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

function buildFixedLessons(lessons) {
    const fixedLessons = [];
    const totalLessons = Array.isArray(lessons) && lessons.length > 0 ? lessons.length : LESSON_COUNT;
    for (let index = 0; index < totalLessons; index++) {
        const rawName = lessons[index] ? pick(lessons[index], 'Lessons_Name', 'lessons_name') : '';
        const lessonName = String(rawName || '').trim() || `Lesson ${index + 1}`;
        fixedLessons.push({
            index: index + 1,
            title: lessonName,
            expanded: false
        });
    }
    return fixedLessons;
}

function renderLessonAccordion(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;

    if (!currentLessonsData.length) {
        container.innerHTML = '';
        return;
    }

    const html = currentLessonsData.map((lesson) => `
        <div class="lesson-accordion ${lesson.expanded ? 'is-expanded' : 'is-collapsed'}">
            <button type="button" class="lesson-header" onclick="toggleLesson(${lesson.index})" aria-expanded="${lesson.expanded ? 'true' : 'false'}">
                <span>บทที่ ${lesson.index}: ${escapeHtml(lesson.title)}</span>
                <span class="lesson-chevron">${lesson.expanded ? '▴' : '▾'}</span>
            </button>
            <div class="lesson-body">
                <div class="curriculum-list">
                    <div class="curriculum-item">
                        <div class="curr-left">
                            <span class="curr-icon">📄</span>
                            <div class="curr-text">
                                <b>เอกสารประกอบบทที่ ${lesson.index}</b>
                                <p>ดาวน์โหลดเอกสารประกอบบทเรียนนี้</p>
                            </div>
                        </div>
                        <button class="btn-orange" onclick="downloadCourseLesson()">ดาวน์โหลดเอกสาร</button>
                    </div>
                    <div class="curriculum-item">
                        <div class="curr-left">
                            <span class="curr-icon">🎬</span>
                            <div class="curr-text">
                                <b>วิดีโอสรุปบทที่ ${lesson.index}</b>
                                <p>รับชมวิดีโอประกอบบทเรียนนี้</p>
                            </div>
                        </div>
                        <button class="btn-orange" onclick="openCourseVideo(${lesson.index})">ชมวิดีโอ</button>
                    </div>
                    <div class="curriculum-item">
                        <div class="curr-left">
                            <span class="curr-icon">📝</span>
                            <div class="curr-text">
                                <b>แบบทดสอบบทที่ ${lesson.index}</b>
                                <p>ทำแบบทดสอบเพื่อทบทวนความเข้าใจของบทนี้</p>
                            </div>
                        </div>
                        <button class="btn-outline-orange" onclick="startQuiz(${lesson.index})">เริ่มทำ Quiz</button>
                    </div>
                </div>
            </div>
        </div>
    `).join('');

    container.innerHTML = html;
}

function renderAllLessonAccordions() {
    renderLessonAccordion('course-curriculum-lesson-list');
    renderLessonAccordion('learning-lesson-text-list');
}

function setText(id, value) {
    const element = document.getElementById(id);
    if (element) {
        element.textContent = value;
    }
}

function updateInstructorInfo(course) {
    const teacherName = String(pick(course, 'teachers_name', 'Teachers_Name')).trim() || 'ยังไม่กำหนดอาจารย์ผู้สอน';
    const teacherRole = teacherName === 'ยังไม่กำหนดอาจารย์ผู้สอน'
        ? 'รอการกำหนดบทบาทอาจารย์ประจำวิชา'
        : 'อาจารย์ประจำวิชา';

    setText('detail-instructor-name', teacherName);
    setText('detail-instructor-role', teacherRole);
    setText('learning-instructor-name', teacherName);
    setText('learning-instructor-role', teacherRole);
}

function updateCourseMeta(course, lessons) {
    const lessonCount = Number(pick(course, 'lesson_count')) || (Array.isArray(lessons) ? lessons.length : 0);
    const quizCount = lessonCount > 0 ? lessonCount : currentLessonsData.length;
    setText('detail-lesson-count', `${lessonCount} บทเรียน`);
    setText('detail-quiz-count', `${quizCount} ชุด`);
    setText('learning-lesson-count', `${lessonCount} บทเรียน`);
    setText('learning-quiz-count', `${quizCount} ชุด`);
}

function applyCourseProgressSummary(summary) {
    currentProgressSummary = summary || null;
    const progressText = `${Number(summary?.progress_percent || 0).toFixed(1)}%`;
    const scoreText = `${Number(summary?.best_score_percent || 0).toFixed(1)}%`;
    setText('detail-progress', progressText);
    setText('detail-score', scoreText);
    setText('learning-progress', progressText);
    setText('learning-score', scoreText);
}

async function fetchCourseProgress(subjectId) {
    if (!subjectId || !enrolledCourses[subjectId]) {
        applyCourseProgressSummary(null);
        return;
    }

    try {
        const response = await fetch(`student_learning_api.php?action=summary&subject_id=${encodeURIComponent(subjectId)}`, {
            credentials: 'same-origin'
        });
        const result = await response.json();
        if (result.status === 'success') {
            applyCourseProgressSummary(result.summary || null);
        }
    } catch (error) {
        applyCourseProgressSummary(null);
    }
}

async function recordLearningEvent(activityType, lessonIndex = 1) {
    if (!currentSubjectId || !enrolledCourses[currentSubjectId]) {
        return;
    }

    try {
        const lesson = currentLessonsData.find((item) => item.index === Number(lessonIndex));
        const formData = new FormData();
        formData.append('action', 'record');
        formData.append('subject_id', currentSubjectId);
        formData.append('lesson_index', String(lessonIndex || 1));
        formData.append('lesson_title', lesson ? lesson.title : `${currentCourseName} บทที่ ${lessonIndex || 1}`);
        formData.append('activity_type', activityType);

        const response = await fetch('student_learning_api.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        const result = await response.json();
        if (result.status === 'success') {
            applyCourseProgressSummary(result.summary || null);
        }
    } catch (error) {
        // keep UI usable even if tracking fails
    }
}

function toggleLesson(lessonIndex) {
    currentLessonsData = currentLessonsData.map((lesson) => {
        if (lesson.index === lessonIndex) {
            return { ...lesson, expanded: !lesson.expanded };
        }
        return lesson;
    });
    renderAllLessonAccordions();
}

// --- 1. ระบบจัดการหน้าและ URL ---
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

// --- 2. ดึงข้อมูลจาก Database (API) ---
async function loadAllCourses() {
    try {
        const response = await fetch('api_courses.php?action=get_all');
        const result = await response.json();
        
        if (result.status === 'success') {
            const grid = document.getElementById('course-grid');
            const dropdown = document.getElementById('course-dropdown');
            if(!grid) return; // ป้องกัน error ถ้าไม่มี element นี้
            
            grid.innerHTML = ''; 
            if (dropdown) dropdown.innerHTML = '';
            
            result.data.forEach(course => {
                const subjectId = String(pick(course, 'Subjects_ID', 'subjects_id'));
                const subjectName = String(pick(course, 'Subjects_Name', 'subjects_name'));
                const subjectDesc = String(pick(course, 'Subjects_Description', 'subjects_description'));
                if (subjectName) courseIdByName[subjectName] = subjectId;

                // ถ้ามีรูปใน DB ค่อยดึงมาใช้ ถ้าไม่มีใช้รูป default
                const imgUrl = course.image_url || 'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?w=600&auto=format&fit=crop&q=60';
                
                const cardHtml = `
                    <div class="card" onclick="showCourse('${subjectId}')">
                        <img src="${imgUrl}" alt="Course Image">
                        <div class="card-content">
                            <span class="card-tag">หลักสูตรแนะนำ</span>
                            <h3>${subjectName || '-'}</h3>
                            <p>${subjectDesc || 'คลิกเพื่อดูรายละเอียด'}</p>
                        </div>
                    </div>
                `;
                grid.innerHTML += cardHtml;

                if (dropdown && subjectId) {
                    const a = document.createElement('a');
                    a.href = '#';
                    a.textContent = subjectName || subjectId;
                    a.addEventListener('click', (e) => {
                        e.preventDefault();
                        showCourse(subjectId);
                    });
                    dropdown.appendChild(a);
                }
            });

            if (dropdown && dropdown.children.length === 0) {
                dropdown.innerHTML = '<a href="#" onclick="return false;">ยังไม่มีรายวิชา</a>';
            }
        }
    } catch (error) {
        console.error("Error loading courses:", error);
        const grid = document.getElementById('course-grid');
        if(grid) grid.innerHTML = '<p style="text-align:center;">ไม่สามารถโหลดข้อมูลวิชาได้ กรุณาลองใหม่อีกครั้ง</p>';
    }
}

async function showCourse(subjectId) {
    if (courseIdByName[subjectId]) {
        subjectId = courseIdByName[subjectId];
    }
    currentSubjectId = subjectId;
    
    try {
        const response = await fetch(`api_courses.php?action=get_detail&id=${subjectId}`);
        const result = await response.json();
        
        if (result.status === 'success') {
            const course = result.course;
            const lessons = result.lessons;
            currentCourseName = String(pick(course, 'Subjects_Name', 'subjects_name')); // เก็บชื่อไว้ใช้ต่อ
            
            // อัปเดตข้อมูลหน้า Detail
            document.getElementById('detail-title').innerText = currentCourseName;
            document.getElementById('detail-desc-top').innerText = String(pick(course, 'Subjects_Description', 'subjects_description')) || 'รายละเอียดเบื้องต้น';
            document.getElementById('detail-desc').innerText = String(pick(course, 'Subjects_Description', 'subjects_description')) || 'คำอธิบายรายวิชา...';
            document.getElementById('detail-duration').innerText = course.duration || 'ไม่ระบุเวลา';
            updateInstructorInfo(course);
            
            currentLessonsData = buildFixedLessons(Array.isArray(lessons) ? lessons : []);
            updateCourseMeta(course, lessons);
            renderAllLessonAccordions();
            applyCourseProgressSummary(null);

            // จัดการสถานะปุ่มลงทะเบียน
            updateEnrollButton(false);
            setCurriculumAccess(false);
            checkCourseEnrollment(subjectId); 

            // สลับไปหน้า Detail และเปิดแท็บ Overview
            openTab({ currentTarget: document.querySelector('.tab-btn') }, 'overview');
            showPage('course-detail');
            
            // เปลี่ยน URL ให้สวยงามและแชร์ได้
            const url = new URL(window.location.href);
            url.searchParams.set('subject_id', subjectId);
            window.history.replaceState({}, '', url);
        } else {
            console.warn('ไม่พบข้อมูลรายวิชา:', result.message);
        }
    } catch (error) {
        console.error("Error fetching course detail:", error);
        alert('เชื่อมต่อฐานข้อมูลล้มเหลว');
    }
}

// --- 3. ระบบการเรียน (Learning Page) ---
function goToCourseLearning() {
    if (!enrolledCourses[currentSubjectId]) {
        alert('กรุณาลงรายวิชาก่อนเข้าเนื้อหาบทเรียน');
        return;
    }

    // ก็อปปี้เนื้อหาจากหน้า Detail มาใส่หน้า Learning
    document.getElementById('learning-title').innerText = document.getElementById('detail-title').innerText;
    document.getElementById('learning-desc-top').innerText = document.getElementById('detail-desc-top').innerText;
    document.getElementById('learning-desc').innerText = document.getElementById('detail-desc').innerText;
    document.getElementById('learning-duration').innerText = document.getElementById('detail-duration').innerText;
    renderAllLessonAccordions();

    showPage('course-learning');
    openLearningTab({ currentTarget: document.querySelector("#course-learning .tab-btn[onclick*='learning-curriculum']") }, 'learning-curriculum');
    recordLearningEvent('course_enter', 1);
}

// --- 4. ระบบเช็คและลงทะเบียนเรียน ---
async function checkCourseEnrollment(subjectId) {
    try {
        const response = await fetch(`course_enrollment_api.php?subject_id=${encodeURIComponent(subjectId)}`, {
            credentials: 'same-origin'
        });
        const result = await response.json();

        if (result.status === 'success') {
            enrolledCourses[subjectId] = Boolean(result.enrolled);
            updateEnrollButton(Boolean(result.enrolled));
            setCurriculumAccess(Boolean(result.enrolled));
            if (result.enrolled) {
                fetchCourseProgress(subjectId);
            }
        } else if (result.status === 'unauthorized') {
            enrolledCourses[subjectId] = false;
            updateEnrollButton(false);
            setCurriculumAccess(false);
            applyCourseProgressSummary(null);
        }
    } catch (error) {
        enrolledCourses[subjectId] = false;
        updateEnrollButton(false);
        setCurriculumAccess(false);
        applyCourseProgressSummary(null);
    }
}

function updateEnrollButton(isEnrolled) {
    const button = document.getElementById('enroll-course-btn');
    if (!button) return;
    
    if(isEnrolled) {
        button.innerText = 'เข้าเรียน';
        button.classList.add('is-enrolled');
    } else {
        button.innerText = 'ลงทะเบียน';
        button.classList.remove('is-enrolled');
    }
}

async function enrollCourseAndOpenLearning() {
    if (!currentSubjectId) return;

    if (enrolledCourses[currentSubjectId]) {
        goToCourseLearning();
        return;
    }

    try {
        const formData = new FormData();
        formData.append('subject_id', currentSubjectId);

        const response = await fetch('course_enrollment_api.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        const result = await response.json();

        if (result.status === 'unauthorized') {
            alert(result.message || 'กรุณาเข้าสู่ระบบนักเรียนก่อนลงรายวิชา');
            const loginUrl = new URL(result.login_url || 'login.php', window.location.href);
            loginUrl.searchParams.set('return', getCourseReturnUrl(currentCourseName));
            window.location.href = loginUrl.href;
            return;
        }

        if (result.status !== 'success') {
            alert(result.message || 'ไม่สามารถลงรายวิชาได้');
            return;
        }

        enrolledCourses[currentSubjectId] = true;
        updateEnrollButton(true);
        setCurriculumAccess(true);
        fetchCourseProgress(currentSubjectId);
        goToCourseLearning();
    } catch (error) {
        alert('เชื่อมต่อระบบลงรายวิชาไม่ได้ กรุณาลองใหม่อีกครั้ง');
    }
}

// --- 5. ระบบ UI ทั่วไป (Tabs, Search) ---
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

// --- 6. ไฟล์และ Modal ---
function downloadCourseLesson() {
    if (!enrolledCourses[currentSubjectId]) {
        alert('กรุณาลงรายวิชาก่อนดาวน์โหลดเอกสาร');
        return;
    }
    // ส่ง Subject ID ไปดึงไฟล์แทน
    window.open(`download_course_lesson.php?subject_id=${encodeURIComponent(currentSubjectId)}`, '_blank');
}

function getLessonVideoPath(lessonIndex) {
    const safeIndex = Math.max(1, Math.min(LESSON_COUNT, Number(lessonIndex) || 1));
    const fallbackPath = LESSON_VIDEO_FILES[0];
    return LESSON_VIDEO_FILES[safeIndex - 1] || fallbackPath;
}

function renderVideoModalBody(lessonIndex) {
    const body = document.getElementById('modal-body');
    if (!body) return;

    const safeIndex = Math.max(1, Math.min(LESSON_COUNT, Number(lessonIndex) || 1));
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
}

function changeModalLessonVideo(lessonIndex) {
    renderVideoModalBody(lessonIndex);
}

function openCourseVideo(lessonIndex) {
    if (!enrolledCourses[currentSubjectId]) {
        alert('กรุณาลงรายวิชาก่อนชมวิดีโอ');
        return;
    }
    const modal = document.getElementById('modal-overlay');
    renderVideoModalBody(lessonIndex || 1);
    modal.style.display = 'flex';
    recordLearningEvent('video_open', lessonIndex || 1);
}

function startQuiz(lessonIndex) {
    if (!enrolledCourses[currentSubjectId]) {
        alert('กรุณาลงรายวิชาก่อนทำแบบทดสอบ');
        return;
    }
    const url = new URL('test.html', window.location.href);
    if (currentCourseName) {
        url.searchParams.set('course', currentCourseName);
    }
    if (currentSubjectId) {
        url.searchParams.set('subject_id', currentSubjectId);
    }
    if (lessonIndex) {
        url.searchParams.set('lesson', String(lessonIndex));
    }
    recordLearningEvent('lesson_open', lessonIndex || 1);
    window.location.href = url.pathname.split('/').pop() + url.search;
}

function setCurriculumAccess(isEnrolled) {
    const lockedMsg = document.getElementById('curriculum-locked-msg');
    const lessonList = document.getElementById('course-curriculum-lesson-list');
    if (!lockedMsg || !lessonList) return;

    if (isEnrolled) {
        lockedMsg.style.display = 'none';
        lessonList.style.display = currentLessonsData.length ? 'grid' : 'none';
    } else {
        lockedMsg.style.display = 'block';
        lessonList.style.display = 'none';
    }
}

function closeModal() {
    const modalBody = document.getElementById('modal-body');
    if(modalBody) modalBody.innerHTML = '';
    document.getElementById('modal-overlay').style.display = 'none';
}

// --- 7. ข้อมูล Static (คู่มือ / ติดต่อ) ---
function showGuide(type) {
    const title = document.getElementById('guide-title');
    const content = document.getElementById('guide-content');
    const guides = {
        'register': ['ขั้นตอนการสมัคร', '1. กด Login <br> 2. กรอก Email <br> 3. เริ่มเรียน'],
        'search': ['วิธีค้นหาบทเรียน', 'ใช้ช่อง Search พิมพ์ชื่อวิชาที่สนใจ'],
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
        content.innerHTML = `<div class="info-card">Email: Flexible@hub.com <br> Line: @FlexibleHub/div>`;
    } else {
        title.innerText = 'สถานที่ตั้ง';
        content.innerHTML = `<div class="info-card">อาคาร Flexible Hub ชั้น 10 กรุงเทพฯ</div>`;
    }
    showPage('page-contact');
}

// --- Initializer ---
document.addEventListener('DOMContentLoaded', () => {
    // โหลดรายวิชาทั้งหมดจาก DB
    loadAllCourses();

    fetch('student_session.php', { credentials: 'same-origin' })
        .then((res) => res.json())
        .then((user) => {
            const loginBtn = document.getElementById('loginBtn');
            const userProfile = document.getElementById('userProfile');
            const userName = document.getElementById('userName');
            const userAvatar = document.getElementById('userAvatar');
            const userProfileBtn = document.getElementById('userProfileBtn');
            const userMenu = document.getElementById('userMenu');

            if (user && user.logged_in) {
                if (loginBtn) loginBtn.style.display = 'none';
                if (userProfile) userProfile.style.display = 'inline-flex';
                if (userName) userName.textContent = user.name || 'ผู้ใช้งาน';
                if (userAvatar) userAvatar.textContent = user.avatar_text || 'U';
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
        })
        .catch(() => {});

    // เช็คว่ามี Parameter การเข้าถึงวิชาตรงๆ ไหม (เช่น ตอนแชร์ลิงก์ให้เพื่อน)
    const params = new URLSearchParams(window.location.search);
    const subjectId = params.get('subject_id');
    const courseName = params.get('course');
    if (subjectId) {
        setTimeout(() => { showCourse(subjectId); }, 300);
    } else if (courseName) {
        // รองรับลิงก์ย้อนกลับจาก test.html ที่ส่งชื่อวิชามา
        setTimeout(() => { showCourse(courseName); }, 400);
    }
});
