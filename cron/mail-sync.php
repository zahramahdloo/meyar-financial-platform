<?php
/** MEYAR — دریافت پاسخ‌های Gmail و تزریق آن‌ها به گفتگوهای سایت */
require_once dirname(__DIR__) . '/inc/db.php';
require_once dirname(__DIR__) . '/inc/chat-mail.php';

if (!function_exists('imap_open')) {
    fwrite(STDERR, "PHP IMAP extension is required.\n");
    exit(2);
}

$user = meyar_env('MEYAR_MAIL_USERNAME') ?: meyar_chat_mail_target();
$password = meyar_env('MEYAR_MAIL_PASSWORD');
$mailbox = meyar_env('MEYAR_IMAP_MAILBOX') ?: '{imap.gmail.com:993/imap/ssl}INBOX';
if ($password === '') {
    fwrite(STDERR, "MEYAR_MAIL_PASSWORD is not configured.\n");
    exit(3);
}

$imap = @imap_open($mailbox, $user, $password, OP_READONLY, 1);
if (!$imap) {
    fwrite(STDERR, "Could not connect to IMAP: " . (imap_last_error() ?: 'unknown error') . "\n");
    exit(4);
}

function meyar_mail_decode_header(string $value): string {
    $parts = function_exists('imap_mime_header_decode') ? @imap_mime_header_decode($value) : [];
    if (!$parts) return $value;
    $out = '';
    foreach ($parts as $part) $out .= (string)$part->text;
    return $out;
}

function meyar_mail_part_body($imap, int $msgNo, $structure, string $partNo = ''): string {
    if (!empty($structure->parts)) {
        foreach ($structure->parts as $index => $part) {
            $body = meyar_mail_part_body($imap, $msgNo, $part, $partNo === '' ? (string)($index + 1) : $partNo . '.' . ($index + 1));
            if ($body !== '') return $body;
        }
        return '';
    }
    $type = (int)($structure->type ?? 0);
    $subtype = strtoupper((string)($structure->subtype ?? ''));
    if ($type !== 0 || $subtype !== 'PLAIN') return '';
    $body = $partNo === '' ? @imap_body($imap, $msgNo) : @imap_fetchbody($imap, $msgNo, $partNo);
    $encoding = (int)($structure->encoding ?? 0);
    if ($encoding === 3) $body = base64_decode($body, true) ?: '';
    elseif ($encoding === 4) $body = quoted_printable_decode($body);
    return (string)$body;
}

$pdo = meyar_db();
$uids = imap_search($imap, 'UNSEEN', SE_UID) ?: [];
$imported = 0;
foreach ($uids as $uid) {
    $uidKey = (string)$uid;
    $check = $pdo->prepare('SELECT 1 FROM mail_imports WHERE mailbox_uid=? LIMIT 1');
    $check->execute([$uidKey]);
    if ($check->fetchColumn()) continue;
    $msgNo = imap_msgno($imap, $uid);
    if (!$msgNo) continue;
    $header = @imap_headerinfo($imap, $msgNo);
    $subject = meyar_mail_decode_header((string)($header->subject ?? ''));
    $token = meyar_chat_extract_token($subject);
    $structure = @imap_fetchstructure($imap, $msgNo);
    $body = $structure ? meyar_mail_part_body($imap, $msgNo, $structure) : (string)@imap_body($imap, $msgNo);
    $body = meyar_chat_clean_mail_body($body);
    $messageId = trim((string)($header->message_id ?? ''));
    $handled = false;
    if ($token !== '' && $body !== '') {
        $st = $pdo->prepare('SELECT * FROM threads WHERE token=? LIMIT 1');
        $st->execute([$token]);
        $thread = $st->fetch();
        if ($thread) {
            $pdo->prepare("INSERT INTO messages(thread_id, sender, admin_name, body, created_at) VALUES(?,'a',?,?,?)")
                ->execute([$thread['id'], 'پشتیبانی ایمیل', $body, time()]);
            $pdo->prepare("UPDATE threads SET last_at=?, visitor_unread=visitor_unread+1, status='open' WHERE id=?")
                ->execute([time(), $thread['id']]);
            $imported++;
            $handled = true;
        }
    }
    // ایمیل‌های نامرتبط را unread نگه می‌داریم؛ فقط پاسخ‌های دارای شناسه MEYAR پردازش می‌شوند.
    if ($handled || $token !== '') {
        $pdo->prepare('INSERT OR IGNORE INTO mail_imports(mailbox_uid, message_id, imported_at) VALUES(?,?,?)')
            ->execute([$uidKey, $messageId, time()]);
        @imap_setflag_full($imap, (string)$uid, '\\Seen', ST_UID);
    }
}
imap_close($imap);
fwrite(STDOUT, "Imported {$imported} chat reply(ies).\n");
