<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flexible Learning Hub</title>
    <style>
        :root {
            --primary-orange: #ff6b00;
            --dark-orange: #e65a00;
            --soft-orange: #fff4ed;
            --deep-blue: #2d3436;
            --text-gray: #636e72;
            --shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Prompt', sans-serif; }
        body { background: #f9ba87; color: var(--deep-blue); }

        /* ระบบสลับหน้า */
        .page { display: none; min-height: 80vh; }
        .page.active { display: block; animation: fadeIn 0.4s ease-in-out; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Navbar */
        .navbar {
            background: white; padding: 15px 8%; display: flex;
            align-items: center; justify-content: space-between;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            position: sticky; top: 0; z-index: 1000;
        }
        .logo { font-size: 24px; font-weight: 800; color: var(--primary-orange); text-decoration: none; cursor: pointer; }
        .nav-links { display: flex; gap: 30px; align-items: center; }

        /* Dropdown */
        .dropdown { position: relative; display: inline-block; }
        .dropbtn { background: none; border: none; font-size: 16px; font-weight: 500; cursor: pointer; color: var(--deep-blue); padding: 10px 0; }
        .dropdown-content {
            display: none; position: absolute; background-color: white; min-width: 220px;
            box-shadow: 0px 10px 30px rgba(0,0,0,0.15); border-radius: 12px; top: 45px; border: 1px solid #0e0808;
        }
        .dropdown-content a { color: var(--deep-blue); padding: 12px 20px; text-decoration: none; display: block; transition: 0.2s; font-size: 14px; }
        .dropdown-content a:hover { background: var(--soft-orange); color: var(--primary-orange); padding-left: 25px; }
        .dropdown:hover .dropdown-content { display: block; }

        /* Hero */
        .hero { background: linear-gradient(135deg, #ff6b00 0%, #ff9100 100%); padding: 60px 8%; text-align: center; color: white; }
        .search-box { width: 100%; max-width: 600px; padding: 18px 30px; border-radius: 50px; border: none; margin-top: 20px; outline: none; box-shadow: var(--shadow); }

        /* Course Cards */
        .container { padding: 50px 8%; }
        .section-title { font-size: 24px; margin-bottom: 30px; border-left: 6px solid var(--primary-orange); padding-left: 15px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 30px; }
        .card { background: white; border-radius: 20px; overflow: hidden; border: 1px solid #eee; transition: 0.4s; cursor: pointer; }
        .card:hover { transform: translateY(-10px); box-shadow: var(--shadow); }
        .card img { width: 100%; height: 180px; object-fit: cover; }
        .card-content { padding: 20px; }
        .card-tag { background: var(--soft-orange); color: var(--primary-orange); padding: 4px 12px; border-radius: 50px; font-size: 12px; font-weight: 600; display: inline-block; margin-bottom: 10px; }

        /* Steps Section (สำหรับหน้า Home เท่านั้น) */
        .steps-bg { background: white; padding: 60px 8%; margin-top: 20px; border-top: 1px solid #ff6b00; }
        .steps-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px; text-align: center; }
        .step-icon { width: 65px; height: 65px; background: var(--soft-orange); color: var(--primary-orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 22px; font-weight: bold; border: 2px solid var(--primary-orange); }

        /* Content Headers & Buttons */
        .content-header { background: white; padding: 40px 8%; border-bottom: 1px solid #ffffff e; text-align: center; }
        .back-btn { color: var(--primary-orange); cursor: pointer; font-weight: 600; margin-bottom: 15px; display: inline-block; border: 1px solid var(--primary-orange); padding: 5px 15px; border-radius: 20px; transition: 0.3s; }
        .back-btn:hover { background: var(--primary-orange); color: white; }
        
        .info-card { background: white; padding: 30px; border-radius: 20px; border: 1px solid #eee; margin-bottom: 20px; }
        .btn-orange { background: var(--primary-orange); color: white; border: none; padding: 10px 25px; border-radius: 8px; cursor: pointer; font-weight: 600; transition: 0.3s; }
        .btn-orange:hover { background: var(--dark-orange); transform: scale(1.05); }

        /* Lesson Layout */
        .lesson-item { 
            background: white; padding: 20px; border-radius: 15px; margin-bottom: 15px; 
            border: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;
        }
        .file-upload-area { margin-top: 10px; padding: 10px; border: 2px dashed #ddd; border-radius: 10px; background: #fdfdfd; }

        .footer { background: var(--deep-blue); color: white; padding: 40px 8%; text-align: center; margin-top: 40px; }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600;800&display=swap" rel="stylesheet">
</head>
<body>

<nav class="navbar">
    <a href="#" class="logo" onclick="showPage('home')">Flexible Learning Hub</a>
    <div class="nav-links">
        <a href="#" onclick="showPage('home')" style="text-decoration:none; color:inherit; font-weight:500;">หน้าหลัก</a>
        
        <div class="dropdown">
            <button class="dropbtn">หมวดหมู่ ▾</button>
            <div class="dropdown-content">
                <a href="#" onclick="showCourse('ภาษาไทย')">ภาษาไทย</a>
                <a href="#" onclick="showCourse('คณิตศาสตร์')">คณิตศาสตร์</a>
                <a href="#" onclick="showCourse('วิทยาศาสตร์')">วิทยาศาสตร์</a>
                <a href="#" onclick="showCourse('สังคมศึกษา')">สังคมศึกษา</a>
                <a href="#" onclick="showCourse('ภาษาอังกฤษ')">ภาษาอังกฤษ</a>
            </div> 
        </div>

        <div class="dropdown">
            <button class="dropbtn">คู่มือการเรียน ▾</button>
            <div class="dropdown-content">
                <a href="#" onclick="showGuide('register')">ขั้นตอนการสมัคร</a>
                <a href="#" onclick="showGuide('search')">ค้นหาบทเรียน</a>
                <a href="#" onclick="showGuide('certificate')">การรับใบประกาศ</a>
            </div> 
        </div>

        <div class="dropdown">
            <button class="dropbtn">ติดต่อเรา ▾</button>
            <div class="dropdown-content">
                <a href="#" onclick="showContact('channel')">ช่องทางการติดต่อ</a>
                <a href="#" onclick="showContact('location')">สถานที่ตั้ง</a>
            </div> 
        </div>

        <button class="btn-orange" style="padding: 8px 20px;">Login</button>
    </div>
</nav>

<div id="home" class="page active">
    <div class="hero">
        <h1>Learning Hub</h1>
        <p style="margin-top:10px; opacity:0.9;">ค้นพบรายวิชาออนไลน์ที่ตอบโจทย์ชีวิตคุณ</p>
        <div class="search-container">
            <input type="text" class="search-box" placeholder="ค้นหาบทเรียนที่คุณสนใจ...">
        </div>
    </div>

    <div class="container">
        <h2 class="section-title">รายวิชาแนะนำ</h2>
        <div class="grid">

            <div class="card" onclick="showCourse('ภาษาอังกฤษ')">
                <img src="https://media.istockphoto.com/id/1264465629/photo/alphabets.webp?a=1&b=1&s=612x612&w=0&k=20&c=C59JmBj2tKU0KZT3a1kMDK8MxC2uqwlgahm8kGfWxDY=">
                <div class="card-content">
                    <span class="card-tag">ภาษาและการสื่อสาร</span>
                    <h3>ภาษาอังกฤษ</h3>
                    <p>เรียนรู้วิธีการสื่อสารอย่างมืออาชีพ</p>
                </div>
            </div>

             <div class="card" onclick="showCourse('ภาษาไทย')">
                <img src="https://plus.unsplash.com/premium_photo-1664910914570-82e5805c21ac?w=600&auto=format&fit=crop&q=60">
                <div class="card-content">
                    <span class="card-tag">ภาษาและการสื่อสาร</span>
                    <h3>ภาษาไทย</h3>
                    <p>ทักษะการใช้ภาษาไทยเพื่อการทำงานที่มีประสิทธิภาพ</p>
                </div>
            </div>

             <div class="card" onclick="showCourse('สังคมศึกษา')">
                <img src="https://images.unsplash.com/photo-1529107386315-e1a2ed48a620?auto=format&fit=crop&q=80&w=500">
                <div class="card-content">
                    <span class="card-tag">วัฒนธรรม</span>
                    <h3>สังคมศึกษา</h3>
                    <p>เรียนรู้วิธีการเข้าสังคมอย่างมืออาชีพ</p>
                </div>
            </div>

            <div class="card" onclick="showCourse('คณิตศาสตร์')">
                <img src="https://images.unsplash.com/photo-1636466497217-26a8cbeaf0aa?w=600&auto=format&fit=crop&q=60">
                <div class="card-content">
                    <span class="card-tag">คำนวณ</span>
                    <h3>คณิตศาสตร์</h3>
                    <p>เพิ่มทักษะการคิดวิเคราะห์เชิงตัวเลข</p>
                </div>
            </div>

            <div class="card" onclick="showCourse('วิทยาศาสตร์')">
                <img src="https://images.unsplash.com/photo-1532094349884-543bc11b234d?auto=format&fit=crop&q=80&w=500">
                <div class="card-content">
                    <span class="card-tag">ทดลอง</span>
                    <h3>วิทยาศาสตร์</h3>
                    <p>เจาะลึกกระบวนการคิดทางวิทยาศาสตร์</p>
                </div>
            </div>
        </div>
    </div>

    

    <div class="steps-bg">
        <h2 class="section-title" style="text-align:center; border:none;">เรียนรู้ง่ายๆ ใน 5 ขั้นตอน</h2>
        <div class="steps-grid" style="margin-top:40px;">
            <div><div class="step-icon">1</div><h4>สมัครสมาชิก</h4></div>
            <div><div class="step-icon">2</div><h4>ค้นหารายวิชา</h4></div>
            <div><div class="step-icon">3</div><h4>เรียนออนไลน์</h4></div>
            <div><div class="step-icon">4</div><h4>ทำแบบทดสอบ</h4></div>
            <div><div class="step-icon">5</div><h4>รับใบประกาศ</h4></div>
        </div>
    </div>
</div>

<div id="course-detail" class="page">
    <div class="content-header">
        <h1 id="detail-title">ชื่อวิชา</h1>
        <p id="detail-desc" style="color:var(--text-gray); margin-top:10px;"></p>
    </div>
    <div class="container" style="max-width: 900px; margin: 0 auto;">
        <div class="info-card">
            <h4>📖 แผนการเรียน</h4>
            <div class="lesson-item">
                <div>
                    <b>บทที่ 1: การปูพื้นฐานความรู้</b>
                    <p style="font-size:14px; color:var(--text-gray);">ไฟล์เอกสารประกอบการเรียน .pdf</p>
                    <div class="file-upload-area">
                        <label style="font-size: 13px;">ส่งงาน/เพิ่มไฟล์เสริม:</label>
                        <input type="file" style="font-size: 12px; margin-top: 5px;">
                    </div>
                </div>
                <button class="btn-orange">ดาวน์โหลดเอกสาร</button>
            </div>
            <div class="lesson-item">
                <div>
                    <b>บทที่ 2: วิดีโอสรุปบทเรียน</b>
                    <p style="font-size:14px; color:var(--text-gray);">ความยาว 20 นาที</p>
                </div>
                <button class="btn-orange">ชมวิดีโอ</button>
            </div>
        </div>
    </div>
</div>

<div id="page-guide" class="page">
    <div class="content-header">
        <h1 id="guide-title">คู่มือการเรียน</h1>
    </div>
    <div class="container" id="guide-content" style="max-width: 800px; margin: 0 auto;"></div>
</div>

<div id="page-contact" class="page">
    <div class="content-header">
        <h1 id="contact-title">ติดต่อเรา</h1>
    </div>
    <div class="container" id="contact-content" style="max-width: 800px; margin: 0 auto;"></div>
</div>

<footer class="footer">
    <p><b>Flexible Learning Hub</b></p>
    <p>© 2026 Flow Learning. All Rights Reserved.</p>
</footer>

<script>
    function showPage(pageId) {
        document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
        document.getElementById(pageId).classList.add('active');
        window.scrollTo(0,0);
    }

    function showCourse(courseName) {
        document.getElementById('detail-title').innerText = courseName;
        document.getElementById('detail-desc').innerText = 'ยินดีต้อนรับเข้าสู่บทเรียน ' + courseName + ' เริ่มต้นพัฒนาทักษะได้เลย';
        showPage('course-detail');
    }

    function showGuide(type) {
        const title = document.getElementById('guide-title');
        const content = document.getElementById('guide-content');
        showPage('page-guide');

        if(type === 'register') {
            title.innerText = 'ขั้นตอนการสมัครสมาชิก';
            content.innerHTML = '<div class="info-card"><h4>📝 วิธีเริ่มใช้งาน</h4><p>1. คลิกปุ่ม Login มุมขวาบน<br>2. สมัครด้วย Email หรือ Google<br>3. ยืนยันตัวตนแล้วเริ่มเรียนได้ทันที</p></div>';
        } else if(type === 'search') {
            title.innerText = 'วิธีค้นหาบทเรียน';
            content.innerHTML = '<div class="info-card"><h4>🔍 การค้นหา</h4><p>พิมพ์ชื่อวิชาในช่อง Search หรือเลือกดูตามหมวดหมู่ที่เมนูด้านบน</p></div>';
        } else if(type === 'certificate') {
            title.innerText = 'การรับใบประกาศ';
            content.innerHTML = '<div class="info-card"><h4>🎓 วุฒิบัตรดิจิทัล</h4><p>เมื่อเรียนจบครบ 100% และสอบผ่านเกณฑ์ ระบบจะให้ดาวน์โหลดใบประกาศทันที</p></div>';
        }
    }

    function showContact(type) {
        const title = document.getElementById('contact-title');
        const content = document.getElementById('contact-content');
        showPage('page-contact');

        if(type === 'channel') {
            title.innerText = 'ช่องทางการติดต่อ';
            content.innerHTML = '<div class="info-card"><h4>📞 ข้อมูลติดต่อ</h4><p><b>อีเมล:</b> contact@flexiblehub.org</p><p><b>Line:</b> @flexiblehub</p></div>';
        } else if(type === 'location') {
            title.innerText = 'สถานที่ตั้ง';
            content.innerHTML = '<div class="info-card"><h4>📍 ที่อยู่สำนักงาน</h4><p>อาคารเรียนรู้ ชั้น 10 กรุงเทพมหานคร</p></div>';
        }
    }
</script>

</body>
</html>