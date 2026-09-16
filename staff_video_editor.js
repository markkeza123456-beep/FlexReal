(() => {
  const subjectId = (new URLSearchParams(location.search).get('subject_id') || '').trim();
  let subject; let lessons = []; let videos = [];
  const $ = (id) => document.getElementById(id);
  const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
  const toast = (message, type = 'success') => { const el = $('toast'); el.textContent = message; el.className = `toast show ${type}`; clearTimeout(toast.timer); toast.timer = setTimeout(() => el.classList.remove('show'), 3000); };
  const duration = (seconds) => { if (seconds === null || seconds === '' || Number.isNaN(Number(seconds))) return '-'; const m = Math.floor(Number(seconds) / 60); return `${m}:${String(Number(seconds) % 60).padStart(2, '0')}`; };

  function setPreview(url) { $('videoPreview').hidden = !url; $('videoPreviewLink').href = url || '#'; $('videoPreviewLink').textContent = url ? 'เปิดวิดีโอปัจจุบัน' : ''; }
  function resetForm() { $('videoForm').reset(); $('videoId').value = ''; $('videoOrder').value = '1'; $('videoFormTitle').textContent = 'เพิ่มวิดีโอใหม่'; setPreview(''); }
  function render() {
    $('subjectTitle').textContent = subject.name;
    $('videoMeta').innerHTML = `<span class="meta-pill">รหัสวิชา ${esc(subject.id)}</span><span class="meta-pill">วิดีโอ ${videos.length} รายการ</span>`;
    $('backLink').href = `staff_subject_editor.php?subject_id=${encodeURIComponent(subject.id)}&section=lessons`;
    $('videoLesson').innerHTML = `<option value="">วิดีโอรวมของรายวิชา</option>${lessons.map((lesson) => `<option value="${esc(lesson.id)}">${esc(lesson.title)}</option>`).join('')}`;
    $('videoTableBody').innerHTML = videos.length ? videos.map((video) => `<tr><td><a class="resource-link video" href="${esc(video.url)}" target="_blank" rel="noopener noreferrer">${esc(video.title)}</a></td><td>${esc(video.lesson_title || 'วิดีโอรวมของรายวิชา')}</td><td>${duration(video.duration_seconds)}</td><td>${esc(video.display_order)}</td><td><div class="action-btns"><button class="btn-icon" type="button" data-edit="${esc(video.id)}">✎</button><button class="btn-icon danger" type="button" data-delete="${esc(video.id)}">✖</button></div></td></tr>`).join('') : '<tr><td colspan="5" class="empty-cell">ยังไม่มีวิดีโอในรายวิชานี้</td></tr>';
  }
  async function load() {
    if (!subjectId) { $('errorPanel').hidden = false; $('errorMessage').textContent = 'ไม่พบรหัสรายวิชา'; return; }
    try { const response = await fetch(`api_staff.php?action=getVideoEditorData&subject_id=${encodeURIComponent(subjectId)}`); const data = await response.json(); if (data.status !== 'success') throw new Error(data.message); subject = data.subject; lessons = data.lessons || []; videos = data.videos || []; render(); } catch (error) { $('errorPanel').hidden = false; $('errorMessage').textContent = error.message || 'ไม่สามารถโหลดวิดีโอได้'; }
  }
  function edit(id) { const video = videos.find((item) => String(item.id) === String(id)); if (!video) return; $('videoId').value = video.id; $('videoTitle').value = video.title || ''; $('videoDescription').value = video.description || ''; $('videoLesson').value = video.lessons_id || ''; $('videoUrl').value = video.url || ''; $('videoDuration').value = video.duration_seconds || ''; $('videoOrder').value = video.display_order || 1; $('videoFormTitle').textContent = `แก้ไขวิดีโอ: ${video.title}`; setPreview(video.url); $('videoEditor').scrollIntoView({ behavior: 'smooth', block: 'start' }); }
  async function remove(id) { const video = videos.find((item) => String(item.id) === String(id)); if (!video || !confirm(`ยืนยันการลบวิดีโอ "${video.title}" ?`)) return; const form = new FormData(); form.append('action', 'deleteVideo'); form.append('id', id); form.append('subject_id', subjectId); try { const result = await (await fetch('api_staff.php', { method: 'POST', body: form })).json(); if (result.status !== 'success') throw new Error(result.message); toast('ลบวิดีโอเรียบร้อยแล้ว'); await load(); } catch (error) { toast(error.message || 'ลบวิดีโอไม่สำเร็จ', 'error'); } }
  $('videoForm').addEventListener('submit', async (event) => { event.preventDefault(); const form = new FormData(); form.append('action', 'saveVideo'); form.append('id', $('videoId').value); form.append('subject_id', subjectId); form.append('title', $('videoTitle').value.trim()); form.append('description', $('videoDescription').value.trim()); form.append('lesson_id', $('videoLesson').value); form.append('url', $('videoUrl').value.trim()); form.append('duration', $('videoDuration').value); form.append('display_order', $('videoOrder').value); if ($('videoFile').files[0]) form.append('video_file', $('videoFile').files[0]); try { const result = await (await fetch('api_staff.php', { method: 'POST', body: form })).json(); if (result.status !== 'success') throw new Error(result.message); toast('บันทึกวิดีโอเรียบร้อยแล้ว'); await load(); resetForm(); } catch (error) { toast(error.message || 'บันทึกวิดีโอไม่สำเร็จ', 'error'); } });
  $('addVideoBtn').addEventListener('click', () => { resetForm(); $('videoEditor').scrollIntoView({ behavior: 'smooth', block: 'start' }); });
  $('clearVideoBtn').addEventListener('click', resetForm);
  $('videoTableBody').addEventListener('click', (event) => { const editBtn = event.target.closest('[data-edit]'); const deleteBtn = event.target.closest('[data-delete]'); if (editBtn) edit(editBtn.dataset.edit); if (deleteBtn) remove(deleteBtn.dataset.delete); });
  load();
})();
