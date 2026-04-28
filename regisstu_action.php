<?php
header('Content-Type: text/html; charset=utf-8');
require_once 'db_connect.php';

// ── Helpers ──────────────────────────────────────────────────────────────────
function alertBack(string $msg): void {
    $safe = addslashes($msg);
    echo "<script>alert('{$safe}'); window.history.back();</script>";
    exit;
}

function alertRedirect(string $msg, string $url): void {
    $safe    = addslashes($msg);
    $safeUrl = addslashes($url);
    echo "<script>alert('{$safe}'); window.location.href='{$safeUrl}';</script>";
    exit;
}

// ── ตรวจสอบว่ามาจาก POST เท่านั้น ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: regisstu.php');
    exit;
}

// ── รับค่าจาก Form ───────────────────────────────────────────────────────────
$role      = trim($_POST['role']      ?? '');
$userid    = preg_replace('/\D/', '', $_POST['userid'] ?? '');
$firstname = trim($_POST['fullname']  ?? '');
$lastname  = trim($_POST['lastname']  ?? '');
$fullname  = trim($firstname . ' ' . $lastname);
$password  = $_POST['password']       ?? '';
$email     = trim($_POST['email']     ?? '');
$phone     = preg_replace('/\D/', '', $_POST['phone'] ?? '');
$house     = trim($_POST['house']     ?? '');
$tambon    = trim($_POST['tambon']    ?? '');
$amphoe    = trim($_POST['amphoe']    ?? '');
$province  = trim($_POST['province']  ?? '');
$zipcode   = trim($_POST['zipcode']   ?? '');
$address   = "{$house} ต.{$tambon} อ.{$amphoe} จ.{$province} {$zipcode}";

// ── Validation พื้นฐาน ────────────────────────────────────────────────────────
if (!in_array($role, ['student', 'teacher', 'parent'], true)) {
    alertBack('บทบาทไม่ถูกต้อง');
}
if (strlen($userid) !== 13) {
    alertBack('เลขบัตรประชาชนต้องมี 13 หลัก');
}
if (!$fullname || !$password || !$email || !$phone) {
    alertBack('กรุณากรอกข้อมูลให้ครบถ้วน');
}
if (strlen($password) < 6) {
    alertBack('รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร');
}

