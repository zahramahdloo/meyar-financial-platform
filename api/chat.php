<?php
/** MEYAR — API چت کاربران (سمت بازدیدکننده) */
require_once dirname(__DIR__) . '/inc/api.php';
meyar_api_begin();

try {
    require_once dirname(__DIR__) . '/inc/db.php';
    $action = (string)($_POST['action'] ?? $_GET['action'] ?? '');
    if (!in_array($action, ['send', 'poll'], true)) meyar_api_error(400, 'bad_action', 'عملیات درخواست معتبر نیست.');
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (($action === 'send' && $method !== 'POST') || ($action === 'poll' && !in_array($method, ['GET', 'POST'], true))) {
        meyar_api_error(405, 'method_not_allowed', 'این درخواست پشتیبانی نمی‌شود.');
    }
    if (!meyar_api_rate_limit('chat-' . $action, $action === 'send' ? 12 : 60, 60)) {
        meyar_api_error(429, 'rate_limited', 'تعداد درخواست‌ها بیش از حد مجاز است.');
    }
    $pdo = meyar_db();
    $tokenRaw = (string)($_POST['token'] ?? $_GET['token'] ?? '');
    if ($tokenRaw !== '' && !preg_match('/^[a-f0-9]{32}$/', $tokenRaw)) meyar_api_error(400, 'bad_token', 'شناسه گفتگو معتبر نیست.');
    $token = $tokenRaw;

function chat_thread(PDO $pdo, string $token): ?array {
    if (strlen($token) !== 32) return null;
    $st = $pdo->prepare("SELECT * FROM threads WHERE token=?");
    $st->execute([$token]);
    return $st->fetch() ?: null;
}

if ($action === 'send') {
    $body = trim((string)($_POST['body'] ?? ''));
    $name = trim(mb_substr((string)($_POST['name'] ?? ''), 0, 40));
    if ($body === '' || mb_strlen($body) > 800) {
        meyar_api_error(400, 'bad_body', 'متن پیام معتبر نیست.');
    }
    if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $body . $name)) meyar_api_error(400, 'bad_body', 'متن پیام معتبر نیست.');

    $thread = $token ? chat_thread($pdo, $token) : null;
    if (!$thread) {
        $token = bin2hex(random_bytes(16));
        $pdo->prepare("INSERT INTO threads(token, name, status, created_at, last_at, admin_unread) VALUES(?,?,'open',?,?,0)")
            ->execute([$token, $name, time(), time()]);
        $thread = chat_thread($pdo, $token);
    } elseif ($name !== '' && $thread['name'] === '') {
        $pdo->prepare("UPDATE threads SET name=? WHERE id=?")->execute([$name, $thread['id']]);
    }

    // محدودیت: حداکثر ۱۰ پیام در دقیقه
    $st = $pdo->prepare("SELECT COUNT(*) c FROM messages WHERE thread_id=? AND sender='v' AND created_at > ?");
    $st->execute([$thread['id'], time() - 60]);
    if ((int)$st->fetch()['c'] >= 10) {
        meyar_api_error(429, 'flood', 'تعداد پیام‌ها بیش از حد مجاز است.');
    }

    $pdo->prepare("INSERT INTO messages(thread_id, sender, body, created_at) VALUES(?,'v',?,?)")
        ->execute([$thread['id'], $body, time()]);
    $pdo->prepare("UPDATE threads SET last_at=?, admin_unread=admin_unread+1, status='open' WHERE id=?")
        ->execute([time(), $thread['id']]);

    meyar_api_response(['ok' => true, 'token' => $token, 'id' => (int)$pdo->lastInsertId()]);
    exit;
}

if ($action === 'poll') {
    $thread = chat_thread($pdo, $token);
    // Keep the old empty result for expired browser tokens so chat can recover silently.
    if (!$thread) meyar_api_response(['ok' => true, 'messages' => []]);
    $afterRaw = (string)($_GET['after'] ?? $_POST['after'] ?? '0');
    if (!preg_match('/^[0-9]{1,12}$/', $afterRaw)) meyar_api_error(400, 'bad_cursor', 'نشانگر پیام معتبر نیست.');
    $after = (int)$afterRaw;
    $st = $pdo->prepare("SELECT id, sender, admin_name, body, created_at FROM messages WHERE thread_id=? AND id>? ORDER BY id LIMIT 100");
    $st->execute([$thread['id'], $after]);
    $msgs = [];
    foreach ($st as $m) {
        $msgs[] = [
            'id'   => (int)$m['id'],
            's'    => $m['sender'],
            'name' => $m['admin_name'],
            'body' => $m['body'],
            't'    => meyar_fa_num(date('H:i', (int)$m['created_at'])),
        ];
    }
    $hasNewAdmin = false;
    foreach ($msgs as $m) { if ($m['s'] === 'a') { $hasNewAdmin = true; break; } }
    if ($hasNewAdmin) {
        $pdo->prepare("UPDATE threads SET visitor_unread=0 WHERE id=?")->execute([$thread['id']]);
    }
    meyar_api_response(['ok' => true, 'messages' => $msgs]);
}
}
catch (Throwable $e) {
    error_log('Meyar chat API error: ' . $e->getMessage());
    meyar_api_error(500, 'server_error');
}
