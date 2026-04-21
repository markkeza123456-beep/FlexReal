<!DOCTYPE html>gfgfggfgfgfgfgfgfg
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Flow Learning</title>

<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:sans-serif;}
body{background:#fff7f0;}

/* Navbar */
.navbar{
    background:#ff6b00;
    color:white;
    padding:15px 25px;
}
.topbar{
    display:flex;
    justify-content:space-between;
}
.menu{
    display:flex;
    gap:20px;
    margin-top:10px;
}
.menu div{cursor:pointer;}

/* Search */
.search-box{
    margin-top:15px;
    background:#f1f1f1;
    border-radius:50px;
    display:flex;
    padding:10px 20px;
}
.search-box input{
    flex:1;
    border:none;
    background:transparent;
    outline:none;
}
.search-btn{
    background:#5a4b7c;
    color:white;
    border-radius:50%;
    width:40px;height:40px;
    display:flex;
    align-items:center;
    justify-content:center;
}

/* Section */
.section{padding:30px;}
.section h2{color:#ff6b00;margin-bottom:15px;}

/* Cards */
.cards{
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(220px,1fr));
    gap:20px;
}
.card{
    background:white;
    padding:15px;
    border-radius:15px;
    box-shadow:0 5px 10px rgba(0,0,0,0.1);
    cursor:pointer;
    transition:0.3s;
}
.card:hover{transform:translateY(-5px);}

.card img{
    width:100%;
    height:150px;
    object-fit:cover;
    border-radius:10px;
    margin-bottom:10px;
}

/* Steps */
.steps{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
    gap:20px;
}
.step{
    background:white;
    padding:20px;
    border-radius:15px;
    text-align:center;
}
.circle{
    width:60px;height:60px;
    background:#ff6b00;
    border-radius:50%;
    color:white;
    display:flex;
    align-items:center;
    justify-content:center;
    margin:auto;
}

/* Page ซ่อน */
.page{display:none;}
.active{display:block;}

/* Back */
.back{
    margin:20px;
    cursor:pointer;
    color:#ff6b00;
}

/* Footer */
.footer{
    background:#333;
    color:white;
    text-align:center;
    padding:20px;
}
</style>
</head>

<body>

<!-- Navbar -->
<div class="navbar">
    <div class="topbar">
        <h2>Flow Learning</h2>
        <div>Login</div>
    </div>

    <div class="menu">
        <div onclick="showPage('home')">หมวดหมู่</div>
        <div>คู่มือการเรียน</div>
    </div>

    <div class="search-box">
        <input type="text" placeholder="ค้นหารายวิชาที่น่าสนใจ...">
        <div class="search-btn">🔍</div>
    </div>
</div>

<!-- ================= HOME ================= -->
<div id="home" class="page active">

<div class="section">
<h2>หมวดหมู่</h2>

<div class="cards">

<div class="card" onclick="showPage('thai')">
<img src="thai.jpg">
<h4>ภาษาไทย</h4>
<p>⏱ 5 ชั่วโมง</p>
</div>

<div class="card" onclick="showPage('math')">
<img src="math.jpg">
<h4>คณิตศาสตร์</h4>
<p>⏱ 6 ชั่วโมง</p>
</div>

<div class="card" onclick="showPage('science')">
<img src="science.jpg">
<h4>วิทยาศาสตร์</h4>
<p>⏱ 4 ชั่วโมง</p>
</div>

<div class="card" onclick="showPage('social')">
<img src="https://picsum.photos/300/150?4">
<h4>สังคม</h4>
<p>⏱ 3 ชั่วโมง</p>
</div>

<div class="card" onclick="showPage('english')">
<img src="https://picsum.photos/300/150?5">
<h4>อังกฤษ</h4>
<p>⏱ 5 ชั่วโมง</p>
</div>

</div>
</div>

<!-- Steps -->
<div class="section">
<h2>5 ขั้นตอนการเรียนออนไลน์</h2>
<div class="steps">
<div class="step"><div class="circle">1</div>สมัครสมาชิก</div>
<div class="step"><div class="circle">2</div>ค้นหา</div>
<div class="step"><div class="circle">3</div>เรียน</div>
<div class="step"><div class="circle">4</div>ทำแบบทดสอบ</div>
<div class="step"><div class="circle">5</div>รับใบประกาศ</div>
</div>
</div>

</div>

<!-- ================= DETAIL PAGES ================= -->

<div id="thai" class="page">
<div class="back" onclick="showPage('home')">⬅ กลับ</div>
<h2 style="padding:20px;">คอร์สภาษาไทย</h2>
</div>

<div id="math" class="page">
<div class="back" onclick="showPage('home')">⬅ กลับ</div>
<h2 style="padding:20px;">คอร์สคณิตศาสตร์</h2>
</div>

<div id="science" class="page">
<div class="back" onclick="showPage('home')">⬅ กลับ</div>
<h2 style="padding:20px;">คอร์สวิทยาศาสตร์</h2>
</div>

<div id="social" class="page">
<div class="back" onclick="showPage('home')">⬅ กลับ</div>
<h2 style="padding:20px;">คอร์สสังคม</h2>
</div>

<div id="english" class="page">
<div class="back" onclick="showPage('home')">⬅ กลับ</div>
<h2 style="padding:20px;">คอร์สภาษาอังกฤษ</h2>
</div>

<!-- Footer -->
<div class="footer">
ติดต่อเรา | Email: thaimooc@thaicyberu.go.th | โทร: 02-0395671
</div>

<script>
function showPage(pageId){
    let pages = document.querySelectorAll(".page");
    pages.forEach(p => p.classList.remove("active"));
    document.getElementById(pageId).classList.add("active");
}
</script>

</body>
</html>