<?php
session_start();
require_once 'db_connect.php'; 

// 1. ตรวจสอบว่าเป็น 'student' หรือไม่
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit;
}

$student_id = $_SESSION['user_id']; // สำหรับนักเรียน ID คือ user_id เลย
$student_name = $_SESSION['name'] ?? 'นักเรียน';

try {
    // 2. ดึงข้อมูลนักเรียน (ตาราง 3.2) [cite: 1148]
    $stmt = $conn->prepare("SELECT * FROM public.student WHERE student_id = :id");
    $stmt->execute(['id' => $student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        $student = [
            'student_id' => $student_id,
            'student_level' => '-',
        ];
    }

    // 3. ดึงคะแนนสอบ (ตาราง 3.15) [cite: 1178]
    $stmt_test = $conn->prepare("SELECT * FROM public.test WHERE student_id = :id ORDER BY test_id DESC");
    $stmt_test->execute(['id' => $student_id]);
    $test_results = $stmt_test->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Student Dashboard - Nexora</title>
    <link rel="stylesheet" href="login.css">
</head>
<body>
    <div class="bg-grid"></div>
    <div class="container" style="max-width: 800px; margin-top: 50px;">
        <div class="card">
            <h1 class="title">แผงควบคุมนักเรียน</h1>
            <p class="subtitle">ยินดีต้อนรับคุณ <?php echo htmlspecialchars($student_name); ?></p>
            <div style="margin-top: 20px; color: var(--text-secondary);">
                <p><strong>รหัสประจำตัว:</strong> <?php echo htmlspecialchars($student['student_id']); ?></p>
                <p><strong>ระดับชั้น:</strong> <?php echo htmlspecialchars($student['student_level']); ?></p>
            </div>
            <a href="logout.php" class="link" style="display: block; margin-top: 20px;">ออกจากระบบ</a>
        </div>
    </div>
</body>
</html>
