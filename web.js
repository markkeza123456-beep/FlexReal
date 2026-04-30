function showPage(pageId) {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    const targetPage = document.getElementById(pageId);
    if (targetPage) targetPage.classList.add('active');
    window.scrollTo(0, 0);
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

function showCourse(courseName) {
    currentCourseName = courseName;
    document.getElementById('detail-title').innerText = courseName;
    const descTop = document.getElementById('detail-desc-top');
    const descBottom = document.getElementById('detail-desc');
    const duration = document.getElementById('detail-duration');

    const courseData = {
        'คณิตศาสตร์': { top: 'เน้นการคิดวิเคราะห์และแก้โจทย์', bot: 'เจาะลึกตรรกะระดับสากล', time: '10 ชั่วโมง' },
        'ภาษาไทย': { top: 'การสื่อสารในที่ทำงาน', bot: 'ทักษะภาษาเพื่อประสิทธิภาพการทำงาน', time: '5 ชั่วโมง' },
        'วิทยาศาสตร์': { top: 'กระบวนการและนวัตกรรม', bot: 'เรียนรู้ฟิสิกส์ เคมี ชีววิทยาพื้นฐาน', time: '12 ชั่วโมง' },
        'สังคมศึกษา': { top: 'วัฒนธรรมและความเป็นพลเมือง', bot: 'ความหลากหลายทางวัฒนธรรม', time: '6 ชั่วโมง' },
        'ภาษาอังกฤษ': { top: 'Business Communication', bot: 'Professional English for career', time: '8 ชั่วโมง' }
    };

    const data = courseData[courseName] || { top: 'รายละเอียดวิชา...', bot: 'หลักสูตรคุณภาพ', time: '6 ชั่วโมง' };
    descTop.innerText = data.top;
    descBottom.innerText = data.bot;
    duration.innerText = data.time;

    // Reset Tab ไปที่หน้าแรกเสมอ
    openTab({ currentTarget: document.querySelector('.tab-btn') }, 'overview');
    showPage('course-detail');
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
    const lessonByCourse = {
        'คณิตศาสตร์': 'lesson-math.pdf',
        'ภาษาไทย': 'lesson-thai.pdf',
        'วิทยาศาสตร์': 'lesson-science.pdf',
        'สังคมศึกษา': 'lesson-social.pdf',
        'ภาษาอังกฤษ': 'lesson-english.pdf'
    };

    const fallbackLesson = 'lesson1.pdf';
    const selectedLesson = lessonByCourse[currentCourseName] || fallbackLesson;
    downloadLesson(selectedLesson);
}

function openLocalVideo(fileName) {
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