<?php
// เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูลของคุณ
require_once 'db_connect.php';

// ตรวจสอบว่าตัวแปร $conn ถูกสร้างขึ้นมาสำเร็จหรือไม่
if (isset($conn)) {
    echo "<div style='text-align: center; margin-top: 50px; font-family: Arial;'>";
    echo "<h1 style='color: #4CAF50;'>✅ เชื่อมต่อ Supabase สำเร็จ! 🎉</h1>";
    echo "<p>ระบบพร้อมดึงข้อมูล 20 ตารางของคุณแล้วครับ</p>";
    echo "</div>";
} else {
    echo "<div style='text-align: center; margin-top: 50px; font-family: Arial;'>";
    echo "<h1 style='color: #f44336;'>❌ เชื่อมต่อไม่สำเร็จ</h1>";
    echo "<p>กรุณาตรวจสอบ Host, Password หรือ Port ในไฟล์ db_connect.php อีกครั้ง</p>";
    echo "</div>";
}
?>