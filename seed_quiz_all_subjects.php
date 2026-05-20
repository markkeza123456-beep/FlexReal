<?php
session_start();
require_once __DIR__ . '/db_connect.php';
header('Content-Type: application/json; charset=utf-8');

function jsonOut(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$role = strtolower((string) ($_SESSION['role'] ?? ''));
if (!isset($_SESSION['user_id']) || !in_array($role, ['teacher', 'staff', 'admin'], true)) {
    jsonOut(['status' => 'unauthorized', 'message' => 'ไม่มีสิทธิ์ใช้งาน'], 401);
}

function nextLessonId(PDO $conn): string
{
    $stmt = $conn->query("SELECT lessons_id FROM public.lessons WHERE lessons_id LIKE 'L%' ORDER BY LENGTH(lessons_id) DESC, lessons_id DESC LIMIT 1");
    $lastId = $stmt->fetchColumn();
    $nextNum = $lastId ? (intval(substr((string) $lastId, 1)) + 1) : 1;
    return 'L' . str_pad((string) $nextNum, 3, '0', STR_PAD_LEFT);
}

function nextQuestionId(PDO $conn): int
{
    $stmt = $conn->query("SELECT COALESCE(MAX(questions_id), 0) + 1 FROM public.test_questions");
    return (int) $stmt->fetchColumn();
}

function ensureFiveLessons(PDO $conn, string $subjectId): array
{
    $stmt = $conn->prepare("SELECT * FROM public.lessons WHERE subjects_id = ? ORDER BY lessons_id ASC");
    $stmt->execute([$subjectId]);
    $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $baseName = !empty($lessons[0]['lessons_name']) ? (string) $lessons[0]['lessons_name'] : 'บทที่ 1';
    $baseHours = (isset($lessons[0]['study_hours']) && is_numeric($lessons[0]['study_hours']))
        ? max(1, (int) $lessons[0]['study_hours'])
        : 1;

    $missing = max(0, 5 - count($lessons));
    if ($missing > 0) {
        $insertLesson = $conn->prepare(
            "INSERT INTO public.lessons (lessons_id, lessons_name, study_hours, subjects_id) VALUES (:id, :name, :hrs, :sub)"
        );
        for ($i = 0; $i < $missing; $i++) {
            $insertLesson->execute([
                ':id' => nextLessonId($conn),
                ':name' => $baseName,
                ':hrs' => $baseHours,
                ':sub' => $subjectId,
            ]);
        }
        $stmt->execute([$subjectId]);
        $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    return $lessons;
}

function buildSeedQuestionsBySubject(string $subjectName, int $lessonIndex): array
{
    $name = mb_strtolower($subjectName, 'UTF-8');

    if (str_contains($name, 'คณิต')) {
        return [
            ['q' => "บทที่ {$lessonIndex}: 24 ÷ 6 มีค่าเท่าไร", 'a' => '4', 'b' => '6', 'c' => '18', 'd' => '30', 'ans' => 'A'],
            ['q' => "บทที่ {$lessonIndex}: 7 × (3 + 2) เท่ากับข้อใด", 'a' => '14', 'b' => '35', 'c' => '12', 'd' => '10', 'ans' => 'B'],
            ['q' => "บทที่ {$lessonIndex}: ข้อใดเป็นจำนวนเฉพาะ", 'a' => '21', 'b' => '27', 'c' => '29', 'd' => '33', 'ans' => 'C'],
            ['q' => "บทที่ {$lessonIndex}: 0.25 เท่ากับเศษส่วนใด", 'a' => '1/2', 'b' => '1/4', 'c' => '2/5', 'd' => '3/4', 'ans' => 'B'],
            ['q' => "บทที่ {$lessonIndex}: สี่เหลี่ยมผืนผ้ายาว 8 กว้าง 5 มีพื้นที่เท่าไร", 'a' => '13', 'b' => '26', 'c' => '40', 'd' => '80', 'ans' => 'C'],
        ];
    }

    if (str_contains($name, 'ไทย')) {
        return [
            ['q' => "บทที่ {$lessonIndex}: คำใดเป็นคำราชาศัพท์", 'a' => 'กิน', 'b' => 'รับประทาน', 'c' => 'เคี้ยว', 'd' => 'ดื่ม', 'ans' => 'B'],
            ['q' => "บทที่ {$lessonIndex}: ข้อใดเป็นประโยคสมบูรณ์", 'a' => 'เมื่อเช้าที่ตลาด', 'b' => 'แมวสีขาว', 'c' => 'นักเรียนอ่านหนังสือในห้องสมุด', 'd' => 'เพราะฝนตก', 'ans' => 'C'],
            ['q' => "บทที่ {$lessonIndex}: คำว่า \"สามัคคี\" ใกล้เคียงกับข้อใด", 'a' => 'แตกแยก', 'b' => 'พร้อมเพรียง', 'c' => 'รวดเร็ว', 'd' => 'โดดเดี่ยว', 'ans' => 'B'],
            ['q' => "บทที่ {$lessonIndex}: ข้อใดใช้ไม้ยมกถูกต้อง", 'a' => 'เด็กๆวิ่งเล่น', 'b' => 'เด็กๆ วิ่งเล่น', 'c' => 'เด็ก ๆวิ่งเล่น', 'd' => 'เด็ก  ๆ วิ่งเล่น', 'ans' => 'B'],
            ['q' => "บทที่ {$lessonIndex}: \"น้ำขึ้นให้รีบตัก\" สื่อความหมายใด", 'a' => 'ควรประหยัดน้ำ', 'b' => 'ทำงานกลางวัน', 'c' => 'รีบคว้าโอกาส', 'd' => 'เดินทางทางน้ำ', 'ans' => 'C'],
        ];
    }

    if (str_contains($name, 'อังกฤษ') || str_contains($name, 'english')) {
        return [
            ['q' => "Lesson {$lessonIndex}: Choose the correct sentence.", 'a' => 'She go to school every day.', 'b' => 'She goes to school every day.', 'c' => 'She going to school every day.', 'd' => 'She gone to school every day.', 'ans' => 'B'],
            ['q' => "Lesson {$lessonIndex}: Synonym of \"happy\" is ...", 'a' => 'Sad', 'b' => 'Angry', 'c' => 'Joyful', 'd' => 'Tired', 'ans' => 'C'],
            ['q' => "Lesson {$lessonIndex}: I ___ a student.", 'a' => 'am', 'b' => 'is', 'c' => 'are', 'd' => 'be', 'ans' => 'A'],
            ['q' => "Lesson {$lessonIndex}: Which one is a noun?", 'a' => 'Run', 'b' => 'Beautiful', 'c' => 'Teacher', 'd' => 'Quickly', 'ans' => 'C'],
            ['q' => "Lesson {$lessonIndex}: Past tense of \"eat\" is ...", 'a' => 'eated', 'b' => 'ate', 'c' => 'eatening', 'd' => 'eat', 'ans' => 'B'],
        ];
    }

    if (str_contains($name, 'วิทย')) {
        return [
            ['q' => "บทที่ {$lessonIndex}: แหล่งพลังงานหลักของโลกคือข้อใด", 'a' => 'ดวงอาทิตย์', 'b' => 'ดวงจันทร์', 'c' => 'ลม', 'd' => 'น้ำใต้ดิน', 'ans' => 'A'],
            ['q' => "บทที่ {$lessonIndex}: น้ำเดือดที่อุณหภูมิเท่าไร", 'a' => '0°C', 'b' => '50°C', 'c' => '100°C', 'd' => '120°C', 'ans' => 'C'],
            ['q' => "บทที่ {$lessonIndex}: อวัยวะใดสูบฉีดเลือด", 'a' => 'ปอด', 'b' => 'หัวใจ', 'c' => 'ตับ', 'd' => 'ไต', 'ans' => 'B'],
            ['q' => "บทที่ {$lessonIndex}: ของเหลวเปลี่ยนเป็นไอเรียกว่าอะไร", 'a' => 'ควบแน่น', 'b' => 'ระเหย', 'c' => 'แข็งตัว', 'd' => 'หลอมเหลว', 'ans' => 'B'],
            ['q' => "บทที่ {$lessonIndex}: พืชสร้างอาหารด้วยกระบวนการใด", 'a' => 'ย่อยอาหาร', 'b' => 'หายใจ', 'c' => 'สังเคราะห์ด้วยแสง', 'd' => 'คายน้ำ', 'ans' => 'C'],
        ];
    }

    return [
        ['q' => "บทที่ {$lessonIndex}: ข้อใดเป็นหน้าที่ของพลเมืองที่ดี", 'a' => 'เคารพกฎหมาย', 'b' => 'ละเมิดกฎ', 'c' => 'ไม่รับผิดชอบ', 'd' => 'เอาเปรียบผู้อื่น', 'ans' => 'A'],
        ['q' => "บทที่ {$lessonIndex}: ข้อใดเป็นทรัพยากรใช้แล้วหมดไป", 'a' => 'ลม', 'b' => 'น้ำ', 'c' => 'ปิโตรเลียม', 'd' => 'แสงอาทิตย์', 'ans' => 'C'],
        ['q' => "บทที่ {$lessonIndex}: การซื้อของคุ้มค่าควรทำอย่างไร", 'a' => 'ซื้อทันที', 'b' => 'เปรียบเทียบราคาและคุณภาพ', 'c' => 'ซื้อเพราะโฆษณา', 'd' => 'ซื้อเพราะเพื่อน', 'ans' => 'B'],
        ['q' => "บทที่ {$lessonIndex}: ข้อใดเป็นวัฒนธรรมไทย", 'a' => 'การไหว้', 'b' => 'ทิ้งขยะเรี่ยราด', 'c' => 'ใช้ความรุนแรง', 'd' => 'ละเมิดสิทธิ', 'ans' => 'A'],
        ['q' => "บทที่ {$lessonIndex}: หลักประชาธิปไตยเน้นเรื่องใด", 'a' => 'การมีส่วนร่วมของประชาชน', 'b' => 'อำนาจคนเดียว', 'c' => 'ไม่รับฟังผู้อื่น', 'd' => 'ไม่ต้องเลือกตั้ง', 'ans' => 'A'],
    ];
}

function ensureFiveQuizQuestions(PDO $conn, string $lessonId, string $subjectName, int $lessonIndex): int
{
    $countStmt = $conn->prepare("SELECT COUNT(*) FROM public.test_questions WHERE lessons_id = ?");
    $countStmt->execute([$lessonId]);
    $currentCount = (int) $countStmt->fetchColumn();

    if ($currentCount >= 5) {
        return 0;
    }

    $seed = buildSeedQuestionsBySubject($subjectName, $lessonIndex);
    $toInsert = 5 - $currentCount;

    $insertStmt = $conn->prepare(
        "INSERT INTO public.test_questions (questions_id, questions_text, choice_a, choice_b, choice_c, choice_d, correct_answer, lessons_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $inserted = 0;
    for ($i = 0; $i < $toInsert; $i++) {
        $item = $seed[$i % count($seed)];
        $insertStmt->execute([
            nextQuestionId($conn),
            $item['q'],
            $item['a'],
            $item['b'],
            $item['c'],
            $item['d'],
            $item['ans'],
            $lessonId,
        ]);
        $inserted++;
    }
    return $inserted;
}

try {
    $subjects = $conn->query("SELECT subjects_id, subjects_name FROM public.subjects ORDER BY subjects_id ASC")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($subjects)) {
        jsonOut(['status' => 'error', 'message' => 'ไม่พบรายวิชาในระบบ'], 404);
    }

    $conn->beginTransaction();
    $summary = [];
    $totalInserted = 0;

    foreach ($subjects as $subject) {
        $subjectId = (string) $subject['subjects_id'];
        $subjectName = (string) ($subject['subjects_name'] ?? '');
        $lessons = ensureFiveLessons($conn, $subjectId);

        $subjectInserted = 0;
        for ($i = 0; $i < 5; $i++) {
            if (!isset($lessons[$i]['lessons_id'])) {
                continue;
            }
            $lessonId = (string) $lessons[$i]['lessons_id'];
            $subjectInserted += ensureFiveQuizQuestions($conn, $lessonId, $subjectName, $i + 1);
        }

        $summary[] = [
            'subject_id' => $subjectId,
            'subject_name' => $subjectName,
            'inserted_questions' => $subjectInserted,
        ];
        $totalInserted += $subjectInserted;
    }

    $conn->commit();
    jsonOut([
        'status' => 'success',
        'message' => 'เพิ่มแบบทดสอบทุกวิชา บทละ 5 ข้อ ครบแล้ว',
        'total_inserted_questions' => $totalInserted,
        'subjects' => $summary,
    ]);
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    jsonOut(['status' => 'error', 'message' => $e->getMessage()], 500);
}
?>
