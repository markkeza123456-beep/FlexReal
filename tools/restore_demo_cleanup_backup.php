<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$confirmation = '--restore-confirmed-pre-demo-state';
if (!in_array($confirmation, $argv, true)) {
    fwrite(STDERR, "This restores the complete pre-cleanup public schema data and overwrites current public rows.\nRun only after the rollback migration and only before adding new demo data.\nPass {$confirmation} to continue.\n");
    exit(2);
}

require __DIR__ . '/../config/db.php';
$backupPath = 'D:/Xampp/backups/FlexReal/flexreal_public_pre_demo_cleanup_20261021.json.gz';
$backupJson = @file_get_contents('compress.zlib://' . $backupPath);
if ($backupJson === false) throw new RuntimeException('Cannot read compressed logical backup.');
$backup = json_decode($backupJson, true, 512, JSON_THROW_ON_ERROR);
if (($backup['format'] ?? '') !== 'flexreal-public-logical-backup-v2') {
    throw new RuntimeException('Unexpected backup format.');
}

$expectedBackupTables = 30;
$expectedBackupRows = 1152;
$backupRows = array_sum(array_map(static fn(array $relation): int => count($relation['rows']), $backup['relations']));
if (count($backup['relations']) !== $expectedBackupTables || $backupRows !== $expectedBackupRows
    || count($backup['sequences'] ?? []) !== 19 || count($backup['routines'] ?? []) !== 1) {
    throw new RuntimeException('Backup contents do not match the validated pre-cleanup snapshot.');
}

$currentTableCount = (int) $conn->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='public' AND table_type='BASE TABLE'")->fetchColumn();
$currentRowCount = 0;
$currentTables = $conn->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public' AND table_type='BASE TABLE' ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
foreach ($currentTables as $table) {
    $quoted = 'public."' . str_replace('"', '""', (string) $table) . '"';
    $currentRowCount += (int) $conn->query('SELECT COUNT(*) FROM ' . $quoted)->fetchColumn();
}
$roles = array_map('intval', $conn->query('SELECT role, COUNT(*) AS total FROM public.users GROUP BY role ORDER BY role')->fetchAll(PDO::FETCH_KEY_PAIR));
if ($currentTableCount !== 30 || $currentRowCount !== 46 || $roles !== ['staff' => 1]
    || $conn->query("SELECT to_regclass('public.course_certificates')")->fetchColumn() === null) {
    throw new RuntimeException('Current database no longer matches the untouched post-cleanup state; refusing to overwrite it.');
}

$relations = [];
foreach ($backup['relations'] as $relation) $relations[$relation['name']] = $relation;
$dependencies = array_fill_keys(array_keys($relations), []);
$fkQuery = $conn->query("SELECT child.relname AS child, parent.relname AS parent FROM pg_constraint f JOIN pg_class child ON child.oid=f.conrelid JOIN pg_namespace child_ns ON child_ns.oid=child.relnamespace JOIN pg_class parent ON parent.oid=f.confrelid JOIN pg_namespace parent_ns ON parent_ns.oid=parent.relnamespace WHERE f.contype='f' AND child_ns.nspname='public' AND parent_ns.nspname='public'");
foreach ($fkQuery as $fk) {
    if (isset($relations[$fk['child']], $relations[$fk['parent']]) && $fk['child'] !== $fk['parent']) {
        $dependencies[$fk['child']][$fk['parent']] = true;
    }
}

$restoreOrder = [];
while (count($restoreOrder) < count($relations)) {
    $ready = [];
    foreach ($dependencies as $table => $parents) {
        if (!in_array($table, $restoreOrder, true)
            && array_diff(array_keys($parents), $restoreOrder) === []) $ready[] = $table;
    }
    if ($ready === []) throw new RuntimeException('Foreign-key dependency cycle found; refusing to restore.');
    sort($ready, SORT_STRING);
    foreach ($ready as $table) $restoreOrder[] = $table;
}

$quotedTables = array_map(static fn(string $table): string => 'public."' . str_replace('"', '""', $table) . '"', array_keys($relations));
$conn->beginTransaction();
try {
    $conn->exec('SET LOCAL statement_timeout = 0');
    $conn->exec('TRUNCATE TABLE ' . implode(', ', $quotedTables) . ' CASCADE');

    foreach ($restoreOrder as $table) {
        $relation = $relations[$table];
        if ($relation['rows'] === []) continue;
        $quotedTable = 'public."' . str_replace('"', '""', $table) . '"';
        $columns = array_column($relation['columns'], 'column_name');
        $quotedColumns = array_map(static fn(string $column): string => '"' . str_replace('"', '""', $column) . '"', $columns);
        $identityAlways = count(array_filter($relation['columns'], static fn(array $column): bool => ($column['is_identity'] ?? 'NO') === 'YES' && ($column['identity_generation'] ?? '') === 'ALWAYS')) > 0;
        $identityClause = $identityAlways ? ' OVERRIDING SYSTEM VALUE' : '';
        $json = json_encode($relation['rows'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
        $sql = 'INSERT INTO ' . $quotedTable . ' (' . implode(', ', $quotedColumns) . ')' . $identityClause
            . ' SELECT ' . implode(', ', $quotedColumns) . ' FROM json_populate_recordset(NULL::' . $quotedTable . ', CAST(:rows AS json))';
        $insert = $conn->prepare($sql);
        $insert->execute([':rows' => $json]);
    }

    foreach ($backup['sequences'] as $sequence) {
        $qualifiedSequence = 'public."' . str_replace('"', '""', (string) $sequence['name']) . '"';
        $lastValue = $sequence['last_value'] === null ? (int) $sequence['start_value'] : (int) $sequence['last_value'];
        $called = $sequence['last_value'] !== null;
        $set = $conn->prepare('SELECT setval(CAST(:sequence AS regclass), :value, :called)');
        $set->execute([':sequence' => $qualifiedSequence, ':value' => $lastValue, ':called' => $called ? 'true' : 'false']);
    }

    foreach ($relations as $table => $relation) {
        $quotedTable = 'public."' . str_replace('"', '""', $table) . '"';
        $actual = (int) $conn->query('SELECT COUNT(*) FROM ' . $quotedTable)->fetchColumn();
        if ($actual !== count($relation['rows'])) throw new RuntimeException('Restored row count verification failed for ' . $table . '.');
    }
    $conn->commit();
} catch (Throwable $error) {
    if ($conn->inTransaction()) $conn->rollBack();
    throw $error;
}

echo 'Restore complete: ' . count($relations) . ' public tables and ' . $backupRows . ' pre-cleanup rows restored. Re-run the FK orphan audit before reopening the demo.'.PHP_EOL;
