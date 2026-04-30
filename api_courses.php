<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php'; // ต้องแน่ใจว่าชื่อไฟล์เชื่อมต่อฐานข้อมูลตรงกับของคุณนะครับ

$action = $_GET['action'] ?? 'get_all';

try {
    if ($action === 'get_all') {
        // ดึงวิชาทั้งหมด
        $stmt = $conn->query("SELECT subjects_id, subjects_name, subjects_description FROM subjects");
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'data' => $courses]);
        
    } elseif ($action === 'get_detail') {
        // ดึงรายละเอียด 1 วิชา
        $id = $_GET['id'] ?? '';
        
        $stmt = $conn->prepare("SELECT Subjects_ID, Subjects_Name, Subjects_Description FROM Subjects WHERE Subjects_ID = ?");
        $stmt->execute([$id]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt_lessons = $conn->prepare("SELECT Lessons_ID, Lessons_Name FROM Lessons WHERE Subjects_ID = ? ORDER BY Lessons_ID ASC");
        $stmt_lessons->execute([$id]);
        $lessons = $stmt_lessons->fetchAll(PDO::FETCH_ASSOC);

        if ($course) {
            echo json_encode(['status' => 'success', 'course' => $course, 'lessons' => $lessons]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบวิชานี้']);
        }
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>