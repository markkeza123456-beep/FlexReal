function showPage(pageId) {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    const targetPage = document.getElementById(pageId);
    if (targetPage) targetPage.classList.add('active');
    window.scrollTo(0, 0);
}

function getCourseReturnUrl(courseName) {
    const url = new URL('web.html', window.location.href);
    url.searchParams.set('course', courseName);
    return url.pathname.split('/').pop() + url.search;
}

function filterCourses() {
    let input = document.getElementById('searchInput').value.toLowerCase();
    let cards = document.getElementsByClassName('card');
    for (let i = 0; i < cards.length; i++) {
        let title = cards[i].querySelector('h3').innerText.toLowerCase();
        cards[i].style.display = title.includes(input) ? "" : "none";
    }
}

let currentCourseName = '';
let enrolledCourses = {};

const courseData = {
    'คณิตศาสตร์': { top: 'เน้นการคิดวิเคราะห์และแก้โจทย์', bot: 'เจาะลึกตรรกะระดับสากล', time: '10 ชั่วโมง' },
    'ภาษาไทย': { top: 'การสื่อสารในที่ทำงาน', bot: 'ทักษะภาษาเพื่อประสิทธิภาพการทำงาน', time: '5 ชั่วโมง' },
    'วิทยาศาสตร์': { top: 'กระบวนการและนวัตกรรม', bot: 'เรียนรู้ฟิสิกส์ เคมี ชีววิทยาพื้นฐาน', time: '12 ชั่วโมง' },
    'สังคมศึกษา': { top: 'วัฒนธรรมและความเป็นพลเมือง', bot: 'ความหลากหลายทางวัฒนธรรม', time: '6 ชั่วโมง' },
    'ภาษาอังกฤษ': { top: 'Business Communication', bot: 'Professional English for career', time: '8 ชั่วโมง' }
};

const lessonTextByCourse = {
    'คณิตศาสตร์': [
        'บทที่ 1: จำนวนนับและการคำนวณพื้นฐาน',
        'บทที่ 2: เศษส่วน ทศนิยม และร้อยละ',
        'บทที่ 3: โจทย์ปัญหาและการคิดวิเคราะห์'
    ],
    'ภาษาไทย': [
        'บทที่ 1: การอ่านจับใจความสำคัญ',
        'บทที่ 2: การเขียนสื่อสารให้ชัดเจน',
        'บทที่ 3: หลักภาษาไทยที่ใช้บ่อยในการทำงาน'
    ],
    'วิทยาศาสตร์': [
        'บทที่ 1: กระบวนการทางวิทยาศาสตร์',
        'บทที่ 2: สารและสมบัติของสาร',
        'บทที่ 3: พลังงานและการเปลี่ยนรูป'
    ],
    'สังคมศึกษา': [
        'บทที่ 1: หน้าที่พลเมืองและสังคม',
        'บทที่ 2: วัฒนธรรมและการอยู่ร่วมกัน',
        'บทที่ 3: เศรษฐกิจพื้นฐานในชีวิตประจำวัน'
    ],
    'ภาษาอังกฤษ': [
        'Lesson 1: Basic Workplace Communication',
        'Lesson 2: Writing Professional Messages',
        'Lesson 3: Speaking with Confidence'
    ]
};

function showCourse(courseName) {
    currentCourseName = courseName;
    document.getElementById('detail-title').innerText = courseName;
    const descTop = document.getElementById('detail-desc-top');
    const descBottom = document.getElementById('detail-desc');
    const duration = document.getElementById('detail-duration');

    const data = courseData[courseName] || { top: 'รายละเอียดวิชา...', bot: 'หลักสูตรคุณภาพ', time: '6 ชั่วโมง' };
    descTop.innerText = data.top;
    descBottom.innerText = data.bot;
    duration.innerText = data.time;
    renderLessonText(courseName, 'course-curriculum-lesson-list');
    updateEnrollButton(false);
    checkCourseEnrollment(courseName);

    // Reset Tab ไปที่หน้าแรกเสมอ
    openTab({ currentTarget: document.querySelector('.tab-btn') }, 'overview');
    showPage('course-detail');

    const url = new URL(window.location.href);
    url.searchParams.set('course', courseName);
    window.history.replaceState({}, '', url);
}

function renderLessonText(courseName, targetId = 'learning-lesson-text-list') {
    const container = document.getElementById(targetId);
    if (!container) return;

    const lessons = lessonTextByCourse[courseName] || [
        'บทที่ 1: เนื้อหาแนะนำรายวิชา',
        'บทที่ 2: กิจกรรมฝึกทักษะ',
        'บทที่ 3: สรุปและทบทวนก่อนทำแบบทดสอบ'
    ];

    container.innerHTML = lessons
        .map((lesson, index) => `<p>${index + 1}. ${lesson}</p>`)
        .join('');
}

function goToCourseLearning() {
    if (!enrolledCourses[currentCourseName]) {
        alert('กรุณาลงรายวิชาก่อนเข้าเนื้อหาบทเรียน');
        return;
    }

    renderLearningPage(currentCourseName);
    showPage('course-learning');
    openLearningTab({ currentTarget: document.querySelector("#course-learning .tab-btn[onclick*='learning-curriculum']") }, 'learning-curriculum');
}

async function checkCourseEnrollment(courseName) {
    try {
        const response = await fetch(`course_enrollment_api.php?course_name=${encodeURIComponent(courseName)}`, {
            credentials: 'same-origin'
        });
        const result = await response.json();

        if (result.status === 'success') {
            enrolledCourses[courseName] = Boolean(result.enrolled);
            updateEnrollButton(Boolean(result.enrolled));
        } else if (result.status === 'unauthorized') {
            enrolledCourses[courseName] = false;
            updateEnrollButton(false);
        }
    } catch (error) {
        enrolledCourses[courseName] = false;
        updateEnrollButton(false);
    }
}

