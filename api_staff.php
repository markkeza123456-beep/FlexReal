<?php
header('Content-Type: application/json; charset=utf-8');

// 1. ดึงไฟล์เชื่อมต่อฐานข้อมูลของคุณ
// *** สมมติว่าในไฟล์ connect.php คุณใช้ตัวแปรชื่อ $conn หรือ $mysqli นะครับ ***
require 'connect.php'; 

// ฟังก์ชันช่วยเหลือสำหรับแปลงข้อมูลจาก DB เป็น Array
function fetchAll($result) {
    $data = [];
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}

// ตรวจสอบว่ามีคำสั่งอะไรส่งมา (ผ่าน GET หรือ POST)
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

switch ($action) {

    // ------------------------------------------------
    // ดึงข้อมูลทั้งหมด (Dashboard โหลดหน้านี้หน้าเดียวได้ครบ)
    // ------------------------------------------------
    case 'getAllData':
        // ดึงข้อมูลสมาชิก (สมมติว่าตารางของคุณชื่อ users หรือ members ปรับชื่อให้ตรงด้วยนะครับ)
        $sql_members = "SELECT id, firstname, lastname, email, role, status FROM users ORDER BY id DESC";
        $members = fetchAll($conn->query($sql_members));

        // ดึงข้อมูลหลักสูตร (ถ้ามีตารางแล้ว)
        // $sql_cur = "SELECT * FROM curricula";
        // $curricula = fetchAll($conn->query($sql_cur));
        $curricula = []; // ใส่ไว้ก่อนถ้ายังไม่มีตาราง

        // ดึงข้อมูลรายวิชา (ถ้ามีตารางแล้ว)
        // $sql_sub = "SELECT * FROM subjects";
        // $subjects = fetchAll($conn->query($sql_sub));
        $subjects = []; // ใส่ไว้ก่อนถ้ายังไม่มีตาราง

        echo json_encode([
            'status' => 'success',
            'members' => $members,
            'curricula' => $curricula,
            'subjects' => $subjects
        ]);
        break;

    // ------------------------------------------------
    // บันทึกการแก้ไขข้อมูลสมาชิก
    // ------------------------------------------------
    case 'saveMember':
        $id = $_POST['id'] ?? '';
        $firstname = $_POST['firstname'] ?? '';
        $lastname = $_POST['lastname'] ?? '';
        $email = $_POST['email'] ?? '';
        $role = $_POST['role'] ?? 'student';
        $status = $_POST['status'] ?? 'active';

        if(empty($id)) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบ ID ของผู้ใช้']);
            exit;
        }

        // ปรับชื่อตาราง (users) ให้ตรงกับ Database ของคุณ
        $stmt = $conn->prepare("UPDATE users SET firstname=?, lastname=?, email=?, role=?, status=? WHERE id=?");
        $stmt->bind_param("sssssi", $firstname, $lastname, $email, $role, $status, $id);

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'อัปเดตข้อมูลสำเร็จ']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'บันทึกข้อมูลล้มเหลว: ' . $conn->error]);
        }
        $stmt->close();
        break;

    // ------------------------------------------------
    // ลบสมาชิก
    // ------------------------------------------------
    case 'deleteMember':
        $id = $_POST['id'] ?? '';

        if(empty($id)) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบ ID ของผู้ใช้']);
            exit;
        }

        // ปรับชื่อตาราง (users) ให้ตรงกับ Database ของคุณ
        $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'ลบผู้ใช้สำเร็จ']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'ลบข้อมูลล้มเหลว']);
        }
        $stmt->close();
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบคำสั่ง (Action)']);
        break;
}

$conn->close();
?>