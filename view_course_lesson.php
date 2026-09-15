<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/learning_progress_lib.php';

function lessonViewerError(string $message, int $status = 400): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><p style="font-family:system-ui;padding:2rem">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
    exit;
}

if (($_SESSION['role'] ?? '') !== 'student' || empty($_SESSION['user_id'])) {
    lessonViewerError('กรุณาเข้าสู่ระบบนักเรียนก่อนอ่านเอกสาร', 401);
}

$courseId = trim((string) ($_GET['subject_id'] ?? $_GET['course_id'] ?? ''));
$position = max(1, (int) ($_GET['lesson'] ?? 1));
if ($courseId === '') lessonViewerError('ไม่พบรหัสรายวิชา');

try {
    $enrollment = $conn->prepare("SELECT 1 FROM public.student_courses WHERE student_id = :student_id AND course_id = :course_id AND status = 'active'");
    $enrollment->execute([':student_id' => (string) $_SESSION['user_id'], ':course_id' => $courseId]);
    if (!$enrollment->fetchColumn()) lessonViewerError('กรุณาลงรายวิชาก่อนอ่านเอกสาร', 403);

    $resource = $conn->prepare("SELECT l.title AS lesson_title, r.title, r.url FROM public.lessons l JOIN public.lesson_resources r ON r.lesson_id = l.lesson_id AND r.resource_type = 'document' WHERE l.course_id = :course_id AND l.position = :position ORDER BY r.position LIMIT 1");
    $resource->execute([':course_id' => $courseId, ':position' => $position]);
    $document = $resource->fetch(PDO::FETCH_ASSOC);
    if (!$document) lessonViewerError('บทเรียนนี้ยังไม่มีเอกสาร');
} catch (PDOException) {
    lessonViewerError('ไม่สามารถเปิดเอกสารได้ในขณะนี้', 500);
}

$relativePath = ltrim(str_replace('\\', '/', (string) $document['url']), '/');
$root = realpath(__DIR__);
$file = realpath(__DIR__ . DIRECTORY_SEPARATOR . $relativePath);
if (!$root || !$file || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file)) {
    lessonViewerError('ไม่พบไฟล์เอกสาร');
}

