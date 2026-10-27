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
      body.innerHTML = '<tr><td class="learning-timeline-empty">ไม่พบข้อมูลในช่วงเวลานี้</td></tr>';
      return;
    }
    body.innerHTML = rows.map(row => {
      const activities = Array.isArray(row.activities) ? row.activities : [];
      if (!activities.length) return `<tr><td class="learning-timeline-empty">${studentView ? '' : `<strong>${esc(row.student_name || 'ไม่ระบุชื่อ')} — </strong>`}ไม่มีความเคลื่อนไหวในช่วงนี้</td></tr>`;
      return activities.map((activity, index) => {
        const date = new Date(activity.occurred_at);
        const time = Number.isNaN(date.getTime()) ? '—' : dateFormat.format(date);
        const title = esc(activity.activity_title || 'กิจกรรม');
        const student = !studentView && index === 0 ? `<strong class="learning-timeline-student">${esc(row.student_name || 'ไม่ระบุชื่อ')}</strong>` : '';
        const attempt = Number(activity.attempt_number || 0);
        let label = '';
        let badge = '';
        if (activity.activity_type === 'lesson') {
          label = `เข้าเรียน: ${title}`;
          badge = activity.is_completed ? '<span class="learning-badge is-passed">สำเร็จ</span>' : '<span class="learning-badge is-lesson">เข้าเรียน</span>';
        } else if (activity.activity_type === 'quiz_start') {
          label = `เริ่มทำแบบทดสอบ: ${title} (รอบที่ ${attempt})`;
          badge = '<span class="learning-badge is-started">เริ่มทำ</span>';
        } else {
          const passed = activity.result_status === 'passed';
          const pending = activity.result_status === 'pending_review';
          const retry = attempt > 1 ? ' - ทำใหม่' : '';
          const resultLabel = passed ? 'ผ่าน' : pending ? 'รอตรวจ' : `ไม่ผ่าน${retry}`;
          label = `ส่งแบบทดสอบ: ${title} (รอบที่ ${attempt}) — คะแนน ${Number(activity.score || 0)}/${Number(activity.total_score || 0)} (${resultLabel})`;
          badge = passed ? '<span class="learning-badge is-passed">ผ่าน</span>' : pending ? '<span class="learning-badge is-started">รอตรวจ</span>' : `<span class="learning-badge is-failed">ไม่ผ่าน${retry}</span>`;
        }
        return `<tr><td class="learning-timeline-cell">${student}<time datetime="${esc(activity.occurred_at)}">${esc(time)}</time><span class="learning-timeline-text">— ${esc(label)}</span>${badge}</td></tr>`;
      }).join('');
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
      const rangeLabel = days === 1 ? 'วันนี้' : `${days} วันที่ผ่านมา`;
      if (summary) summary.textContent = directorView
        ? `ผู้เรียน active ${Number(data.active_count || 0).toLocaleString('th-TH')} คน · ${rangeLabel}`
        : studentView ? `กิจกรรมการเรียนของคุณ${days === 1 ? ' ' : 'ในช่วง '}${rangeLabel}`
          : `ผู้เรียน active ${Number(data.active_count || 0).toLocaleString('th-TH')} คน · นักเรียนที่ดูแล ${Number(pagination.total || 0).toLocaleString('th-TH')} คน · ${rangeLabel}`;
      if (pageInfo) pageInfo.textContent = `หน้า ${page} / ${pages} · ${Number(pagination.total || 0).toLocaleString('th-TH')} คน`;
      if (previous) previous.disabled = page <= 1;
      if (next) next.disabled = page >= pages;
    } catch (error) {
      if (current !== serial) return;
      body.innerHTML = `<tr><td class="learning-timeline-empty" style="color:#b91c1c">${esc(error.message || 'โหลดรายงานไม่สำเร็จ')}</td></tr>`;
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
