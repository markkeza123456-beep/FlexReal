<?php
// 1. เริ่มต้น Session เพื่อให้ระบบรู้จัก Session ปัจจุบันที่จะลบ
session_start();

// 2. ล้างตัวแปร Session ทั้งหมดที่เคยเก็บไว้ (เช่น user_id, role, name)
$_SESSION = array();

// 3. ถ้ามีการใช้ Cookie สำหรับ Session ให้ลบทิ้งด้วย (เพื่อความปลอดภัยสูงสุด)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. ทำลาย Session ในฝั่ง Server
session_destroy();

// 5. ส่งผู้ใช้งานกลับไปที่หน้า Login
header("Location: login.php");
exit();
?>