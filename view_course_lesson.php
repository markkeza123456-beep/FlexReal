<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/learning_progress_lib.php';

function lessonViewerError(string $message, int $status = 400): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>เปิดบทเรียนไม่ได้</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#9FA8DA;color:#303F9F;font-family:system-ui,sans-serif}.message{max-width:560px;margin:24px;padding:24px 28px;background:#fff;border:1px solid #9FA8DA;border-top:5px solid #5C6BC0;border-radius:16px;box-shadow:0 12px 32px rgba(48,63,159,.08);font-size:1.05rem;line-height:1.7}</style></head><body><main class="message">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</main></body></html>';
    exit;
}


function officePreviewPdf(string $sourceFile): ?string
{
    $configured = trim((string) getenv('SOFFICE_BIN'));
    $candidates = array_filter([
        $configured,
        '/Applications/LibreOffice.app/Contents/MacOS/soffice',
        '/opt/homebrew/bin/soffice',
        '/usr/local/bin/soffice',
        '/usr/bin/soffice',
        '/usr/bin/libreoffice',
    ]);
    $binary = null;
    foreach ($candidates as $candidate) {
        if (is_file($candidate) && is_executable($candidate)) { $binary = $candidate; break; }
    }
    if ($binary === null) return null;

    $cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'flexreal-document-previews';
    if (!is_dir($cacheDir) && !mkdir($cacheDir, 0700, true) && !is_dir($cacheDir)) return null;
    $cacheKey = hash('sha256', $sourceFile . '|' . (string) filemtime($sourceFile) . '|' . (string) filesize($sourceFile));
    $target = $cacheDir . DIRECTORY_SEPARATOR . $cacheKey . '.pdf';
    if (is_file($target) && filesize($target) > 0) return $target;

    $lock = fopen($target . '.lock', 'c');
    if ($lock === false) return null;
    try {
        if (!flock($lock, LOCK_EX)) return null;
        if (is_file($target) && filesize($target) > 0) return $target;
        $outputDir = $cacheDir . DIRECTORY_SEPARATOR . $cacheKey . '-' . bin2hex(random_bytes(6));
        if (!mkdir($outputDir, 0700, true)) return null;
        try {
            exec(escapeshellarg($binary) . ' --headless --convert-to pdf --outdir ' . escapeshellarg($outputDir) . ' ' . escapeshellarg($sourceFile) . ' 2>&1', $output, $exitCode);
            $generated = glob($outputDir . DIRECTORY_SEPARATOR . '*.pdf') ?: [];
            if ($exitCode !== 0 || $generated === [] || !is_file($generated[0])) return null;
            if (!rename($generated[0], $target)) return null;
            return $target;
        } finally {
            foreach (glob($outputDir . DIRECTORY_SEPARATOR . '*') ?: [] as $temporaryFile) @unlink($temporaryFile);
            @rmdir($outputDir);
        }
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
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

    $resource = $conn->prepare("SELECT l.title AS lesson_title, r.title, r.url FROM public.lessons l JOIN public.lesson_resources r ON r.lesson_id = l.lesson_id AND r.resource_type = 'document' WHERE l.course_id = :course_id AND l.position = :position ORDER BY r.position DESC, r.resource_id DESC LIMIT 1");
    $resource->execute([':course_id' => $courseId, ':position' => $position]);
    $document = $resource->fetch(PDO::FETCH_ASSOC);
    if (!$document) lessonViewerError('บทเรียนนี้ยังไม่มีเอกสาร');
} catch (PDOException) {
    lessonViewerError('ไม่สามารถเปิดเอกสารได้ในขณะนี้', 500);
}

$storedUrl = trim((string) $document['url']);
$isRemote = filter_var($storedUrl, FILTER_VALIDATE_URL) !== false && in_array(strtolower((string) parse_url($storedUrl, PHP_URL_SCHEME)), ['http', 'https'], true);
$relativePath = $isRemote ? $storedUrl : ltrim(str_replace('\\', '/', $storedUrl), '/');
$file = null;
if (!$isRemote) {
    $root = realpath(__DIR__);
    $candidate = realpath(__DIR__ . DIRECTORY_SEPARATOR . $relativePath);
    if (!$root || !$candidate || !str_starts_with($candidate, $root . DIRECTORY_SEPARATOR) || !is_file($candidate)) lessonViewerError('ไม่พบไฟล์เอกสาร');
    $file = $candidate;
}

$title = (string) ($document['title'] ?: $document['lesson_title']);
$extension = strtolower(pathinfo((string) parse_url($relativePath, PHP_URL_PATH), PATHINFO_EXTENSION));
$officeExtensions = ['doc', 'docx', 'odt', 'rtf', 'ppt', 'pptx', 'xls', 'xlsx'];
$imageExtensions = ['avif', 'gif', 'jpeg', 'jpg', 'png', 'webp'];



if (($_GET['preview'] ?? '') === 'pdf') {
    if (!in_array($extension, $officeExtensions, true)) lessonViewerError('ไฟล์นี้ไม่รองรับการแปลงเป็นหน้าหนังสือ', 415);
    if ($file === null) lessonViewerError('ไฟล์เอกสารนี้ไม่รองรับการแปลงเป็นหน้าหนังสือ', 415);
    $preview = officePreviewPdf($file);
    if ($preview === null) lessonViewerError('ยังไม่สามารถแปลงเอกสารเป็นหน้าหนังสือได้ กรุณาติดตั้ง LibreOffice บนเครื่องเซิร์ฟเวอร์', 503);
    header('Content-Type: application/pdf');
    header('Content-Length: ' . (string) filesize($preview));
    header('Content-Disposition: inline; filename="document-preview.pdf"');
    header('Cache-Control: private, max-age=3600');
    readfile($preview);
    exit;
}
$resumePage = 0;
$documentProgressPercent = 0.0;
$savedProgress = [];
try {
    $resume = $conn->prepare('SELECT l.lesson_id, COALESCE(lp.document_page_index, 0) AS page_index, COALESCE(lp.document_progress_percent, 0) AS progress_percent FROM public.lessons l LEFT JOIN public.lesson_progress_summary lp ON l.lesson_id = lp.lesson_id AND lp.student_id = :student_id WHERE l.course_id = :course_id AND l.position = :position');
    $resume->execute([':student_id' => (string) $_SESSION['user_id'], ':course_id' => $courseId, ':position' => $position]);
    $savedProgress = $resume->fetch(PDO::FETCH_ASSOC) ?: [];
    $resumePage = max(0, (int) ($savedProgress['page_index'] ?? 0));
    $documentProgressPercent = min(100, max(0, (float) ($savedProgress['progress_percent'] ?? 0)));
} catch (PDOException) {  }
$documentProgressPayload = json_encode(['type' => 'lesson-document-progress', 'courseId' => $courseId, 'lessonIndex' => $position, 'lessonId' => (int) ($savedProgress['lesson_id'] ?? 0), 'resumePage' => $resumePage, 'documentProgressPercent' => $documentProgressPercent, 'documentUrl' => $relativePath], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="assets/css/theme.css?v=20261022-mobile-layout-v2">
  <style>
    :root { --pdf-toolbar-height: 58px; --pdf-safe-top: env(safe-area-inset-top, 0px); --pdf-safe-bottom: env(safe-area-inset-bottom, 0px); }
    * { box-sizing:border-box; }
    html, body { width:100%; height:100%; min-height:100%; }
    body { display:flex; flex-direction:column; margin:0; color:#24303b; font-family:"IBM Plex Sans Thai",system-ui,sans-serif; background:#525a67; overflow:hidden; }
    .reader-header { display:flex; flex:0 0 auto; gap:12px; align-items:center; justify-content:space-between; min-height:var(--pdf-toolbar-height); padding:8px 58px 8px 14px; padding-top:max(8px, var(--pdf-safe-top)); background:#fff; border-bottom:1px solid #e8eaed; font-weight:700; }
    .header-title { display:flex; flex-direction:column; gap:2px; min-width:0; }.header-title > span:first-child { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .pdf-toolbar { display:flex; flex:0 0 auto; align-items:center; gap:6px; }
    .pdf-toolbar button { min-width:44px; min-height:44px; border:1px solid #d2d9e5; border-radius:9px; padding:7px 10px; background:#fff; color:#24303b; font:inherit; font-size:15px; cursor:pointer; touch-action:manipulation; }
    .pdf-toolbar button:focus-visible { outline:3px solid #69a9e8; outline-offset:2px; }
    .pdf-toolbar .zoom-label { min-width:48px; text-align:center; font-size:13px; font-weight:600; }
    .pdf-reader { flex:1 1 auto; width:100%; min-width:0; min-height:0; margin:0; overflow:auto; overscroll-behavior:contain; -webkit-overflow-scrolling:touch; background:#525a67; }
    .pdf-pages { display:flex; flex-direction:column; align-items:center; gap:18px; width:max-content; min-width:100%; padding:18px; }
    .pdf-page { display:block; flex:0 0 auto; max-width:none; height:auto; background:#fff; box-shadow:0 2px 12px rgba(15,23,42,.3); }
    .pdf-loading { padding:28px; color:#fff; text-align:center; }
    main:not(.pdf-reader) { width:100%; max-width:980px; min-height:calc(100dvh - 58px); margin:auto; padding:20px; }
    .book-image { display:block; max-width:100%; max-height:calc(100dvh - 120px); margin:auto; box-shadow:0 8px 26px rgba(15,23,42,.16); background:#fff; }
    .notice { padding: 18px; border-radius: 10px; background: #9FA8DA; color: #303F9F; }
    @media (max-width:768px) {
      .reader-header { gap:6px; padding-left:max(8px, env(safe-area-inset-left)); padding-right:max(58px, calc(env(safe-area-inset-right, 0px) + 52px)); }
      .header-title { flex:1 1 auto; }
      .pdf-toolbar { gap:3px; }
      .pdf-toolbar button { min-width:42px; min-height:44px; padding:6px 8px; }
      .pdf-toolbar #pdf-open-window { max-width:112px; line-height:1.15; font-size:12px; }
      .pdf-pages { align-items:flex-start; gap:12px; padding:12px; }
      .pdf-page { align-self:center; }
      .pdf-reader { padding-bottom:var(--pdf-safe-bottom); }
    }
  </style>
    <link rel="stylesheet" href="assets/css/responsive.css?v=20261022-sidebar-menu-fix">
</head>
<body>
  <header class="reader-header"><div class="header-title"><span><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></span></div><?php if ($extension === 'pdf'): ?><div class="pdf-toolbar" role="toolbar" aria-label="เครื่องมืออ่าน PDF"><button type="button" id="pdf-zoom-out" aria-label="ซูมออก">−</button><span class="zoom-label" id="pdf-zoom-label" aria-live="polite">100%</span><button type="button" id="pdf-zoom-in" aria-label="ซูมเข้า">+</button><button type="button" id="pdf-open-window">เปิดเต็มจอ / แท็บใหม่</button></div><?php endif; ?></header>
<?php if ($extension === 'pdf'): ?>
  <main class="pdf-reader" id="pdf-reader" aria-label="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>"><div id="pdf-pages" class="pdf-pages"><div class="pdf-loading">กำลังเปิดเอกสาร…</div></div></main>
<?php elseif (in_array($extension, $officeExtensions, true)): ?>
  <main><div class="notice"><strong>เอกสารนี้ยังไม่ใช่ PDF</strong><br><br>กรุณากลับไปแก้ไขบทเรียนและอัปโหลดไฟล์ PDF เพื่อเปิดอ่านแบบเลื่อนทีละหน้า</div></main>
<?php elseif (in_array($extension, $imageExtensions, true)): ?>
  <main><img class="book-image" src="<?= htmlspecialchars($relativePath, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>"></main>
<?php else: ?>
  <main><div class="notice">ไฟล์ชนิดนี้ยังไม่รองรับการแสดงเป็นหน้าหนังสือ กรุณาดาวน์โหลดเพื่อเปิดด้วยโปรแกรมที่รองรับ</div></main>
<?php endif; ?>
<script src="assets/js/responsive.js?v=20261022-menu-auto-close" defer></script>
</body>
<script type="module">
  const documentPayload = <?= $documentProgressPayload ?>;
  const reader = document.getElementById('pdf-reader');
  const pagesContainer = document.getElementById('pdf-pages');
  const zoomLabel = document.getElementById('pdf-zoom-label');
  const zoomOutButton = document.getElementById('pdf-zoom-out');
  const zoomInButton = document.getElementById('pdf-zoom-in');
  const openWindowButton = document.getElementById('pdf-open-window');
  let pdfDocument = null;
  let baseScale = 1;
  let zoomFactor = 1;
  let pageCanvases = [];
  let renderVersion = 0;
  let restoredInitialPage = false;

  let readingSaveInFlight = false;
  let readingLastSaveAt = 0;
  let pendingReadingProgress = null;
  let readingSaveTimer = null;
  const readingSaveIntervalMs = 2000;
  function sendReadingProgress(payload, useBeacon = false) {
    const form = new FormData();
    form.append('lesson_id', String(documentPayload.lessonId)); form.append('progress_type', 'document');
    form.append('progress_value', String(payload.percent)); form.append('resume_page', String(payload.resumePage));
    form.append('current_page', String(payload.currentPage)); form.append('total_pages', String(payload.totalPages));
    form.append('is_completed', payload.percent >= 100 ? 'true' : 'false');
    if (useBeacon) return navigator.sendBeacon('save_progress.php', form);
    return fetch('save_progress.php', { method: 'POST', body: form, credentials: 'same-origin' })
      .then((response) => response.json())
      .then((result) => result.ok === true);
  }
  function scheduleReadingSave() {
    if (readingSaveTimer || readingSaveInFlight || !pendingReadingProgress) return;
    const waitMs = Math.max(0, readingSaveIntervalMs - (Date.now() - readingLastSaveAt));
    readingSaveTimer = setTimeout(flushReadingProgress, waitMs);
  }
  async function flushReadingProgress() {
    readingSaveTimer = null;
    if (readingSaveInFlight || !pendingReadingProgress) return;
    const payload = pendingReadingProgress;
    pendingReadingProgress = null;
    readingSaveInFlight = true;
    readingLastSaveAt = Date.now();
    try {
      const saved = await sendReadingProgress(payload);
      if (saved) window.parent.postMessage({ ...documentPayload, progressPercent: payload.percent }, window.location.origin);
    } catch (_) {}
    finally { readingSaveInFlight = false; scheduleReadingSave(); }
  }
  function saveReadingProgress(percent, currentPage) {
    pendingReadingProgress = {
      percent: Math.max(0, Math.min(100, percent)),
      resumePage: currentPage,
      currentPage,
      totalPages: pdfDocument.numPages,
    };
    scheduleReadingSave();
  }
  function flushReadingProgressOnExit() {
    if (readingSaveTimer) clearTimeout(readingSaveTimer);
    readingSaveTimer = null;
    if (readingSaveInFlight || !pendingReadingProgress) return;
    sendReadingProgress(pendingReadingProgress, true);
    pendingReadingProgress = null;
  }
  window.addEventListener('pagehide', flushReadingProgressOnExit);
  document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'hidden') flushReadingProgressOnExit(); });
  function updateZoomControls() {
    if (zoomLabel) zoomLabel.textContent = `${Math.round(baseScale * zoomFactor * 100)}%`;
    if (zoomOutButton) zoomOutButton.disabled = zoomFactor <= 0.65;
    if (zoomInButton) zoomInButton.disabled = zoomFactor >= 3;
  }

  async function renderPages() {
    const currentVersion = ++renderVersion;
    const oldScrollTop = reader.scrollTop;
    const oldScrollRatio = reader.scrollHeight > reader.clientHeight
      ? oldScrollTop / (reader.scrollHeight - reader.clientHeight)
      : 0;
    const renderedCanvases = [];
    pagesContainer.replaceChildren();
    for (let pageNumber = 1; pageNumber <= pdfDocument.numPages; pageNumber += 1) {
      const pdfPage = await pdfDocument.getPage(pageNumber);
      if (currentVersion !== renderVersion) return;
      const scale = baseScale * zoomFactor;
      const viewport = pdfPage.getViewport({ scale });
      const pixelRatio = Math.min(2, Math.max(1, window.devicePixelRatio || 1));
      const canvas = document.createElement('canvas');
      canvas.className = 'pdf-page';
      canvas.dataset.pageNumber = String(pageNumber);
      canvas.width = Math.ceil(viewport.width * pixelRatio);
      canvas.height = Math.ceil(viewport.height * pixelRatio);
      canvas.style.width = `${Math.ceil(viewport.width)}px`;
      canvas.style.height = `${Math.ceil(viewport.height)}px`;
      canvas.setAttribute('aria-label', `หน้า ${pageNumber} จาก ${pdfDocument.numPages}`);
      pagesContainer.append(canvas);
      await pdfPage.render({
        canvasContext: canvas.getContext('2d', { alpha: false }),
        viewport,
        transform: pixelRatio === 1 ? null : [pixelRatio, 0, 0, pixelRatio, 0, 0],
      }).promise;
      if (currentVersion !== renderVersion) return;
      renderedCanvases.push(canvas);
    }
    pageCanvases = renderedCanvases;
    if (!restoredInitialPage && documentPayload.resumePage > 1) {
      const resumeCanvas = pageCanvases[Math.min(pageCanvases.length - 1, documentPayload.resumePage - 1)];
      if (resumeCanvas) {
        const readerTop = reader.getBoundingClientRect().top;
        reader.scrollTop = Math.max(0, reader.scrollTop + resumeCanvas.getBoundingClientRect().top - readerTop - 18);
      }
    } else {
      reader.scrollTop = Math.max(0, (reader.scrollHeight - reader.clientHeight) * oldScrollRatio);
    }
    restoredInitialPage = true;
  }

  async function renderPdf() {
    if (!reader || !pagesContainer) return;

    try {
      const pdfjsLib = await import('https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.min.mjs');
      pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.worker.min.mjs';
      pdfDocument = await pdfjsLib.getDocument(documentPayload.documentUrl).promise;
      const firstPage = await pdfDocument.getPage(1);
      const baseViewport = firstPage.getViewport({ scale: 1 });
      const availableWidth = Math.max(240, reader.clientWidth - 24);
      // Keep phone text readable: never shrink the first render below 115%.
      baseScale = Math.max(1.15, Math.min(2, availableWidth / baseViewport.width));
      updateZoomControls();
      await renderPages();

      let highestReadPercent = documentPayload.documentProgressPercent;
      let scrollQueued = false;
      const recordScrolledPages = () => {
        scrollQueued = false;
        const visibleBottom = reader.scrollTop + reader.clientHeight;
        let currentPage = 1;
        pageCanvases.forEach((canvas, index) => {
          const canvasRect = canvas.getBoundingClientRect();
          const readerTop = reader.getBoundingClientRect().top;
          const canvasBottom = reader.scrollTop + canvasRect.bottom - readerTop;
          if (visibleBottom >= canvasBottom - Math.min(canvas.offsetHeight * 0.2, reader.clientHeight * 0.2)) currentPage = index + 1;
        });
        const atEnd = visibleBottom >= reader.scrollHeight - 8;
        const percent = Math.max(highestReadPercent, atEnd ? 100 : (currentPage / pdfDocument.numPages) * 100);
        if (percent <= highestReadPercent && currentPage === documentPayload.resumePage) return;
        highestReadPercent = percent;
        saveReadingProgress(percent, currentPage);
      };

      reader.addEventListener('scroll', () => {
        if (!scrollQueued) {
          scrollQueued = true;
          requestAnimationFrame(recordScrolledPages);
        }
      }, { passive: true });
    } catch (error) {
      console.error('PDF reader failed to render:', error);
      pagesContainer.innerHTML = '<div class="notice">ไม่สามารถเปิดเอกสารได้ กรุณาลองใหม่อีกครั้ง</div>';
    }
  }

  zoomOutButton?.addEventListener('click', () => { zoomFactor = Math.max(0.65, Math.round((zoomFactor - 0.15) * 100) / 100); updateZoomControls(); renderPages(); });
  zoomInButton?.addEventListener('click', () => { zoomFactor = Math.min(3, Math.round((zoomFactor + 0.15) * 100) / 100); updateZoomControls(); renderPages(); });
  openWindowButton?.addEventListener('click', async () => {
    try {
      if (document.fullscreenElement) await document.exitFullscreen();
      else if (document.documentElement.requestFullscreen) await document.documentElement.requestFullscreen();
      else throw new Error('Fullscreen is unavailable');
    } catch (_) {
      window.open(window.location.href, '_blank', 'noopener,noreferrer');
    }
  });

  renderPdf();
</script>
</html>
