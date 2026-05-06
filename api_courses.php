<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

try {
    // 1. ดึงรายวิชาทั้งหมด (หน้าแรก)
    if ($action === 'get_all') {
        $stmt = $conn->query("SELECT subjects_id, subjects_name, subjects_description FROM public.subjects ORDER BY subjects_name ASC");
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'data' => $courses]);
    }
    // 2. ดึงรายละเอียดวิชา และ บทเรียนย่อย
    elseif ($action === 'get_detail') {
        $id = $_GET['id'] ?? '';
        
        $stmt = $conn->prepare("SELECT * FROM public.subjects WHERE subjects_id = ?");
        $stmt->execute([$id]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$course) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบรายวิชานี้']);
            exit;
        }

        // ดึงบทเรียนของวิชานี้
        $stmtL = $conn->prepare("SELECT * FROM public.lessons WHERE subjects_id = ? ORDER BY lessons_id ASC");
        $stmtL->execute([$id]);
        $lessons = $stmtL->fetchAll(PDO::FETCH_ASSOC);

        // ดึงชื่ออาจารย์ (เขียนดัก Error ป้องกันเว็บพัง)
        $course['teachers_name'] = 'ไม่ระบุอาจารย์ผู้สอน';
        if (!empty($course['teachers_id'])) {
            try {
                $stmtT = $conn->prepare("SELECT teachers_name FROM public.teachers WHERE teachers_id = ?");
                $stmtT->execute([$course['teachers_id']]);
                $teacher = $stmtT->fetch(PDO::FETCH_ASSOC);
                if ($teacher && !empty($teacher['teachers_name'])) {
                    $course['teachers_name'] = $teacher['teachers_name'];
                }
            } catch (Exception $ex) {
                // ข้ามไปถ้าไม่มีตาราง
            }
        }

        echo json_encode(['status' => 'success', 'course' => $course, 'lessons' => $lessons]);
    }
} catch(Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>