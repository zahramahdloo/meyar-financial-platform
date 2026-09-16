<?php
/** MEYAR — API چت کاربران (سمت بازدیدکننده) */
require_once dirname(__DIR__) . '/inc/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$pdo    = meyar_db();
$action = (string)($_POST['action'] ?? $_GET['action'] ?? '');
$token  = preg_replace('/[^a-f0-9]/', '', (string)($_POST['token'] ?? $_GET['token'] ?? ''));

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
        echo json_encode(['ok' => false, 'error' => 'bad_body']); exit;
    }

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
        echo json_encode(['ok' => false, 'error' => 'flood']); exit;
    }

    $pdo->prepare("INSERT INTO messages(thread_id, sender, body, created_at) VALUES(?,'v',?,?)")
        ->execute([$thread['id'], $body, time()]);
    $pdo->prepare("UPDATE threads SET last_at=?, admin_unread=admin_unread+1, status='open' WHERE id=?")
        ->execute([time(), $thread['id']]);

    echo json_encode(['ok' => true, 'token' => $token, 'id' => (int)$pdo->lastInsertId()]);
    exit;
}

if ($action === 'poll') {
    $thread = chat_thread($pdo, $token);
    if (!$thread) { echo json_encode(['ok' => true, 'messages' => []]); exit; }
    $after = max(0, (int)($_GET['after'] ?? $_POST['after'] ?? 0));
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
    echo json_encode(['ok' => true, 'messages' => $msgs], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'bad_action']);
