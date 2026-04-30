// 1. ข้อมูลจำลอง (Mock Data)
const coursesData = [
    { id: '01', name: 'คณิตศาสตร์พื้นฐาน', progress: 90, score: 92, status: 'ดีเยี่ยม', class: 'excellent', update: '2 ชม. ที่แล้ว' },
    { id: '02', name: 'ภาษาอังกฤษเพื่อการสื่อสาร', progress: 75, score: 85, status: 'ดี', class: 'good', update: 'เมื่อวานนี้' },
    { id: '03', name: 'วิทยาศาสตร์กายภาพ', progress: 50, score: 71, status: 'ปานกลาง', class: 'average', update: '3 วันที่แล้ว' },
    { id: '04', name: 'ประวัติศาสตร์ไทย', progress: 30, score: 58, status: 'ต้องดูแล', class: 'warning', update: '1 สัปดาห์ที่แล้ว' },
    { id: '05', name: 'ศิลปะสร้างสรรค์', progress: 85, score: 88, status: 'ดี', class: 'good', update: '5 วันที่แล้ว' }
];

// 2. การควบคุมการสลับหน้า (Navigation)
const btnDashboard = document.getElementById('btn-dashboard');
const btnLessons = document.getElementById('btn-lessons');
const pageDashboard = document.getElementById('dashboard-page');
const pageLessons = document.getElementById('lesson-page');

function switchPage(activeBtn, activePage, inactiveBtn, inactivePage) {
    activeBtn.classList.add('active');
    inactiveBtn.classList.remove('active');
    activePage.style.display = 'block';
    inactivePage.style.display = 'none';
}

btnLessons.addEventListener('click', () => {
    switchPage(btnLessons, pageLessons, btnDashboard, pageDashboard);
    renderLessonCards();
});

btnDashboard.addEventListener('click', () => {
    switchPage(btnDashboard, pageDashboard, btnLessons, pageLessons);
});

// 3. ฟังก์ชัน Render ข้อมูลในตาราง (หน้า Dashboard)
function renderTable(data) {
    const tableBody = document.getElementById('courseTableBody');
    tableBody.innerHTML = data.map(item => `
        <tr>
            <td style="color: #888;">${item.id}</td>
            <td style="font-weight: 500;">${item.name}</td>
            <td>${item.progress}%</td>
            <td>${item.score}</td>
            <td><span class="status-label ${item.class}">${item.status}</span></td>
        </tr>
    `).join('');
}

// 4. ฟังก์ชัน Render การ์ดบทเรียน (หน้า Lessons)
function renderLessonCards() {
    const container = document.getElementById('lessons-list');
    container.innerHTML = coursesData.map(course => `
        <div class="lesson-card">
            <h3 style="margin-bottom: 5px;">${course.name}</h3>
            <p style="font-size: 0.75rem; color: #888;">อัปเดตล่าสุด: ${course.update}</p>
            
            <div class="progress-container" style="margin-top: 20px;">
                <div style="display: flex; justify-content: space-between; font-size: 0.8rem; margin-bottom: 5px;">
                    <span>ความคืบหน้า</span>
                    <span style="color: var(--accent-orange); font-weight: bold;">${course.progress}%</span>
                </div>
                <div class="progress-bar-bg">
                    <div class="progress-bar-fill" style="width: ${course.progress}%"></div>
                </div>
            </div>
            
            <button class="btn-learn">เข้าสู่บทเรียน</button>
        </div>
    `).join('');
}

// 5. ระบบค้นหา (Search)
document.getElementById('courseSearch').addEventListener('input', (e) => {
    const term = e.target.value.toLowerCase();
    const filtered = coursesData.filter(c => c.name.toLowerCase().includes(term));
    renderTable(filtered);
});

// 6. Initial Load
renderTable(coursesData);