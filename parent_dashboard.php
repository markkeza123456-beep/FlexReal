<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit();
}

$parent_name = $_SESSION['name'];
$student_id = $_SESSION['current_student_id'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Parent Dashboard</title>
    <link rel="stylesheet" href="login.css">
</head>
<body>
    <div class="bg-grid"></div>
    <div class="container" style="max-width: 600px; margin-top: 100px;">
        <div class="card">
            <h1 class="title">แผงควบคุมผู้ปกครอง</h1>
            <p class="subtitle">ยินดีต้อนรับคุณ <?php echo htmlspecialchars($parent_name); ?></p>
            <p style="color: var(--orange);">กำลังดูข้อมูลนักเรียนรหัส: <?php echo $student_id ? htmlspecialchars($student_id) : 'ยังไม่ผูกข้อมูล'; ?></p>
            <br>
            <a href="logout.php" class="link">ออกจากระบบ</a>
        </div>
    </div>
</body>
</html>