try {

    // ════════════════════════════════════════════════════════════════════════
    //  STUDENT
    //  columns: student_id, student_name, email, tel, status_address,
    //           education_level, student_level, pin, password
    // ════════════════════════════════════════════════════════════════════════
    if ($role === 'student') {
        $pin           = $_POST['student_pin'] ?? '';
        $student_level = trim($_POST['level']  ?? '');

        if (strlen($pin) !== 6 || !ctype_digit($pin)) {
            alertBack('PIN ต้องเป็นตัวเลข 6 หลัก');
        }
        if (!$student_level) {
            alertBack('กรุณาเลือกระดับชั้น');
        }

        // ── Upload ไฟล์วุฒิการศึกษา (เก็บบน server) ──────────────────────
        if (!isset($_FILES['cert']) || $_FILES['cert']['error'] !== UPLOAD_ERR_OK) {
            alertBack('กรุณาแนบไฟล์วุฒิการศึกษา');
        }
        $ext     = strtolower(pathinfo($_FILES['cert']['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
        if (!in_array($ext, $allowed, true)) {
            alertBack('ประเภทไฟล์ไม่ถูกต้อง (PDF, JPG, PNG เท่านั้น)');
        }
        if ($_FILES['cert']['size'] > 5 * 1024 * 1024) {
            alertBack('ไฟล์ต้องมีขนาดไม่เกิน 5MB');
        }
        $uploadDir = __DIR__ . '/uploads/certs/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $safeName = 'cert_' . $userid . '_' . time() . '.' . $ext;
        if (!move_uploaded_file($_FILES['cert']['tmp_name'], $uploadDir . $safeName)) {
            alertBack('ไม่สามารถอัปโหลดไฟล์ได้ กรุณาลองใหม่');
        }

        // ── ตรวจสอบ ID ซ้ำ ────────────────────────────────────────────────
        $chk = $conn->prepare("SELECT student_id FROM public.student WHERE student_id = :id");
        $chk->execute(['id' => $userid]);
        if ($chk->rowCount() > 0) {
            alertBack('รหัสบัตรประชาชนนี้ถูกลงทะเบียนแล้ว');
        }

        // ── INSERT ────────────────────────────────────────────────────────
        $stmt = $conn->prepare("
            INSERT INTO public.student
                (student_id, student_name, email, tel, status_address,
                 education_level, student_level, pin, password)
            VALUES
                (:student_id, :student_name, :email, :tel, :status_address,
                 :education_level, :student_level, :pin, :password)
        ");
        $stmt->execute([
            'student_id'      => $userid,
            'student_name'    => $fullname,
            'email'           => $email,
            'tel'             => $phone,
            'status_address'  => $address,
            'education_level' => $student_level,
            'student_level'   => $student_level,
            'pin'             => $pin,
            'password'        => $password,
        ]);

        alertRedirect('ลงทะเบียนนักเรียนสำเร็จ! 🎉', 'login.php');
    }


    // ════════════════════════════════════════════════════════════════════════
    //  TEACHER — ยังไม่มีตาราง teacher ใน DB
    // ════════════════════════════════════════════════════════════════════════
    elseif ($role === 'teacher') {
        alertBack('ระบบยังไม่รองรับการลงทะเบียนอาจารย์ กรุณาติดต่อผู้ดูแลระบบ');
    }


    // ════════════════════════════════════════════════════════════════════════
    //  PARENT
    //  columns: parents_id, password, parents_name, parents_address,
    //           email, tel, pin
    // ════════════════════════════════════════════════════════════════════════
    elseif ($role === 'parent') {
        $relation = trim($_POST['relation']         ?? '');
        $link_sid = preg_replace('/\D/', '', $_POST['link_student_id']  ?? '');
        $link_pin = trim($_POST['link_student_pin'] ?? '');

        if (!$relation) {
            alertBack('กรุณาเลือกความสัมพันธ์กับนักเรียน');
        }
        if (strlen($link_sid) !== 13) {
            alertBack('รหัสบัตรประชาชนนักเรียนต้องมี 13 หลัก');
        }
        if (strlen($link_pin) !== 6 || !ctype_digit($link_pin)) {
            alertBack('PIN นักเรียนต้องเป็นตัวเลข 6 หลัก');
        }

        // ── ตรวจสอบ PIN นักเรียน ─────────────────────────────────────────
        $chk = $conn->prepare("
            SELECT student_id FROM public.student
            WHERE student_id = :sid AND pin = :pin
        ");
        $chk->execute(['sid' => $link_sid, 'pin' => $link_pin]);
        if ($chk->rowCount() === 0) {
            alertBack('ไม่สามารถผูกบัญชีได้: รหัสนักเรียนหรือ PIN ไม่ถูกต้อง');
        }

        // ── ตรวจสอบ ID ซ้ำ ────────────────────────────────────────────────
        $chk2 = $conn->prepare("SELECT parents_id FROM public.parents WHERE parents_id = :id");
        $chk2->execute(['id' => $userid]);
        if ($chk2->rowCount() > 0) {
            alertBack('รหัสบัตรประชาชนนี้ถูกลงทะเบียนแล้ว');
        }

        // ── INSERT parents ────────────────────────────────────────────────
        // หมายเหตุ: ตาราง parents ไม่มี column relation → เก็บใน pin field แทน
        // หากต้องการเพิ่ม column relation ให้รัน: ALTER TABLE public.parents ADD COLUMN relation VARCHAR;
        $ins = $conn->prepare("
            INSERT INTO public.parents
                (parents_id, password, parents_name, parents_address, email, tel, pin)
            VALUES
                (:parents_id, :password, :parents_name, :parents_address, :email, :tel, :pin)
        ");
        $ins->execute([
            'parents_id'      => $userid,
            'password'        => $password,
            'parents_name'    => $fullname,
            'parents_address' => $address,
            'email'           => $email,
            'tel'             => $phone,
            'pin'             => $relation,
        ]);

        // ── ผูก parent_id เข้า student ───────────────────────────────────
        $upd = $conn->prepare("
            UPDATE public.student SET parent_id = :pid WHERE student_id = :sid
        ");
        $upd->execute(['pid' => $userid, 'sid' => $link_sid]);

        alertRedirect('ลงทะเบียนและเชื่อมโยงข้อมูลบุตรหลานสำเร็จ! 👨‍👩‍👧', 'login.php');
    }

} catch (PDOException $e) {
    $errMsg = htmlspecialchars($e->getMessage());
    echo "<p style='color:red;font-family:monospace;padding:20px;'>
            ❌ เกิดข้อผิดพลาดในฐานข้อมูล:<br><br>{$errMsg}
          </p>";
}