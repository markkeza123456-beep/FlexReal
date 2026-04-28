<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flow Learning - Premium Orange</title>
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
        body { background: #fafafa; color: var(--deep-blue); }

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
            display: none; position: absolute; background-color: white; min-width: 200px;
            box-shadow: 0px 10px 30px rgba(0,0,0,0.15); border-radius: 12px; top: 45px; border: 1px solid #eee;
        }
        .dropdown-content a { color: var(--deep-blue); padding: 12px 20px; text-decoration: none; display: block; transition: 0.2s; }
        .dropdown-content a:hover { background: var(--soft-orange); color: var(--primary-orange); }
        .dropdown:hover .dropdown-content { display: block; }

        /* Hero */
        .hero { background: linear-gradient(135deg, #ff6b00 0%, #ff9100 100%); padding: 60px 8%; text-align: center; color: white; }
        .search-box { width: 100%; max-width: 600px; padding: 18px 30px; border-radius: 50px; border: none; margin-top: 20px; outline: none; }

        /* Course Cards */
        .container { padding: 50px 8%; }
        .section-title { font-size: 24px; margin-bottom: 30px; border-left: 6px solid var(--primary-orange); padding-left: 15px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 30px; }
        .card { background: white; border-radius: 20px; overflow: hidden; border: 1px solid #eee; transition: 0.4s; cursor: pointer; }
        .card:hover { transform: translateY(-10px); box-shadow: var(--shadow); }
        .card img { width: 100%; height: 180px; object-fit: cover; }
        .card-content { padding: 20px; }
        .card-tag { background: var(--soft-orange); color: var(--primary-orange); padding: 5px 12px; border-radius: 50px; font-size: 12px; font-weight: 600; display: inline-block; margin-bottom: 10px; }

        /* Detail Pages Header */
        .content-header { background: white; padding: 40px 8%; border-bottom: 1px solid #eee; }
        .info-card { background: white; padding: 25px; border-radius: 15px; border: 1px solid #eee; margin-bottom: 20px; }

        /* Lesson List */
        .lesson-header { background: white; padding: 40px 8%; border-bottom: 1px solid #eee; }
        .back-btn { color: var(--primary-orange); cursor: pointer; font-weight: 600; margin-bottom: 20px; display: inline-block; }
        .lesson-list { list-style: none; margin-top: 20px; }
        .lesson-item { 
            background: white; padding: 20px; border-radius: 15px; margin-bottom: 15px; 
            border: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;
        }
        .start-btn { background: var(--primary-orange); color: white; border: none; padding: 8px 20px; border-radius: 8px; cursor: pointer; }

        /* Steps Section (Home Only) */
        .steps-bg { background: white; padding: 60px 8%; margin-top: 20px; border-top: 1px solid #eee; }
        .steps-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; text-align: center; }
        .step-icon { width: 70px; height: 70px; background: var(--soft-orange); color: var(--primary-orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 24px; font-weight: bold; }

        .footer { background: var(--deep-blue); color: white; padding: 50px 8%; text-align: center; margin-top: 50px; }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600;800&display=swap" rel="stylesheet">
</head>
<body>

<nav class="navbar">
    <a href="#" class="logo" onclick="showPage('home')"> Flexible Learning Hub </a>
    <div class="nav-links">
        <a href="#" onclick="showPage('home')" style="text-decoration:none; color:inherit;">หน้าหลัก</a>
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
                <a href="#" onclick="showPage('page-guide')">ขั้นตอนการสมัคร</a>
                <a href="#" onclick="showPage('page-guide')">ค้นหาบทเรียน</a>
                <a href="#" onclick="showPage('page-guide')">การรับใบประกาศ</a>
            </div> 
        </div>
        <div class="dropdown">
            <button class="dropbtn">ติดต่อเรา ▾</button>
            <div class="dropdown-content">
                <a href="#" onclick="showPage('page-contact')">ช่องทางการติดต่อ</a>
                <a href="#" onclick="showPage('page-contact')">สถานที่ตั้ง</a>
            </div> 
        </div>
        <button style="background:var(--primary-orange); color:white; border:none; padding:8px 20px; border-radius:8px; cursor:pointer;">Login</button>
    </div>
</nav>

<div id="home" class="page active">
    <div class="hero">
        <h1> Learning Hub </h1>
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
                    <p>เรียนรู้วิธีการสื่อสารในที่ทำงานอย่างมืออาชีพ</p>
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
                    <p>เรียนรู้วิธีการเข้าสังคมในที่ทำงานอย่างมืออาชีพ</p>
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
    <div class="lesson-header">
        <div class="back-btn" onclick="showPage('home')">← กลับหน้าหลัก</div>
        <h1 id="detail-title">ชื่อวิชา</h1>
        <p id="detail-desc" style="color:var(--text-gray); margin-top:10px;">รายละเอียดวิชาแบบย่อ...</p>
    </div>

    <div class="container">
        <h3 class="section-title">เนื้อหาบทเรียน</h3>
        <ul class="lesson-list">
            <li class="lesson-item">
                <div>
                    <b>บทที่ 1: พื้นฐานและการเตรียมตัว</b>
                    <p style="font-size:14px; color:var(--text-gray);">วิดีโอความยาว 15 นาที</p>
                </div>
                <button class="start-btn">เริ่มเรียน</button>
            </li>
            <li class="lesson-item">
                <div>
                    <b>บทที่ 2: การประยุกต์ใช้ในชีวิตจริง</b>
                    <p style="font-size:14px; color:var(--text-gray);">วิดีโอความยาว 25 นาที</p>
                </div>
                <button class="start-btn">เริ่มเรียน</button>
            </li>
            <li class="lesson-item">
                <div>
                    <b>แบบทดสอบหลังเรียน (Final Exam)</b>
                    <p style="font-size:14px; color:var(--text-gray);">10 ข้อสอบเพื่อรับใบประกาศ</p>
                </div>
                <button class="start-btn" style="background:var(--deep-blue);">ทำข้อสอบ</button>
            </li>
        </ul>
    </div>
</div>

<div id="page-guide" class="page">
    <div class="content-header">
        <div class="back-btn" onclick="showPage('home')">← กลับหน้าหลัก</div>
        <h1>คู่มือการใช้งานระบบ</h1>
        <p>ขั้นตอนง่ายๆ เพื่อเริ่มต้นการเรียนรู้กับเรา</p>
    </div>
    <div class="container" style="max-width: 800px; margin: 0 auto;">
        <div class="info-card">
            <h4>1. การสมัครสมาชิก</h4>
            <p>คลิกที่ปุ่ม Login มุมขวาบน และเลือกสมัครสมาชิกใหม่โดยใช้อีเมลหรือบัญชี Google</p>
        </div>
        <div class="info-card">
            <h4>2. การค้นหาและลงทะเบียน</h4>
            <p>เลือกหมวดหมู่ที่สนใจ หรือใช้ช่องค้นหาในหน้าหลัก เมื่อเจอวิชาที่ชอบให้กด "ลงทะเบียนเรียน"</p>
        </div>
    </div>
</div>

<div id="page-contact" class="page">
    <div class="content-header">
        <div class="back-btn" onclick="showPage('home')">← กลับหน้าหลัก</div>
        <h1>ติดต่อเรา</h1>
    </div>
    <div class="container" style="max-width: 800px; margin: 0 auto;">
        <div class="info-card">
            <h4>📞 ช่องทางการติดต่อ</h4>
            <p><b>อีเมล:</b> kbu@thaimooc.org</p>
            <p><b>Line ID:</b> @kbu_learning</p>
        </div>
    </div>
</div>

<footer class="footer">
    <p><b> KBU Learning Hub </b></p>
    <p>อีเมล: kbu@thaimooc.org | โทร: 02-XXX-XXXX</p>
    <p style="font-size:12px; margin-top:20px;">© 2026 Flow Learning. All Rights Reserved.</p>
</footer>

<script>
    function showPage(pageId) {
        document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
        const targetPage = document.getElementById(pageId);
        if(targetPage) {
            targetPage.classList.add('active');
        }
        window.scrollTo(0,0);
    }

    function showCourse(courseName) {
        const title = document.getElementById('detail-title');
        const desc = document.getElementById('detail-desc');
        
        title.innerText = courseName;
        desc.innerText = 'เรียนรู้วิธีการพัฒนาทักษะแบบมืออาชีพในรายวิชา ' + courseName;

        showPage('course-detail');
    }
</script>

</body>
</html>
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
            
            <div class="card" onclick="showCourse('สังคมศึกษา')">
                <img src="https://images.unsplash.com/photo-1529107386315-e1a2ed48a620?auto=format&fit=crop&q=80&w=500">
                <div class="card-content">
                    <span class="card-tag">วัฒนธรรม</span>
                    <h3>สังคมศึกษา</h3>
                    <p>เรียนรู้วิธีการเข้าสังคมในที่ทำงานอย่างมืออาชีพ</p>
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
</div>
