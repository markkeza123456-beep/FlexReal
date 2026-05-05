<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'กรุณาล็อกอินใหม่']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    // ---- 1. เพิ่มบทเรียน (Lessons) ----
    if ($action === 'add_lesson') {
        $lesson_name = $_POST['lesson_name'] ?? '';
        $subject_id = $_POST['subject_id'] ?? ''; 
        
        if(empty($lesson_name) || empty($subject_id)) {
            throw new Exception("ข้อมูลไม่ครบถ้วน");
        }

        // สร้าง ID บทเรียนแบบสุ่ม (เช่น LSN1689...)
        $lesson_id = 'LSN' . time() . rand(10,99);

        $stmt = $conn->prepare("INSERT INTO public.lessons (lessons_id, lessons_name, study_hours, subjects_id) VALUES (:id, :name, :hrs, :sub)");
        $stmt->execute([
            'id' => $lesson_id, 
            'name' => $lesson_name, 
            'hrs' => 1,
            'sub' => $subject_id
        ]);

        echo json_encode(['success' => true, 'message' => 'เพิ่มบทเรียนสำเร็จ!']);
    }

    // ---- 2. เพิ่มแบบทดสอบ (Test_Questions) ----
    elseif ($action === 'add_quiz') {
        $lesson_id = $_POST['lesson_id'] ?? '';
        $question = $_POST['question'] ?? '';
        $choices = $_POST['choices'] ?? ''; 
        $answer = $_POST['answer'] ?? '';

        if(empty($lesson_id) || empty($question)) {
            throw new Exception("ข้อมูลคำถามไม่ครบ");
        }

        // หา ID ล่าสุดของคำถาม แล้วบวก 1
        $stmtId = $conn->query("SELECT COALESCE(MAX(questions_id), 0) + 1 FROM public.test_questions");
        $nextId = $stmtId->fetchColumn();

        // แยกตัวเลือก (Choice)
        $choiceArr = explode("\n", str_replace("\r", "", $choices));
        $cA = $choiceArr[0] ?? '-';
        $cB = $choiceArr[1] ?? '-';
        $cC = $choiceArr[2] ?? '-';
        $cD = $choiceArr[3] ?? '-';

        $stmt = $conn->prepare("INSERT INTO public.test_questions (questions_id, questions_text, choice_a, choice_b, choice_c, choice_d, correct_answer, lessons_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nextId, $question, $cA, $cB, $cC, $cD, $answer, $lesson_id]);

        echo json_encode(['success' => true, 'message' => 'เพิ่มคำถามสำเร็จ!']);
    }

} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>