<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (strtolower((string) ($_SESSION['role'] ?? '')) !== 'director' || empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/db.php';
$userId = (string) $_SESSION['user_id'];
$profileQuery = $conn->prepare('SELECT s.first_name, s.last_name, s.email, s.phone, s.avatar_url,
    a.address_line, a.subdistrict, a.district, a.province, a.postal_code
    FROM public.staff s
    LEFT JOIN LATERAL (
      SELECT address_line, subdistrict, district, province, postal_code
      FROM public.user_addresses WHERE user_id = s.user_id
      ORDER BY is_primary DESC, address_id ASC LIMIT 1
    ) a ON TRUE
    WHERE s.user_id = :user_id LIMIT 1');
$profileQuery->execute([':user_id' => $userId]);
$profile = $profileQuery->fetch(PDO::FETCH_ASSOC) ?: [];
$firstName = (string) ($profile['first_name'] ?? '');
$lastName = (string) ($profile['last_name'] ?? '');
$avatarUrl = trim((string) ($profile['avatar_url'] ?? ''));
$escape = static fn($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$initial = mb_substr(trim($firstName . ' ' . $lastName) ?: 'ผ', 0, 1, 'UTF-8');
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ข้อมูลส่วนตัว | Flexible Learning Hub</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/theme.css?v=20261022-mobile-layout-v2">
  <link rel="stylesheet" href="assets/css/director_dash.css?v=20261022-director-profile-readonly-colors">
  <link rel="stylesheet" href="assets/css/responsive.css?v=20261022-sidebar-menu-fix">
</head>
<body class="director-dashboard">
  <header class="director-topbar">
    <a class="director-brand" href="director_dash.php" aria-label="กลับหน้าแดชบอร์ด"><img src="images/flexible-learning-hub-logo-transparent.png" alt="Flexible Learning Hub"></a>
    <div class="director-account">
      <details class="director-profile-menu">
        <summary><span class="director-avatar" aria-hidden="true"><?php if ($avatarUrl !== ''): ?><img src="<?= $escape($avatarUrl) ?>" alt=""><?php else: ?><?= $escape($initial) ?><?php endif; ?></span><strong><?= $escape(trim($firstName . ' ' . $lastName) ?: 'ผู้บริหาร') ?></strong></summary>
        <div class="director-profile-dropdown"><a class="director-menu-item" href="director_profile.php">ข้อมูลส่วนตัว</a><a class="director-menu-logout" href="logout.php">ออกจากระบบ</a></div>
      </details>
    </div>
  </header>
  <main class="director-main director-profile-main">
    <section class="director-heading"><div><h1>ข้อมูลส่วนตัว</h1><p>จัดการข้อมูลโปรไฟล์ของผู้บริหาร</p></div></section>
    <div class="director-profile-layout">
      <section class="director-profile-card">
        <h2 class="director-profile-card-title"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg> โปรไฟล์ผู้บริหาร</h2>
        <div class="director-profile-identity"><span class="director-profile-large-avatar"><img id="directorProfileAvatar" src="<?= $escape($avatarUrl) ?>" alt="รูปโปรไฟล์" <?= $avatarUrl === '' ? 'hidden' : '' ?>><span id="directorProfileInitial" <?= $avatarUrl !== '' ? 'hidden' : '' ?>><?= $escape($initial) ?></span><button class="director-avatar-change" id="directorAvatarChange" type="button" aria-label="เปลี่ยนรูปโปรไฟล์" title="เปลี่ยนรูปโปรไฟล์"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 4h-4l-2 3H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3z"/><circle cx="12" cy="13" r="3"/></svg></button><input id="directorAvatarFile" type="file" accept="image/*" hidden></span><div><h2><?= $escape(trim($firstName . ' ' . $lastName) ?: 'ผู้บริหาร') ?></h2><p>ผู้บริหาร</p><p class="director-avatar-feedback" id="directorAvatarFeedback" role="status" aria-live="polite"></p></div></div>
        <form id="directorProfileForm">
        <input type="hidden" name="user_id" value="<?= $escape($userId) ?>">
        <div class="director-profile-fields">
          <label><span>ชื่อ</span><input name="first_name" required maxlength="100" value="<?= $escape($firstName) ?>"></label>
          <label><span>นามสกุล</span><input name="last_name" required maxlength="100" value="<?= $escape($lastName) ?>"></label>
          <label><span>เลขบัตรประจำตัวประชาชน</span><input class="director-profile-readonly" value="<?= $escape($userId) ?>" readonly aria-readonly="true"></label>
          <label><span>อีเมล</span><input class="director-profile-readonly" name="email" type="email" maxlength="254" value="<?= $escape($profile['email'] ?? '') ?>" readonly aria-readonly="true"></label>
          <label><span>เบอร์โทรศัพท์</span><input class="director-profile-readonly" name="phone" type="tel" maxlength="30" value="<?= $escape($profile['phone'] ?? '') ?>" readonly aria-readonly="true"></label>
        </div>
        <p class="director-profile-feedback" id="directorProfileFeedback" role="status" aria-live="polite"></p>
        <div class="director-profile-actions"><button class="director-profile-save" type="submit">บันทึกข้อมูล</button></div>
        </form>
      </section>
      <form id="directorPasswordForm" class="director-profile-card director-password-card">
        <h2 class="director-profile-card-title"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg> เปลี่ยนรหัสผ่าน</h2>
        <p class="director-password-hint">เว้นว่างไว้หากไม่ต้องการเปลี่ยนรหัสผ่าน</p>
        <div class="director-profile-fields director-password-grid">
          <label><span>รหัสผ่านปัจจุบัน</span><span class="director-password-input"><input name="pwd_current" type="password" autocomplete="current-password"><button type="button" class="director-password-toggle" aria-label="แสดงรหัสผ่าน" title="แสดงรหัสผ่าน"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></span></label>
          <label><span>รหัสผ่านใหม่</span><span class="director-password-input"><input name="pwd_new" type="password" minlength="6" autocomplete="new-password"><button type="button" class="director-password-toggle" aria-label="แสดงรหัสผ่าน" title="แสดงรหัสผ่าน"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></span></label>
          <label><span>ยืนยันรหัสผ่านใหม่</span><span class="director-password-input"><input name="pwd_confirm" type="password" minlength="6" autocomplete="new-password"><button type="button" class="director-password-toggle" aria-label="แสดงรหัสผ่าน" title="แสดงรหัสผ่าน"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></span></label>
        </div>
        <p class="director-profile-feedback" id="directorPasswordFeedback" role="status" aria-live="polite"></p>
        <div class="director-profile-actions"><button class="director-profile-save" type="submit">บันทึกรหัสผ่าน</button></div>
      </form>
    </div>
  </main>
  <script>
    const directorAvatarInput = document.getElementById('directorAvatarFile');
    const directorAvatarFeedback = document.getElementById('directorAvatarFeedback');
    document.getElementById('directorAvatarChange').addEventListener('click', () => directorAvatarInput.click());
    directorAvatarInput.addEventListener('change', async () => {
      const file = directorAvatarInput.files?.[0];
      directorAvatarInput.value = '';
      if (!file) return;
      if (!file.type.startsWith('image/')) {
        directorAvatarFeedback.textContent = 'กรุณาเลือกไฟล์รูปภาพ';
        directorAvatarFeedback.dataset.state = 'error';
        return;
      }
      if (file.size > 10 * 1024 * 1024) {
        directorAvatarFeedback.textContent = 'ขนาดรูปต้องไม่เกิน 10 MB';
        directorAvatarFeedback.dataset.state = 'error';
        return;
      }
      directorAvatarFeedback.textContent = 'กำลังบันทึกรูปโปรไฟล์...';
      directorAvatarFeedback.dataset.state = 'pending';
      try {
        const image = new Image();
        const imageData = await new Promise((resolve, reject) => {
          image.onload = () => resolve(image);
          image.onerror = () => reject(new Error('เปิดไฟล์รูปภาพไม่สำเร็จ'));
          image.src = URL.createObjectURL(file);
        });
        const side = Math.min(imageData.naturalWidth, imageData.naturalHeight);
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = 300;
        canvas.getContext('2d').drawImage(imageData, (imageData.naturalWidth - side) / 2, (imageData.naturalHeight - side) / 2, side, side, 0, 0, 300, 300);
        URL.revokeObjectURL(image.src);
        const response = await fetch('uploadavatar_staff.php', {
          method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
          body: JSON.stringify({ image: canvas.toDataURL('image/png') })
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'บันทึกรูปไม่สำเร็จ');
        const profileAvatar = document.getElementById('directorProfileAvatar');
        profileAvatar.src = result.url;
        profileAvatar.hidden = false;
        document.getElementById('directorProfileInitial').hidden = true;
        const topbarAvatar = document.querySelector('.director-profile-menu .director-avatar');
        topbarAvatar.replaceChildren(Object.assign(document.createElement('img'), { src: result.url, alt: '' }));
        directorAvatarFeedback.textContent = 'เปลี่ยนรูปโปรไฟล์เรียบร้อยแล้ว';
        directorAvatarFeedback.dataset.state = 'success';
      } catch (error) {
        directorAvatarFeedback.textContent = error.message || 'บันทึกรูปไม่สำเร็จ กรุณาลองใหม่';
        directorAvatarFeedback.dataset.state = 'error';
      }
    });

    document.querySelectorAll('.director-password-toggle').forEach((button) => {
      button.addEventListener('click', () => {
        const input = button.parentElement.querySelector('input');
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        const label = show ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน';
        button.setAttribute('aria-label', label);
        button.title = label;
        button.innerHTML = show
          ? '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18M10.6 10.6a2 2 0 002.8 2.8M9.9 5.2A10.8 10.8 0 0112 5c6.4 0 10 7 10 7a14.7 14.7 0 01-3 3.8M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7c1.4 0 2.7-.3 3.8-.8"/></svg>'
          : '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';
      });
    });

    document.getElementById('directorProfileForm').addEventListener('submit', async (event) => {
      event.preventDefault();
      const form = event.currentTarget;
      const feedback = document.getElementById('directorProfileFeedback');
      const button = form.querySelector('button[type="submit"]');
      const data = new FormData(form);
      button.disabled = true;
      feedback.textContent = 'กำลังบันทึกข้อมูล...';
      feedback.dataset.state = 'pending';
      try {
        const response = await fetch('update_staff_profile.php', { method: 'POST', body: data, credentials: 'same-origin' });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'บันทึกข้อมูลไม่สำเร็จ');
        feedback.textContent = result.message || 'บันทึกข้อมูลเรียบร้อยแล้ว';
        feedback.dataset.state = 'success';
        const name = `${data.get('first_name')} ${data.get('last_name')}`.trim();
        document.querySelector('.director-profile-identity h2').textContent = name;
        document.querySelector('.director-profile-menu summary strong').textContent = name;
      } catch (error) {
        feedback.textContent = error.message || 'บันทึกข้อมูลไม่สำเร็จ';
        feedback.dataset.state = 'error';
      } finally {
        button.disabled = false;
      }
    });

    document.getElementById('directorPasswordForm').addEventListener('submit', async (event) => {
      event.preventDefault();
      const form = event.currentTarget;
      const feedback = document.getElementById('directorPasswordFeedback');
      const button = form.querySelector('button[type="submit"]');
      const data = new FormData(form);
      if (!data.get('pwd_current') || !data.get('pwd_new') || !data.get('pwd_confirm')) {
        feedback.textContent = 'กรุณากรอกรหัสผ่านให้ครบทุกช่อง';
        feedback.dataset.state = 'error';
        return;
      }
      if (data.get('pwd_new') !== data.get('pwd_confirm')) {
        feedback.textContent = 'รหัสผ่านใหม่และการยืนยันไม่ตรงกัน';
        feedback.dataset.state = 'error';
        return;
      }
      const profileData = new FormData(document.getElementById('directorProfileForm'));
      data.set('user_id', <?= json_encode($userId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
      ['first_name', 'last_name', 'email', 'phone', 'address_line', 'subdistrict', 'district', 'province', 'postal_code'].forEach((field) => {
        data.set(field, profileData.get(field) || '');
      });
      button.disabled = true;
      feedback.textContent = 'กำลังบันทึกรหัสผ่าน...';
      feedback.dataset.state = 'pending';
      try {
        const response = await fetch('update_staff_profile.php', { method: 'POST', body: data, credentials: 'same-origin' });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'บันทึกรหัสผ่านไม่สำเร็จ');
        feedback.textContent = result.message || 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว';
        feedback.dataset.state = 'success';
        form.reset();
        form.querySelectorAll('.director-password-toggle').forEach((toggle) => {
          toggle.setAttribute('aria-label', 'แสดงรหัสผ่าน');
          toggle.title = 'แสดงรหัสผ่าน';
          toggle.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';
        });
      } catch (error) {
        feedback.textContent = error.message || 'บันทึกรหัสผ่านไม่สำเร็จ';
        feedback.dataset.state = 'error';
      } finally {
        button.disabled = false;
      }
    });
  </script>
</body>
</html>
