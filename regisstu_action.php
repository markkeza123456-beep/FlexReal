<?php
session_start();
// เรียกใช้ไฟล์เชื่อมต่อ Supabase PDO ของคุณ
require_once 'db_connect.php'; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. รับค่าพื้นฐานที่ทุก Role ต้องมี
    $role = $_POST['role'];
    
    // ตัดเครื่องหมาย - ออกจากรหัสบัตรและเบอร์โทร เพื่อให้บันทึกลง DB เป็นตัวเลขล้วน
    $userid = str_replace('-', '', $_POST['userid']); 
    $phone = str_replace('-', '', $_POST['phone']);
    
    $password = $_POST['password']; 
    $fullname = $_POST['fullname'] . ' ' . $_POST['lastname'];
    $email = $_POST['email'] ?? '-';

    // รับค่าที่อยู่แยกส่วนเพื่อบันทึกลงตาราง addresses
    $house    = $_POST['house'];
    $tambon   = $_POST['tambon'];
    $amphoe   = $_POST['amphoe'];
    $province = $_POST['province'];
    $zipcode  = $_POST['zipcode'];
    
    // รวมที่อยู่เป็นข้อความยาวสำหรับตาราง Role เดิม (ถ้ายังจำเป็นต้องใช้)[cite: 12]
    $address_full = $house . ' ต.' . $tambon . ' อ.' . $amphoe . ' จ.' . $province . ' ' . $zipcode;

    try {
        // เริ่ม Transaction เพื่อความปลอดภัยของข้อมูล[cite: 12]
        $conn->beginTransaction();

        // 2. เช็คก่อนว่ามีรหัสบัตรประชาชนนี้ในระบบ (ตาราง User) หรือยัง?[cite: 12]
        $check_user = $conn->prepare('SELECT User_ID FROM "User" WHERE User_ID = ?');
        $check_user->execute([$userid]);
        if ($check_user->rowCount() > 0) {
            throw new Exception("รหัสบัตรประชาชนนี้ถูกลงทะเบียนในระบบแล้ว");
        }

        // ---------------------------------------------------------
        // บันทึกข้อมูลที่อยู่ลงตาราง public.addresses (ตามภาพ image_a0bf3a.png)
        // ---------------------------------------------------------
        $stmt_addr = $conn->prepare("
            INSERT INTO public.addresses (user_id, house_number, tambon, amphoe, province, zipcode) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt_addr->execute([$userid, $house, $tambon, $amphoe, $province, $zipcode]);

        // ---------------------------------------------------------
        // กรณี: สมัครเป็น "นักเรียน"[cite: 12]
        // ---------------------------------------------------------
        if ($role === 'student') {
            $level = $_POST['level'];
            $pin = $_POST['student_pin']; 

            $stmt_user = $conn->prepare('INSERT INTO "User" (User_ID, Password, Status) VALUES (?, ?, ?)');
            $stmt_user->execute([$userid, $password, 'Student']);

            $stmt_stu = $conn->prepare("INSERT INTO Student (Student_ID, Student_Name, Email, Tel, Status_Address, Student_Level, PIN) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt_stu->execute([$userid, $fullname, $email, $phone, $address_full, $level, $pin]);

        } 
        // ---------------------------------------------------------
        // กรณี: สมัครเป็น "อาจารย์"[cite: 12]
        // ---------------------------------------------------------
        elseif ($role === 'teacher') {
            $stmt_user = $conn->prepare('INSERT INTO "User" (User_ID, Password, Status) VALUES (?, ?, ?)');
            $stmt_user->execute([$userid, $password, 'Teacher']);

            $stmt_teacher = $conn->prepare("INSERT INTO Teachers (Teachers_ID, Password, Teachers_Name, Email, Tel, Teachers_Address) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_teacher->execute([$userid, $password, $fullname, $email, $phone, $address_full]);
        } 
        // ---------------------------------------------------------
        // กรณี: สมัครเป็น "ผู้ปกครอง"[cite: 12]
        // ---------------------------------------------------------
        elseif ($role === 'parent') {
            $link_student_id = str_replace('-', '', $_POST['link_student_id']);
            $link_student_pin = $_POST['link_student_pin'];

            $check_stu = $conn->prepare("SELECT Student_ID FROM Student WHERE Student_ID = ? AND PIN = ?");
            $check_stu->execute([$link_student_id, $link_student_pin]);
            
            if ($check_stu->rowCount() == 0) {
                throw new Exception("ข้อมูลไม่ถูกต้อง! ไม่พบรหัสนักเรียน หรือ PIN ของบุตรไม่ตรงกัน");
            }

            $stmt_user = $conn->prepare('INSERT INTO "User" (User_ID, Password, Status) VALUES (?, ?, ?)');
            $stmt_user->execute([$userid, $password, 'Parent']);

            $stmt_parent = $conn->prepare("INSERT INTO Parents (Parents_ID, Password, Parents_Name, Email, Tel, Parents_Address, PIN) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt_parent->execute([$userid, $password, $fullname, $email, $phone, $address_full, $link_student_pin]);

            $link_update = $conn->prepare("UPDATE Student SET Parent_ID = ? WHERE Student_ID = ?");
            $link_update->execute([$userid, $link_student_id]);
        }

        // กดยืนยันการบันทึกข้อมูลทั้งหมดลงฐานข้อมูล[cite: 12]
        $conn->commit();

        echo "<script>
                alert('ลงทะเบียนสำเร็จ! กรุณาเข้าสู่ระบบ');
                window.location.href = 'login.php';
              </script>";
        exit();

    } catch (Exception $e) {
        // หากเกิด Error ให้ยกเลิกการบันทึกทั้งหมด[cite: 12]
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error_msg = $e->getMessage();
        
        echo "<script>
                alert('เกิดข้อผิดพลาด: {$error_msg}');
                window.history.back();
              </script>";
        exit();
    }
} else {
    header("Location: regisstu.php");
    exit();
}
?>