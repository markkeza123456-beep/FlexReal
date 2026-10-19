<?php
session_start();
$staffRole = strtolower(trim((string) ($_SESSION['role'] ?? '')));
if (empty($_SESSION['user_id']) || $staffRole !== 'staff') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db_connect.php';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = (string) $_SESSION['csrf_token'];

try {
    $teachersStmt = $conn->query(
        "SELECT t.user_id, t.full_name, t.email,
                CASE WHEN COALESCE(latest_permission.is_active, FALSE) THEN 1 ELSE 0 END AS has_transfer_permission
         FROM public.teachers t
         JOIN public.users u ON u.user_id = t.user_id AND u.role = 'teacher'
         LEFT JOIN LATERAL (
             SELECT p.is_active
             FROM public.transfer_permissions p
             WHERE p.instructor_id = t.user_id
             ORDER BY p.granted_at DESC, p.permission_id DESC
             LIMIT 1
         ) latest_permission ON TRUE
         ORDER BY t.full_name ASC, t.user_id ASC"
    );
    $teachers = $teachersStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Staff transfer permission page failed: ' . $e->getMessage());
    http_response_code(503);
    $teachers = null;
}

function hTransferPermission(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>จัดการสิทธิ์เทียบโอน | Flexible Learning Hub</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@600;700;800&family=IBM+Plex+Sans+Thai:wght@300;400;500;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="staffdash.css?v=20261019-transfer-permissions" />
  <link rel="stylesheet" href="theme.css" />
  <script src="staff_transfer_permissions.js" defer></script>
</head>
<body>
  <div class="bg-grid"></div>
  <div class="glow-orb orb-1"></div>
  <div class="glow-orb orb-2"></div>

  <nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <a class="brand-home-link" href="staffdash.php">
        <div class="flex-brand-mark" aria-hidden="true"><svg viewBox="0 0 64 64" fill="none"><polygon points="32,6 53,18 53,42 32,56 11,42 11,18" stroke="#f07f37" stroke-width="5"/><polygon points="32,15 45,23 45,39 32,47 19,39 19,23" fill="#f4e8db"/></svg></div>
        <div class="flex-brand-text"><span class="flex-brand-name">FLEXIBLE</span><span class="flex-brand-sub">LEARNING HUB</span></div>
      </a>
      <button class="sidebar-close" id="sidebarClose" type="button" title="ปิดเมนู">ปิด</button>
    </div>

    <div class="nav">
      <span class="nav-section-label">หลัก</span>
      <a class="nav-item" href="staffdash.php?page=dashboard">แดชบอร์ด</a>
      <span class="nav-section-label">หลักสูตร</span>
      <a class="nav-item" href="staffdash.php?page=curriculum-list">รายการหลักสูตร</a>
      <span class="nav-section-label">รายวิชา</span>
      <a class="nav-item" href="staffdash.php?page=subject-list">รายการรายวิชา</a>
      <span class="nav-section-label">สมาชิก</span>
      <a class="nav-item" href="staffdash.php?page=member-list">จัดการผู้ใช้งาน</a>
      <a class="nav-item" href="staffdash.php?page=staff-add">เพิ่มเจ้าหน้าที่</a>
      <span class="nav-section-label">สิทธิ์การใช้งาน</span>
      <a class="nav-item active" href="staff_transfer_permissions.php" aria-current="page">จัดการสิทธิ์เทียบโอน</a>
    </div>

    <div class="sidebar-footer">
      <div class="user-avatar" aria-hidden="true">ST</div>
      <div class="user-detail"><span class="user-name"><?= hTransferPermission((string) ($_SESSION['name'] ?? 'บัญชีเจ้าหน้าที่')) ?></span><span class="user-role">เจ้าหน้าที่</span></div>
      <a class="btn-logout" href="logout.php">ออกจากระบบ</a>
    </div>
  </nav>

  <div class="main-wrap">
    <header class="topbar">
      <button class="menu-btn" id="menuBtn" type="button" title="เปิดเมนู">เมนู</button>
      <span class="topbar-title">จัดการสิทธิ์เทียบโอน</span>
    </header>
    <main class="content">
      <section class="page active">
        <div class="page-header">
          <div>
            <div class="page-title">จัดการสิทธิ์เทียบโอนหน่วยกิต</div>
            <div class="page-sub">กำหนดว่าอาจารย์คนใดสามารถเข้าถึงหน้าจัดการคำขอเทียบโอนได้</div>
          </div>
        </div>

        <div class="panel" style="padding:20px;">
          <div class="table-wrap">
            <?php if ($teachers === null): ?>
              <p class="permission-empty">โหลดรายชื่ออาจารย์ไม่สำเร็จ กรุณาลองใหม่ภายหลัง</p>
            <?php elseif ($teachers === []): ?>
              <p class="permission-empty">ไม่พบรายชื่ออาจารย์ในระบบ</p>
            <?php else: ?>
              <table class="data-table">
                <thead><tr><th>รหัสอาจารย์</th><th>ชื่ออาจารย์</th><th>อีเมล</th><th>สิทธิ์เทียบโอน</th><th>จัดการ</th></tr></thead>
                <tbody>
                <?php foreach ($teachers as $teacher): $isEnabled = filter_var($teacher['has_transfer_permission'], FILTER_VALIDATE_BOOLEAN); ?>
                  <tr data-teacher-row="<?= hTransferPermission((string) $teacher['user_id']) ?>" data-enabled="<?= $isEnabled ? '1' : '0' ?>">
                    <td class="mono"><?= hTransferPermission((string) $teacher['user_id']) ?></td>
                    <td><?= hTransferPermission((string) ($teacher['full_name'] ?? '')) ?></td>
                    <td><?= hTransferPermission((string) ($teacher['email'] ?? '-')) ?></td>
                    <td><span class="permission-state <?= $isEnabled ? 'is-enabled' : 'is-disabled' ?>" data-permission-state><?= $isEnabled ? 'มีสิทธิ์' : 'ไม่มีสิทธิ์' ?></span></td>
                    <td>
                      <button type="button" class="permission-action <?= $isEnabled ? 'is-revoke' : 'is-grant' ?>" data-permission-action="<?= $isEnabled ? 'revoke' : 'grant' ?>">
                        <?= $isEnabled ? 'ดึงสิทธิ์กลับ' : 'ให้สิทธิ์' ?>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </div>
      </section>
    </main>
  </div>
  <div class="toast" id="permissionToast" role="status" aria-live="polite"></div>
  <script>window.transferPermissionCsrf = <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
</body>
</html>
