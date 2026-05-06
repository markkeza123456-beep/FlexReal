<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

try {
    if ($action === 'get_questions') {
        $subject_id = $_GET['subject_id'] ?? '';
        $lesson_index = max(1, intval($_GET['lesson'] ?? 1));
        $offset = $lesson_index - 1;

        // 1. หา Lessons_ID จากลำดับบทเรียน
        $stmtL = $conn->prepare("SELECT lessons_id FROM public.lessons WHERE subjects_id = ? ORDER BY lessons_id ASC LIMIT 1 OFFSET ?");
        $stmtL->execute([$subject_id, $offset]);
        $lesson_id = $stmtL->fetchColumn();

        if (!$lesson_id) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบบทเรียน']);
            exit;
        }

        // 2. ดึงคำถามข้อสอบของบทเรียนนี้
        $stmtQ = $conn->prepare("SELECT * FROM public.test_questions WHERE lessons_id = ? ORDER BY questions_id ASC");
        $stmtQ->execute([$lesson_id]);
        $questions_raw = $stmtQ->fetchAll(PDO::FETCH_ASSOC);

        $formatted_questions = [];
        foreach ($questions_raw as $q) {
            $options = [];
            $answer_index = 0;
            $ans = $q['correct_answer'];
            
            // แปลงข้อมูลจากที่อาจารย์เลือก ให้เป็นรูปแบบที่ระบบข้อสอบนักเรียนเข้าใจ
            if ($q['choice_a'] === 'ถูก' && $q['choice_b'] === 'ผิด') {
                $options = ['ถูก (True)', 'ผิด (False)'];
                $answer_index = ($ans === 'A') ? 0 : 1;
            } else if ($ans !== '-') {
                $options = [$q['choice_a'], $q['choice_b'], $q['choice_c'], $q['choice_d']];
                $map = ['A' => 0, 'B' => 1, 'C' => 2, 'D' => 3];
                $answer_index = $map[$ans] ?? 0;
            } else {
                $options = ['(คำถามข้อเขียน: อาจารย์จะตรวจให้คะแนนภายหลัง)'];
                $answer_index = 0;
            }

            $formatted_questions[] = [
                'question' => $q['questions_text'],
                'options' => $options,
                'answer' => $answer_index
            ];
        }

        echo json_encode(['status' => 'success', 'questions' => $formatted_questions]);
    }
} catch(Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>