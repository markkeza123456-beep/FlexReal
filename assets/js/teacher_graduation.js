(() => {
  const message = document.getElementById('teacherGraduationMessage');
  const list = document.getElementById('teacherGraduationList');
  let csrfToken = '';
  let graduationRequests = [];
  let statusFilter = 'pending';

  if (!message || !list) return;

  const escapeHtml = value => {
    const node = document.createElement('span');
    node.textContent = value == null ? '' : String(value);
    return node.innerHTML;
  };

  const formatDate = value => {
    if (!value) return '-';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? String(value) : new Intl.DateTimeFormat('th-TH', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
  };

  async function request(url, options = {}) {
    const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', ...options });
    const data = await response.json();
    if (!response.ok || data.status !== 'success') throw new Error(data.message || 'ดำเนินการไม่สำเร็จ');
    return data;
  }

  function render() {
    const query = (document.getElementById('teacherGraduationSearch')?.value || '').trim().toLocaleLowerCase();
    const pendingCount = graduationRequests.filter(item => item.status === 'pending').length;
    const approvedCount = graduationRequests.filter(item => item.status === 'approved').length;
    const pendingCountElement = document.getElementById('teacherGraduationPendingCount');
    const approvedCountElement = document.getElementById('teacherGraduationApprovedCount');
    if (pendingCountElement) pendingCountElement.textContent = String(pendingCount);
    if (approvedCountElement) approvedCountElement.textContent = String(approvedCount);
    const requests = graduationRequests.filter(item => {
      if (!query && item.status !== statusFilter) return false;
      return !query || `${item.student_name || ''} ${item.student_id || ''}`.toLocaleLowerCase().includes(query);
    });
    message.hidden = requests.length > 0;
    message.textContent = requests.length ? '' : graduationRequests.length
      ? (query ? 'ไม่พบคำขอที่ตรงกับคำค้นหา' : statusFilter === 'pending' ? 'ไม่มีคำขอที่รออนุมัติ' : 'ยังไม่มีคำขอที่อนุมัติแล้ว')
      : 'ไม่มีคำขอจบการศึกษา';
    list.innerHTML = requests.map(item => {
      const courses = Array.isArray(item.courses) ? item.courses : [];
      const approved = item.status === 'approved';
      const hasPdf = Boolean(item.document_bucket && item.document_object_path);
      const retryPdf = approved && !hasPdf;
      const regeneratePdf = approved && hasPdf;
      const action = regeneratePdf ? 'regenerate_pdf' : retryPdf ? 'retry_pdf' : 'approve';
      const buttonText = regeneratePdf ? 'สร้าง PDF ฉบับใหม่' : retryPdf ? 'ลองออก PDF อีกครั้ง' : 'อนุมัติและออก PDF';
      const stateText = regeneratePdf
        ? 'อนุมัติแล้ว · มี PDF แล้ว สามารถสร้างใหม่ตามแบบล่าสุดได้'
        : retryPdf ? 'อนุมัติแล้ว · PDF ยังไม่พร้อม' : item.eligible ? 'ตรวจสอบครบทุกวิชาแล้ว' : 'ยังมีวิชาที่ไม่ผ่าน จึงอนุมัติไม่ได้';
      const statusBadge = approved
        ? `<span class="teacher-graduation-status is-approved">อนุมัติแล้ว${hasPdf ? '' : ' · PDF ยังไม่พร้อม'}</span>`
        : '<span class="teacher-graduation-status is-pending">รออนุมัติ</span>';
      const rows = courses.map(course => `<tr>
        <td>${escapeHtml(course.code || '-')}</td>
        <td>${escapeHtml(course.name || '-')}</td>
        <td>${escapeHtml(course.credits ?? 0)}</td>
        <td>${Number(course.transferred_lessons || 0) > 0 ? `TR · เทียบโอน (${escapeHtml(course.transfer_summary || '-')})` : escapeHtml(course.grade_label || '-')}</td>
      </tr>`).join('');
      return `<article class="teacher-graduation-card">
        <header class="teacher-graduation-heading"><div><h2>${escapeHtml(item.student_name || 'นักเรียน')}</h2><p>รหัส ${escapeHtml(item.student_id)} · ส่งเมื่อ ${escapeHtml(formatDate(item.submitted_at))} · ${statusBadge}</p></div><strong>${Number(item.total_credits || 0).toLocaleString('th-TH')} หน่วยกิต</strong></header>
        <div class="teacher-graduation-table-wrap"><table class="teacher-graduation-table"><thead><tr><th>รหัสวิชา</th><th>รายวิชา</th><th>หน่วยกิต</th><th>ผลการเรียน</th></tr></thead><tbody>${rows || '<tr><td colspan="4">ไม่พบรายวิชา</td></tr>'}</tbody></table></div>
        <footer class="teacher-graduation-actions"><span class="${!item.eligible && !approved ? 'teacher-graduation-ineligible' : ''}">${stateText}</span><button type="button" data-action="${action}" data-request-id="${escapeHtml(item.request_id)}" ${!item.eligible && !approved ? 'disabled' : ''}>${buttonText}</button></footer>
      </article>`;
    }).join('');
  }

  async function load() {
    message.hidden = false;
    message.textContent = 'กำลังโหลดคำขอ...';
    try {
      const data = await request('staff_graduation_api.php');
      csrfToken = String(data.csrf_token || '');
      graduationRequests = Array.isArray(data.requests) ? data.requests : [];
      render();
    } catch (error) {
      message.hidden = false;
      message.textContent = error.message || 'ไม่สามารถโหลดคำขอได้';
      list.innerHTML = '';
    }
  }

  document.querySelectorAll('[data-teacher-graduation-filter]').forEach(button => {
    button.addEventListener('click', () => {
      statusFilter = button.dataset.teacherGraduationFilter || 'pending';
      document.querySelectorAll('[data-teacher-graduation-filter]').forEach(tab => tab.classList.toggle('active', tab === button));
      render();
    });
  });
  document.getElementById('teacherGraduationSearch')?.addEventListener('input', render);

  list.addEventListener('click', async event => {
    const button = event.target.closest('button[data-action]');
    if (!button || button.disabled) return;
    const action = button.dataset.action;
    const prompt = action === 'approve'
      ? 'ยืนยันอนุมัติจบการศึกษาและออกใบสรุปผลการเรียน PDF?'
      : action === 'regenerate_pdf'
        ? 'ยืนยันสร้างใบสรุปผลการเรียน PDF ฉบับใหม่แทนไฟล์เดิม?'
        : 'ยืนยันลองสร้าง PDF สำหรับคำขอที่อนุมัติแล้วอีกครั้ง?';
    if (!window.confirm(prompt)) return;
    button.disabled = true;
    const form = new FormData();
    form.append('action', action);
    form.append('request_id', button.dataset.requestId);
    form.append('csrf_token', csrfToken);
    try {
      const result = await request('staff_graduation_api.php', { method: 'POST', body: form });
      message.hidden = false;
      message.textContent = result.message || 'บันทึกเรียบร้อย';
      if (action === 'approve') {
        statusFilter = 'approved';
        document.querySelectorAll('[data-teacher-graduation-filter]').forEach(tab => tab.classList.toggle('active', tab.dataset.teacherGraduationFilter === 'approved'));
      }
      await load();
    } catch (error) {
      button.disabled = false;
      message.hidden = false;
      message.textContent = error.message || 'ดำเนินการไม่สำเร็จ';
    }
  });

  window.loadTeacherGraduationRequests = load;
})();
