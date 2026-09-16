<?php
/** MEYAR — چت با کاربران (سمت ادمین) */
require_once __DIR__ . '/_panel.php';
panel_guard('chat');

$pdo  = meyar_db();
$user = meyar_user();
$csrf = meyar_csrf();

/* ---------- AJAX ---------- */
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $tid = (int)($_GET['thread'] ?? $_POST['thread'] ?? 0);

    if ($_GET['ajax'] === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!meyar_csrf_ok()) { echo json_encode(['ok' => false]); exit; }
        $body = trim((string)($_POST['body'] ?? ''));
        if ($tid && $body !== '' && mb_strlen($body) <= 800) {
            $pdo->prepare("INSERT INTO messages(thread_id, sender, admin_name, body, created_at) VALUES(?,'a',?,?,?)")
                ->execute([$tid, $user['name'], $body, time()]);
            $pdo->prepare("UPDATE threads SET last_at=?, visitor_unread=visitor_unread+1 WHERE id=?")->execute([time(), $tid]);
            echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]); exit;
        }
        echo json_encode(['ok' => false]); exit;
    }

    if ($_GET['ajax'] === 'poll') {
        $after = max(0, (int)($_GET['after'] ?? 0));
        $st = $pdo->prepare("SELECT id, sender, admin_name, body, created_at FROM messages WHERE thread_id=? AND id>? ORDER BY id LIMIT 200");
        $st->execute([$tid, $after]);
        $msgs = [];
        foreach ($st as $m) {
            $msgs[] = ['id'=>(int)$m['id'], 's'=>$m['sender'], 'name'=>$m['admin_name'],
                       'body'=>$m['body'], 't'=>meyar_fa_num(date('H:i', (int)$m['created_at']))];
        }
        $pdo->prepare("UPDATE threads SET admin_unread=0 WHERE id=?")->execute([$tid]);
        echo json_encode(['ok' => true, 'messages' => $msgs], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($_GET['ajax'] === 'threads') {
        $rows = $pdo->query("SELECT id, name, status, last_at, admin_unread FROM threads ORDER BY last_at DESC LIMIT 100")->fetchAll();
        foreach ($rows as &$r) {
            $r['last_fa'] = meyar_fa_num(date('m/d H:i', (int)$r['last_at']));
            $r['name'] = $r['name'] ?: 'کاربر مهمان';
        }
        echo json_encode(['ok' => true, 'threads' => $rows], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($_GET['ajax'] === 'close' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (meyar_csrf_ok() && $tid) {
            $pdo->prepare("UPDATE threads SET status='closed' WHERE id=?")->execute([$tid]);
            echo json_encode(['ok' => true]); exit;
        }
        echo json_encode(['ok' => false]); exit;
    }
    echo json_encode(['ok' => false]); exit;
}

panel_header('چت کاربران', 'chat');
?>

<div class="chat-admin">
  <aside class="threads-col card">
    <h2>گفتگوها</h2>
    <div id="threadList" class="thread-list"><p class="hint">در حال بارگذاری…</p></div>
  </aside>
  <section class="conv-col card">
    <div id="convHead" class="conv-head"><span class="hint">یک گفتگو را انتخاب کنید</span></div>
    <div id="convBody" class="conv-body"></div>
    <form id="convForm" class="conv-form" hidden>
      <input type="text" id="convText" placeholder="پاسخ خود را بنویسید…" maxlength="800" autocomplete="off" required>
      <button class="btn" type="submit">ارسال</button>
      <button class="btn btn-danger" type="button" id="closeThread">بستن گفتگو</button>
    </form>
  </section>
</div>

<style>
.chat-admin { display: grid; grid-template-columns: 300px 1fr; gap: 18px; align-items: start; }
.threads-col { max-height: 76vh; overflow-y: auto; }
.thread-list { display: flex; flex-direction: column; gap: 6px; }
.thread-item {
  display: flex; align-items: center; gap: 10px; padding: 10px 12px;
  border: 1px solid var(--line); border-radius: 12px; cursor: pointer; transition: all .2s;
}
.thread-item:hover { border-color: rgba(212,164,55,.4); }
.thread-item.active { background: linear-gradient(135deg, rgba(240,207,122,.14), rgba(168,124,31,.12)); border-color: var(--gold); }
.thread-item .t-name { flex: 1; font-size: 13.5px; font-weight: 600; }
.thread-item .t-time { font-size: 11px; color: var(--mut); }
.thread-item .nbadge { background: var(--red); color: #fff; font-size: 11px; min-width: 20px; height: 20px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; padding: 0 6px; }
.thread-item.closed { opacity: .55; }
.conv-col { display: flex; flex-direction: column; height: 76vh; }
.conv-head { border-bottom: 1px solid var(--line); padding-bottom: 10px; margin-bottom: 10px; font-weight: 700; }
.conv-body { flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; padding: 6px 2px; }
.conv-form { display: flex; gap: 8px; padding-top: 12px; border-top: 1px solid var(--line); }
.conv-form[hidden] { display: none; }
.conv-form input { flex: 1; }
.bubble { max-width: 75%; padding: 9px 14px; border-radius: 14px; font-size: 13.5px; white-space: pre-wrap; word-break: break-word; }
.m-v { align-self: flex-start; }
.m-v .bubble { background: var(--card2); border: 1px solid var(--line); border-bottom-right-radius: 4px; }
.m-a { align-self: flex-end; display: flex; flex-direction: column; align-items: flex-end; }
.m-a .bubble { background: linear-gradient(135deg, #f0cf7a, #d4a437); color: #241a05; border-bottom-left-radius: 4px; }
.m-meta { font-size: 10.5px; color: var(--mut); margin-top: 2px; }
@media (max-width: 900px) { .chat-admin { grid-template-columns: 1fr; } .conv-col { height: 60vh; } }
</style>

<script>
(function () {
  var csrf = '<?= $csrf ?>';
  var currentThread = 0, lastId = 0, pollTimer = null;
  var listEl = document.getElementById('threadList');
  var bodyEl = document.getElementById('convBody');
  var headEl = document.getElementById('convHead');
  var formEl = document.getElementById('convForm');
  var textEl = document.getElementById('convText');
  var faD = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
  function faNum(s){return String(s).replace(/\d/g,function(d){return faD[+d];});}

  function loadThreads() {
    fetch('chat.php?ajax=threads', {cache:'no-store'}).then(function(r){return r.json();}).then(function (j) {
      if (!j.ok) return;
      listEl.innerHTML = '';
      if (!j.threads.length) { listEl.innerHTML = '<p class="hint">هنوز گفتگویی شروع نشده.</p>'; return; }
      j.threads.forEach(function (t) {
        var d = document.createElement('div');
        d.className = 'thread-item' + (t.id === currentThread ? ' active' : '') + (t.status === 'closed' ? ' closed' : '');
        d.innerHTML = '<span class="t-name"></span><span class="t-time"></span>' +
                      (t.admin_unread > 0 ? '<span class="nbadge">' + faNum(t.admin_unread) + '</span>' : '');
        d.querySelector('.t-name').textContent = t.name + (t.status === 'closed' ? ' (بسته)' : '');
        d.querySelector('.t-time').textContent = t.last_fa;
        d.addEventListener('click', function () { openThread(t.id, t.name); });
        listEl.appendChild(d);
      });
    }).catch(function(){});
  }

  function addMsg(m) {
    var d = document.createElement('div');
    d.className = m.s === 'a' ? 'm-a' : 'm-v';
    d.innerHTML = '<div class="bubble"></div><div class="m-meta"></div>';
    d.querySelector('.bubble').textContent = m.body;
    d.querySelector('.m-meta').textContent = (m.s === 'a' ? (m.name || 'ادمین') : 'کاربر') + ' · ' + m.t;
    bodyEl.appendChild(d);
    bodyEl.scrollTop = bodyEl.scrollHeight;
  }

  function pollConv() {
    if (!currentThread) return;
    fetch('chat.php?ajax=poll&thread=' + currentThread + '&after=' + lastId, {cache:'no-store'})
      .then(function(r){return r.json();}).then(function (j) {
        if (!j.ok) return;
        j.messages.forEach(function (m) { if (m.id > lastId) { lastId = m.id; addMsg(m); } });
      }).catch(function(){});
  }

  function openThread(id, name) {
    currentThread = id; lastId = 0;
    bodyEl.innerHTML = '';
    headEl.innerHTML = '';
    headEl.textContent = 'گفتگو با ' + name;
    formEl.hidden = false;
    pollConv();
    loadThreads();
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(function () { pollConv(); loadThreads(); }, 5000);
    textEl.focus();
  }

  formEl.addEventListener('submit', function (e) {
    e.preventDefault();
    var text = textEl.value.trim();
    if (!text || !currentThread) return;
    var fd = new FormData();
    fd.append('csrf', csrf);
    fd.append('thread', currentThread);
    fd.append('body', text);
    textEl.value = '';
    fetch('chat.php?ajax=send', { method: 'POST', body: fd })
      .then(function(r){return r.json();}).then(function () { pollConv(); });
  });

  document.getElementById('closeThread').addEventListener('click', function () {
    if (!currentThread || !confirm('این گفتگو بسته شود؟')) return;
    var fd = new FormData();
    fd.append('csrf', csrf);
    fd.append('thread', currentThread);
    fetch('chat.php?ajax=close', { method: 'POST', body: fd })
      .then(function(){ loadThreads(); });
  });

  loadThreads();
  setInterval(loadThreads, 15000);
})();
</script>

<?php panel_footer(); ?>
