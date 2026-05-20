<?php
session_start();
require_once __DIR__ . '/db_connect.php';
header('Content-Type: application/json; charset=utf-8');

function out(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$role = strtolower((string) ($_SESSION['role'] ?? ''));
if (!isset($_SESSION['user_id']) || !in_array($role, ['teacher', 'staff', 'admin'], true)) {
    out(['status' => 'unauthorized', 'message' => 'ไม่มีสิทธิ์ใช้งาน'], 401);
}

function tableColumns(PDO $conn, string $schema, string $table): array
{
    $stmt = $conn->prepare(
        "SELECT column_name
         FROM information_schema.columns
         WHERE table_schema = :schema AND table_name = :table
         ORDER BY ordinal_position"
    );
    $stmt->execute([':schema' => $schema, ':table' => $table]);
    return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function nextNumericId(PDO $conn, string $table, string $idColumn): int
{
    $stmt = $conn->query("SELECT COALESCE(MAX($idColumn), 0) + 1 FROM public.$table");
    return (int) $stmt->fetchColumn();
}

function buildSeedQuestionsBySubject(string $subjectName, int $lessonNo): array
{
    $name = mb_strtolower($subjectName, 'UTF-8');

    if (str_contains($name, 'คณิต')) {
        return [
            ['q' => "บทที่ {$lessonNo}: 24 ÷ 6 มีค่าเท่าไร", 'a' => '4', 'b' => '6', 'c' => '18', 'd' => '30', 'ans' => 'A'],
            ['q' => "บทที่ {$lessonNo}: 7 × (3 + 2) เท่ากับข้อใด", 'a' => '14', 'b' => '35', 'c' => '12', 'd' => '10', 'ans' => 'B'],
            ['q' => "บทที่ {$lessonNo}: ข้อใดเป็นจำนวนเฉพาะ", 'a' => '21', 'b' => '27', 'c' => '29', 'd' => '33', 'ans' => 'C'],
            ['q' => "บทที่ {$lessonNo}: 0.25 เท่ากับเศษส่วนใด", 'a' => '1/2', 'b' => '1/4', 'c' => '2/5', 'd' => '3/4', 'ans' => 'B'],
            ['q' => "บทที่ {$lessonNo}: สี่เหลี่ยมผืนผ้ายาว 8 กว้าง 5 มีพื้นที่เท่าไร", 'a' => '13', 'b' => '26', 'c' => '40', 'd' => '80', 'ans' => 'C'],
        ];
    }

    if (str_contains($name, 'ไทย')) {
        return [
            ['q' => "บทที่ {$lessonNo}: คำใดเป็นคำราชาศัพท์", 'a' => 'กิน', 'b' => 'รับประทาน', 'c' => 'เคี้ยว', 'd' => 'ดื่ม', 'ans' => 'B'],
            ['q' => "บทที่ {$lessonNo}: ข้อใดเป็นประโยคสมบูรณ์", 'a' => 'เมื่อเช้าที่ตลาด', 'b' => 'แมวสีขาว', 'c' => 'นักเรียนอ่านหนังสือในห้องสมุด', 'd' => 'เพราะฝนตก', 'ans' => 'C'],
            ['q' => "บทที่ {$lessonNo}: คำว่า \"สามัคคี\" ใกล้เคียงกับข้อใด", 'a' => 'แตกแยก', 'b' => 'พร้อมเพรียง', 'c' => 'รวดเร็ว', 'd' => 'โดดเดี่ยว', 'ans' => 'B'],
            ['q' => "บทที่ {$lessonNo}: ข้อใดใช้ไม้ยมกถูกต้อง", 'a' => 'เด็กๆวิ่งเล่น', 'b' => 'เด็กๆ วิ่งเล่น', 'c' => 'เด็ก ๆวิ่งเล่น', 'd' => 'เด็ก  ๆ วิ่งเล่น', 'ans' => 'B'],
            ['q' => "บทที่ {$lessonNo}: \"น้ำขึ้นให้รีบตัก\" หมายถึงข้อใด", 'a' => 'ควรประหยัดน้ำ', 'b' => 'ทำงานกลางวัน', 'c' => 'รีบคว้าโอกาส', 'd' => 'เดินทางทางน้ำ', 'ans' => 'C'],
        ];
    }

    if (str_contains($name, 'อังกฤษ') || str_contains($name, 'english')) {
        return [
            ['q' => "Lesson {$lessonNo}: Choose the correct sentence.", 'a' => 'She go to school every day.', 'b' => 'She goes to school every day.', 'c' => 'She going to school every day.', 'd' => 'She gone to school every day.', 'ans' => 'B'],
            ['q' => "Lesson {$lessonNo}: Synonym of \"happy\" is ...", 'a' => 'Sad', 'b' => 'Angry', 'c' => 'Joyful', 'd' => 'Tired', 'ans' => 'C'],
            ['q' => "Lesson {$lessonNo}: I ___ a student.", 'a' => 'am', 'b' => 'is', 'c' => 'are', 'd' => 'be', 'ans' => 'A'],
            ['q' => "Lesson {$lessonNo}: Which one is a noun?", 'a' => 'Run', 'b' => 'Beautiful', 'c' => 'Teacher', 'd' => 'Quickly', 'ans' => 'C'],
            ['q' => "Lesson {$lessonNo}: Past tense of \"eat\" is ...", 'a' => 'eated', 'b' => 'ate', 'c' => 'eatening', 'd' => 'eat', 'ans' => 'B'],
        ];
    }

    if (str_contains($name, 'วิทย')) {
        return [
            ['q' => "บทที่ {$lessonNo}: แหล่งพลังงานหลักของโลกคือข้อใด", 'a' => 'ดวงอาทิตย์', 'b' => 'ดวงจันทร์', 'c' => 'ลม', 'd' => 'น้ำใต้ดิน', 'ans' => 'A'],
            ['q' => "บทที่ {$lessonNo}: น้ำเดือดที่อุณหภูมิเท่าไร", 'a' => '0°C', 'b' => '50°C', 'c' => '100°C', 'd' => '120°C', 'ans' => 'C'],
            ['q' => "บทที่ {$lessonNo}: อวัยวะใดสูบฉีดเลือด", 'a' => 'ปอด', 'b' => 'หัวใจ', 'c' => 'ตับ', 'd' => 'ไต', 'ans' => 'B'],
            ['q' => "บทที่ {$lessonNo}: ของเหลวเปลี่ยนเป็นไอเรียกว่าอะไร", 'a' => 'ควบแน่น', 'b' => 'ระเหย', 'c' => 'แข็งตัว', 'd' => 'หลอมเหลว', 'ans' => 'B'],
            ['q' => "บทที่ {$lessonNo}: พืชสร้างอาหารด้วยกระบวนการใด", 'a' => 'ย่อยอาหาร', 'b' => 'หายใจ', 'c' => 'สังเคราะห์ด้วยแสง', 'd' => 'คายน้ำ', 'ans' => 'C'],
        ];
    }

    return [
        ['q' => "บทที่ {$lessonNo}: ข้อใดเป็นหน้าที่ของพลเมืองที่ดี", 'a' => 'เคารพกฎหมาย', 'b' => 'ละเมิดกฎ', 'c' => 'ไม่รับผิดชอบ', 'd' => 'เอาเปรียบผู้อื่น', 'ans' => 'A'],
        ['q' => "บทที่ {$lessonNo}: ข้อใดเป็นทรัพยากรใช้แล้วหมดไป", 'a' => 'ลม', 'b' => 'น้ำ', 'c' => 'ปิโตรเลียม', 'd' => 'แสงอาทิตย์', 'ans' => 'C'],
        ['q' => "บทที่ {$lessonNo}: การซื้อของคุ้มค่าควรทำอย่างไร", 'a' => 'ซื้อทันที', 'b' => 'เปรียบเทียบราคาและคุณภาพ', 'c' => 'ซื้อเพราะโฆษณา', 'd' => 'ซื้อเพราะเพื่อน', 'ans' => 'B'],
        ['q' => "บทที่ {$lessonNo}: ข้อใดเป็นวัฒนธรรมไทย", 'a' => 'การไหว้', 'b' => 'ทิ้งขยะเรี่ยราด', 'c' => 'ใช้ความรุนแรง', 'd' => 'ละเมิดสิทธิ', 'ans' => 'A'],
        ['q' => "บทที่ {$lessonNo}: หลักประชาธิปไตยเน้นเรื่องใด", 'a' => 'การมีส่วนร่วมของประชาชน', 'b' => 'อำนาจคนเดียว', 'c' => 'ไม่รับฟังผู้อื่น', 'd' => 'ไม่ต้องเลือกตั้ง', 'ans' => 'A'],
    ];
}

function insertQuizQuestionAdaptive(PDO $conn, array $columns, array $row, int &$nextQuizId): void
{
    $data = [];
    if (in_array('quiz_id', $columns, true)) {
        $data['quiz_id'] = $nextQuizId++;
    }
    if (in_array('subjects_id', $columns, true)) {
        $data['subjects_id'] = $row['subjects_id'];
    }
    if (in_array('lesson_no', $columns, true)) {
        $data['lesson_no'] = $row['lesson_no'];
    }
    if (in_array('question_text', $columns, true)) {
        $data['question_text'] = $row['question_text'];
    }
    if (in_array('option_a', $columns, true)) {
        $data['option_a'] = $row['option_a'];
    }
    if (in_array('option_b', $columns, true)) {
        $data['option_b'] = $row['option_b'];
    }
    if (in_array('option_c', $columns, true)) {
        $data['option_c'] = $row['option_c'];
    }
    if (in_array('option_d', $columns, true)) {
        $data['option_d'] = $row['option_d'];
    }
    if (in_array('correct_answer', $columns, true)) {
        $data['correct_answer'] = $row['correct_answer'];
    } elseif (in_array('correct_option', $columns, true)) {
        $data['correct_option'] = $row['correct_answer'];
    } elseif (in_array('answer', $columns, true)) {
        $data['answer'] = $row['correct_answer'];
    }

    if (empty($data)) {
        throw new Exception('ไม่พบคอลัมน์ที่รองรับการ insert ในตาราง quiz_questions');
    }

    $cols = array_keys($data);
    $sql = "INSERT INTO public.quiz_questions (" . implode(', ', $cols) . ") VALUES (" . implode(', ', array_map(fn($c) => ':' . $c, $cols)) . ")";
    $stmt = $conn->prepare($sql);
    $params = [];
    foreach ($data as $k => $v) {
        $params[':' . $k] = $v;
    }
    $stmt->execute($params);
}

try {
    $columns = tableColumns($conn, 'public', 'quiz_questions');
    if (empty($columns)) {
        out(['status' => 'error', 'message' => 'ไม่พบตาราง quiz_questions หรือไม่มีคอลัมน์'], 404);
    }

    $subjects = $conn->query("SELECT subjects_id, subjects_name FROM public.subjects ORDER BY subjects_id ASC")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($subjects)) {
        out(['status' => 'error', 'message' => 'ไม่พบรายวิชาในระบบ'], 404);
    }

    $nextQuizId = in_array('quiz_id', $columns, true) ? nextNumericId($conn, 'quiz_questions', 'quiz_id') : 0;
    $countStmt = $conn->prepare("SELECT COUNT(*) FROM public.quiz_questions WHERE subjects_id = :subjects_id AND lesson_no = :lesson_no");

    $conn->beginTransaction();
    $totalInserted = 0;
    $summary = [];

    foreach ($subjects as $subject) {
        $subjectId = (string) $subject['subjects_id'];
        $subjectName = (string) ($subject['subjects_name'] ?? '');
        $subjectInserted = 0;

        for ($lessonNo = 1; $lessonNo <= 5; $lessonNo++) {
            $countStmt->execute([':subjects_id' => $subjectId, ':lesson_no' => $lessonNo]);
            $current = (int) $countStmt->fetchColumn();
            if ($current >= 5) {
                continue;
            }

            $seed = buildSeedQuestionsBySubject($subjectName, $lessonNo);
            $missing = 5 - $current;
            for ($i = 0; $i < $missing; $i++) {
                $item = $seed[$i % count($seed)];
                insertQuizQuestionAdaptive($conn, $columns, [
                    'subjects_id' => $subjectId,
                    'lesson_no' => $lessonNo,
                    'question_text' => $item['q'],
                    'option_a' => $item['a'],
                    'option_b' => $item['b'],
                    'option_c' => $item['c'],
                    'option_d' => $item['d'],
                    'correct_answer' => $item['ans'],
                ], $nextQuizId);
                $subjectInserted++;
            }
        }

        $summary[] = [
            'subjects_id' => $subjectId,
            'subjects_name' => $subjectName,
            'inserted_questions' => $subjectInserted,
        ];
        $totalInserted += $subjectInserted;
    }

    $conn->commit();
    out([
        'status' => 'success',
        'message' => 'เพิ่มข้อสอบลงตาราง quiz_questions ครบทุกวิชาทุกบทแล้ว',
        'total_inserted_questions' => $totalInserted,
        'subjects' => $summary,
    ]);
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    out(['status' => 'error', 'message' => $e->getMessage()], 500);
}
?>
