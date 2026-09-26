<?php
/** MEYAR — اعلان ایمیلی و همگام‌سازی پاسخ‌های چت */
require_once __DIR__ . '/bootstrap.php';

function meyar_chat_mail_target(): string {
    return meyar_env('MEYAR_CHAT_EMAIL') ?: 'meyargroup2000@gmail.com';
}

function meyar_chat_mail_subject(string $token): string {
    return '[MEYAR #' . $token . '] پیام جدید چت سایت';
}

function meyar_chat_mail_headers(): string {
    $from = meyar_env('MEYAR_MAIL_FROM') ?: ('no-reply@' . ((string)($_SERVER['HTTP_HOST'] ?? 'localhost')));
    $from = preg_replace('/[^a-zA-Z0-9._%+@-]/', '', $from);
    return "MIME-Version: 1.0\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "From: MEYAR Chat <" . $from . ">\r\n"
        . "Reply-To: " . meyar_chat_mail_target() . "\r\n";
}

function meyar_chat_notify_admin(array $thread, string $body): bool {
    $token = (string)($thread['token'] ?? '');
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) return false;
    $name = trim((string)($thread['name'] ?? '')) ?: 'کاربر مهمان';
    $site = (string)($_SERVER['HTTP_HOST'] ?? 'سایت معیار');
    $message = "پیام جدیدی در چت سایت MEYAR دریافت شد.\n\n"
        . "کاربر: " . $name . "\n"
        . "شناسه گفتگو: " . $token . "\n"
        . "آدرس سایت: " . $site . "\n\n"
        . "متن پیام:\n" . trim($body) . "\n\n"
        . "برای پاسخ، همین ایمیل را Reply کنید و شناسه [MEYAR #" . $token . "] را در عنوان نگه دارید.\n"
        . "پاسخ شما از طریق همین گفتگو به کاربر نمایش داده می‌شود.\n";
    $subject = '=?UTF-8?B?' . base64_encode(meyar_chat_mail_subject($token)) . '?=';
    $params = '';
    $envelope = meyar_env('MEYAR_MAIL_ENVELOPE_FROM');
    if ($envelope !== '' && function_exists('mail')) $params = '-f' . preg_replace('/[^a-zA-Z0-9._%+@-]/', '', $envelope);
    return function_exists('mail') && @mail(meyar_chat_mail_target(), $subject, $message, meyar_chat_mail_headers(), $params);
}

function meyar_chat_extract_token(string $subject): string {
    $decoded = function_exists('iconv_mime_decode') ? @iconv_mime_decode($subject, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8') : $subject;
    if (!is_string($decoded) || $decoded === '') $decoded = $subject;
    return preg_match('/\[MEYAR\s*#([a-f0-9]{32})\]/i', $decoded, $m) ? strtolower($m[1]) : '';
}

function meyar_chat_clean_mail_body(string $body): string {
    $body = preg_replace('/\r\n?/', "\n", $body);
    $body = preg_replace('/^>.*$/m', '', $body);
    $body = preg_replace('/\n--\s*\n.*$/s', '', $body);
    $body = trim(preg_replace("/\n{3,}/", "\n\n", $body));
    return mb_substr($body, 0, 800);
}
