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
    assignments: document.getElementById('btn-assignments')
};

const pages = {
    dashboard: document.getElementById('dashboard-page'),
    lessons: document.getElementById('lesson-page'),
    assignments: document.getElementById('assignment-page')
};

// Function: Switch Page
function showPage(pageKey) {
    Object.keys(pages).forEach(key => {
        pages[key].style.display = (key === pageKey) ? 'block' : 'none';
        navBtns[key].classList.toggle('active', key === pageKey);
    });

    if (pageKey === 'lessons') renderLessons();
    if (pageKey === 'assignments') renderAssignments();
}

// Event Listeners
navBtns.dashboard.addEventListener('click', () => showPage('dashboard'));
navBtns.lessons.addEventListener('click', () => showPage('lessons'));
navBtns.assignments.addEventListener('click', () => showPage('assignments'));

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