<?php
// Shared staff navigation and profile footer for all staff pages.
$sidebarName = (string) ($displayName ?? $_SESSION['name'] ?? $_SESSION['user_name'] ?? 'บัญชีเจ้าหน้าที่');
$sidebarRole = (string) ($displayRole ?? 'เจ้าหน้าที่');
$sidebarAvatar = (string) ($staffAvatarUrl ?? '');
$sidebarInitials = (string) ($avatarInitials ?? 'ST');
try {
    require_once __DIR__ . '/../config/db.php';
    $profileQuery = $conn->prepare('SELECT first_name, last_name, avatar_url FROM public.staff WHERE user_id = :id LIMIT 1');
    $profileQuery->execute([':id' => (string) ($_SESSION['user_id'] ?? '')]);
    $sidebarProfile = $profileQuery->fetch(PDO::FETCH_ASSOC) ?: [];
    $databaseName = trim((string) ($sidebarProfile['first_name'] ?? '') . ' ' . (string) ($sidebarProfile['last_name'] ?? ''));
    if ($databaseName !== '') $sidebarName = $databaseName;
    if ($sidebarAvatar === '') $sidebarAvatar = (string) ($sidebarProfile['avatar_url'] ?? '');
} catch (Throwable $error) {
    error_log('Staff sidebar profile lookup failed: ' . $error->getMessage());
}
if (!isset($avatarInitials) && trim($sidebarName) !== '') {
    $initialParts = preg_split('/\s+/u', trim($sidebarName)) ?: [];
    $sidebarInitials = '';
    foreach ($initialParts as $part) {
        $sidebarInitials .= mb_strtoupper(mb_substr($part, 0, 1, 'UTF-8'), 'UTF-8');
        if (mb_strlen($sidebarInitials, 'UTF-8') >= 2) break;
    }
    $sidebarInitials = $sidebarInitials ?: 'ST';
}
$sidebarInteractiveProfile = !empty($staffSidebarInteractiveProfile);
$sidebarActivePage = (string) ($staffSidebarActivePage ?? '');
$sidebarEscape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<nav class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <a class="brand-home-link sidebar-brand-link" href="staffdash.php" title="กลับหน้าหลักและรายวิชา">
      <img class="sidebar-brand-logo" src="images/flexible-learning-hub-logo-transparent.png" alt="Flexible Learning Hub">
    </a>
    <button class="sidebar-close" id="sidebarClose" type="button" title="ปิดเมนู">ปิด</button>
  </div>
  <div class="nav">
    <span class="nav-section-label">หลัก</span>
    <a class="nav-item <?= $sidebarActivePage === 'dashboard' ? 'active' : '' ?>" data-page="dashboard" href="staffdash.php?page=dashboard"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>แดชบอร์ด</a>
    <span class="nav-section-label">หลักสูตร</span>
    <a class="nav-item" data-page="curriculum-list" href="staffdash.php?page=curriculum-list"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>รายการหลักสูตร</a>
    <span class="nav-section-label">รายวิชา</span>
    <a class="nav-item" data-page="subject-list" href="staffdash.php?page=subject-list"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>รายการรายวิชา</a>
    <span class="nav-section-label">สมาชิก</span>
    <a class="nav-item" data-page="member-list" href="staffdash.php?page=member-list"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>จัดการผู้ใช้งาน</a>
    <a class="nav-item" data-page="staff-add" href="staffdash.php?page=staff-add"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><path d="M12 17v6m-3-3h6"/></svg>เพิ่มเจ้าหน้าที่</a>
    <span class="nav-section-label">สิทธิ์การใช้งาน</span>
    <a class="nav-item <?= $sidebarActivePage === 'permissions' ? 'active' : '' ?>" href="staff_transfer_permissions.php" <?= $sidebarActivePage === 'permissions' ? 'aria-current="page"' : '' ?>><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/><circle cx="12" cy="16" r="1"/></svg>จัดการสิทธิ์เทียบโอน</a>
    <a class="nav-item" href="review_transcript.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h8"/></svg>ตรวจทรานสคริปต์</a>
    <a class="nav-item" data-page="graduation-requests" href="staffdash.php?page=graduation-requests"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16v16H4z"/><path d="m8 12 2.5 2.5L16 9M8 2v4M16 2v4"/></svg>อนุมัติจบการศึกษา</a>
  </div>
  <a href="logout.php" class="sidebar-logout">ออกจากระบบ</a>
  <div class="sidebar-footer<?= $sidebarInteractiveProfile ? ' staff-profile-trigger' : '' ?>" <?= $sidebarInteractiveProfile ? 'role="button" tabindex="0" onclick="openStaffProfile()" onkeydown="if(event.key===\'Enter\'||event.key===\' \'){event.preventDefault();openStaffProfile()}" title="ดูหรือแก้ไขโปรไฟล์"' : '' ?>>
    <?php if ($sidebarInteractiveProfile): ?><input type="file" id="staffAvatarInput" accept="image/*" style="display:none" onchange="staffPreviewAvatar(this)"><?php endif; ?>
    <div class="staff-avatar-wrap">
      <div class="user-avatar"<?= $sidebarInteractiveProfile ? ' id="staffAvatarCircle"' : '' ?> style="width:40px;height:40px;overflow:hidden;padding:0;flex-shrink:0;">
        <img<?= $sidebarInteractiveProfile ? ' id="staffAvatarImg"' : '' ?> src="<?= $sidebarEscape($sidebarAvatar) ?>" alt="" style="<?= $sidebarAvatar !== '' ? 'display:block;' : 'display:none;' ?>width:100%;height:100%;object-fit:cover;border-radius:50%;">
        <span<?= $sidebarInteractiveProfile ? ' id="staffAvatarInitial"' : '' ?> style="<?= $sidebarAvatar !== '' ? 'display:none;' : '' ?>"><?= $sidebarEscape($sidebarInitials) ?></span>
      </div>
      <?php if ($sidebarInteractiveProfile): ?><button type="button" class="staff-avatar-edit" aria-label="เปลี่ยนรูปโปรไฟล์" title="เปลี่ยนรูป" onclick="event.stopPropagation();document.getElementById('staffAvatarInput').click()" onkeydown="event.stopPropagation()">✎</button><?php endif; ?>
    </div>
    <div class="user-detail"><span class="user-name"><?= $sidebarEscape($sidebarName) ?></span><span class="user-role"><?= $sidebarEscape($sidebarRole) ?></span></div>
  </div>
</nav>
