<?php
require_once __DIR__ . '/check_auth.php';

function hTransfer(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

try {
    $requestsStmt = $conn->query(
        'SELECT user_id, course_id, document_url
         FROM public.credit_transfers
         ORDER BY course_id, user_id'
    );
    $requests = $requestsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Credit transfer request list failed: ' . $e->getMessage());
    http_response_code(503);
    $requests = null;
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>คำขอเทียบโอนหน่วยกิต - Flexible Learning Hub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/teacherdash.css?v=20260916-hidden-video-list">
    <link rel="stylesheet" href="assets/css/theme.css?v=20261020-soft-orange-theme">
    <style>
        .transfer-content { max-width: 1100px; margin: 0 auto; padding: 36px; }
        .transfer-content h1 { margin: 0 0 8px; }
        .transfer-content .intro { margin: 0 0 24px; color: var(--text-dim); }
        .transfer-panel { overflow-x: auto; padding: 20px; border: 1px solid var(--border); border-radius: var(--radius); background: var(--bg2); }
        .transfer-table { width: 100%; border-collapse: collapse; text-align: left; }
        .transfer-table th, .transfer-table td { padding: 12px; border-bottom: 1px solid var(--border); vertical-align: top; }
        .transfer-table th { color: var(--text-dim); font-weight: 600; }
        .transfer-table a { color: var(--orange); overflow-wrap: anywhere; }
        .transfer-empty, .transfer-error { padding: 24px; color: var(--text-dim); text-align: center; }
        @media (max-width: 700px) { .transfer-content { padding: 24px 16px; } }
    </style>
</head>
<body>
<aside class="sidebar">
    <a class="sidebar-logo" href="teacherdash.php">
        <div><div class="logo-name">FLEXIBLE</div><div class="logo-sub">LEARNING HUB</div></div>
    </a>
    <nav class="sidebar-nav">
        <a href="teacherdash.php" class="nav-item"><span class="nav-icon"></span><span>แดชบอร์ด</span></a>
        <a href="teacherdash.php" class="nav-item"><span class="nav-icon"></span><span>บทเรียน</span></a>
        <a href="teacherdash.php" class="nav-item"><span class="nav-icon"></span><span>ออกรายงาน</span></a>
        <a href="teacher_credit_transfers.php" class="nav-item active"><span class="nav-icon"></span><span>เทียบโอนหน่วยกิต</span></a>
    </nav>
    <a href="logout.php" class="sidebar-logout"><span></span><span>ออกจากระบบ</span></a>
</aside>

<main class="main">
    <div class="transfer-content">
        <h1>คำขอเทียบโอนหน่วยกิต</h1>
        <p class="intro">รายการคำขอที่นักศึกษาส่งเข้ามา</p>
        <section class="transfer-panel" aria-live="polite">
            <?php if ($requests === null): ?>
                <p class="transfer-error">โหลดคำขอไม่สำเร็จ กรุณาลองใหม่ภายหลัง</p>
            <?php elseif ($requests === []): ?>
                <p class="transfer-empty">ยังไม่มีคำขอเทียบโอน</p>
            <?php else: ?>
                <table class="transfer-table">
                    <thead><tr><th>รหัสนักศึกษา</th><th>รหัสรายวิชา</th><th>เอกสารประกอบ</th></tr></thead>
                    <tbody>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td><?= hTransfer((string) $request['user_id']) ?></td>
                            <td><?= hTransfer((string) $request['course_id']) ?></td>
                            <td><a href="<?= hTransfer((string) $request['document_url']) ?>" target="_blank" rel="noopener noreferrer">เปิดเอกสาร</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </div>
</main>
</body>
</html>
