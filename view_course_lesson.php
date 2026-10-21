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
$documentTotalPages = 0;
$documentReadPages = [];
try {
    ensureLearningProgressTables($conn);
    $resume = $conn->prepare("SELECT COALESCE(lp.document_page_index, 0) AS page_index, COALESCE(lp.document_progress_percent, 0) AS progress_percent, COALESCE(lp.document_total_pages, 0) AS total_pages, COALESCE(lp.document_read_pages, '[]'::jsonb) AS read_pages FROM public.lesson_progress lp JOIN public.lessons l ON l.lesson_id = lp.lesson_id WHERE lp.student_id = :student_id AND l.course_id = :course_id AND l.position = :position");
    $resume->execute([':student_id' => (string) $_SESSION['user_id'], ':course_id' => $courseId, ':position' => $position]);
    $savedProgress = $resume->fetch(PDO::FETCH_ASSOC) ?: [];
    $resumePage = max(0, (int) ($savedProgress['page_index'] ?? 0));
    $documentProgressPercent = min(100, max(0, (float) ($savedProgress['progress_percent'] ?? 0)));
    $documentTotalPages = max(0, (int) ($savedProgress['total_pages'] ?? 0));
    $documentReadPages = json_decode((string) ($savedProgress['read_pages'] ?? '[]'), true) ?: [];
} catch (PDOException) {  }
$documentProgressPayload = json_encode(['type' => 'lesson-document-progress', 'courseId' => $courseId, 'lessonIndex' => $position, 'resumePage' => $resumePage, 'documentProgressPercent' => $documentProgressPercent, 'documentTotalPages' => $documentTotalPages, 'documentReadPages' => $documentReadPages, 'documentUrl' => $relativePath], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="assets/css/theme.css?v=20261021-login-shared-background">
  <style>
    body { margin: 0; color: #24303b; font-family: "IBM Plex Sans Thai", system-ui, sans-serif; background: #f7f8fa; }
    header { display:flex; gap:12px; align-items:center; justify-content:space-between; padding: 12px 20px; background: #fff; border-bottom: 1px solid #e8eaed; font-weight: 700; }
    .header-title { display:flex; flex-direction:column; gap:2px; min-width:0; }.header-title > span:first-child { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }.reading-status { color:#64748b; font-size:.82rem; font-weight:500; }
    main { max-width: 980px; min-height: calc(100vh - 59px); margin: auto; padding: 20px; box-sizing: border-box; }
    .pdf-reader { height:calc(100vh - 99px); overflow-y:auto; border:1px solid #d8dee9; border-radius:8px; background:#525a67; box-shadow:0 8px 26px rgba(15,23,42,.16); }
    .pdf-pages { display:flex; flex-direction:column; align-items:center; gap:18px; padding:18px; }
    .pdf-page { display:block; max-width:100%; height:auto; background:#fff; box-shadow:0 2px 12px rgba(15,23,42,.3); }
    .pdf-loading { padding:28px; color:#fff; text-align:center; }
    .book-image { display:block; max-width:100%; max-height:calc(100vh - 120px); margin:auto; box-shadow:0 8px 26px rgba(15,23,42,.16); background:#fff; }
    .notice { padding: 18px; border-radius: 10px; background: #9FA8DA; color: #303F9F; }
  </style>
    <link rel="stylesheet" href="assets/css/responsive.css">
</head>
<body>
  <header class="reader-header"><div class="header-title"><span><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></span><span id="reading-status" class="reading-status"></span></div></header>
<?php if ($extension === 'pdf'): ?>
  <main class="pdf-reader" id="pdf-reader" aria-label="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>"><div id="pdf-pages" class="pdf-pages"><div class="pdf-loading">กำลังเปิดเอกสาร…</div></div></main>
<?php elseif (in_array($extension, $officeExtensions, true)): ?>
  <main><div class="notice"><strong>เอกสารนี้ยังไม่ใช่ PDF</strong><br><br>กรุณากลับไปแก้ไขบทเรียนและอัปโหลดไฟล์ PDF เพื่อเปิดอ่านแบบเลื่อนทีละหน้า</div></main>
<?php elseif (in_array($extension, $imageExtensions, true)): ?>
  <main><img class="book-image" src="<?= htmlspecialchars($relativePath, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>"></main>
<?php else: ?>
  <main><div class="notice">ไฟล์ชนิดนี้ยังไม่รองรับการแสดงเป็นหน้าหนังสือ กรุณาดาวน์โหลดเพื่อเปิดด้วยโปรแกรมที่รองรับ</div></main>
<?php endif; ?>
<script src="assets/js/responsive.js?v=20261021-responsive" defer></script>
</body>
<script type="module">
  const documentPayload = <?= $documentProgressPayload ?>;
  const reader = document.getElementById('pdf-reader');
  const pagesContainer = document.getElementById('pdf-pages');
  let pageCanvases = [];
  let readPages = new Set(Array.isArray(documentPayload.documentReadPages) ? documentPayload.documentReadPages.map(Number).filter(Number.isInteger) : []);
  let activePage = Math.max(1, Number(documentPayload.resumePage) || 1);
  let saveInFlight = null;

  function currentProgressPercent(totalPages) {
    return totalPages > 0 ? readPages.size / totalPages * 100 : 0;
  }

  function makeProgressForm() {
    const form = new FormData();
    form.append('action', 'record'); form.append('subject_id', documentPayload.courseId);
    form.append('lesson_index', String(documentPayload.lessonIndex)); form.append('activity_type', 'document_progress');
    form.append('progress_percent', String(Math.max(0, Math.min(100, currentProgressPercent(pageCanvases.length)))));
    form.append('resume_position', String(activePage));
    form.append('document_total_pages', String(pageCanvases.length));
    form.append('document_read_pages', JSON.stringify([...readPages]));
    return form;
  }

  async function saveReadingProgress(useBeacon = false) {
    if (pageCanvases.length === 0) return false;
    const percent = currentProgressPercent(pageCanvases.length);
    const form = makeProgressForm();
    if (useBeacon && navigator.sendBeacon) {
      const sent = navigator.sendBeacon('student_learning_api.php', form);
      window.parent.postMessage({ ...documentPayload, progressPercent: percent }, window.location.origin);
      return sent;
    }
    if (saveInFlight) return saveInFlight;
    saveInFlight = fetch('student_learning_api.php', { method: 'POST', body: form, credentials: 'same-origin', keepalive: true })
      .then(response => response.ok)
      .catch(() => false)
      .finally(() => { saveInFlight = null; });
    const saved = await saveInFlight;
    if (saved) window.parent.postMessage({ ...documentPayload, progressPercent: percent }, window.location.origin);
    return saved;
  }
  function updateReadingStatus(percent) {
    const status = document.getElementById('reading-status');
    if (!status) return;
    const documentPercent = Math.round(Math.max(0, Math.min(100, percent)));
    const lessonPercent = Math.round(documentPercent * 0.30 * 10) / 10;
    status.textContent = `อ่านแล้ว ${readPages.size}/${pageCanvases.length} หน้า (${documentPercent}%) · คิดเป็น ${lessonPercent}% ของบทเรียน`;
  }

  async function renderPdf() {
    if (!reader || !pagesContainer) {
      updateReadingStatus(documentPayload.documentProgressPercent);
      return;
    }

    try {
      const pdfjsLib = await import('https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.min.mjs');
      pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.worker.min.mjs';
      const pdf = await pdfjsLib.getDocument(documentPayload.documentUrl).promise;
      pagesContainer.replaceChildren();

      for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber += 1) {
        const pdfPage = await pdf.getPage(pageNumber);
        const unscaledViewport = pdfPage.getViewport({ scale: 1 });
        const availableWidth = Math.max(320, reader.clientWidth - 36);
        const viewport = pdfPage.getViewport({ scale: Math.min(2, availableWidth / unscaledViewport.width) });
        const canvas = document.createElement('canvas');
        canvas.className = 'pdf-page';
        canvas.dataset.pageNumber = String(pageNumber);
        canvas.width = Math.ceil(viewport.width);
        canvas.height = Math.ceil(viewport.height);
        canvas.setAttribute('aria-label', `หน้า ${pageNumber} จาก ${pdf.numPages}`);
        await pdfPage.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
        pagesContainer.append(canvas);
        pageCanvases.push(canvas);
      }

      if (readPages.size === 0 && documentPayload.documentProgressPercent > 0) {
        const legacyPages = Math.min(pdf.numPages, Math.floor(documentPayload.documentProgressPercent / 100 * pdf.numPages));
        for (let page = 1; page <= legacyPages; page += 1) readPages.add(page);
      }
      readPages = new Set([...readPages].filter(page => page >= 1 && page <= pdf.numPages));
      updateReadingStatus(currentProgressPercent(pdf.numPages));
      if (documentPayload.resumePage > 0) {
        const resumeCanvas = pageCanvases[Math.min(pageCanvases.length - 1, documentPayload.resumePage - 1)];
        reader.scrollTop = Math.max(0, resumeCanvas.offsetTop - 18);
      }

      const visiblePages = new Set();
      const dwellTimers = new Map();
      const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
          const page = Number(entry.target.dataset.pageNumber);
          if (entry.isIntersecting && entry.intersectionRatio >= 0.4) {
            visiblePages.add(page);
            activePage = page;
            if (!readPages.has(page) && !dwellTimers.has(page)) {
              dwellTimers.set(page, window.setTimeout(() => {
                dwellTimers.delete(page);
                if (visiblePages.has(page)) {
                  readPages.add(page);
                  updateReadingStatus(currentProgressPercent(pdf.numPages));
                  saveReadingProgress();
                }
              }, 2500));
            }
          } else {
            visiblePages.delete(page);
            if (dwellTimers.has(page)) {
              clearTimeout(dwellTimers.get(page));
              dwellTimers.delete(page);
            }
          }
        });
      }, { root: reader, threshold: [0.4] });
      pageCanvases.forEach(canvas => observer.observe(canvas));
      reader.addEventListener('scroll', () => {
        if (visiblePages.size > 0) saveReadingProgress();
      }, { passive: true });
      window.setInterval(() => {
        if (document.visibilityState === 'visible' && visiblePages.size > 0) saveReadingProgress();
      }, 8000);
    } catch (_) {
      pagesContainer.innerHTML = '<div class="notice">ไม่สามารถเปิดเอกสารได้ กรุณาลองใหม่อีกครั้ง</div>';
      updateReadingStatus(documentPayload.documentProgressPercent);
    }
  }

  window.addEventListener('message', async event => {
    if (event.origin !== window.location.origin || event.data?.type !== 'lesson-document-save-request') return;
    await saveReadingProgress();
    window.parent.postMessage({ type: 'lesson-document-save-complete' }, window.location.origin);
  });
  document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'hidden') saveReadingProgress(true); });
  window.addEventListener('pagehide', () => saveReadingProgress(true));
  window.addEventListener('beforeunload', () => saveReadingProgress(true));

  renderPdf();
</script>
</html>
