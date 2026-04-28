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
ฟังก์ชันแสดงรายละเอียดวิชา
 */
function showCourse(courseName) {
    const titleElement = document.getElementById('detail-title');
    const descTopElement = document.getElementById('detail-desc-top'); // ตัวแปรสำหรับบรรทัดบน
    const descElement = document.getElementById('detail-desc');       // ตัวแปรใน Tab Overview
    const durationElement = document.getElementById('detail-duration');

    // เปลี่ยนชื่อหัวข้อหลัก
    titleElement.innerText = courseName;
    
    // ตรวจสอบวิชาและเปลี่ยนข้อความให้ตรงกัน
    if (courseName === 'คณิตศาสตร์') {
        descTopElement.innerText = 'รายละเอียดเบื้องต้นของวิชาคณิตศาสตร์: เน้นการคิดวิเคราะห์และแก้โจทย์';
        descElement.innerText = 'เจาะลึกตรรกะและการแก้โจทย์ปัญหาอย่างเป็นระบบในระดับสากล';
        durationElement.innerText = '10 ชั่วโมง';
    } 
    else if (courseName === 'ภาษาไทย') {
        descTopElement.innerText = 'รายละเอียดเบื้องต้นของวิชาภาษาไทย: การสื่อสารและการใช้ภาษาในที่ทำงาน';
        descElement.innerText = 'ทักษะการใช้ภาษาไทยเพื่อการทำงานและการสื่อสารอย่างมีประสิทธิภาพ';
        durationElement.innerText = '5 ชั่วโมง';
    }
    else if (courseName === 'วิทยาศาสตร์') {
        descTopElement.innerText = 'รายละเอียดเบื้องต้นของวิชาวิทยาศาสตร์: กระบวนการทางวิทยาศาสตร์และนวัตกรรม';
        descElement.innerText = 'เรียนรู้การทดลองและแนวคิดพื้นฐานทางฟิสิกส์ เคมี และชีววิทยา';
        durationElement.innerText = '12 ชั่วโมง';
    }
    else if (courseName === 'สังคมศึกษา') {
        descTopElement.innerText = 'รายละเอียดเบื้องต้นของวิชาสังคมศึกษา: วัฒนธรรมและความเป็นพลเมือง';
        descElement.innerText = 'เรียนรู้วิธีการเข้าสังคมและทำความเข้าใจความหลากหลายทางวัฒนธรรม';
        durationElement.innerText = '6 ชั่วโมง';
    }
    else if (courseName === 'ภาษาอังกฤษ') {
        descTopElement.innerText = 'รายละเอียดเบื้องต้นของวิชาอังกฤษ: English for Business Communication';
        descElement.innerText = 'Professional English skills for career advancement and daily life.';
        durationElement.innerText = '8 ชั่วโมง';
    }
    else {
        descTopElement.innerText = 'รายละเอียดเบื้องต้นของวิชา...';
        descElement.innerText = 'เริ่มต้นเรียนรู้และพัฒนาทักษะไปกับหลักสูตรคุณภาพจากผู้เชี่ยวชาญ';
        durationElement.innerText = '6 ชั่วโมง';
    }

    // เมื่อตั้งค่าเสร็จแล้วจึงเปิดหน้า Course Detail
    showPage('course-detail');
}

/**
 * ฟังก์ชันสำหรับสลับ Tab ( Overview / Curriculum / Instructor )
 * เพิ่มเติม: ให้กลับมาที่หน้า Overview ทุกครั้งที่กดวิชาใหม่
 */
function openTab(evt, tabName) {
    const tabContents = document.getElementsByClassName("tab-content");
    for (let i = 0; i < tabContents.length; i++) {
        tabContents[i].style.display = "none";
        tabContents[i].classList.remove("active");
    }

    const tabButtons = document.getElementsByClassName("tab-btn");
    for (let i = 0; i < tabButtons.length; i++) {
        tabButtons[i].classList.remove("active");
    }

    document.getElementById(tabName).style.display = "block";
    document.getElementById(tabName).classList.add("active");
    evt.currentTarget.classList.add("active");
}
/**
 * ฟังก์ชันแสดงหน้าคู่มือการเรียน 
 */
