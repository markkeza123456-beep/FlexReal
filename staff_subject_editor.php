<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>แก้ไขรายวิชาและบทเรียน | NEXORA</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@600;700;800&family=IBM+Plex+Sans+Thai:wght@300;400;500;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="staff_subject_editor.css" />
</head>
<body>
  <div class="bg-grid"></div>
  <div class="glow-orb orb-1"></div>
  <div class="glow-orb orb-2"></div>

  <header class="topbar">
    <a class="btn-ghost" href="staffdash.php?page=subject-list">← กลับไปหน้ารายวิชา</a>
    <div class="topbar-copy">
      <span class="eyebrow">STAFF SUBJECT EDITOR</span>
      <h1>แก้ไขรายวิชาและบทเรียน</h1>
    </div>
  </header>

  <main class="page-shell">
    <section class="hero-card">
      <div>
        <p class="hero-label">จัดการเนื้อหารายวิชา</p>
        <h2 id="heroTitle">กำลังโหลดข้อมูลรายวิชา...</h2>
        <p class="hero-sub" id="heroSubtitle">หน้านี้ใช้สำหรับแก้ไขข้อมูลวิชาและจัดการบทเรียนภายในวิชาเดียวกัน</p>
      </div>
      <div class="hero-meta" id="heroMeta">
        <span class="meta-pill">รหัสวิชา -</span>
        <span class="meta-pill">ประเภท -</span>
        <span class="meta-pill">บทเรียน 0 รายการ</span>
      </div>
    </section>

    <section class="layout-grid" id="editorContent">
      <div class="panel">
        <div class="panel-head">
          <div>
            <p class="panel-label">Subject</p>
            <h3>ข้อมูลรายวิชา</h3>
          </div>
        </div>
        <form class="form-card" id="subjectForm">
          <input type="hidden" id="subjectId" />
          <div class="form-grid-2">
            <div class="form-field">
              <label class="form-label" for="subjectCode">รหัสวิชา <span class="req">*</span></label>
              <div class="input-wrap">
                <span class="input-icon">#</span>
                <input type="text" id="subjectCode" required />
              </div>
            </div>
            <div class="form-field">
              <label class="form-label" for="subjectCredit">หน่วยกิต <span class="req">*</span></label>
              <div class="input-wrap">
                <span class="input-icon">C</span>
                <input type="number" id="subjectCredit" min="0" required />
              </div>
            </div>
          </div>
          <div class="form-field">
            <label class="form-label" for="subjectName">ชื่อวิชา <span class="req">*</span></label>
            <div class="input-wrap">
              <span class="input-icon">T</span>
              <input type="text" id="subjectName" required />
            </div>
          </div>
          <div class="form-field">
            <label class="form-label" for="subjectType">ประเภทวิชา</label>
            <div class="input-wrap select-wrap">
              <span class="input-icon">≡</span>
              <select id="subjectType">
                <option value="required">บังคับ</option>
                <option value="elective">เลือก</option>
              </select>
              <svg class="select-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="6 9 12 15 18 9"></polyline>
              </svg>
            </div>
          </div>
          <div class="form-actions">
            <a class="btn-ghost" href="staffdash.php?page=subject-list">ยกเลิก</a>
            <button type="submit" class="btn-primary"><span class="btn-text">บันทึกรายวิชา</span></button>
          </div>
        </form>
      </div>

      <aside class="panel tips-panel">
        <div class="panel-head">
          <div>
            <p class="panel-label">Overview</p>
            <h3>สรุปการจัดการ</h3>
          </div>
        </div>
        <div class="tip-list">
          <div class="tip-card">
            <span class="tip-title">รายวิชา</span>
            <strong id="statSubjectName">-</strong>
            <p>แก้ไขชื่อ รหัส หน่วยกิต และประเภทวิชาได้จากฟอร์มด้านซ้าย</p>
          </div>
          <div class="tip-card">
            <span class="tip-title">บทเรียน</span>
            <strong id="statLessonCount">0 รายการ</strong>
            <p>เพิ่ม แก้ไข และลบบทเรียนของวิชานี้ได้จากตารางด้านล่าง</p>
          </div>
          <div class="tip-card">
            <span class="tip-title">การกลับไปหน้าเดิม</span>
            <strong>Staff Dashboard</strong>
            <p>เมื่อกดกลับ ระบบจะพาไปหน้ารายวิชาใน `staffdash` โดยตรง</p>
          </div>
        </div>
      </aside>
    </section>

    <section class="panel lessons-panel" id="lessonSection">
      <div class="panel-head lesson-head">
        <div>
          <p class="panel-label">Lessons</p>
          <h3>รายการบทเรียน</h3>
        </div>
        <button type="button" class="btn-primary" id="addLessonBtn"><span class="btn-text">+ เพิ่มบทเรียน</span></button>
      </div>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th style="width: 96px;">รูปภาพ</th>
              <th>ชื่อบทเรียน</th>
              <th>วิดีโอ</th>
              <th style="width: 140px;">จัดการ</th>
            </tr>
          </thead>
          <tbody id="lessonTableBody">
            <tr>
              <td colspan="4" class="empty-cell">กำลังโหลดบทเรียน...</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="panel" id="lessonEditor">
      <div class="panel-head">
        <div>
          <p class="panel-label">Lesson Form</p>
          <h3 id="lessonFormTitle">เพิ่มบทเรียนใหม่</h3>
        </div>
      </div>
      <form class="form-card lesson-form" id="lessonForm" enctype="multipart/form-data">
        <input type="hidden" id="lessonId" value="" />
        <div class="form-field">
          <label class="form-label" for="lessonTitle">ชื่อบทเรียน <span class="req">*</span></label>
          <div class="input-wrap">
            <span class="input-icon">L</span>
            <input type="text" id="lessonTitle" required />
          </div>
        </div>
        <div class="form-field">
          <label class="form-label" for="lessonContent">รายละเอียดเนื้อหา</label>
          <div class="input-wrap">
            <span class="input-icon">≣</span>
            <textarea id="lessonContent" rows="5" placeholder="สรุปรายละเอียดของบทเรียนนี้"></textarea>
          </div>
        </div>
        <div class="form-grid-2">
          <div class="form-field">
            <label class="form-label" for="lessonImage">รูปภาพหน้าปก</label>
            <div class="input-wrap file-wrap">
              <span class="input-icon">🖼</span>
              <input type="file" id="lessonImage" accept="image/*" />
            </div>
            <div class="image-preview" id="imagePreview" hidden>
              <img id="previewImage" alt="ภาพตัวอย่างบทเรียน" />
              <span id="previewLabel">รูปปัจจุบัน</span>
            </div>
          </div>
          <div class="form-field">
            <label class="form-label" for="lessonVideo">ลิงก์วิดีโอ</label>
            <div class="input-wrap">
              <span class="input-icon">▶</span>
              <input type="url" id="lessonVideo" placeholder="https://..." />
            </div>
          </div>
        </div>
        <div class="form-actions">
          <button type="button" class="btn-ghost" id="cancelLessonBtn">ล้างฟอร์ม</button>
          <button type="submit" class="btn-primary"><span class="btn-text">บันทึกบทเรียน</span></button>
        </div>
      </form>
    </section>

    <section class="panel error-panel" id="errorPanel" hidden>
      <div class="panel-head">
        <div>
          <p class="panel-label">Error</p>
          <h3>ไม่สามารถโหลดข้อมูลได้</h3>
        </div>
      </div>
      <div class="error-body" id="errorMessage">กรุณาตรวจสอบรหัสรายวิชาแล้วลองใหม่อีกครั้ง</div>
    </section>
  </main>

  <div class="toast" id="toast"></div>
  <script src="staff_subject_editor.js"></script>
</body>
</html>
