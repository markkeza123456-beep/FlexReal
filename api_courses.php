<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php'; // ต้องแน่ใจว่าชื่อไฟล์เชื่อมต่อฐานข้อมูลตรงกับของคุณนะครับ

$action = $_GET['action'] ?? 'get_all';

try {
    if ($action === 'get_all') {
        // ดึงวิชาทั้งหมด
        $stmt = $conn->query("
            SELECT
                s.subjects_id,
                s.subjects_name,
                s.subjects_description,
                s.teachers_id,
                t.teachers_name,
                COALESCE(COUNT(l.lessons_id), 0) AS lesson_count
            FROM subjects s
            LEFT JOIN teachers t ON t.teachers_id = s.teachers_id
            LEFT JOIN lessons l ON l.subjects_id = s.subjects_id
            GROUP BY s.subjects_id, s.subjects_name, s.subjects_description, s.teachers_id, t.teachers_name
            ORDER BY s.subjects_name
        ");
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'data' => $courses]);
        
    } elseif ($action === 'get_detail') {
        // ดึงรายละเอียด 1 วิชา
        $id = $_GET['id'] ?? '';
        
        $stmt = $conn->prepare("
            SELECT
                s.Subjects_ID,
                s.Subjects_Name,
                s.Subjects_Description,
                s.teachers_id,
                t.teachers_name,
                COALESCE(COUNT(l2.lessons_id), 0) AS lesson_count
            FROM Subjects s
            LEFT JOIN teachers t ON t.teachers_id = s.teachers_id
            LEFT JOIN lessons l2 ON l2.subjects_id = s.subjects_id
            WHERE s.Subjects_ID = ?
            GROUP BY s.Subjects_ID, s.Subjects_Name, s.Subjects_Description, s.teachers_id, t.teachers_name
        ");
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
