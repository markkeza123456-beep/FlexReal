(() => {
  const body = document.getElementById('learningProgressBody');
  if (!body) return;

  const summary = document.getElementById('learningProgressSummary');
  const search = document.getElementById('learningProgressSearch');
  const pageInfo = document.getElementById('learningProgressPageInfo');
  const previous = document.getElementById('learningProgressPrev');
  const next = document.getElementById('learningProgressNext');
  const studentView = document.body.classList.contains('student-dashboard-page') || Boolean(document.getElementById('dashboard-page'));
  const directorView = document.body.classList.contains('director-dashboard');
  const dateFormat = new Intl.DateTimeFormat('th-TH', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Bangkok' });
  let days = 1;
  let page = 1;
  let pages = 1;
  let query = '';
  let serial = 0;
  let searchTimer;

  const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);

  function renderRows(rows) {
    if (!rows.length) {
      const colspan = studentView ? 3 : 4;
      body.innerHTML = `<tr><td colspan="${colspan}" style="text-align:center;padding:16px">ไม่พบข้อมูลในช่วงเวลานี้</td></tr>`;
      return;
    }
    body.innerHTML = rows.map(row => {
      const hasActivity = Boolean(row.last_activity_at);
      const active = Number(row.lessons_active || 0);
      const completed = Number(row.lessons_completed || 0);
      const quizzes = Number(row.quizzes_submitted || 0);
      const passed = Number(row.quizzes_passed || 0);
      const failed = Number(row.quizzes_failed || 0);
      const progressPercent = Math.min(100, Math.max(0, Number(row.progress_percent) || 0));
      const last = hasActivity ? dateFormat.format(new Date(row.last_activity_at)) : '—';
      const lessons = Array.isArray(row.lessons) ? row.lessons : [];
      const quizzesList = Array.isArray(row.quizzes) ? row.quizzes : [];
      const lessonList = lessons.length
        ? `<ul class="learning-activity-list">${lessons.map(lesson => `<li><span>${esc(lesson.title || 'บทเรียน')}</span>${lesson.is_completed ? '<span class="learning-badge is-passed">สำเร็จ</span>' : ''}</li>`).join('')}</ul>`
        : '<span class="learning-muted">ไม่มีบทเรียนในช่วงนี้</span>';
      const quizList = quizzesList.length
        ? `<ul class="learning-activity-list">${quizzesList.map(quiz => {
            const passedAttempt = quiz.status === 'passed';
            const badges = `${passedAttempt ? '<span class="learning-badge is-passed">ผ่าน</span>' : '<span class="learning-badge is-failed">ไม่ผ่าน</span>'}${quiz.has_retry ? '<span class="learning-badge is-warning">ทำซ้ำ</span>' : ''}${quiz.has_failed ? '<span class="learning-badge is-failed">รอบล่าสุดไม่ผ่าน</span>' : ''}`;
            return `<li class="learning-quiz-item"><div><strong>${esc(quiz.quiz_title || 'แบบทดสอบ')} · รอบที่ ${Number(quiz.attempt_number || 0)}</strong><small>${Number(quiz.score || 0)}/${Number(quiz.total_score || 0)}</small></div><div class="learning-badges">${badges}</div></li>`;
          }).join('')}</ul>`
        : '<span class="learning-muted">ไม่มีการสอบในช่วงนี้</span>';
      const details = `<div class="learning-activity-summary">เข้าเรียนจริง: ${active} บท · สำเร็จ ${completed} บท<br>ทำแบบทดสอบทั้งหมด: ${quizzes} ครั้ง (ผ่าน ${passed} / ไม่ผ่าน ${failed})<br>ความคืบหน้ารวม: ${progressPercent}%</div><div class="learning-activity-columns"><div><strong>บทเรียน</strong>${lessonList}</div><div><strong>ประวัติการสอบ</strong>${quizList}</div></div>`;
      if (studentView) return `<tr><td>${details}</td><td>${esc(hasActivity ? 'มีกิจกรรม' : 'ไม่มีความเคลื่อนไหว')}</td><td>${esc(last)}</td></tr>`;
      return `<tr><td>${esc(row.student_name || 'ไม่ระบุชื่อ')}</td><td>${details}</td><td>${esc(last)}</td></tr>`;
    }).join('');
  }

  async function load() {
    const current = ++serial;
    body.innerHTML = `<tr><td colspan="${studentView ? 3 : 4}" style="text-align:center;padding:16px">กำลังโหลด...</td></tr>`;
    try {
      const params = new URLSearchParams({ days: String(days), page: String(page), q: query });
      const response = await fetch(`learning_progress_report_api.php?${params}`, { credentials: 'same-origin', cache: 'no-store' });
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.message || 'โหลดรายงานไม่สำเร็จ');
      if (current !== serial) return;
      renderRows(Array.isArray(data.rows) ? data.rows : []);
      const pagination = data.pagination || { page: 1, pages: 1, total: 1 };
      page = Number(pagination.page || 1);
      pages = Number(pagination.pages || 1);
      if (summary) summary.textContent = directorView
        ? `ผู้เรียน active ${Number(data.active_count || 0).toLocaleString('th-TH')} คน ใน ${days} วันที่ผ่านมา`
        : studentView ? `กิจกรรมการเรียนของคุณใน ${days} วันที่ผ่านมา`
          : `ผู้เรียน active ${Number(data.active_count || 0).toLocaleString('th-TH')} คน · นักเรียนที่ดูแล ${Number(pagination.total || 0).toLocaleString('th-TH')} คน`;
      if (pageInfo) pageInfo.textContent = `หน้า ${page} / ${pages} · ${Number(pagination.total || 0).toLocaleString('th-TH')} คน`;
      if (previous) previous.disabled = page <= 1;
      if (next) next.disabled = page >= pages;
    } catch (error) {
      if (current !== serial) return;
      body.innerHTML = `<tr><td colspan="${studentView ? 3 : 4}" style="text-align:center;color:#b91c1c;padding:16px">${esc(error.message || 'โหลดรายงานไม่สำเร็จ')}</td></tr>`;
      if (summary) summary.textContent = 'ไม่สามารถโหลดรายงานได้';
    }
  }

  document.querySelectorAll('[data-learning-days]').forEach(button => button.addEventListener('click', () => {
    days = [1, 3, 7].includes(Number(button.dataset.learningDays)) ? Number(button.dataset.learningDays) : 1;
    page = 1;
    document.querySelectorAll('[data-learning-days]').forEach(item => {
      const active = item === button;
      item.classList.toggle('is-active', active);
      item.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    load();
  }));

  search?.addEventListener('input', () => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => { query = search.value.trim(); page = 1; load(); }, 250);
  });
  previous?.addEventListener('click', () => { if (page > 1) { page -= 1; load(); } });
  next?.addEventListener('click', () => { if (page < pages) { page += 1; load(); } });
  load();
})();
