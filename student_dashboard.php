<?php
session_start();
require_once 'db_connect.php'; 

// 1. เธ•เธฃเธงเธเธชเธญเธเธงเนเธฒเน€เธเนเธ 'student' เธซเธฃเธทเธญเนเธกเน
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit;
}

$student_id = $_SESSION['user_id']; // เธชเธณเธซเธฃเธฑเธเธเธฑเธเน€เธฃเธตเธขเธ ID เธเธทเธญ user_id เน€เธฅเธข
$student_name = $_SESSION['name'] ?? 'เธเธฑเธเน€เธฃเธตเธขเธ';

try {
    // 2. เธ”เธถเธเธเนเธญเธกเธนเธฅเธเธฑเธเน€เธฃเธตเธขเธ (เธ•เธฒเธฃเธฒเธ 3.2) [cite: 1148]
    $stmt = $conn->prepare("SELECT * FROM public.student WHERE student_id = :id");
    $stmt->execute(['id' => $student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        $student = [
            'student_id' => $student_id,
            'student_level' => '-',
        ];
    }

    // 3. เธ”เธถเธเธเธฐเนเธเธเธชเธญเธ (เธ•เธฒเธฃเธฒเธ 3.15) [cite: 1178]
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
            <h1 class="title">เนเธเธเธเธงเธเธเธธเธกเธเธฑเธเน€เธฃเธตเธขเธ</h1>
            <p class="subtitle">เธขเธดเธเธ”เธตเธ•เนเธญเธเธฃเธฑเธเธเธธเธ“ <?php echo htmlspecialchars($student_name); ?></p>
            <div style="margin-top: 20px; color: var(--text-secondary);">
                <p><strong>เธฃเธซเธฑเธชเธเธฃเธฐเธเธณเธ•เธฑเธง:</strong> <?php echo htmlspecialchars($student['student_id']); ?></p>
                <p><strong>เธฃเธฐเธ”เธฑเธเธเธฑเนเธ:</strong> <?php echo htmlspecialchars($student['student_level']); ?></p>
            </div>
            <a href="logout.php" class="link" style="display: block; margin-top: 20px;">เธญเธญเธเธเธฒเธเธฃเธฐเธเธ</a>
        </div>
    </div>
</body>
</html>

