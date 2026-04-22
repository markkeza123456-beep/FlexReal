/**
 * ฟังก์ชันหลักสำหรับสลับหน้าจอ
 */
function showPage(pageId) {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    document.getElementById(pageId).classList.add('active');
    window.scrollTo(0, 0);
}

/**
 * ฟังก์ชันสำหรับค้นหาวิชา (เพิ่มเข้าไปใหม่)
 * ทำงานร่วมกับ id="searchInput" ในไฟล์ HTML
 */
function filterCourses() {
    let input = document.getElementById('searchInput').value.toLowerCase();
    let cards = document.getElementsByClassName('card');

    for (let i = 0; i < cards.length; i++) {
        let title = cards[i].querySelector('h3').innerText.toLowerCase();
        if (title.includes(input)) {
            cards[i].style.display = ""; // แสดงผล
        } else {
            cards[i].style.display = "none"; // ซ่อน
        }
    }
}

/**
 * ฟังก์ชันแสดงรายละเอียดวิชา
 */
function showCourse(courseName) {
    document.getElementById('detail-title').innerText = courseName;
    document.getElementById('detail-desc').innerText = 'ยินดีต้อนรับเข้าสู่บทเรียน ' + courseName + ' เริ่มต้นพัฒนาทักษะได้เลย';
    showPage('course-detail');
}

/**
 * ฟังก์ชันแสดงหน้าคู่มือการเรียน
 */
function showGuide(type) {
    const title = document.getElementById('guide-title');
    const content = document.getElementById('guide-content');
    showPage('page-guide');

    if (type === 'register') {
        title.innerText = 'ขั้นตอนการสมัครสมาชิก';
        content.innerHTML = '<div class="orange-body"><div class="info-card"><h4>📝 วิธีเริ่มใช้งาน</h4><p>1. คลิกปุ่ม Login มุมขวาบน<br>2. สมัครด้วย Email หรือ Google<br>3. ยืนยันตัวตนแล้วเริ่มเรียนได้ทันที</p></div></div>';
    } else if (type === 'search') {
        title.innerText = 'วิธีค้นหาบทเรียน';
        content.innerHTML = '<div class="orange-body"><div class="info-card"><h4>🔍 การค้นหา</h4><p>พิมพ์ชื่อวิชาในช่อง Search หรือเลือกดูตามหมวดหมู่ที่เมนูด้านบน</p></div></div>';
    } else if (type === 'certificate') {
        title.innerText = 'การรับใบประกาศ';
        content.innerHTML = '<div class="orange-body"><div class="info-card"><h4>🎓 วุฒิบัตรดิจิทัล</h4><p>เมื่อเรียนจบครบ 100% และสอบผ่านเกณฑ์ ระบบจะให้ดาวน์โหลดใบประกาศทันที</p></div></div>';
    }
}

/**
 * ฟังก์ชันแสดงหน้าติดต่อเรา
 */
function showContact(type) {
    const title = document.getElementById('contact-title');
    const content = document.getElementById('contact-content');
    showPage('page-contact');

    if (type === 'channel') {
        title.innerText = 'ช่องทางการติดต่อ';
        content.innerHTML = '<div class="orange-body"><div class="info-card"><h4>📞 ข้อมูลติดต่อ</h4><p><b>อีเมล:</b> contact@flexiblehub.org</p><p><b>Line:</b> @flexiblehub</p></div></div>';
    } else if (type === 'location') {
        title.innerText = 'สถานที่ตั้ง';
        content.innerHTML = '<div class="orange-body"><div class="info-card"><h4>📍 ที่อยู่สำนักงาน</h4><p>อาคารเรียนรู้ ชั้น 10 กรุงเทพมหานคร</p></div></div>';
    }
}