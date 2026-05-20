<?php
declare(strict_types=1);

function ensureSubjectTypeColumn(PDO $conn): void
{
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $stmt = $conn->prepare(
        "SELECT 1
         FROM information_schema.columns
         WHERE table_schema = 'public'
           AND table_name = 'subjects'
           AND column_name = 'subject_type'
         LIMIT 1"
    );
    $stmt->execute();

    if (!(bool) $stmt->fetchColumn()) {
        $conn->exec("ALTER TABLE public.subjects ADD COLUMN subject_type VARCHAR(20) NOT NULL DEFAULT 'elective'");
    }

    $initialized = true;
}

function ensureCurriculumSubjectTypeColumn(PDO $conn): void
{
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $stmt = $conn->prepare(
        "SELECT 1
         FROM information_schema.columns
         WHERE table_schema = 'public'
           AND table_name = 'curriculums_subject'
           AND column_name = 'subject_type'
         LIMIT 1"
    );
    $stmt->execute();

    if (!(bool) $stmt->fetchColumn()) {
        $conn->exec("ALTER TABLE public.curriculums_subject ADD COLUMN subject_type VARCHAR(20) NOT NULL DEFAULT 'required'");
    }

    $initialized = true;
}

function normalizeSubjectType(?string $type): string
{
    return strtolower(trim((string) $type)) === 'required' ? 'required' : 'elective';
}

function findActiveCurriculumIdByLevel(PDO $conn, string $level): ?string
{
    $stmt = $conn->prepare(
        "SELECT curriculums_id
         FROM public.curriculums
         WHERE level = :level
           AND status = 'active'
         ORDER BY curriculums_id ASC
         LIMIT 1"
    );
    $stmt->execute([':level' => $level]);
    $curriculumId = $stmt->fetchColumn();

    return $curriculumId === false ? null : (string) $curriculumId;
}

function assignCurriculumToStudentByLevel(PDO $conn, string $studentId, string $level): ?string
{
    ensureSubjectTypeColumn($conn);
    ensureCurriculumSubjectTypeColumn($conn);

    $curriculumId = findActiveCurriculumIdByLevel($conn, $level);
    if ($curriculumId === null) {
        return null;
    }

    $conn->prepare(
        "UPDATE public.student
         SET studcurriculums_id = :curriculum_id
         WHERE student_id = :student_id"
    )->execute([
        ':curriculum_id' => $curriculumId,
        ':student_id' => $studentId,
    ]);

    return $curriculumId;
}

function assignCurriculumAndEnrollRequiredSubjects(PDO $conn, string $studentId, string $level): ?string
{
    return assignCurriculumToStudentByLevel($conn, $studentId, $level);
}
