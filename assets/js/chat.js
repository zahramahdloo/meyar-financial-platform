/* MEYAR — ویجت چت آنلاین */
(function () {
  'use strict';
  var fab = document.getElementById('chatFab');
  var panel = document.getElementById('chatPanel');
  var closeBtn = document.getElementById('chatClose');
  var body = document.getElementById('chatBody');
  var form = document.getElementById('chatForm');
  var nameInp = document.getElementById('chatName');
  var textInp = document.getElementById('chatText');
  var badge = document.getElementById('chatBadge');
  if (!fab || !panel) return;

  var API = (window.MEYAR_BASE || './') + 'api/chat.php';
  var token = null, lastId = 0, open = false, pollTimer = null;
  var faD = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
  function faNum(s){return String(s).replace(/\d/g,function(d){return faD[+d];});}

  try {
    token = localStorage.getItem('meyar_chat_token');
    lastId = parseInt(localStorage.getItem('meyar_chat_last') || '0', 10) || 0;
    var savedName = localStorage.getItem('meyar_chat_name');
    if (savedName && nameInp) { nameInp.value = savedName; nameInp.style.display = 'none'; }
  } catch (e) {}

  function addMsg(m) {
    var div = document.createElement('div');
    div.className = 'chat-msg ' + (m.s === 'a' ? 'a' : 'v');
    var who = m.s === 'a' ? (m.name ? m.name + ' — پشتیبانی' : 'پشتیبانی') : 'شما';
    div.innerHTML = '<div class="chat-bubble"></div><div class="chat-meta"></div>';
    div.querySelector('.chat-bubble').textContent = m.body;
    div.querySelector('.chat-meta').textContent = who + ' · ' + (m.t || '');
    body.appendChild(div);
    body.scrollTop = body.scrollHeight;
  }

  function poll() {
    if (!token) return;
    fetch(API + '?action=poll&token=' + token + '&after=' + lastId, { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j.ok || !j.messages) return;
        var newAdmin = 0;
        j.messages.forEach(function (m) {
          if (m.id > lastId) {
            lastId = m.id;
            addMsg(m);
            if (m.s === 'a') newAdmin++;
          }
        });
        if (newAdmin && !open) {
          badge.hidden = false;
          badge.textContent = faNum(newAdmin);
        }
        try { localStorage.setItem('meyar_chat_last', String(lastId)); } catch (e) {}
      })
      .catch(function () {});
  }

  function startPolling(fast) {
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(poll, fast ? 6000 : 25000);
  }

  fab.addEventListener('click', function () {
    open = !open;
    panel.hidden = !open;
    if (open) {
      badge.hidden = true;
      textInp.focus();
      poll();
      startPolling(true);
    } else {
      startPolling(false);
    }
  });
  closeBtn.addEventListener('click', function () {
    open = false;
    panel.hidden = true;
    startPolling(false);
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var text = textInp.value.trim();
    if (!text) return;
    var name = nameInp ? nameInp.value.trim() : '';
    var fd = new FormData();
    fd.append('action', 'send');
    fd.append('body', text);
    fd.append('name', name);
    if (token) fd.append('token', token);
    textInp.value = '';
    addMsg({ s: 'v', body: text, t: '' });
    fetch(API, { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (j.ok) {
          token = j.token;
          if (j.id > lastId) lastId = j.id;
          try {
            localStorage.setItem('meyar_chat_token', token);
            localStorage.setItem('meyar_chat_last', String(lastId));
            if (name) { localStorage.setItem('meyar_chat_name', name); nameInp.style.display = 'none'; }
          } catch (e) {}
        }
      })
      .catch(function () {});
  });

  if (token) { poll(); startPolling(false); }
})();
