(() => {
  const root = document.getElementById('staffContentManager');
  if (!root) return;
  const subjectId = root.dataset.subjectId || '';
  const $ = (id) => document.getElementById(id);
  let lessons = [];
  let videos = [];
  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
  function formatEditDate(value, isEpoch = false) {
    if (value === null || value === undefined || value === '') return 'ยังไม่มีข้อมูลวันที่';
    const date = isEpoch ? new Date(Number(value) * 1000) : new Date(value);
    if (Number.isNaN(date.getTime())) return 'ยังไม่มีข้อมูลวันที่';
    const parts = Object.fromEntries(new Intl.DateTimeFormat('en-GB', {
      timeZone: 'Asia/Bangkok', day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
    }).formatToParts(date).map((part) => [part.type, part.value]));
    return `${parts.day}/${parts.month}/${Number(parts.year) + 543} ${parts.hour}:${parts.minute}`;
  }
  const resourceUrl = (value) => {
    try {
      const url = new URL(String(value || ''), window.location.href);
      return ['http:', 'https:'].includes(url.protocol) ? url.href : '';
    } catch { return ''; }
  };
  function youtubeEmbedUrl(value) {
    try {
      const url = new URL(value);
      if (url.hostname === 'youtu.be') return `https://www.youtube-nocookie.com/embed/${encodeURIComponent(url.pathname.slice(1))}`;
      if (url.hostname.endsWith('youtube.com')) {
        const id = url.searchParams.get('v') || url.pathname.match(/\/embed\/([^/?]+)/)?.[1] || url.pathname.match(/\/shorts\/([^/?]+)/)?.[1];
        if (id) return `https://www.youtube-nocookie.com/embed/${encodeURIComponent(id)}`;
      }
    } catch { /* Uploaded video paths are handled by the native player. */ }
    return '';
  }
  function openResourceViewer(title, value, type) {
    const url = resourceUrl(value);
    if (!url) return;
    const modal = $('cmResourceViewer');
    const body = $('cmViewerBody');
    $('cmViewerTitle').textContent = title || (type === 'video' ? 'วิดีโอบทเรียน' : 'เอกสารประกอบ');
    body.replaceChildren();
    if (type === 'video') {
      const embedUrl = youtubeEmbedUrl(url);
      if (embedUrl || !/\.(mp4|webm|ogg)(?:$|[?#])/i.test(url)) {
        const frame = document.createElement('iframe');
        frame.src = embedUrl || url;
        frame.title = title || 'วิดีโอบทเรียน';
        frame.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
        frame.allowFullscreen = true;
        body.append(frame);
      } else {
        const video = document.createElement('video');
        video.src = url;
        video.controls = true;
        video.playsInline = true;
        video.preload = 'metadata';
        body.append(video);
      }
    } else {
      const frame = document.createElement('iframe');
      frame.src = url;
      frame.title = title || 'เอกสารประกอบ';
      body.append(frame);
    }
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('resource-viewer-open');
    $('cmViewerClose').focus();
  }
  function closeResourceViewer() {
    const modal = $('cmResourceViewer');
    if (!modal.classList.contains('open')) return;
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    $('cmViewerBody').replaceChildren();
    document.body.classList.remove('resource-viewer-open');
  }
  function toast(message) { const el = $('cmToast'); el.textContent = message; el.classList.add('show'); clearTimeout(toast.timer); toast.timer = setTimeout(() => el.classList.remove('show'), 2600); }
  async function request(url, options) {
    const response = await fetch(url, { credentials: 'same-origin', ...options });
    const data = await response.json();
    if (!response.ok || data.status !== 'success') throw new Error(data.message || 'บันทึกไม่สำเร็จ');
    return data;
  }
  function render() {
    $('cmLessonCount').textContent = `${lessons.length} บทเรียน`;
    $('cmLessonList').innerHTML = lessons.map((lesson) => `<div class="content-manager-item"><div class="content-manager-item-main"><span>${escapeHtml(lesson.title)}</span><small class="content-manager-item-meta">แก้ไขล่าสุด ${escapeHtml(formatEditDate(lesson.last_modified_at))} · โดย ${escapeHtml(lesson.editor_name || 'ยังไม่มีข้อมูล')}</small></div><div class="content-manager-item-actions">${lesson.document_path ? `<button type="button" class="content-manager-link" data-view-resource="document" data-resource-url="${escapeHtml(lesson.document_path)}" data-resource-title="${escapeHtml(lesson.document_name || lesson.title || 'เอกสารประกอบ')}">เปิดเอกสาร</button>` : ''}<button type="button" class="content-manager-edit" data-edit-lesson="${escapeHtml(lesson.id)}">แก้ไข</button></div></div>`).join('');
    $('cmVideoLesson').innerHTML = lessons.map((lesson) => `<option value="${escapeHtml(lesson.id)}">${escapeHtml(lesson.title)}</option>`).join('') || '<option value="">เพิ่มบทเรียนก่อน</option>';
    $('cmVideoLesson').value = lessons[0]?.id || '';
    $('cmVideoList').innerHTML = videos.map((video) => `<div class="content-manager-item"><div class="content-manager-item-main"><div class="content-manager-item-actions"><button type="button" class="content-manager-link" data-view-resource="video" data-resource-url="${escapeHtml(video.url)}" data-resource-title="${escapeHtml(video.title || 'วิดีโอบทเรียน')}">${escapeHtml(video.title || 'วิดีโอบทเรียน')}</button><button type="button" class="content-manager-edit" data-edit-video="${escapeHtml(video.id)}">แก้ไข</button></div><small class="content-manager-item-meta">แก้ไขล่าสุด ${escapeHtml(formatEditDate(video.last_modified_epoch, true))} · โดย ${escapeHtml(video.editor_name || 'ยังไม่มีข้อมูล')}</small></div><span>${escapeHtml(video.lesson_title || '')}</span></div>`).join('');
  }
  async function load() {
    try {
      const [lessonData, videoData] = await Promise.all([
        request(`api_staff.php?action=getSubjectEditorData&subject_id=${encodeURIComponent(subjectId)}`),
        request(`api_staff.php?action=getVideoEditorData&subject_id=${encodeURIComponent(subjectId)}`),
      ]);
      lessons = lessonData.lessons || [];
      videos = videoData.videos || [];
      render();
    } catch (error) { toast(error.message || 'โหลดข้อมูลไม่สำเร็จ'); }
  }
  $('cmLessonForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const button = event.currentTarget.querySelector('button[type=submit]');
    const creatingLesson = !$('cmLessonId').value;
    button.disabled = true;
    $('cmLessonStatus').classList.remove('is-error');
    try {
      const form = new FormData();
      form.append('action', 'saveLesson'); form.append('id', $('cmLessonId').value); form.append('subject_id', subjectId);
      form.append('title', $('cmLessonName').value.trim()); form.append('content', '');
      if ($('cmLessonDocument').files[0]) form.append('document', $('cmLessonDocument').files[0]);
      const result = await request('api_staff.php', { method: 'POST', body: form });
      $('cmLessonId').value = result.lesson_id || '';
      $('cmLessonHeading').textContent = 'แก้ไขบทเรียน';
      $('cmLessonStatus').textContent = 'บันทึกบทเรียนเรียบร้อยแล้ว';
      $('cmLessonDocument').value = '';
      if (creatingLesson) {
        $('cmLessonId').value = '';
        $('cmLessonName').value = '';
        $('cmLessonHeading').textContent = 'เพิ่มบทเรียน';
        $('cmLessonDocumentCurrent').hidden = true;
        $('cmLessonDocumentCurrent').textContent = '';
      }
      await load(); toast('บันทึกบทเรียนเรียบร้อยแล้ว');
    } catch (error) { $('cmLessonStatus').textContent = error.message || 'บันทึกบทเรียนไม่สำเร็จ'; $('cmLessonStatus').classList.add('is-error'); }
    finally { button.disabled = false; }
  });
  $('cmLessonList').addEventListener('click', (event) => {
    const resource = event.target.closest('[data-view-resource]');
    if (resource) { openResourceViewer(resource.dataset.resourceTitle, resource.dataset.resourceUrl, resource.dataset.viewResource); return; }
    const button = event.target.closest('[data-edit-lesson]'); if (!button) return;
    const lesson = lessons.find((item) => String(item.id) === button.dataset.editLesson); if (!lesson) return;
    $('cmLessonId').value = lesson.id; $('cmLessonName').value = lesson.title || ''; $('cmLessonHeading').textContent = 'แก้ไขบทเรียน';
    $('cmLessonDocumentCurrent').hidden = !lesson.document_path;
    $('cmLessonDocumentCurrent').innerHTML = lesson.document_path ? `เอกสารปัจจุบัน: <button type="button" class="content-manager-link" data-view-resource="document" data-resource-url="${escapeHtml(lesson.document_path)}" data-resource-title="${escapeHtml(lesson.document_name || lesson.title || 'เอกสารประกอบ')}">${escapeHtml(lesson.document_name || 'เปิดเอกสาร')}</button>` : '';
    $('cmLessonName').focus();
  });
  $('cmClearLessonBtn').addEventListener('click', () => {
    $('cmLessonForm').reset();
    $('cmLessonId').value = '';
    $('cmLessonHeading').textContent = 'เพิ่มบทเรียน';
    $('cmLessonDocumentCurrent').hidden = true;
    $('cmLessonDocumentCurrent').textContent = '';
    $('cmLessonStatus').textContent = '';
    $('cmLessonStatus').classList.remove('is-error');
    $('cmLessonName').focus();
  });
  $('cmLessonDocumentCurrent').addEventListener('click', (event) => {
    const resource = event.target.closest('[data-view-resource]');
    if (resource) openResourceViewer(resource.dataset.resourceTitle, resource.dataset.resourceUrl, resource.dataset.viewResource);
  });
  $('cmVideoList').addEventListener('click', (event) => {
    const editButton = event.target.closest('[data-edit-video]');
    if (editButton) {
      const video = videos.find((item) => String(item.id) === editButton.dataset.editVideo);
      if (!video) return;
      $('cmVideoId').value = video.id;
      $('cmVideoTitle').value = video.title || 'วิดีโอบทเรียน';
      $('cmVideoLesson').value = video.lessons_id || '';
      $('cmVideoUrl').value = video.url || '';
      $('cmVideoFile').value = '';
      $('cmVideoHeading').textContent = 'แก้ไขวิดีโอ';
      $('cmVideoFileOptional').textContent = ' (เว้นว่างไว้เพื่อใช้ไฟล์เดิม)';
      $('cmVideoStatus').textContent = '';
      $('cmVideoStatus').classList.remove('is-error');
      $('cmVideoForm').scrollIntoView({ behavior: 'smooth', block: 'center' });
      $('cmVideoTitle').focus({ preventScroll: true });
      return;
    }
    const resource = event.target.closest('[data-view-resource]');
    if (resource) openResourceViewer(resource.dataset.resourceTitle, resource.dataset.resourceUrl, resource.dataset.viewResource);
  });
  $('cmViewerClose').addEventListener('click', closeResourceViewer);
  $('cmResourceViewer').addEventListener('click', (event) => { if (event.target === $('cmResourceViewer')) closeResourceViewer(); });
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeResourceViewer(); });
  $('cmVideoForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const button = event.currentTarget.querySelector('button[type=submit]'); button.disabled = true;
    $('cmVideoStatus').classList.remove('is-error');
    try {
      const form = new FormData();
      form.append('action', 'saveVideo'); form.append('id', $('cmVideoId').value); form.append('subject_id', subjectId); form.append('title', $('cmVideoTitle').value.trim());
      const editingVideo = videos.find((item) => String(item.id) === $('cmVideoId').value);
      form.append('lesson_id', $('cmVideoLesson').value); form.append('url', $('cmVideoUrl').value.trim()); form.append('display_order', String(editingVideo?.display_order || videos.length + 1));
      if ($('cmVideoFile').files[0]) form.append('video_file', $('cmVideoFile').files[0]);
      await request('api_staff.php', { method: 'POST', body: form });
      $('cmVideoForm').reset(); $('cmVideoId').value = ''; $('cmVideoTitle').value = 'วิดีโอบทเรียน'; $('cmVideoUrl').value = ''; $('cmVideoLesson').value = lessons[0]?.id || '';
      $('cmVideoHeading').textContent = 'เพิ่มและจัดการวิดีโอ'; $('cmVideoFileOptional').textContent = '*'; $('cmVideoStatus').textContent = 'บันทึกวิดีโอเรียบร้อยแล้ว';
      await load(); toast('บันทึกวิดีโอเรียบร้อยแล้ว');
    } catch (error) { $('cmVideoStatus').textContent = error.message || 'บันทึกวิดีโอไม่สำเร็จ'; $('cmVideoStatus').classList.add('is-error'); }
    finally { button.disabled = false; }
  });
  $('cmClearVideoBtn').addEventListener('click', () => {
    $('cmVideoForm').reset();
    $('cmVideoId').value = '';
    $('cmVideoLesson').value = lessons[0]?.id || '';
    $('cmVideoTitle').value = 'วิดีโอบทเรียน';
    $('cmVideoUrl').value = '';
    $('cmVideoHeading').textContent = 'เพิ่มและจัดการวิดีโอ';
    $('cmVideoFileOptional').textContent = '*';
    $('cmVideoStatus').textContent = '';
    $('cmVideoStatus').classList.remove('is-error');
  });
  load();
})();