function showGuide(type) {
    const title = document.getElementById('guide-title');
    const content = document.getElementById('guide-content');
    
    if (type === 'register') {
        title.innerText = 'ขั้นตอนการสมัครสมาชิก';
        content.innerHTML = `
            <div class="info-card">
                <h4 style="color:var(--primary-orange); margin-bottom:15px;">📝 วิธีเริ่มใช้งาน</h4>
                <p>1. คลิกปุ่ม <b>Login</b> มุมขวาบนของหน้าจอ</p>
                <p>2. กรอกรายละเอียดเพื่อสมัครสมาชิกด้วย Email หรือเลือกใช้งานผ่านระบบ Google</p>
                <p>3. ยืนยันตัวตนแล้วเริ่มเรียนได้ทันที</p>
            </div>`;
    } else if (type === 'search') {
        title.innerText = 'วิธีค้นหาบทเรียน';
        content.innerHTML = `
            <div class="info-card">
                <h4 style="color:var(--primary-orange); margin-bottom:15px;">🔍 การค้นหา</h4>
                <p>ท่านสามารถค้นหาบทเรียนได้โดยพิมพ์ชื่อวิชาในช่อง Search หรือเลือกดูตามหมวดหมู่ที่เมนูด้านบน</p>
            </div>`;
    } else if (type === 'certificate') {
        title.innerText = 'การรับใบประกาศ';
        content.innerHTML = `
            <div class="info-card">
                <h4 style="color:var(--primary-orange); margin-bottom:15px;">🎓 วุฒิบัตรดิจิทัล</h4>
                <p>เมื่อเรียนจบครบ 100% และสอบผ่านเกณฑ์ ระบบจะให้ดาวน์โหลดใบประกาศทันทีในหน้าสรุปผล</p>
            </div>`;
    }
    
    showPage('page-guide');
}

/**
 * ฟังก์ชันแสดงหน้าติดต่อเรา (ปรับปรุงเพื่อให้จัดกึ่งกลางตาม CSS ใหม่)
 */
function showContact(type) {
    const title = document.getElementById('contact-title');
    const content = document.getElementById('contact-content');
    
    if (type === 'channel') {
        title.innerText = 'ช่องทางการติดต่อ';
        content.innerHTML = `
            <div class="info-card">
                <h4 style="color:var(--primary-orange); margin-bottom:15px;">📞 ข้อมูลติดต่อ</h4>
                <p><b>อีเมล:</b> @flexiblehub.kbu</p>
                <p><b>Line:</b> @flexiblehub</p>
            </div>`;
    } else if (type === 'location') {
        title.innerText = 'สถานที่ตั้ง';
        content.innerHTML = `
            <div class="info-card">
                <h4 style="color:var(--primary-orange); margin-bottom:15px;">📍 ที่อยู่สำนักงาน</h4>
                <p>อาคารflexblehub ชั้น 10 กรุงเทพมหานคร</p>
            </div>`;
    }
    
    showPage('page-contact');
}

/**
 * ฟังก์ชันสลับ Tab (Overview, Curriculum, Instructor)
 */
function openTab(evt, tabName) {
    // 1. ซ่อนเนื้อหา Tab ทั้งหมด
    const tabContents = document.getElementsByClassName("tab-content");
    for (let i = 0; i < tabContents.length; i++) {
        tabContents[i].style.display = "none";
        tabContents[i].classList.remove("active");
    }

    // 2. เอาคลาส 'active' ออกจากปุ่มทั้งหมด
    const tabButtons = document.getElementsByClassName("tab-btn");
    for (let i = 0; i < tabButtons.length; i++) {
        tabButtons[i].className = tabButtons[i].className.replace(" active", "");
    }

    // 3. แสดงเนื้อหา Tab ที่เลือก และเพิ่มคลาส 'active' ให้ปุ่มที่กด
    document.getElementById(tabName).style.display = "block";
    document.getElementById(tabName).classList.add("active");
    evt.currentTarget.className += " active";
}