<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$staffRole = strtolower(trim((string) ($_SESSION['role'] ?? '')));
if (empty($_SESSION['user_id']) || $staffRole !== 'staff') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/db.php';
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
  <link rel="stylesheet" href="assets/css/staffdash.css?v=20261021-login-shared-background" />
  <link rel="stylesheet" href="assets/css/theme.css?v=20261021-user-sidebar-logo-left-nudge" />
  <script src="assets/js/staff_transfer_permissions.js" defer></script>
    <link rel="stylesheet" href="assets/css/responsive.css">
</head>
<body>
  <div class="bg-grid"></div>
  <div class="glow-orb orb-1"></div>
  <div class="glow-orb orb-2"></div>

  <div class="dashboard-container">
  <?php $staffSidebarActivePage = "permissions"; include __DIR__ . "/components/staff_sidebar.php"; ?>

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
  </div>
  <div class="toast" id="permissionToast" role="status" aria-live="polite"></div>
  <script>window.transferPermissionCsrf = <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="assets/js/responsive.js?v=20261021-responsive" defer></script>
</body>
</html>