function updateEnrollButton(isEnrolled) {
    const button = document.getElementById('enroll-course-btn');
    if (!button) return;

    button.innerText = isEnrolled ? 'เข้าเรียน' : 'ลงรายวิชา';
}

async function enrollCourseAndOpenLearning() {
    if (!currentCourseName) return;

    if (enrolledCourses[currentCourseName]) {
        goToCourseLearning();
        return;
    }

    try {
        const formData = new FormData();
        formData.append('course_name', currentCourseName);

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

        enrolledCourses[currentCourseName] = true;
        updateEnrollButton(true);
        goToCourseLearning();
    } catch (error) {
        alert('เชื่อมต่อระบบลงรายวิชาไม่ได้ กรุณาลองใหม่อีกครั้ง');
    }
}

function renderLearningPage(courseName) {
    const data = courseData[courseName] || { top: 'เริ่มเรียนรายวิชา', bot: 'หลักสูตรคุณภาพ', time: '6 ชั่วโมง' };
    document.getElementById('learning-title').innerText = courseName;
    document.getElementById('learning-desc-top').innerText = data.top;
    document.getElementById('learning-desc').innerText = data.bot;
    document.getElementById('learning-duration').innerText = data.time;
    renderLessonText(courseName, 'learning-lesson-text-list');
}

function openLearningTab(evt, tabName) {
    document.querySelectorAll("#course-learning .learning-tab-content").forEach(t => t.style.display = "none");
    document.querySelectorAll("#course-learning .tab-btn").forEach(b => b.classList.remove("active"));

    document.getElementById(tabName).style.display = "block";
    if (evt && evt.currentTarget) evt.currentTarget.classList.add("active");
}

// --- 3. ระบบ Tab ---
function openTab(evt, tabName) {
    document.querySelectorAll(".tab-content").forEach(t => t.style.display = "none");
    document.querySelectorAll(".tab-btn").forEach(b => b.classList.remove("active"));
    
    document.getElementById(tabName).style.display = "block";
    if (evt && evt.currentTarget) evt.currentTarget.classList.add("active");
}

// --- 4. การจัดการไฟล์เอกสารและวิดีโอ ---
function downloadLesson(fileName) {
    const filePath = 'docs/' + fileName;
    window.open(filePath, '_blank');
}

function downloadCourseLesson() {
    if (!enrolledCourses[currentCourseName]) {
        alert('กรุณากดไปยังรายวิชาเพื่อลงรายวิชาก่อนดาวน์โหลดเอกสาร');
        return;
    }
    window.open(`download_course_lesson.php?course_name=${encodeURIComponent(currentCourseName)}`, '_blank');
}

function openLocalVideo(fileName) {
    if (!enrolledCourses[currentCourseName]) {
        alert('กรุณาลงรายวิชาก่อนชมวิดีโอ');
        return;
    }

    const modal = document.getElementById('modal-overlay');
    const body = document.getElementById('modal-body');
    const videoPath = 'videos/' + fileName; 

    body.innerHTML = `
        <h3 style="margin-bottom:15px; color:#E67E22;">🎥 วิดีโอบทเรียน</h3>
        <video width="100%" controls autoplay style="border-radius:10px; background:#000;">
            <source src="${videoPath}" type="video/mp4">
            เบราว์เซอร์ไม่รองรับวิดีโอ
        </video>
    `;
    modal.style.display = 'flex';
}

// --- 5. ระบบ Modal & Quiz ---
function startQuiz() {
    if (!enrolledCourses[currentCourseName]) {
        alert('กรุณาลงรายวิชาก่อนทำแบบทดสอบ');
        return;
    }

    const modal = document.getElementById('modal-overlay');
    const body = document.getElementById('modal-body');
    body.innerHTML = `
        <h3 style="margin-bottom:20px; color:#E67E22;">📝 แบบทดสอบหลังเรียน</h3>
        <div style="text-align:left; background:#f9f9f9; padding:20px; border-radius:10px;">
            <p><b>ข้อที่ 1:</b> ข้อใดคือหลักการของ Flexible Hub?</p>
            <label style="display:block; margin:10px 0;"><input type="radio" name="q1"> ก. เรียนที่ไหน เมื่อไหร่ก็ได้</label>
            <label style="display:block; margin:10px 0;"><input type="radio" name="q1"> ข. ต้องเข้าเรียนตามเวลา</label>
        </div>
        <button class="btn-enroll" style="margin-top:20px; width:100%;" onclick="submitQuiz()">ส่งคำตอบ</button>
    `;
    modal.style.display = 'flex';
}

function submitQuiz() {
    alert('บันทึกคะแนนเรียบร้อยแล้ว!');
    closeModal();
}

function closeModal() {
    const modalBody = document.getElementById('modal-body');
    if(modalBody) modalBody.innerHTML = '';
    document.getElementById('modal-overlay').style.display = 'none';
}

// --- 6. คู่มือและติดต่อ ---
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

function openCourseVideo() {
    const videoByCourse = {
        'คณิตศาสตร์': 'lesson-math.mp4',
        'ภาษาไทย': 'lesson-thai.mp4',
        'วิทยาศาสตร์': 'lesson-science.mp4',
        'สังคมศึกษา': 'lesson-social.mp4',
        'ภาษาอังกฤษ': 'lesson-english.mp4'
    };

    const fallbackVideo = 'lesson1.mp4';
    const selectedVideo = videoByCourse[currentCourseName] || fallbackVideo;
    openLocalVideo(selectedVideo);
}

document.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    const courseName = params.get('course');
    if (courseName) {
        showCourse(courseName);
    }
});
