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
        if(empty($lesson_name) || empty($subject_id)) throw new Exception("ข้อมูลไม่ครบถ้วน");

        $lesson_id = 'LSN' . time() . rand(10,99);
        $stmt = $conn->prepare("INSERT INTO public.lessons (lessons_id, lessons_name, study_hours, subjects_id) VALUES (:id, :name, :hrs, :sub)");
        $stmt->execute(['id' => $lesson_id, 'name' => $lesson_name, 'hrs' => 1, 'sub' => $subject_id]);
        echo json_encode(['success' => true, 'message' => 'เพิ่มบทเรียนสำเร็จ!']);
    }
    // ---- 2. แก้ไขบทเรียน ----
    elseif ($action === 'edit_lesson') {
        $lesson_id = $_POST['lesson_id'] ?? '';
        $lesson_name = $_POST['lesson_name'] ?? '';
        if(empty($lesson_id) || empty($lesson_name)) throw new Exception("ข้อมูลไม่ครบถ้วน");

        $stmt = $conn->prepare("UPDATE public.lessons SET lessons_name = :name WHERE lessons_id = :id");
        $stmt->execute(['name' => $lesson_name, 'id' => $lesson_id]);
        echo json_encode(['success' => true, 'message' => 'แก้ไขบทเรียนสำเร็จ!']);
    }
    // ---- 3. ลบบทเรียน ----
    elseif ($action === 'delete_lesson') {
        $lesson_id = $_POST['lesson_id'] ?? '';
        if(empty($lesson_id)) throw new Exception("ไม่พบรหัสบทเรียน");

        $conn->prepare("DELETE FROM public.test_questions WHERE lessons_id = ?")->execute([$lesson_id]);
        $conn->prepare("DELETE FROM public.lessons WHERE lessons_id = ?")->execute([$lesson_id]);
        echo json_encode(['success' => true, 'message' => 'ลบบทเรียนสำเร็จ!']);
    }

    // ---- 4. เพิ่มแบบทดสอบ ----
    elseif ($action === 'add_quiz') {
        $lesson_id = $_POST['lesson_id'] ?? '';
        $question = $_POST['question'] ?? '';
        $type = $_POST['type'] ?? 'choice';
        
        $cA = $_POST['choice_a'] ?? '';
        $cB = $_POST['choice_b'] ?? '';
        $cC = $_POST['choice_c'] ?? '';
        $cD = $_POST['choice_d'] ?? '';
        $answer = $_POST['answer'] ?? '';

        if(empty($lesson_id) || empty($question)) throw new Exception("ข้อมูลคำถามไม่ครบ");

        // จัดการข้อมูลตามประเภทข้อสอบ
        if ($type === 'truefalse') {
            $cA = 'ถูก'; $cB = 'ผิด'; $cC = '-'; $cD = '-';
        } elseif ($type === 'essay') {
            $cA = '-'; $cB = '-'; $cC = '-'; $cD = '-';
            $answer = '-'; // ใช้เครื่องหมาย - เป็นสัญลักษณ์ของข้อเขียน
        }

        $stmtId = $conn->query("SELECT COALESCE(MAX(questions_id), 0) + 1 FROM public.test_questions");
        $nextId = $stmtId->fetchColumn();

        $stmt = $conn->prepare("INSERT INTO public.test_questions (questions_id, questions_text, choice_a, choice_b, choice_c, choice_d, correct_answer, lessons_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nextId, $question, $cA, $cB, $cC, $cD, $answer, $lesson_id]);
        echo json_encode(['success' => true, 'message' => 'เพิ่มคำถามสำเร็จ!']);
    }

    // ---- 5. แก้ไขแบบทดสอบ ----
    elseif ($action === 'edit_quiz') {
        $quiz_id = $_POST['quiz_id'] ?? '';
        $question = $_POST['question'] ?? '';
        $type = $_POST['type'] ?? 'choice';
        
        $cA = $_POST['choice_a'] ?? '';
        $cB = $_POST['choice_b'] ?? '';
        $cC = $_POST['choice_c'] ?? '';
        $cD = $_POST['choice_d'] ?? '';
        $answer = $_POST['answer'] ?? '';

        if(empty($quiz_id) || empty($question)) throw new Exception("ข้อมูลไม่ครบถ้วน");

        if ($type === 'truefalse') {
            $cA = 'ถูก'; $cB = 'ผิด'; $cC = '-'; $cD = '-';
        } elseif ($type === 'essay') {
            $cA = '-'; $cB = '-'; $cC = '-'; $cD = '-';
            $answer = '-';
        }

        $stmt = $conn->prepare("UPDATE public.test_questions SET questions_text = ?, choice_a = ?, choice_b = ?, choice_c = ?, choice_d = ?, correct_answer = ? WHERE questions_id = ?");
        $stmt->execute([$question, $cA, $cB, $cC, $cD, $answer, $quiz_id]);
        echo json_encode(['success' => true, 'message' => 'แก้ไขคำถามสำเร็จ!']);
    }

    // ---- 6. ลบแบบทดสอบ ----
    elseif ($action === 'delete_quiz') {
        $quiz_id = $_POST['quiz_id'] ?? '';
        if(empty($quiz_id)) throw new Exception("ไม่พบรหัสคำถาม");

        $conn->prepare("DELETE FROM public.test_questions WHERE questions_id = ?")->execute([$quiz_id]);
        echo json_encode(['success' => true, 'message' => 'ลบคำถามสำเร็จ!']);
    }

} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>