<?php
session_start();
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
require_once __DIR__ . '/gmail_oauth.php';

function gmailOAuthPage(string $title, string $content, int $status = 200): never
{
    http_response_code($status);
    echo '<!doctype html><html lang="th"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title><body style="font:16px system-ui,sans-serif;max-width:760px;margin:48px auto;padding:0 20px;color:#24324a"><h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>' . $content . '</body></html>';
    exit;
}

$expectedState = (string) ($_SESSION['gmail_oauth_state'] ?? '');
$receivedState = (string) ($_GET['state'] ?? '');
unset($_SESSION['gmail_oauth_state']);
if ($expectedState === '' || $receivedState === '' || !hash_equals($expectedState, $receivedState)) {
    gmailOAuthPage('ยืนยัน OAuth ไม่สำเร็จ', '<p>state ไม่ตรงกัน กรุณาเริ่มขั้นตอนใหม่จากเว็บ</p>', 400);
}
if (isset($_GET['error'])) {
    gmailOAuthPage('Google ไม่อนุญาตสิทธิ์', '<p>' . htmlspecialchars((string) $_GET['error'], ENT_QUOTES, 'UTF-8') . '</p>', 400);
}

try {
    $tokens = gmailOAuthExchangeCode((string) ($_GET['code'] ?? ''));
    $refreshToken = (string) ($tokens['refresh_token'] ?? '');
    if ($refreshToken === '') throw new RuntimeException('Google did not return a refresh token. Start again and approve access when prompted.');
    $escapedToken = htmlspecialchars($refreshToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    gmailOAuthPage('เชื่อม Gmail สำเร็จ', '<p>คัดลอก Refresh Token ไปตั้งเป็น Render Environment Variable ชื่อ <code>GMAIL_REFRESH_TOKEN</code> แล้วลบหรือปิดหน้านี้</p><textarea readonly rows="4" style="width:100%;box-sizing:border-box;padding:12px">' . $escapedToken . '</textarea><p>อย่าส่ง token นี้ให้ผู้อื่นหรือใส่ในซอร์สโค้ด</p>');
} catch (Throwable $error) {
    error_log('[auth.gmail-oauth] callback failed: ' . $error->getMessage());
    gmailOAuthPage('เชื่อม Gmail ไม่สำเร็จ', '<p>' . htmlspecialchars($error->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>', 500);
}
