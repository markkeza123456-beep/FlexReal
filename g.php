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

        /* Navbar */
        .navbar {
            background: white;
            padding: 15px 8%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .logo {
            font-size: 24px;
            font-weight: 800;
            color: var(--primary-orange);
            text-decoration: none;
            letter-spacing: -1px;
        }

        .nav-links { display: flex; gap: 30px; align-items: center; }

        /* Dropdown Style เหมือนรูปเป๊ะ */
        .dropdown { position: relative; display: inline-block; }
        .dropbtn {
            background: none;
            border: none;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            color: var(--deep-blue);
            padding: 10px 0;
        }
        .dropbtn:hover { color: var(--primary-orange); }

        .dropdown-content {
            display: none;
            position: absolute;
            background-color: white;
            min-width: 280px;
            box-shadow: 0px 10px 30px rgba(0,0,0,0.15);
            border-radius: 12px;
            padding: 10px 0;
            top: 45px;
            border: 1px solid #eee;
        }
        .dropdown-content a {
            color: var(--deep-blue);
            padding: 12px 20px;
            text-decoration: none;
            display: block;
            font-size: 15px;
            transition: 0.2s;
        }
        .dropdown-content a:hover {
            background-color: var(--soft-orange);
            color: var(--primary-orange);
            padding-left: 25px;
        }
        .dropdown:hover .dropdown-content { display: block; }

        /* Hero & Search */
        .hero {
            background: linear-gradient(135deg, #ff6b00 0%, #ff9100 100%);
            padding: 60px 8%;
            text-align: center;
            color: white;
        }
        .search-container {
            max-width: 700px;
            margin: 30px auto 0;
            position: relative;
        }
        .search-box {
            width: 100%;
            padding: 18px 30px;
            border-radius: 50px;
            border: none;
            font-size: 16px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            outline: none;
        }
        .search-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: var(--deep-blue);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 40px;
            cursor: pointer;
            transition: 0.3s;
        }
        .search-btn:hover { background: #000; }

        /* Course Section */
        .container { padding: 50px 8%; }
        .section-title {
            font-size: 24px;
            margin-bottom: 30px;
            border-left: 6px solid var(--primary-orange);
            padding-left: 15px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
        }

        .card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            transition: 0.4s;
            border: 1px solid #eee;
        }
        .card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow);
        }
        .card img { width: 100%; height: 180px; object-fit: cover; }
        .card-content { padding: 20px; }
        .card-tag {
            background: var(--soft-orange);
            color: var(--primary-orange);
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 10px;
        }
        .card h3 { font-size: 18px; margin-bottom: 10px; }
        .card p { color: var(--text-gray); font-size: 14px; }

        /* Steps */
        .steps-bg { background: white; padding: 60px 8%; margin-top: 50px; }
        .steps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            text-align: center;
        }
        .step-icon {
            width: 70px; height: 70px;
            background: var(--soft-orange);
            color: var(--primary-orange);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 24px;
            font-weight: bold;
        }

        .footer {
            background: var(--deep-blue);
            color: #bdc3c7;
            padding: 50px 8%;
            text-align: center;
            line-height: 1.8;
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600;800&display=swap" rel="stylesheet">
</head>
<body>

<nav class="navbar">
    <a href="#" class="logo"> Learning</a>
    <div class="nav-links">
         </div>
        <a href="#" style="text-decoration:none; color:inherit;">หน้าหลัก</a>
    </div>

        <div class="dropdown">
            <button class="dropbtn">หมวดหมู่ ▾</button>
            <div class="dropdown-content">
                <a href="#">ภาษาไทย</a>
                <a href="#">คณิตศาสตร์</a>
                <a href="#">วิทยาศาสตร์</a>
                <a href="#">สังคมศึกษา</a>
                <a href="#">ภาษาอังกฤษ</a>
            </div> 
        </div>
        <div class="dropdown">
            <button class="dropbtn">คู่มือการเรียน ▾</button>
            <div class="dropdown-content">
                <a href="#">ขั้นตอนการสมัคร</a>
                <a href="#">ค้นหาบทเรียน</a>
                <a href="#">เข้าเรียน</a>
                <a href="#">ทดสอบการเรียนร</a>
                <a href="#">รับใบประกาส</a>
        </div>
        </div>
        <div class="dropdown">
            <button class="dropbtn">ติดต่อเรา▾</button>
            <div class="dropdown-content">
                <a href="#">เบอร์โทรศัพท์</a>
                <a href="#">อีเมล</a>
                <a href="#">สถานที่ติดต่อ</a>
        </div>

        </div>
        <button style="background:var(--primary-orange); color:white; border:none; padding:8px 20px; border-radius:8px; cursor:pointer;">Login</button>
    </div>
</nav>

<div class="hero">
    <h1> KBU </h1>
    <p style="margin-top:10px; opacity:0.9;">ค้นพบรายวิชาออนไลน์ที่ตอบโจทย์ชีวิตคุณ</p>
    <div class="search-container">
        <input type="text" class="search-box" placeholder="ค้นหาบทเรียนที่คุณสนใจ...">
        <button class="search-btn">ค้นหาเลย</button>
    </div>
</div>

<div class="container">
    <h2 class="section-title">รายวิชาแนะนำ</h2>
    <div class="grid">
        <div class="card">
            <img src="https://images.unsplash.com/photo-1546410531-bb4caa6b424d?auto=format&fit=crop&q=80&w=500" alt="edu">
            <div class="card-content">
                <span class="card-tag">ภาษาและการสื่อสาร</span>
                <h3>ภาษาอังกฤษ</h3>
                <p>เรียนรู้วิธีการสื่อสารในที่ทำงานอย่างมืออาชีพ</p>
            </div>
        </div>
        <div class="card">
            <img src="https://images.unsplash.com/photo-1517694712202-14dd9538aa97?auto=format&fit=crop&q=80&w=500" alt="tech">
            <div class="card-content">
                <span class="card-tag">ภาษาและการสื่อสาร</span>
                <h3>ภาษาไทย</h3>
                <p>เรียนรู้วิธีการสื่อสารในที่ทำงานอย่างมืออาชีพ</p>
            </div>
             </div>
        <div class="card">
            <img src="https://images.unsplash.com/photo-1517694712202-14dd9538aa97?auto=format&fit=crop&q=80&w=500" alt="tech">
            <div class="card-content">
                <span class="card-tag">วัฒนธรรม</span>
                <h3>สังคมศึกษา</h3>
                <p>เรียนรู้วิธีการเข้าสังคมในที่ทำงานอย่างมืออาชีพ</p>
            </div>
             </div>
        <div class="card">
    <img src="math.jpg" alt="Math">
    <div class="card-content">
        <span class="card-tag">คำนวณ</span>
        <h3>คณิตศาสตร์</h3>
        <p>เรียนรู้วิธีการคำนวณในที่ทำงานอย่างมืออาชีพ</p>
    </div>
</div>
        <div class="card">
            <img src="https://images.unsplash.com/photo-1517694712202-14dd9538aa97?auto=format&fit=crop&q=80&w=500" alt="tech">
            <div class="card-content">
                <span class="card-tag">ทดลอง</span>
                <h3>วิทยาศาสตร์</h3>
                <p>เรียนรู้วิธีการคิดวิเคราะห์ในที่ทำงานอย่างมืออาชีพ</p>
            </div>
        </div>
        </div>
    </div>
</div>

<div class="steps-bg">
    <h2 class="section-title" style="text-align:center; border:none;">เรียนรู้ง่ายๆ ใน 5 ขั้นตอน</h2>
    <div class="steps-grid" style="margin-top:40px;">
        <div>
            <div class="step-icon">1</div>
            <h4>สมัครสมาชิก</h4>
            <p style="font-size:14px; color:var(--text-gray);">สร้างบัญชีฟรีเพื่อเข้าถึงบทเรียน</p>
        </div>
        <div>
            <div class="step-icon">2</div>
            <h4>ค้นหารายวิชา</h4>
            <p style="font-size:14px; color:var(--text-gray);">สร้างบัญชีฟรีเพื่อเข้าถึงบทเรียน</p>
        </div>
        <div>
            <div class="step-icon">3</div>
            <h4>เรียนออนไลน์</h4>
            <p style="font-size:14px; color:var(--text-gray);">สร้างบัญชีฟรีเพื่อเข้าถึงบทเรียน</p>
        </div>
        <div>
            <div class="step-icon">4</div>
            <h4>ทำแบบทดสอบ</h4>
            <p style="font-size:14px; color:var(--text-gray);">ค้นหาวิจาที่ตรงกับความสนใจของคุณ</p>
        </div>
        <div>
            <div class="step-icon">5</div>
            <h4>รับใบประกาศ</h4>
            <p style="font-size:14px; color:var(--text-gray);">เรียนจบทำแบบทดสอบและรับวุฒิบัตร</p>
        </div>
    </div>
</div>

<footer class="footer">
    <p><b> KBU </b></p>
    <p>อีเมล: kbu@thaimooc.org | โทร: 02-XXX-XXXX</p>
    <p style="font-size:12px; margin-top:20px;">© 2026 Flow Learning. All Rights Reserved.</p>
</footer>

</body>
</html>