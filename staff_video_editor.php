<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>จัดการวิดีโอรายวิชา | NEXORA</title>
  <link rel="stylesheet" href="staff_subject_editor.css" />
  <link rel="stylesheet" href="theme.css" />
</head>
<body>
  <div class="bg-grid"></div><div class="glow-orb orb-1"></div><div class="glow-orb orb-2"></div>
  <header class="topbar">
    <a class="btn-ghost" id="backLink" href="staffdash.php?page=subject-list">← กลับไปหน้าบทเรียน</a>
    <div class="topbar-copy"><span class="eyebrow">VIDEO LIBRARY</span><h1>จัดการวิดีโอรายวิชา</h1></div>
  </header>
  <main class="page-shell">
    <section class="hero-card">
      <div><p class="hero-label">Subject videos</p><h2 id="subjectTitle">กำลังโหลดรายวิชา...</h2><p class="hero-sub">วิดีโอถูกเก็บแยกจากบทเรียน และสามารถเลือกผูกกับบทเรียนได้</p></div>
      <div class="hero-meta" id="videoMeta"><span class="meta-pill">วิดีโอ 0 รายการ</span></div>
    </section>

    <section class="panel lessons-panel">
      <div class="panel-head lesson-head"><div><p class="panel-label">Videos</p><h3>คลังวิดีโอ</h3></div><button type="button" class="btn-primary" id="addVideoBtn"><span class="btn-text">+ เพิ่มวิดีโอ</span></button></div>
      <div class="table-wrap"><table class="data-table"><thead><tr><th>ชื่อวิดีโอ</th><th>บทเรียน</th><th>ความยาว</th><th>ลำดับ</th><th style="width:140px">จัดการ</th></tr></thead><tbody id="videoTableBody"><tr><td colspan="5" class="empty-cell">กำลังโหลดวิดีโอ...</td></tr></tbody></table></div>
    </section>

    <section class="panel" id="videoEditor">
      <div class="panel-head"><div><p class="panel-label">Video form</p><h3 id="videoFormTitle">เพิ่มวิดีโอใหม่</h3></div></div>
      <form class="form-card lesson-form" id="videoForm" enctype="multipart/form-data">
        <input type="hidden" id="videoId" />
        <div class="form-grid-2">
          <div class="form-field"><label class="form-label" for="videoTitle">ชื่อวิดีโอ <span class="req">*</span></label><div class="input-wrap"><span class="input-icon">▶</span><input id="videoTitle" required /></div></div>
          <div class="form-field"><label class="form-label" for="videoLesson">เชื่อมกับบทเรียน</label><div class="input-wrap select-wrap"><select id="videoLesson"><option value="">วิดีโอรวมของรายวิชา</option></select></div></div>
        </div>
        <div class="form-field"><label class="form-label" for="videoDescription">คำอธิบาย</label><div class="input-wrap"><textarea id="videoDescription" rows="4" placeholder="รายละเอียดวิดีโอ"></textarea></div></div>
        <div class="lesson-resource-grid">
          <div class="resource-card"><div class="resource-head"><div class="resource-icon video">🎬</div><div><h4>ไฟล์วิดีโอ</h4><p>อัปโหลดไฟล์วิดีโอเข้าสู่คลังของรายวิชานี้</p></div></div><div class="form-field compact"><label class="form-label" for="videoFile">เลือกไฟล์</label><div class="input-wrap file-wrap"><span class="input-icon">↥</span><input id="videoFile" type="file" accept="video/*" /></div></div></div>
          <div class="resource-card"><div class="resource-head"><div class="resource-icon video">🔗</div><div><h4>หรือลิงก์วิดีโอ</h4><p>สำหรับ YouTube หรือแหล่งวิดีโอภายนอก</p></div></div><div class="form-field compact"><label class="form-label" for="videoUrl">URL</label><div class="input-wrap"><span class="input-icon">↗</span><input id="videoUrl" type="url" placeholder="https://..." /></div></div></div>
        </div>
        <div class="form-grid-2"><div class="form-field"><label class="form-label" for="videoDuration">ความยาว (วินาที)</label><div class="input-wrap"><input id="videoDuration" type="number" min="0" /></div></div><div class="form-field"><label class="form-label" for="videoOrder">ลำดับแสดงผล</label><div class="input-wrap"><input id="videoOrder" type="number" min="1" value="1" required /></div></div></div>
        <div class="resource-preview" id="videoPreview" hidden><span class="resource-pill">วิดีโอปัจจุบัน</span><a id="videoPreviewLink" target="_blank" rel="noopener noreferrer"></a></div>
        <div class="form-actions"><button type="button" class="btn-ghost" id="clearVideoBtn">ล้างฟอร์ม</button><button class="btn-primary" type="submit"><span class="btn-text">บันทึกวิดีโอ</span></button></div>
      </form>
    </section>
    <section class="panel error-panel" id="errorPanel" hidden><div class="error-body" id="errorMessage"></div></section>
  </main><div class="toast" id="toast"></div><script src="staff_video_editor.js"></script>
</body>
</html>
