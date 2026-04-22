<?php
header('Content-Type: text/html; charset=utf-8');
require_once 'db_connect.php'; // ไฟล์ที่ใช้ $conn เชื่อมต่อ Supabase

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'];
    $userid = $_POST['userid'];
    $fullname = $_POST['fullname'];
    $password = $_POST['password'];

    try {
        if ($role === 'student') {
            $pin = $_POST['student_pin'];
            // เพิ่มข้อมูลลงตาราง student ตาม Schema [cite: 1148]
            $stmt = $conn->prepare("INSERT INTO public.student (student_id, student_name, password, pin) VALUES (:id, :name, :pw, :pin)");
            $stmt->execute(['id' => $userid, 'name' => $fullname, 'pw' => $password, 'pin' => $pin]);
            echo "<script>alert('ลงทะเบียนนักเรียนสำเร็จ!'); window.location.href='login.php';</script>";

        } else if ($role === 'parent') {
            $link_sid = $_POST['link_student_id'];
            $link_pin = $_POST['link_student_pin'];

            // 1. ตรวจสอบ PIN นักเรียนว่าถูกต้องหรือไม่ [cite: 1149]
            $check = $conn->prepare("SELECT student_id FROM public.student WHERE student_id = :sid AND pin = :pin");
            $check->execute(['sid' => $link_sid, 'pin' => $link_pin]);
            
            if ($check->rowCount() > 0) {
                // 2. เพิ่มข้อมูลผู้ปกครองลงตาราง parents [cite: 1151]
                $ins = $conn->prepare("INSERT INTO public.parents (parents_id, password, parents_name) VALUES (:pid, :pw, :name)");
                $ins->execute(['pid' => $userid, 'pw' => $password, 'name' => $fullname]);

                // 3. ผูก parent_id เข้ากับตาราง student ของนักเรียนคนนั้น [cite: 1149]
                $upd = $conn->prepare("UPDATE public.student SET parent_id = :pid WHERE student_id = :sid");
                $upd->execute(['pid' => $userid, 'sid' => $link_sid]);

                echo "<script>alert('ลงทะเบียนและเชื่อมโยงข้อมูลบุตรหลานสำเร็จ!'); window.location.href='login.php';</script>";
            } else {
                echo "<script>alert('ไม่สามารถผูกบัญชีได้: รหัสนักเรียนหรือ PIN ไม่ถูกต้อง'); window.history.back();</script>";
            }
        }
    } catch (PDOException $e) {
        echo "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}