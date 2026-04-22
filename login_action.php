<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
error_reporting(0); // ป้องกัน Error อื่นๆ มาทำลายรูปแบบ JSON

try {
    require_once 'db_connect.php'; 

    $role     = $_POST['role'] ?? '';
    $id_card  = $_POST['email'] ?? '';      
    $password = $_POST['password'] ?? '';

    if (empty($role) || empty($id_card) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
        exit;
    }

    $user_data = null;
    $redirect_url = '';

    switch ($role) {
        case 'student':
            // อ้างอิงตาราง 3.2 [cite: 1148]
            $stmt = $conn->prepare("SELECT student_id AS user_id, password, student_name AS name FROM public.student WHERE student_id = :id");
            $stmt->execute(['id' => $id_card]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $redirect_url = 'student_dashboard.php'; 
            break;
        case 'parent':
            // อ้างอิงตาราง 3.3 [cite: 1151] ร่วมกับตารางนักเรียนเพื่อหาลูกที่ผูกไว้ 
            $stmt = $conn->prepare("SELECT p.parents_id AS user_id, p.password, p.parents_name AS name, s.student_id 
                                   FROM public.parents p 
                                   LEFT JOIN public.student s ON p.parents_id = s.parent_id 
                                   WHERE p.parents_id = :id");
            $stmt->execute(['id' => $id_card]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $redirect_url = 'parent_dashboard.php';
            break;
        // บทบาทอื่นดึงตาม ID ของตนเอง [cite: 1153-1159]
        case 'teacher':
            $stmt = $conn->prepare("SELECT teachers_id AS user_id, password, teachers_name AS name FROM public.teachers WHERE teachers_id = :id");
            $stmt->execute(['id' => $id_card]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $redirect_url = 'teacher_dashboard.php';
            break;
    }

    if ($user_data && $password === $user_data['password']) {
        $_SESSION['user_id'] = $user_data['user_id'];
        $_SESSION['role'] = $role;
        $_SESSION['name'] = $user_data['name'] ?? '';
        if ($role === 'parent') $_SESSION['current_student_id'] = $user_data['student_id'];

        echo json_encode(['status' => 'success', 'redirect_url' => $redirect_url]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ถูกต้อง']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'ระบบขัดข้อง']);
}