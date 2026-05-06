<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json; charset=utf-8');

// รับข้อมูล JSON ที่ส่งมาจากหน้าข้อสอบ (test.js)
$data = json_decode(file_get_contents('php://input'), true);

// ตรวจสอบว่าใช่นักเรียนไหม
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['status' => 'unauthorized', 'message' => 'กรุณาเข้าสู่ระบบก่อนทำแบบทดสอบ']);
    exit;
}

$student_id = $_SESSION['user_id'];
$subject_id = $data['subject_id'] ?? '';
$lesson_index = intval($data['lesson_index'] ?? 1);
$score = floatval($data['score'] ?? 0);
$total_score = intval($data['total_score'] ?? 0);
$answers = $data['answers'] ?? []; // ตัวอย่างข้อมูล: [0, 1, 2, null] (0=ก, 1=ข, 2=ค, 3=ง)

try {
    // เริ่มระบบ Transaction (ถ้าพังกลางทาง จะไม่บันทึกเลย ป้องกันข้อมูลแหว่ง)
    $conn->beginTransaction();

    // 1. หาว่าบทเรียนที่นักเรียนกำลังสอบคือ Lessons_ID อะไร (ดึงตามลำดับของบทเรียน)
    $offset = $lesson_index - 1;
    $stmtL = $conn->prepare("SELECT lessons_id FROM public.lessons WHERE subjects_id = ? ORDER BY lessons_id ASC LIMIT 1 OFFSET ?");
    $stmtL->execute([$subject_id, $offset]);
    $lesson_id = $stmtL->fetchColumn();

    if (!$lesson_id) {
        throw new Exception("ไม่พบบทเรียน");
    }

    // 2. บันทึก "คะแนนรวม" ลงตาราง test 
    // (ใช้ RETURNING test_id เพื่อให้ฐานข้อมูลรัน ID ให้ และส่งกลับมาให้เราใช้ต่อ)
    $stmtTest = $conn->prepare("INSERT INTO public.test (student_id, lessons_id, score) VALUES (?, ?, ?) RETURNING test_id");
    $stmtTest->execute([$student_id, $lesson_id, $score]);
    $new_test_id = $stmtTest->fetchColumn();

    // 3. ดึง "รหัสคำถาม" ทั้งหมดของบทเรียนนี้มาเรียงให้ตรงกับที่แสดงให้นักเรียนสอบ
    $stmtQ = $conn->prepare("SELECT questions_id, correct_answer FROM public.test_questions WHERE lessons_id = ? ORDER BY questions_id ASC");
    $stmtQ->execute([$lesson_id]);
    $questions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);

    // 4. วนลูปบันทึก "คำตอบรายข้อ" ลงตาราง test_answers
    $stmtAns = $conn->prepare("INSERT INTO public.test_answers (questions_id, test_id, selected_choice) VALUES (?, ?, ?)");
    
    // ตัวแปลง index จาก Javascript (0, 1, 2, 3) กลับเป็น A, B, C, D
    $choice_map = [0 => 'A', 1 => 'B', 2 => 'C', 3 => 'D'];
    
    foreach ($questions as $index => $q) {
        $q_id = $q['questions_id'];
        $selected_val = '-'; // ค่าเริ่มต้นถ้าไม่ได้ตอบ
        
        // เช็คว่านักเรียนตอบข้อนี้ไหม (และต้องไม่เป็น null)
        if (isset($answers[$index]) && $answers[$index] !== null) {
            $ans_idx = $answers[$index];
            
            if ($q['correct_answer'] === '-') {
                // สำหรับคำถามอัตนัย (ข้อเขียน) ปัจจุบันในระบบยังเป็น index อยู่ ให้ใส่ - ไว้ก่อน
                $selected_val = '-'; 
            } else {
                // สำหรับคำถามปรนัย (4 ตัวเลือก) และ ถูก/ผิด
                $selected_val = $choice_map[$ans_idx] ?? '-';
            }
        }
        
        // ยัดข้อมูลลงตาราง test_answers
        $stmtAns->execute([$q_id, $new_test_id, $selected_val]);
    }

    // 5. บันทึกลงตาราง Learning Records เพื่อปลดล็อกบทถัดไป (สมมติระบบของคุณมี)
    $stmtRecord = $conn->prepare("INSERT INTO public.learning_records (student_id, lessons_id, activity_type) VALUES (?, ?, 'quiz_passed') ON CONFLICT DO NOTHING");
    $stmtRecord->execute([$student_id, $lesson_id]);

    // ยืนยันการบันทึกข้อมูลทั้งหมด
    $conn->commit();

    // คำนวณว่าผ่านเกณฑ์ไหม (สมมติผ่านที่ 60%)
    $required_score = ceil($total_score * 0.6);
    $status = ($score >= $required_score) ? 'pass' : 'fail';

    echo json_encode(['status' => 'success', 'quiz_status' => $status, 'message' => 'บันทึกคำตอบสำเร็จ!']);

} catch(Exception $e) {
    // ถ้าพังให้ย้อนกลับการบันทึกทั้งหมด
    $conn->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'DB Error: ' . $e->getMessage()]);
}
?>