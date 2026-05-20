<?php
declare(strict_types=1);

const SUPPORT_CONTACT_PHONE = '02-XXX-XXXX';

function ensureUserAccountStatusColumn(PDO $conn): void
{
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $stmt = $conn->prepare(
        "SELECT 1
         FROM information_schema.columns
         WHERE table_schema = 'public'
           AND table_name = 'User'
           AND column_name = 'account_status'
         LIMIT 1"
    );
    $stmt->execute();
    $exists = (bool) $stmt->fetchColumn();

    if (!$exists) {
        $conn->exec('ALTER TABLE public."User" ADD COLUMN account_status VARCHAR(20) NOT NULL DEFAULT \'active\'');
    }

    $initialized = true;
}

function normalizeAccountStatus(?string $status): string
{
    return strtolower(trim((string) $status)) === 'inactive' ? 'inactive' : 'active';
}

function suspendedAccountMessage(): string
{
    return 'บัญชีถูกระงับ กรุณาติดต่อเจ้าหน้าที่เบอร์ ' . SUPPORT_CONTACT_PHONE;
}
