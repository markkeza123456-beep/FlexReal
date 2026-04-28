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
    $email = $_POST['email'];
    
    // รวมที่อยู่ให้เป็นข้อความยาวๆ
    $address = $_POST['house'] . ' ต.' . $_POST['tambon'] . ' อ.' . $_POST['amphoe'] . ' จ.' . $_POST['province'] . ' ' . $_POST['zipcode'];

    try {
        // เริ่ม Transaction (ถ้ามีอันไหน Error จะได้ยกเลิกการบันทึกทั้งหมด ป้องกันข้อมูลขยะ)
        $conn->beginTransaction();

        // 2. เช็คก่อนว่ามีรหัสบัตรประชาชนนี้ในระบบ (ตาราง User) หรือยัง?
        $check_user = $conn->prepare('SELECT User_ID FROM "User" WHERE User_ID = ?');
        $check_user->execute([$userid]);
        if ($check_user->rowCount() > 0) {
            throw new Exception("รหัสบัตรประชาชนนี้ถูกลงทะเบียนในระบบแล้ว");
        }

        // ---------------------------------------------------------
        // กรณี: สมัครเป็น "นักเรียน"
        // ---------------------------------------------------------
        if ($role === 'student') {
            $level = $_POST['level'];
            $pin = $_POST['student_pin']; // PIN 6 หลักที่นักเรียนตั้งเอง

            // บันทึกลงตาราง User (ใช้ "User" เพราะเป็นคำสงวนใน Postgres)
            $stmt_user = $conn->prepare('INSERT INTO "User" (User_ID, Password, Status) VALUES (?, ?, ?)');
            $stmt_user->execute([$userid, $password, 'Student']);

            // บันทึกลงตาราง Student
            $stmt_stu = $conn->prepare("INSERT INTO Student (Student_ID, Student_Name, Email, Tel, Status_Address, Student_Level, PIN) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt_stu->execute([$userid, $fullname, $email, $phone, $address, $level, $pin]);

        } 
        // ---------------------------------------------------------
        // กรณี: สมัครเป็น "อาจารย์"
        // ---------------------------------------------------------
        elseif ($role === 'teacher') {
            // บันทึกลงตาราง User
            $stmt_user = $conn->prepare('INSERT INTO "User" (User_ID, Password, Status) VALUES (?, ?, ?)');
            $stmt_user->execute([$userid, $password, 'Teacher']);

            // บันทึกลงตาราง Teachers
            $stmt_teacher = $conn->prepare("INSERT INTO Teachers (Teachers_ID, Password, Teachers_Name, Email, Tel, Teachers_Address) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_teacher->execute([$userid, $password, $fullname, $email, $phone, $address]);

            // (วิชาที่สอน $_POST['subject'] จะต้องนำไปจัดสรรในตาราง Subjects ในภายหลัง)
        } 
        // ---------------------------------------------------------
        // กรณี: สมัครเป็น "ผู้ปกครอง" (ต้องเช็ค PIN นักเรียน!)
        // ---------------------------------------------------------
        elseif ($role === 'parent') {
            $link_student_id = str_replace('-', '', $_POST['link_student_id']);
            $link_student_pin = $_POST['link_student_pin'];

            // Step 1: ค้นหานักเรียนด้วยรหัสบัตรและ PIN
            $check_stu = $conn->prepare("SELECT Student_ID FROM Student WHERE Student_ID = ? AND PIN = ?");
            $check_stu->execute([$link_student_id, $link_student_pin]);
            
            if ($check_stu->rowCount() == 0) {
                // ถ้าไม่เจอ หรือ PIN ผิด ให้เด้ง Error ทันที
                throw new Exception("ข้อมูลไม่ถูกต้อง! ไม่พบรหัสนักเรียน หรือ PIN ของบุตรไม่ตรงกัน");
            }

            // Step 2: ถ้า PIN ถูกต้อง ให้บันทึกผู้ปกครองลงตาราง User
            $stmt_user = $conn->prepare('INSERT INTO "User" (User_ID, Password, Status) VALUES (?, ?, ?)');
            $stmt_user->execute([$userid, $password, 'Parent']);

            // Step 3: บันทึกลงตาราง Parents
            $stmt_parent = $conn->prepare("INSERT INTO Parents (Parents_ID, Password, Parents_Name, Email, Tel, Parents_Address) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_parent->execute([$userid, $password, $fullname, $email, $phone, $address]);

            // Step 4: **ไฮไลท์สำคัญ** อัปเดตตาราง Student เพื่อโยงรหัส Parent_ID เข้ากับตัวนักเรียน
            $link_update = $conn->prepare("UPDATE Student SET Parent_ID = ? WHERE Student_ID = ?");
            $link_update->execute([$userid, $link_student_id]);
        }

        // กดยืนยันการบันทึกข้อมูลทั้งหมดลงฐานข้อมูล
        $conn->commit();

        // แจ้งเตือนสำเร็จและเด้งไปหน้า Login
        echo "<script>
                alert('ลงทะเบียนสำเร็จ! กรุณาเข้าสู่ระบบ');
                window.location.href = 'login.php';
              </script>";
        exit();

    } catch (Exception $e) {
        // หากเกิด Error ตรงไหนก็ตาม ให้ยกเลิกการบันทึก (Rollback)
        $conn->rollBack();
        $error_msg = $e->getMessage();
        
        // แจ้งเตือน Error และเด้งกลับไปหน้าฟอร์ม
        echo "<script>
                alert('เกิดข้อผิดพลาด: {$error_msg}');
                window.history.back();
              </script>";
        exit();
    }
} else {
    // ถ้ามีคนแอบพิมพ์เข้า URL นี้ตรงๆ โดยไม่ผ่านฟอร์ม ให้เตะกลับไปหน้าสมัคร
    header("Location: regisstu.php");
    exit();
}
?>