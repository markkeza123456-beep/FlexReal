<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['user_id']) || strtolower(trim((string) ($_SESSION['role'] ?? ''))) !== 'staff') {
    header('Location: login.php');
    exit;
}
$contentManagerMode = (($_GET['view'] ?? '') === 'content');
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>แก้ไขรายวิชาและบทเรียน | Flexible Learning Hub</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@600;700;800&family=IBM+Plex+Sans+Thai:wght@300;400;500;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/staff_subject_editor.css?v=20261021-login-shared-background" />
  <?php if ($contentManagerMode): ?><link rel="stylesheet" href="assets/css/staff_content_manager.css?v=20261022-content-manager-v13" /><?php endif; ?>
    <link rel="stylesheet" href="assets/css/theme.css?v=20261022-mobile-layout-v2">
    <link rel="stylesheet" href="assets/css/responsive.css?v=20261022-sidebar-menu-fix">
</head>
<body class="<?= $contentManagerMode ? 'content-manager-mode' : '' ?>">
  <div class="bg-grid"></div>
  <div class="glow-orb orb-1"></div>
  <div class="glow-orb orb-2"></div>

  <?php if ($contentManagerMode): ?>
  <main class="staff-content-manager" id="staffContentManager" data-subject-id="<?= htmlspecialchars((string) ($_GET['subject_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
    <header class="content-manager-title"><h1>จัดการเนื้อหาวิชา</h1><a class="btn-ghost" href="staffdash.php?page=subject-list">ปิด</a></header>
    <div class="content-manager-grid">
      <section class="content-manager-card">
        <div class="content-manager-card-heading"><div><p>LESSON CONTENT</p><h2 id="cmLessonHeading">เพิ่มบทเรียน</h2></div><span id="cmLessonCount">กำลังโหลด...</span></div>
        <form id="cmLessonForm" enctype="multipart/form-data">
          <input type="hidden" id="cmLessonId">
          <label for="cmLessonName">ชื่อบทเรียน *</label><input id="cmLessonName" required placeholder="เช่น 1.1 ตรรกศาสตร์เบื้องต้น">
          <label for="cmLessonDocument">เอกสารประกอบ (PDF)</label><input id="cmLessonDocument" type="file" accept="application/pdf,.pdf">
          <div id="cmLessonDocumentCurrent" class="content-manager-current" hidden></div>
          <div class="content-manager-actions"><button class="content-manager-save" type="submit">บันทึกบทเรียน</button><button class="content-manager-reset" type="button" id="cmClearLessonBtn">ล้างฟอร์ม</button></div>
          <p id="cmLessonStatus" class="content-manager-status" aria-live="polite"></p>
        </form>
        <details class="content-manager-existing"><summary>รายการบทเรียนเดิม</summary><div class="content-manager-list" id="cmLessonList"></div></details>
      </section>
      <section class="content-manager-card">
        <div class="content-manager-card-heading"><div><p>VIDEO CONTENT</p><h2 id="cmVideoHeading">เพิ่มและจัดการวิดีโอ</h2></div></div>
        <form id="cmVideoForm" enctype="multipart/form-data">
          <input id="cmVideoId" type="hidden" value="">
          <select id="cmVideoLesson" required aria-label="บทเรียนที่จะผูกวิดีโอ" hidden><option value="">เลือกบทเรียน</option></select>
          <label for="cmVideoTitle">ชื่อวิดีโอ *</label><input id="cmVideoTitle" type="text" value="วิดีโอบทเรียน" required>
          <label for="cmVideoFile">ไฟล์วิดีโอ <span id="cmVideoFileOptional">*</span></label><input id="cmVideoFile" type="file" accept="video/*">
          <input id="cmVideoUrl" type="hidden" value="">
          <div class="content-manager-actions"><button class="content-manager-save" type="submit">บันทึกวิดีโอ</button><button class="content-manager-reset" type="button" id="cmClearVideoBtn">ล้างฟอร์ม</button></div>
          <p id="cmVideoStatus" class="content-manager-status" aria-live="polite"></p>
        </form>
        <details class="content-manager-existing"><summary>รายการวิดีโอเดิม</summary><div class="content-manager-list" id="cmVideoList"></div></details>
      </section>
    </div>
    <div class="content-manager-toast" id="cmToast" role="status" aria-live="polite"></div>
  </main>
  <div class="resource-viewer" id="cmResourceViewer" aria-hidden="true">
    <div class="resource-viewer-dialog" role="dialog" aria-modal="true" aria-labelledby="cmViewerTitle">
      <header class="resource-viewer-header"><h2 id="cmViewerTitle">ดูสื่อการเรียน</h2><button type="button" id="cmViewerClose" aria-label="ปิดหน้าต่าง">ปิด</button></header>
      <div class="resource-viewer-body" id="cmViewerBody"></div>
    </div>
  </div>
  <?php endif; ?>

  <header class="topbar">
    <a class="btn-ghost btn-back" href="staffdash.php?page=subject-list">← กลับไปหน้ารายวิชา</a>
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
        <span class="meta-pill">บทเรียน 0/3 รายการ</span>
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

    </section>

    <section class="panel lessons-panel" id="lessonSection">
      <div class="panel-head lesson-head">
        <div>
          <p class="panel-label">Lessons</p>
          <h3>รายการบทเรียน</h3>
        </div>
        <div class="form-actions">
          <a class="btn-ghost" id="manageVideosLink" href="#">จัดการวิดีโอ</a>
          <button type="button" class="btn-primary" id="addLessonBtn"><span class="btn-text">+ เพิ่มบทเรียน</span></button>
        </div>
      </div>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>ชื่อบทเรียน</th>
              <th>เอกสาร</th>
              <th style="width: 140px;">จัดการ</th>
            </tr>
          </thead>
          <tbody id="lessonTableBody">
            <tr>
              <td colspan="3" class="empty-cell">กำลังโหลดบทเรียน...</td>
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
        <div class="lesson-resource-grid">
          <div class="resource-card">
            <div class="resource-head">
              <div class="resource-icon document"></div>
              <div>
                <h4>เอกสารประกอบการเรียน</h4>
                <p>อัปโหลดไฟล์ PDF สำหรับผู้เรียน</p>
              </div>
            </div>
            <div class="form-field compact">
              <label class="form-label" for="lessonDocument">ไฟล์เอกสาร</label>
              <div class="input-wrap file-wrap">
                <span class="input-icon">↥</span>
                <input type="file" id="lessonDocument" accept="application/pdf,.pdf" />
              </div>
            </div>
            <div class="resource-preview" id="documentPreview" hidden>
              <span class="resource-pill">ไฟล์ปัจจุบัน</span>
              <a id="documentPreviewLink" href="#" target="_blank" rel="noopener noreferrer"></a>
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
  <script src="assets/js/staff_subject_editor.js?v=20261022-unlimited-lessons"></script>
<?php if ($contentManagerMode): ?><script src="assets/js/staff_content_manager.js?v=20261022-staff-content-manager-v13"></script><?php endif; ?>
<script src="assets/js/responsive.js?v=20261022-menu-auto-close" defer></script>
</body>
</html>
