<?php
// ไฟล์นี้ใช้ตรวจสอบ schema จริงของตารางใน Supabase
// รันครั้งเดียว แล้วลบทิ้งได้เลย
header('Content-Type: text/html; charset=utf-8');
require_once 'db_connect.php';

$tables = ['student', 'parents', 'teacher'];

echo '<pre style="font-family:monospace;font-size:14px;padding:20px;">';
echo "=== SUPABASE SCHEMA CHECKER ===\n\n";

foreach ($tables as $table) {
    echo "──────────────────────────────\n";
    echo "TABLE: public.$table\n";
    echo "──────────────────────────────\n";
    try {
        $stmt = $conn->query("
            SELECT column_name, data_type, is_nullable, column_default
            FROM information_schema.columns
            WHERE table_schema = 'public' AND table_name = '$table'
            ORDER BY ordinal_position
        ");
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($cols)) {
            echo "  ⚠️  ไม่พบตารางนี้\n";
        } else {
            printf("  %-25s %-20s %-10s %s\n", "COLUMN", "TYPE", "NULLABLE", "DEFAULT");
            echo str_repeat('-', 80) . "\n";
            foreach ($cols as $col) {
                printf(
                    "  %-25s %-20s %-10s %s\n",
                    $col['column_name'],
                    $col['data_type'],
                    $col['is_nullable'],
                    $col['column_default'] ?? '-'
                );
            }
        }
    } catch (PDOException $e) {
        echo "  ERROR: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

echo "=== END ===\n";
echo '</pre>';
?>