$title = (string) ($document['title'] ?: $document['lesson_title']);
$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$resumePage = 0;
try {
    ensureLearningProgressTables($conn);
    $resume = $conn->prepare('SELECT COALESCE(lp.document_page_index, 0) FROM public.lesson_progress lp JOIN public.lessons l ON l.lesson_id = lp.lesson_id WHERE lp.student_id = :student_id AND l.course_id = :course_id AND l.position = :position');
    $resume->execute([':student_id' => (string) $_SESSION['user_id'], ':course_id' => $courseId, ':position' => $position]);
    $resumePage = max(0, (int) ($resume->fetchColumn() ?: 0));
} catch (PDOException) { /* The viewer can still render if progress setup is unavailable. */ }
$documentProgressPayload = json_encode(['type' => 'lesson-document-progress', 'courseId' => $courseId, 'lessonIndex' => $position, 'resumePage' => $resumePage], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
  <style>
    body { margin: 0; color: #24303b; font-family: "IBM Plex Sans Thai", system-ui, sans-serif; background: #f7f8fa; }
    header { display:flex; gap:12px; align-items:center; justify-content:space-between; padding: 12px 20px; background: #fff; border-bottom: 1px solid #e8eaed; font-weight: 700; }
    main { max-width: 900px; min-height: calc(100vh - 59px); margin: auto; padding: 24px; box-sizing: border-box; background: #fff; }
    .docx p { margin: 0 0 1em; line-height: 1.85; white-space: pre-wrap; }
    .docx-page { display:none; } .docx-page.active { display:block; }
    .page-nav { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-top:28px; padding-top:16px; border-top:1px solid #e8eaed; }
    button { border:0; border-radius:8px; padding:9px 14px; font:inherit; font-weight:700; cursor:pointer; background:#e67e22; color:#fff; } button:disabled { opacity:.45; cursor:not-allowed; }
    iframe { display: block; width: 100%; height: calc(100vh - 59px); border: 0; }
    .notice { padding: 18px; border-radius: 10px; background: #fff7ed; color: #9a3412; }
  </style>
</head>
<body>
  <header><span><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></span><button id="complete-reading" type="button" onclick="completeReading()" <?= $extension === 'docx' ? 'disabled' : '' ?>>อ่านจบแล้ว</button></header>
<?php if ($extension === 'pdf'): ?>
  <iframe title="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>" src="<?= htmlspecialchars($relativePath, ENT_QUOTES, 'UTF-8') ?>#view=FitH"></iframe>
<?php elseif ($extension === 'docx'): ?>
  <main class="docx">
<?php
    $zip = new ZipArchive();
    if ($zip->open($file) !== true || ($xml = $zip->getFromName('word/document.xml')) === false) {
        echo '<div class="notice">ไม่สามารถแสดงเนื้อหาไฟล์ Word นี้ได้</div>';
    } else {
        $dom = new DOMDocument();
        $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($dom);
        $paragraphs = $xpath->query('//*[local-name()="p"]');
        $paragraphTexts = [];
        foreach ($paragraphs as $paragraph) {
            $parts = $xpath->query('.//*[local-name()="t"]', $paragraph);
            $text = '';
            foreach ($parts as $part) $text .= $part->textContent;
            if (trim($text) !== '') $paragraphTexts[] = $text;
        }
        if ($paragraphTexts === []) {
            echo '<div class="notice">เอกสารนี้ไม่มีข้อความที่สามารถแสดงในเว็บได้</div>';
        } else {
            $pages = array_chunk($paragraphTexts, 8);
            foreach ($pages as $pageIndex => $page) {
                echo '<section class="docx-page' . ($pageIndex === 0 ? ' active' : '') . '" data-page="' . ($pageIndex + 1) . '">';
                foreach ($page as $text) echo '<p>' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</p>';
                echo '</section>';
            }
            echo '<div class="page-nav"><button type="button" id="previous-page" onclick="changePage(-1)" disabled>← ก่อนหน้า</button><span id="page-indicator"></span><button type="button" id="next-page" onclick="changePage(1)">ถัดไป →</button></div>';
        }
        $zip->close();
    }
?>
  </main>
<?php else: ?>
  <main><div class="notice">รองรับการอ่านในเว็บสำหรับไฟล์ PDF และ Word (.docx) เท่านั้น</div></main>
<?php endif; ?>
</body>
<script>
  const documentPayload = <?= $documentProgressPayload ?>;
  let page = 0;
  const pages = Array.from(document.querySelectorAll('.docx-page'));
  async function saveReadingProgress(percent, resumePage) {
    const form = new FormData();
    form.append('action', 'record'); form.append('subject_id', documentPayload.courseId);
    form.append('lesson_index', String(documentPayload.lessonIndex)); form.append('activity_type', 'document_progress');
    form.append('progress_percent', String(Math.max(0, Math.min(100, percent)))); form.append('resume_position', String(resumePage));
    try { await fetch('student_learning_api.php', { method: 'POST', body: form, credentials: 'same-origin' }); } catch (_) {}
    window.parent.postMessage({ ...documentPayload, progressPercent: percent }, window.location.origin);
  }
  function completeReading() {
    saveReadingProgress(100, pages.length || 1);
    const button = document.getElementById('complete-reading');
    if (button) { button.textContent = 'บันทึกแล้ว ✓'; button.disabled = true; }
  }
  function renderPage() {
    pages.forEach((node, index) => node.classList.toggle('active', index === page));
    const previous = document.getElementById('previous-page'); const next = document.getElementById('next-page'); const indicator = document.getElementById('page-indicator');
    if (!pages.length) return;
    previous.disabled = page === 0;
    next.disabled = page === pages.length - 1;
    next.textContent = page === pages.length - 1 ? 'หน้าสุดท้าย' : 'ถัดไป →';
    indicator.textContent = `หน้า ${page + 1} / ${pages.length}`;
    if (page === pages.length - 1) document.getElementById('complete-reading').disabled = false;
    saveReadingProgress(((page + 1) / pages.length) * 100, page + 1);
  }
  function changePage(step) { page = Math.max(0, Math.min(pages.length - 1, page + step)); renderPage(); }
  if (pages.length && documentPayload.resumePage > 0) page = Math.min(pages.length - 1, documentPayload.resumePage - 1);
  if (!pages.length) saveReadingProgress(1, 0);
  renderPage();
</script>
</html>
