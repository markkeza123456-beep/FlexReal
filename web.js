// --- ตัวแปรหลักของระบบ ---
let currentSubjectId = '';
let currentCourseName = '';
let enrolledCourses = {};
let courseIdByName = {};

function pick(obj, ...keys) {
    for (const key of keys) {
        if (obj && obj[key] !== undefined && obj[key] !== null) return obj[key];
    }
    return '';
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
            if(!grid) return; // ป้องกัน error ถ้าไม่มี element นี้
            
            grid.innerHTML = ''; 
            
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
            });
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
            
            // วาดรายการบทเรียนในแท็บ "เนื้อหาในคอร์ส"
            const lessonContainer = document.getElementById('course-curriculum-lesson-list');
            if (lessons.length > 0) {
                lessonContainer.innerHTML = lessons.map((lesson, index) => `<p>${index + 1}. ${pick(lesson, 'Lessons_Name', 'lessons_name') || '-'}</p>`).join('');
            } else {
                lessonContainer.innerHTML = '<p style="color: gray;">ยังไม่มีการเพิ่มเนื้อหาบทเรียนสำหรับวิชานี้</p>';
            }

            // จัดการสถานะปุ่มลงทะเบียน
            updateEnrollButton(false);
            checkCourseEnrollment(subjectId); 

            // สลับไปหน้า Detail และเปิดแท็บ Overview
            openTab({ currentTarget: document.querySelector('.tab-btn') }, 'overview');
            showPage('course-detail');
            
            // เปลี่ยน URL ให้สวยงามและแชร์ได้
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
    document.getElementById('learning-lesson-text-list').innerHTML = document.getElementById('course-curriculum-lesson-list').innerHTML;

    showPage('course-learning');
    openLearningTab({ currentTarget: document.querySelector("#course-learning .tab-btn[onclick*='learning-curriculum']") }, 'learning-curriculum');
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
        } else if (result.status === 'unauthorized') {
            enrolledCourses[subjectId] = false;
            updateEnrollButton(false);
        }
    } catch (error) {
        enrolledCourses[subjectId] = false;
        updateEnrollButton(false);
    }
}

function updateEnrollButton(isEnrolled) {
    const button = document.getElementById('enroll-course-btn');
    if (!button) return;
    
    if(isEnrolled) {
        button.innerText = 'เข้าเรียน';
        button.classList.add('is-enrolled');
    } else {
        button.innerText = 'ลงรายวิชา';
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

function openCourseVideo() {
    if (!enrolledCourses[currentSubjectId]) {
        alert('กรุณาลงรายวิชาก่อนชมวิดีโอ');
        return;
    }
    const modal = document.getElementById('modal-overlay');
    const body = document.getElementById('modal-body');
    // อนาคตสามารถดึง URL วิดีโอจาก DB มาใส่ตรงนี้ได้
    const videoPath = 'lesson1.mp4'; 

    body.innerHTML = `
        <h3 style="margin-bottom:15px; color:#E67E22;">🎥 วิดีโอบทเรียน</h3>
        <video width="100%" controls autoplay style="border-radius:10px; background:#000;">
            <source src="${videoPath}" type="video/mp4">
            เบราว์เซอร์ไม่รองรับวิดีโอ
        </video>
    `;
    modal.style.display = 'flex';
}

function startQuiz() {
    if (!enrolledCourses[currentSubjectId]) {
        alert('กรุณาลงรายวิชาก่อนทำแบบทดสอบ');
        return;
    }
    const modal = document.getElementById('modal-overlay');
    const body = document.getElementById('modal-body');
    body.innerHTML = `
        <h3 style="margin-bottom:20px; color:#E67E22;">📝 แบบทดสอบหลังเรียน</h3>
        <div style="text-align:left; background:#f9f9f9; padding:20px; border-radius:10px;">
            <p>ระบบเตรียมแสดงแบบทดสอบสำหรับรายวิชานี้...</p>
        </div>
        <button class="btn-enroll" style="margin-top:20px; width:100%;" onclick="closeModal()">ปิดหน้าต่าง</button>
    `;
    modal.style.display = 'flex';
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
    if (subjectId) {
        setTimeout(() => { showCourse(subjectId); }, 300); // ดีเลย์นิดนึงรอให้หน้าเตรียมตัวเสร็จ
    }
});
